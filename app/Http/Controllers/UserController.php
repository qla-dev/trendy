<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CustomRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::all();
        $roleOptions = $this->roleOptions();
        $canManageRoles = auth()->user()->canManageRoles();
        
        // Prepare data for DataTables
        $usersData = $users->map(function($user) {
            return [
                $user->name,
                $user->username,
                trim((string) ($user->email ?? '')) !== '' ? $user->email : '-',
                $user->role,
                $user->created_at->format('d.m.Y'),
                $user->id // for actions
            ];
        });
        
        return view('content.apps.user.app-user-list', compact('users', 'usersData', 'roleOptions', 'canManageRoles'));
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('content.apps.user.app-user-create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $canManageRoles = $request->user()->canManageRoles();
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique($this->usersTableForValidation(), 'username'),
            ],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique($this->usersTableForValidation(), 'email'),
            ],
            'password' => 'required|string|min:6|confirmed',
            'role' => $canManageRoles ? ['required', Rule::in($this->availableRoles())] : ['nullable', Rule::in([User::ROLE_USER])],
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        if (User::isRoleManagerUsername($request->username)) {
            return back()->withErrors(['username' => 'Ovo korisničko ime je rezervisano.'])->withInput();
        }

        try {
            User::create([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->input('email'),
                'password' => Hash::make($request->password),
                'role' => $canManageRoles ? $request->role : User::ROLE_USER,
            ]);

            return redirect()->route('app-user-list')
                ->with('success', 'Korisnik je uspješno kreiran.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Greška pri kreiranju korisnika. Molimo pokušajte ponovo.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::findOrFail($id);
        $roleOptions = $this->roleOptions();
        $canManageRoles = auth()->user()->canManageRoles();
        return view('content.apps.user.app-user-view-account', compact('user', 'roleOptions', 'canManageRoles'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $user = User::findOrFail($id);
        $roleOptions = $this->roleOptions();
        $canManageRoles = auth()->user()->canManageRoles();
        return view('content.apps.user.app-user-edit', compact('user', 'roleOptions', 'canManageRoles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $canManageRoles = $request->user()->canManageRoles();
        if ($user->canManageRoles() && (!$canManageRoles || $request->name !== $user->name || $request->username !== $user->username)) {
            abort(403);
        }
        if (User::isRoleManagerUsername($request->username) && $request->username !== $user->username) {
            return back()->withErrors(['username' => 'Ovo korisničko ime je rezervisano.'])->withInput();
        }
        if (!$canManageRoles && $request->has('role') && $request->role !== $user->role) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique($this->usersTableForValidation(), 'username')->ignore($id),
            ],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique($this->usersTableForValidation(), 'email')->ignore($id),
            ],
            'role' => $canManageRoles ? ['required', Rule::in($this->availableRoles())] : ['nullable'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $user->update([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->input('email'),
                'role' => $canManageRoles ? $request->role : $user->role,
            ]);

            return redirect()->route('app-user-list')
                ->with('success', 'Korisnik je uspješno ažuriran.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Greška pri ažuriranju korisnika. Molimo pokušajte ponovo.');
        }
    }

    public function updateRole(Request $request, string $id)
    {
        abort_unless($request->user()->canManageRoles(), 403);

        $user = User::findOrFail($id);
        $data = $request->validate([
            'role' => ['required', Rule::in($this->availableRoles())],
        ]);

        $user->update(['role' => $data['role']]);

        return redirect()->route('app-user-list')
            ->with('success', 'Uloga korisnika je uspješno promijenjena.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        abort_if($user->canManageRoles(), 403);
        try {
            $user->delete();

            return redirect()->route('app-user-list')
                ->with('success', 'Korisnik je uspješno obrisan.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Greška pri brisanju korisnika. Molimo pokušajte ponovo.');
        }
    }

    /**
     * Show user account view
     */
    public function viewAccount(string $id)
    {
        $user = User::findOrFail($id);
        $roleOptions = $this->roleOptions();
        $canManageRoles = auth()->user()->canManageRoles();
        return view('content.apps.user.app-user-view-account', compact('user', 'roleOptions', 'canManageRoles'));
    }

    private function usersTableForValidation(): string
    {
        $userModel = new User();
        $connection = $userModel->getConnectionName();
        $table = $userModel->getTable();

        if (is_string($connection) && $connection !== '') {
            return $connection . '.' . $table;
        }

        return $table;
    }

    private function availableRoles(): array
    {
        return array_keys($this->roleOptions());
    }

    private function roleOptions(): array
    {
        $known = array_merge([
            User::ROLE_ADMIN => 'Admin',
            User::ROLE_USER => 'Korisnik',
            User::ROLE_KONTROLA => 'Kontrola',
            User::ROLE_BRAVARIJA => 'Bravarija',
            User::ROLE_PROIZVODNJA => 'Proizvodnja',
        ], array_map(fn (array $role) => $role['name'], app(CustomRoles::class)->all()));

        foreach (User::whereNotNull('role')->distinct()->pluck('role') as $role) {
            if ($role !== '' && !isset($known[$role])) {
                $known[$role] = ucfirst($role);
            }
        }

        return $known;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::all();
        
        // Prepare data for DataTables
        $usersData = $users->map(function($user) {
            return [
                '', // empty for responsive control
                $user->name,
                $user->username,
                $user->email,
                $user->role,
                $user->created_at->format('d.m.Y'),
                $user->id // for actions
            ];
        });
        
        return view('content.apps.user.app-user-list', compact('users', 'usersData'));
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
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:admin,user',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            User::create([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
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
        return view('content.apps.user.app-user-view-account', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $user = User::findOrFail($id);
        return view('content.apps.user.app-user-edit', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $id,
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'role' => 'required|in:admin,user',
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
                'email' => $request->email,
                'role' => $request->role,
            ]);

            return redirect()->route('app-user-list')
                ->with('success', 'Korisnik je uspješno ažuriran.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Greška pri ažuriranju korisnika. Molimo pokušajte ponovo.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $user = User::findOrFail($id);
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
        return view('content.apps.user.app-user-view-account', compact('user'));
    }
}
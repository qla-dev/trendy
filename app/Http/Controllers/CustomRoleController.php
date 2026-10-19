<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CustomRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CustomRoleController extends Controller
{
    public function index(CustomRoles $roles)
    {
        abort_unless(Auth::user()?->canManageRoles(), 403);

        $stored = $roles->all();
        $userCounts = User::selectRaw('role, COUNT(*) as total')->groupBy('role')->pluck('total', 'role');
        $roleList = [];
        foreach (CustomRoles::BUILTIN as $key => $name) {
            $roleList[$key] = [
                'name' => $name,
                'slugs' => $stored[$key]['slugs'] ?? $roles->defaults($key),
                'builtin' => true,
            ];
        }
        foreach ($stored as $key => $role) {
            if (!isset(CustomRoles::BUILTIN[$key])) {
                $roleList[$key] = $role + ['builtin' => false];
            }
        }

        foreach ($userCounts as $key => $count) {
            if ($key !== null && $key !== '' && !isset($roleList[$key])) {
                $roleList[$key] = [
                    'name' => ucfirst($key),
                    'slugs' => $roles->defaults($key),
                    'builtin' => true,
                ];
            }
        }

        $roleSections = $roles->sections();

        return view('content.apps.user.app-role-list', compact('roleList', 'userCounts', 'roleSections'));
    }

    public function store(Request $request, CustomRoles $roles)
    {
        abort_unless(Auth::user()?->canManageRoles(), 403);

        $data = $request->validateWithBag('createRole', [
            'role_name' => 'required|string|max:100',
            'items' => 'sometimes|array',
            'items.*' => ['string', Rule::in($roles->allowedSlugs())],
        ]);

        $name = trim($data['role_name']);
        if ($name === '' || in_array(mb_strtolower($name), ['admin', 'korisnik', 'user', 'kontrola', 'bravarija', 'proizvodnja'], true)) {
            return back()->withErrors(['role_name' => 'Unesite drugi naziv uloge.'], 'createRole')->withInput();
        }

        try {
            $roles->create($name, $data['items'] ?? []);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['role_name' => $e->getMessage()], 'createRole')->withInput();
        }

        return redirect()->route('app-role-list')->with('success', 'Uloga je uspješno dodana.');
    }

    public function update(Request $request, string $key, CustomRoles $roles)
    {
        abort_unless(Auth::user()?->canManageRoles(), 403);
        $data = $request->validateWithBag('editRole', [
            'role_name' => 'required|string|max:100',
            'items' => 'sometimes|array',
            'items.*' => ['string', Rule::in($roles->allowedSlugs())],
        ]);
        $name = trim($data['role_name']);
        if ($name === '' || (!isset(CustomRoles::BUILTIN[$key]) && in_array(mb_strtolower($name), ['admin', 'korisnik', 'user', 'kontrola', 'bravarija', 'proizvodnja'], true))) {
            return back()->withErrors(['role_name' => 'Unesite drugi naziv uloge.'], 'editRole')->withInput();
        }
        try {
            $roles->update($key, $name, $data['items'] ?? []);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['role_name' => $e->getMessage()], 'editRole')->withInput();
        }
        return redirect()->route('app-role-list')->with('success', 'Uloga je uspješno ažurirana.');
    }

    public function destroy(string $key, CustomRoles $roles)
    {
        abort_unless(Auth::user()?->canManageRoles(), 403);
        if (User::where('role', $key)->exists()) {
            return back()->with('error', 'Ulogu nije moguće obrisati dok je dodijeljena korisnicima.');
        }
        try {
            $roles->delete($key);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('app-role-list')->with('success', 'Uloga je uspješno obrisana.');
    }
}

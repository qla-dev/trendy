<?php

namespace App\Http\Controllers\Cnc;

use App\Http\Controllers\Controller;
use App\Models\Cnc\CncUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CncAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = CncUser::where('email', strtolower(trim($request->email)))->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        if (!$user->aktivan) {
            return response()->json(['error' => 'Account inactive'], 403);
        }

        $token = $user->createToken('cnc-spa');

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'imePrezime' => $user->ime_prezime,
                'email' => $user->email,
                'role' => $user->role,
                'aktivan' => $user->aktivan,
                'permissions' => $user->permissions ?? [],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('cnc_token');
        if ($token) {
            $token->delete();
        }

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('cnc_user');

        return response()->json([
            'id' => $user->id,
            'imePrezime' => $user->ime_prezime,
            'email' => $user->email,
            'role' => $user->role,
            'aktivan' => $user->aktivan,
            'permissions' => $user->permissions ?? [],
        ]);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Cnc\CncPersonalAccessToken;
use App\Models\Cnc\CncUser;
use Closure;
use Illuminate\Http\Request;

class CncAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $hashedToken = hash('sha256', $token);
        $accessToken = CncPersonalAccessToken::where('token', $hashedToken)->first();

        if (!$accessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $user = CncUser::find($accessToken->cnc_user_id);

        if (!$user || !$user->aktivan) {
            return response()->json(['error' => 'Account inactive'], 403);
        }

        $accessToken->update(['last_used_at' => now()]);

        $request->attributes->set('cnc_user', $user);
        $request->attributes->set('cnc_token', $accessToken);

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CncAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->attributes->get('cnc_user');

        if (!$user || !$user->isAdmin()) {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        return $next($request);
    }
}

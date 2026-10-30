<?php

namespace App\Http\Middleware;

use App\Models\User;

class RestrictSupplier
{
    public function handle($request, \Closure $next)
    {
        $user = $request->user();
        if ($user && $user->getRole() === User::ROLE_SUPPLIER) {
            $route = $request->route()?->getName() ?? '';
            if (!str_starts_with($route, 'supplier.') && $route !== 'auth.logout') {
                if ($request->isMethod('get') && $route === 'dashboard-ecommerce') return redirect()->route('supplier.index');
                abort(403);
            }
        }
        return $next($request);
    }
}

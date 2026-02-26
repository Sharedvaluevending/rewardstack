<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        $userRole = $request->user()->role;
        
        // Treat 'user' and 'customer' as equivalent for portal access
        $normalizedRoles = array_map(function ($role) {
            return $role === 'user' ? 'customer' : $role;
        }, $roles);
        
        $normalizedUserRole = $userRole === 'user' ? 'customer' : $userRole;

        if (!in_array($normalizedUserRole, $normalizedRoles)) {
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}


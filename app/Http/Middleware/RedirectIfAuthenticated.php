<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = $request->user();
                $role = $user?->role;

                // Avoid redirecting to a missing /home route (common cause of PWA icon 404).
                return match ($role) {
                    'admin' => redirect()->route('admin.dashboard'),
                    'business' => redirect()->route('business.dashboard'),
                    'employee' => redirect()->route('employee.redeem'),
                    'user', 'customer' => redirect()->route('portal.dashboard'),
                    default => redirect(RouteServiceProvider::HOME),
                };
            }
        }

        return $next($request);
    }
}

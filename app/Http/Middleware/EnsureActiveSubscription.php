<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    /**
     * Handle an incoming request.
     * 
     * Checks if business has active subscription or valid trial.
     * If trial expired and no subscription, redirects to billing page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user || !$user->business) {
            return redirect()->route('login');
        }

        $business = $user->business;

        // Testing accounts bypass all subscription checks
        if ($business->is_testing_account) {
            return $next($request);
        }

        // Check if business has active subscription
        if ($business->hasActiveSubscription()) {
            return $next($request);
        }

        // Trial expired and no subscription - redirect to billing
        // Allow access to billing page itself to prevent redirect loop
        if ($request->routeIs('business.billing*') || $request->routeIs('business.settings')) {
            return $next($request);
        }

        return redirect()
            ->route('business.billing')
            ->with('error', 'Your trial has expired. Please subscribe to continue using the platform.');
    }
}


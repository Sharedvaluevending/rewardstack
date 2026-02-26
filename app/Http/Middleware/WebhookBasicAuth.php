<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class WebhookBasicAuth
{
    /**
     * Require HTTP Basic Auth for webhook endpoints.
     *
     * Usage: ->middleware('webhook.basic:{provider}')
     *
     * Reads:
     * - services.{provider}.webhook_username
     * - services.{provider}.webhook_password
     */
    public function handle(Request $request, Closure $next, string $provider)
    {
        // Some providers provide a webhook "API key" token.
        // If a token is configured and matches, allow without requiring basic auth.
        $expectedToken = (string) config("services.$provider.webhook_token", '');
        $providedToken = (string) $request->header('X-Webhook-Token', '');
        if ($expectedToken !== '' && $providedToken !== '' && hash_equals($expectedToken, $providedToken)) {
            return $next($request);
        }

        $expectedUser = (string) config("services.$provider.webhook_username", '');
        $expectedPass = (string) config("services.$provider.webhook_password", '');

        // In production, require at least one auth method (token OR basic auth).
        if (app()->environment('production') && $expectedToken === '' && ($expectedUser === '' || $expectedPass === '')) {
            return response()->json(['error' => 'Webhook auth not configured'], 500);
        }

        // Non-prod: allow if not configured (keeps local dev easy).
        if ($expectedToken === '' && ($expectedUser === '' || $expectedPass === '')) {
            return $next($request);
        }

        $providedUser = (string) $request->getUser();
        $providedPass = (string) $request->getPassword();

        if ($providedUser === '' || $providedPass === '') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!hash_equals($expectedUser, $providedUser) || !hash_equals($expectedPass, $providedPass)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}



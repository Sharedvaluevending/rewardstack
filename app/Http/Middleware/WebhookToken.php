<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class WebhookToken
{
    /**
     * If a token is configured for the provider, require X-Webhook-Token.
     *
     * Usage: ->middleware('webhook.token:stripe')
     */
    public function handle(Request $request, Closure $next, string $provider)
    {
        $expected = config("services.$provider.webhook_token");

        // In production, require a configured token and validate it.
        if (app()->environment('production') && !$expected) {
            return response()->json(['error' => 'Webhook token not configured'], 500);
        }

        // Backwards-compatible: if no token configured (non-prod), allow.
        if (!$expected) {
            return $next($request);
        }

        // Some providers (like Printful) can't easily send custom headers,
        // so we also accept a token via query string (?token=...).
        $provided = (string) $request->header('X-Webhook-Token', '');
        if ($provided === '') {
            $provided = (string) $request->query('token', '');
        }

        if ($provided === '' || !hash_equals((string) $expected, $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}

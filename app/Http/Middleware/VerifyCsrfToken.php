<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'play/*',
        'api/play/*',
        'api/game/*',
        // Merch previews are authenticated and same-site cookies are lax;
        // CSRF mismatches were causing intermittent 419s and "front-only" fallbacks.
        'business/merch/preview',
        // Webhooks (Stripe/Printful/etc.) are server-to-server calls and cannot include CSRF tokens.
        'webhooks/stripe',
        'webhooks/printful',
        'webhooks/sendgrid/events',
    ];
}

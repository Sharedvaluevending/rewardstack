<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Allow camera for the in-app/PWA scanner (Portal Scan). Keep microphone disabled.
        $response->headers->set('Permissions-Policy', 'geolocation=(self), microphone=(), camera=(self)');
        
        $averyFormAction = $this->averyFormAction();

        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' https://js.stripe.com; " .
               "style-src 'self' 'unsafe-inline' https://rsms.me https://fonts.googleapis.com; " .
               "img-src 'self' data: https: blob:; " .
               "font-src 'self' https://rsms.me https://fonts.gstatic.com; " .
               "connect-src 'self' https://api.stripe.com https://api.printful.com https://api.bigdatacloud.net; " .
               "frame-src 'self' https://js.stripe.com https://hooks.stripe.com; " .
               "form-action 'self' {$averyFormAction}; " .
               "frame-ancestors 'self';";
        
        $response->headers->set('Content-Security-Policy', $csp);

        // HTTPS enforcement in production
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    protected function averyFormAction(): string
    {
        $url = (string) config('avery.merge_direct_url', '');
        $parts = parse_url($url);

        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return 'https://services.print.avery.com';
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }
}


<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    // Default post-auth “home”. We keep this to a real route to avoid PWA/home-screen 404s.
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        // Bind Game model to use slug for route model binding
        Route::bind('game', function ($value) {
            return \App\Models\Game::where('slug', $value)->firstOrFail();
        });

        // Bind GameSession model to use session_token for route model binding
        Route::bind('session', function ($value) {
            return \App\Models\GameSession::where('session_token', $value)->firstOrFail();
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });

    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // General API rate limit
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Strict rate limit for login attempts - 5 per minute per IP
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())->response(function () {
                return response()->json([
                    'message' => 'Too many login attempts. Please try again in a minute.',
                ], 429);
            });
        });

        // Rate limit for registration - 3 per hour per IP
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(3)->by($request->ip())->response(function () {
                return response()->json([
                    'message' => 'Too many registration attempts. Please try again later.',
                ], 429);
            });
        });

        // Rate limit for password reset requests - 3 per hour per IP
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perHour(3)->by($request->ip())->response(function () {
                return response()->json([
                    'message' => 'Too many password reset requests. Please try again later.',
                ], 429);
            });
        });

        // Rate limit for QR scans - 30 per minute per IP (prevent abuse)
        RateLimiter::for('scan', function (Request $request) {
            // Users can scan + navigate quickly (especially when jumping between portal/play/promo).
            // Keep this lenient enough to avoid 429s during normal usage.
            return Limit::perMinute(120)->by($request->ip());
        });

        // Rate limit for scan geo updates - 30 per minute per IP
        RateLimiter::for('scan-geo', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        // Rate limit for guest "email me this promo" - prevent spam/abuse
        RateLimiter::for('promo-email', function (Request $request) {
            // A bit stricter than scans since this can trigger outbound email.
            return Limit::perMinute(5)->by($request->ip());
        });

        // Rate limit for SendGrid Event Webhook (bursty; keep lenient but bounded)
        RateLimiter::for('webhook-sendgrid', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        });

        // Rate limit for staff token lookup screen (redeem by per-user promo token)
        RateLimiter::for('redeem-token-lookup', function (Request $request) {
            $perMinute = app()->environment('testing') ? 10 : 30;
            $userId = $request->user()?->id ?: 'guest';
            return Limit::perMinute($perMinute)->by("redeem-token-lookup|{$userId}|{$request->ip()}");
        });

        // Rate limit for staff redemption action (real-money endpoint)
        RateLimiter::for('redeem-staff', function (Request $request) {
            $perMinute = app()->environment('testing') ? 10 : 20;
            $userId = $request->user()?->id ?: 'guest';

            return Limit::perMinute($perMinute)
                ->by("redeem-staff|{$userId}|{$request->ip()}")
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many redemption attempts. Please wait a moment and try again.',
                    ], 429);
                });
        });

        // Rate limit for game plays - 10 per minute per user
        RateLimiter::for('game', function (Request $request) {
            // Note: a single "play" can generate multiple requests (start session, start playing, submit score, etc.)
            // so this needs to be high enough for normal user behavior while still preventing abuse.
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limit for game play PAGES (Inertia GET routes).
        // Keep this much more lenient than the gameplay API throttling so "View" from the portal doesn't 429.
        RateLimiter::for('game-pages', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Strict limit for order placement - 5 per hour
        RateLimiter::for('orders', function (Request $request) {
            return Limit::perHour(5)->by($request->user()?->id ?: $request->ip());
        });
    }
}

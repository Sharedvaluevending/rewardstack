<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Ziggy route list cache TTL (seconds). Clear cache after route changes: php artisan cache:clear
     */
    private const ZIGGY_CACHE_TTL = 3600;

    /**
     * Notification count/list cache TTL per user (seconds).
     */
    private const NOTIFICATIONS_CACHE_TTL = 45;

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        if ($user) {
            $user->loadMissing('business');
        }

        $authUser = $user;
        $unreadCount = $user ? $this->cachedUnreadNotificationsCount($user) : 0;
        $notifications = $user ? $this->cachedUnreadNotifications($user) : [];

        $ziggyBase = Cache::remember('ziggy.routes', self::ZIGGY_CACHE_TTL, fn () => (new Ziggy)->toArray());

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $authUser,
                'unreadNotificationsCount' => $unreadCount,
                'notifications' => $notifications,
            ],
            'ziggy' => fn () => [
                ...$ziggyBase,
                'location' => $request->url(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'redemption' => fn () => $request->session()->get('redemption'),
                'merch_tag_code' => fn () => $request->session()->get('merch_tag_code'),
                'merch_tag_url' => fn () => $request->session()->get('merch_tag_url'),
                'merch_tags' => fn () => $request->session()->get('merch_tags'),
            ],
        ];
    }

    private function cachedUnreadNotificationsCount($user): int
    {
        return (int) Cache::remember(
            'inertia.unread_notifications.' . $user->id,
            self::NOTIFICATIONS_CACHE_TTL,
            fn () => $user->unreadNotifications()->count()
        );
    }

    private function cachedUnreadNotifications($user)
    {
        return Cache::remember(
            'inertia.notifications.' . $user->id,
            self::NOTIFICATIONS_CACHE_TTL,
            fn () => $user->unreadNotifications()->limit(10)->get()
        );
    }
}


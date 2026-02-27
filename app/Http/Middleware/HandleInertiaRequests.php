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
        $portalBadges = $user ? $this->cachedPortalBadges($user) : ['home' => 0, 'scans' => 0, 'games' => 0, 'merch' => 0, 'referrals' => 0];

        $ziggyBase = Cache::remember('ziggy.routes', self::ZIGGY_CACHE_TTL, fn () => (new Ziggy)->toArray());

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $authUser,
                'unreadNotificationsCount' => $unreadCount,
                'notifications' => $notifications,
                'portalBadges' => $portalBadges,
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

    private function cachedPortalBadges($user): array
    {
        return Cache::remember(
            'inertia.portal_badges.' . $user->id,
            self::NOTIFICATIONS_CACHE_TTL,
            function () use ($user) {
                $badges = ['home' => 0, 'scans' => 0, 'games' => 0, 'merch' => 0, 'referrals' => 0];
                $unread = $user->unreadNotifications()->get();
                foreach ($unread as $n) {
                    $type = $n->data['type'] ?? null;
                    if (in_array($type, ['punch_card_completed', 'promo_token_awarded', 'leaderboard_prize', 'reward_won', 'reward_expiring'])) {
                        $badges['scans']++;
                    } elseif ($type === 'merch_referral_reward') {
                        $badges['merch']++;
                    } elseif (in_array($type, ['referral_commission_earned', 'referral_commission_reversed', 'referral_business_upgraded', 'referral_business_cancelled'])) {
                        $badges['referrals']++;
                    }
                }
                return $badges;
            }
        );
    }
}


<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\GamePlay;
use App\Models\GameReward;
use App\Models\UserBadge;
use App\Models\Badge;
use App\Models\QRCode;
use App\Models\Business;
use App\Models\Promotion;
use App\Models\UserPromoToken;
use App\Services\GameService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PortalController extends Controller
{
    protected GameService $gameService;

    public function __construct(GameService $gameService)
    {
        $this->gameService = $gameService;
    }

    /**
     * Portal Dashboard
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Associate any anonymous game plays with this user
        $this->gameService->associateAnonymousGamePlays($user);

        // Ensure badge count is accurate (avoid stale counters)
        $totalBadges = UserBadge::where('user_id', $user->id)->count();

        // Compute win stats from game_plays to avoid stale user counters.
        $totalGames = GamePlay::where('user_id', $user->id)->count();
        $totalWins = GamePlay::where('user_id', $user->id)->where('result', GamePlay::RESULT_WIN)->count();
        $winRate = $totalGames > 0 ? round(($totalWins / $totalGames) * 100, 1) : 0;

        // Dashboard business directory filters
        $prefs = is_array($user->preferences) ? $user->preferences : [];
        $defaultCity = isset($prefs['default_city']) ? trim((string) $prefs['default_city']) : '';
        $defaultRegion = isset($prefs['default_region']) ? trim((string) $prefs['default_region']) : '';

        $mode = $request->get('mode'); // all | near | city
        $mode = is_string($mode) ? strtolower(trim($mode)) : null;
        if (!in_array($mode, ['all', 'near', 'city'], true)) {
            // Default: use "near" if the user has a default city+region; otherwise show all.
            $mode = ($defaultCity !== '' && $defaultRegion !== '') ? 'near' : 'all';
        }

        $filterCity = $request->get('city');
        $filterRegion = $request->get('region');
        $filterCity = is_string($filterCity) ? trim($filterCity) : '';
        $filterRegion = is_string($filterRegion) ? trim($filterRegion) : '';

        $effectiveCity = null;
        $effectiveRegion = null;
        if ($mode === 'near') {
            $effectiveCity = $defaultCity !== '' ? $defaultCity : null;
            $effectiveRegion = $defaultRegion !== '' ? $defaultRegion : null;
        } elseif ($mode === 'city') {
            $effectiveCity = $filterCity !== '' ? $filterCity : null;
            $effectiveRegion = $filterRegion !== '' ? $filterRegion : null;
        }

        // City dropdown options (City + State) grow over time as businesses add them
        $cityOptions = Business::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereNotNull('city')
            ->whereNotNull('state')
            ->select(['city', 'state'])
            ->distinct()
            ->orderBy('state')
            ->orderBy('city')
            ->limit(500)
            ->get()
            ->map(fn ($row) => [
                'city' => $row->city,
                'region' => $row->state,
                'label' => trim($row->city . ', ' . $row->state),
            ])
            ->values();

        $businessQuery = Business::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->select([
                'id',
                'name',
                'slug',
                'type',
                'logo_path',
                'primary_color',
                'secondary_color',
                'city',
                'state',
                'address_line1',
                'postal_code',
                'website',
                'phone',
                'settings',
                'subscription_tier',
            ]);

        if ($effectiveCity && $effectiveRegion) {
            $businessQuery
                ->where('city', $effectiveCity)
                ->where('state', $effectiveRegion);
        }

        $businesses = $businessQuery
            ->orderBy('name')
            ->limit(100)
            ->get();

        // Featured promo lookup (Growth+ only)
        $featuredPromotionIds = $businesses
            ->filter(fn ($b) => $b->canAccess('featured_promo'))
            ->map(function ($b) {
                $settings = is_array($b->settings) ? $b->settings : [];
                $id = $settings['featured_promotion_id'] ?? null;
                return is_numeric($id) ? (int) $id : null;
            })
            ->filter()
            ->unique()
            ->values();

        $featuredPromotionsById = collect();
        if ($featuredPromotionIds->count() > 0) {
            $featuredPromotionsById = Promotion::query()
                ->whereIn('id', $featuredPromotionIds->all())
                ->where('is_active', true)
                ->get(['id', 'name', 'description', 'discount_type', 'discount_value', 'starts_at', 'ends_at', 'business_id'])
                ->keyBy('id');
        }

        $businessDirectory = $businesses->map(function (Business $b) use ($featuredPromotionsById) {
            $settings = is_array($b->settings) ? $b->settings : [];
            $featuredId = isset($settings['featured_promotion_id']) ? (int) $settings['featured_promotion_id'] : null;
            $featuredPromo = ($b->canAccess('featured_promo') && $featuredId)
                ? $featuredPromotionsById->get($featuredId)
                : null;

            return [
                'id' => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'type' => $b->type,
                'logo_url' => $b->logo_url,
                'primary_color' => $b->primary_color,
                'secondary_color' => $b->secondary_color,
                'city' => $b->city,
                'region' => $b->state,
                'public_href' => '/b/' . $b->slug,
                'featured_promo' => $featuredPromo ? [
                    'id' => $featuredPromo->id,
                    'name' => $featuredPromo->name,
                    'description' => $featuredPromo->description,
                    'display_value' => $featuredPromo->getDisplayDescription(),
                    'ends_at' => $featuredPromo->ends_at?->format('M d, Y'),
                ] : null,
                'can_feature_promo' => $b->canAccess('featured_promo'),
            ];
        })->values();

        $stackableQrCode = QRCode::query()
            ->where('type', 'stackable')
            ->where('is_active', true)
            ->whereNotNull('stackable_pool_id')
            ->first(['id', 'code', 'name']);

        // Calculate stats
        $stats = [
            'total_games' => $totalGames,
            'total_wins' => $totalWins,
            'win_rate' => $winRate,
            'current_streak' => $user->current_streak,
            'best_streak' => $user->best_streak,
            'lifetime_score' => $user->lifetime_score,
            'level' => $user->level,
            'xp' => $user->xp,
            'level_progress' => $user->getLevelProgress(),
            'total_badges' => $totalBadges,
            'total_rewards' => $user->total_rewards_won,
            'total_savings' => $user->total_savings,
        ];

        return Inertia::render('Portal/Dashboard', [
            'stats' => $stats,
            'featuredBadges' => $user->getFeaturedBadges(),
            'businessDirectory' => $businessDirectory,
            'stackableQrCode' => $stackableQrCode,
            'cityOptions' => $cityOptions,
            'directoryFilters' => [
                'mode' => $mode,
                'city' => $effectiveCity,
                'region' => $effectiveRegion,
            ],
            'userDefaultLocation' => [
                'city' => $defaultCity ?: null,
                'region' => $defaultRegion ?: null,
            ],
        ]);
    }

    /**
     * User Profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        // Compute win stats from game_plays to avoid stale user counters.
        $totalGames = GamePlay::where('user_id', $user->id)->count();
        $totalWins = GamePlay::where('user_id', $user->id)->where('result', GamePlay::RESULT_WIN)->count();
        $winRate = $totalGames > 0 ? round(($totalWins / $totalGames) * 100, 1) : 0;

        // Rewards stats (DB-driven so they stay correct even if legacy counters drift)
        // - "Rewards Won" includes classic GameReward entries + play-to-win promo token wins (UP-...).
        // - "Rewards Redeemed" includes redeemed GameRewards + redeemed promo tokens (UP-...).
        $gameRewardsWon = GameReward::where('user_id', $user->id)->count();

        // Cross-DB compatible check for the token-code marker stored in game_data JSON.
        $promoTokenWins = GamePlay::where('user_id', $user->id)
            ->where('result', GamePlay::RESULT_WIN)
            ->whereNotNull('game_data')
            ->where('game_data', 'like', '%"_user_promo_token_code"%')
            ->count();

        $gameRewardsRedeemed = GameReward::where('user_id', $user->id)
            ->where('status', GameReward::STATUS_REDEEMED)
            ->count();

        $promoTokensRedeemed = UserPromoToken::query()
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNotNull('redeemed_at')
                  ->orWhereNotNull('redemption_id');
            })
            ->count();

        return Inertia::render('Portal/Profile', [
            'user' => $user,
            'stats' => [
                'total_games' => $totalGames,
                'total_wins' => $totalWins,
                'win_rate' => $winRate,
                'current_streak' => $user->current_streak,
                'best_streak' => $user->best_streak,
                'lifetime_score' => $user->lifetime_score,
                'highest_score' => $user->highest_score,
                'level' => $user->level,
                'xp' => $user->xp,
                'level_progress' => $user->getLevelProgress(),
                'total_badges' => UserBadge::where('user_id', $user->id)->count(),
                'badge_points' => $user->badge_points,
                'total_rewards_won' => $gameRewardsWon + $promoTokenWins,
                'total_rewards_redeemed' => $gameRewardsRedeemed + $promoTokensRedeemed,
                'total_savings' => $user->total_savings,
            ],
            'featuredBadges' => $user->getFeaturedBadges(),
            'favoriteBusiness' => $user->loadMissing('favoriteBusiness')->favoriteBusiness,
            'favoriteGame' => $user->loadMissing('favoriteGame')->favoriteGame,
        ]);
    }

    /**
     * Update Profile
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $request->user()->id,
            'default_city' => 'nullable|string|max:100',
            'default_region' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        // Store default location in preferences JSON (keeps schema stable).
        $preferences = is_array($user->preferences) ? $user->preferences : [];
        $preferences['default_city'] = $validated['default_city'] ?? null;
        $preferences['default_region'] = $validated['default_region'] ?? null;
        $user->preferences = $preferences;

        unset($validated['default_city'], $validated['default_region']);

        $user->update($validated);

        return back()->with('success', 'Profile updated successfully');
    }

    /**
     * Upload user avatar/logo
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,gif,webp|max:2048',
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar_path) {
            \Storage::disk('public')->delete($user->avatar_path);
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return back()->with('success', 'Logo uploaded successfully!');
    }

    /**
     * Remove user avatar/logo
     */
    public function removeAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar_path) {
            \Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return back()->with('success', 'Logo removed successfully');
    }

    /**
     * Badges Collection
     */
    public function badges(Request $request)
    {
        $user = $request->user();

        // Get earned badges
        $earnedBadges = UserBadge::where('user_id', $user->id)
            ->with('badge')
            ->orderBy('earned_at', 'desc')
            ->get();

        // Get available badges (not yet earned)
        $earnedBadgeIds = $earnedBadges->pluck('badge_id');
        $availableBadges = Badge::active()
            ->visible()
            ->available()
            ->whereNotIn('id', $earnedBadgeIds)
            ->get();

        // Group badges by category
        $badgesByCategory = $earnedBadges->groupBy(fn($ub) => $ub->badge?->category ?? 'other');

        return Inertia::render('Portal/Badges', [
            'earnedBadges' => $earnedBadges,
            'availableBadges' => $availableBadges,
            'badgesByCategory' => $badgesByCategory,
            'totalEarned' => $earnedBadges->count(),
            'totalAvailable' => $availableBadges->count() + $earnedBadges->count(),
            'featuredBadges' => $user->getFeaturedBadges(),
        ]);
    }

    /**
     * Toggle Badge Featured Status
     */
    public function toggleBadgeFeature(Request $request, UserBadge $badge)
    {
        if ($badge->user_id !== $request->user()->id) {
            abort(403);
        }

        $badge->toggleFeatured();

        return back();
    }

    /**
     * Levels & Badges Page
     */
    public function levels(Request $request)
    {
        $user = $request->user();

        // Calculate level progress
        $levelProgress = $user->getLevelProgress();
        $level = (int) ($user->level ?: 1);
        $xp = (int) ($user->xp ?: 0);
        $xpForNextLevel = $user->getXpForLevel($level + 1);
        $xpForCurrentLevel = $user->getXpForLevel($level);
        $xpNeededForNextLevel = $xpForNextLevel - $xp;

        // Get level-exclusive QR codes user can access
        $accessiblePromotions = QRCode::where('type', 'level_exclusive')
            ->where('is_active', true)
            ->whereNotNull('required_level')
            ->where('required_level', '<=', $level)
            ->whereHas('promotion', function ($q) {
                $q->where('is_active', true);
            })
            ->with(['promotion', 'business:id,name,logo_path'])
            ->get()
            ->map(function ($qrCode) {
                if (!$qrCode->promotion || !$qrCode->business) {
                    return null;
                }
                return [
                    'id' => $qrCode->id,
                    'qr_code' => [
                        'id' => $qrCode->id,
                        'code' => $qrCode->code,
                        'required_level' => $qrCode->required_level,
                    ],
                    'promotion' => [
                        'id' => $qrCode->promotion->id,
                        'name' => $qrCode->promotion->name,
                        'display_value' => $qrCode->promotion->getDisplayDescription(),
                    ],
                    'business' => [
                        'id' => $qrCode->business->id,
                        'name' => $qrCode->business->name,
                        'logo_url' => $qrCode->business->logo_url,
                    ],
                ];
            })->filter()->values();

        // Get level-exclusive QR codes user is close to unlocking (within 2 levels)
        $upcomingPromotions = QRCode::where('type', 'level_exclusive')
            ->where('is_active', true)
            ->whereNotNull('required_level')
            ->where('required_level', '>', $level)
            ->where('required_level', '<=', $level + 2)
            ->whereHas('promotion', function ($q) {
                $q->where('is_active', true);
            })
            ->with(['promotion', 'business:id,name,logo_path'])
            ->orderBy('required_level')
            ->limit(5)
            ->get()
            ->map(function ($qrCode) {
                if (!$qrCode->promotion || !$qrCode->business) {
                    return null;
                }
                return [
                    'id' => $qrCode->id,
                    'qr_code' => [
                        'id' => $qrCode->id,
                        'code' => $qrCode->code,
                        'required_level' => $qrCode->required_level,
                    ],
                    'promotion' => [
                        'id' => $qrCode->promotion->id,
                        'name' => $qrCode->promotion->name,
                        'display_value' => $qrCode->promotion->getDisplayDescription(),
                    ],
                    'business' => [
                        'id' => $qrCode->business->id,
                        'name' => $qrCode->business->name,
                        'logo_url' => $qrCode->business->logo_url,
                    ],
                ];
            })->filter()->values();

        // Get badges (reuse logic from badges() method)
        $earnedBadges = UserBadge::where('user_id', $user->id)
            ->with('badge')
            ->orderBy('earned_at', 'desc')
            ->limit(6)
            ->get();

        $availableBadges = Badge::active()
            ->visible()
            ->available()
            ->whereNotIn('id', $earnedBadges->pluck('badge_id'))
            ->limit(6)
            ->get();

        return Inertia::render('Portal/Levels', [
            'user' => [
                'id' => $user->id,
                'level' => $level,
                'xp' => $xp,
            ],
            'levelProgress' => $levelProgress,
            'xpNeededForNextLevel' => max(0, $xpNeededForNextLevel),
            'accessiblePromotions' => $accessiblePromotions,
            'upcomingPromotions' => $upcomingPromotions,
            'earnedBadges' => $earnedBadges,
            'availableBadges' => $availableBadges,
            'featuredBadges' => $user->getFeaturedBadges(),
        ]);
    }
}


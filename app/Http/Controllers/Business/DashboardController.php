<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\QRCode;
use App\Models\Promotion;
use App\Models\Scan;
use App\Models\Redemption;
use App\Models\BusinessPartnership;
use App\Models\StackableEntry;
use App\Models\UserPromoToken;
use App\Services\OnboardingService;
use App\Support\OnboardingQr;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->user()->business;

        if (!$business) {
            // Return empty dashboard if no business setup yet
            return Inertia::render('Business/Dashboard', [
                'business' => null,
                'stats' => [
                    'total_qr_codes' => 0,
                    'active_promotions' => 0,
                    'scans_period' => 0,
                    'redemptions_period' => 0,
                    'promotion_scans_period' => 0,
                    'punch_card_scans_period' => 0,
                    'game_scans_period' => 0,
                    'leaderboard_scans_period' => 0,
                    'info_scans_period' => 0,
                    'stackable_scans_period' => 0,
                    'cross_promo_scans_period' => 0,
                    'game_plays_period' => 0,
                    'prizes_issued_period' => 0,
                    'prizes_redeemed_period' => 0,
                    'prize_redemption_rate' => 0,
                    'game_prizes_issued_period' => 0,
                    'game_prizes_redeemed_period' => 0,
                    'game_prize_redemption_rate' => 0,
                    'punch_stamps_period' => 0,
                    'punch_completions_period' => 0,
                    'punch_customers_period' => 0,
                    'punch_completion_rate' => 0,
                    'total_savings' => 0,
                ],
                'partnershipStats' => [
                    'active_partners' => 0,
                    'pending_incoming' => 0,
                    'pending_outgoing' => 0,
                    'active_pools' => 0,
                    'pending_pool_approvals' => 0,
                    'pool_reach' => 0,
                ],
                'chartData' => [],
                'recentScans' => [],
                'recentRedemptions' => [],
                'topQRCodes' => [],
                'topPromotions' => [],
                'onboardingSteps' => [],
                'onboardingProgress' => ['completed' => 0, 'total' => 0, 'percentage' => 0],
                'showOnboarding' => false,
            ]);
        }

        $excludeQrId = QRCode::query()
            ->where('business_id', $business->id)
            ->where('code', OnboardingQr::CODE)
            ->value('id');

        // Defaults so a single failing sub-query doesn't zero-out the entire dashboard
        // (We still surface an error banner, but keep whatever data we can.)
        $stats = [
            'total_qr_codes' => 0,
            'active_promotions' => 0,
            'scans_period' => 0,
            'promotion_scans_period' => 0,
            'punch_card_scans_period' => 0,
            'game_scans_period' => 0,
            'leaderboard_scans_period' => 0,
            'info_scans_period' => 0,
            'stackable_scans_period' => 0,
            'cross_promo_scans_period' => 0,
            'game_plays_period' => 0,
            'redemptions_period' => 0,
            // Prize-based rate (1-to-1): redeemed prizes / issued prizes
            'prizes_issued_period' => 0,
            'prizes_redeemed_period' => 0,
            'prize_redemption_rate' => 0,
            // Game prize breakdown (issued via game wins)
            'game_prizes_issued_period' => 0,
            'game_prizes_redeemed_period' => 0,
            'game_prize_redemption_rate' => 0,
            // Punch card funnel (separate from prize tokens)
            'punch_stamps_period' => 0,
            'punch_completions_period' => 0,
            'punch_customers_period' => 0,
            'punch_completion_rate' => 0,
            'total_savings' => 0,
        ];
        $partnershipStats = [
            'active_partners' => 0,
            'pending_incoming' => 0,
            'pending_outgoing' => 0,
            'active_pools' => 0,
            'pending_pool_approvals' => 0,
            'pool_reach' => 0,
        ];
        $chartData = [];
        $recentScans = [];
        $recentRedemptions = [];
        $topQRCodes = [];
        $topPromotions = [];
        $onboardingSteps = [];
        $onboardingProgress = ['completed' => 0, 'total' => 0, 'percentage' => 0];
        $showOnboarding = false;

        try {
            // Get date range (default last 30 days)
            $startDate = Carbon::now()->subDays(30)->startOfDay();
            $endDate = Carbon::now()->endOfDay();
            $startDateStr = $startDate->format('Y-m-d H:i:s');
            $endDateStr = $endDate->format('Y-m-d H:i:s');

            // Build bindings safely (prevents "invalid parameter number" mistakes as this query evolves).
            // Placeholders:
            // - 3 single placeholders (qr_codes business_id, intended_use, promotions business_id)
            // - 18 "(business_id, start, end)" triplets
            $range = [$business->id, $startDateStr, $endDateStr];
            $rangeBindings = [];
            for ($i = 0; $i < 18; $i++) {
                array_push($rangeBindings, ...$range);
            }
            $bindings = [$business->id, QRCode::INTENDED_USE_LEADERBOARD_PRIZE, $business->id, ...$rangeBindings];

            // Combine multiple count queries into one using raw SQL.
            // IMPORTANT:
            // - scans are engagement
            // - redemption performance should be based on issued prizes (user_promo_tokens), not scans
            $counts = \DB::selectOne("
                SELECT 
                    (SELECT COUNT(*) FROM qr_codes WHERE business_id = ? AND deleted_at IS NULL AND (intended_use IS NULL OR intended_use != ?)) as total_qr_codes,
                    (SELECT COUNT(*) FROM promotions WHERE business_id = ? AND is_active = 1 AND deleted_at IS NULL) as active_promotions,
                    (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ?) as scans_period,
                    (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND (scan_type IS NULL OR scan_type = 'promotion')) as promotion_scans_period,
                    (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND scan_type = 'punch_card') as punch_card_scans_period,
                    (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND scan_type = 'qrcade_game') as game_scans_period,
                    (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND scan_type = 'qrcade_leaderboard') as leaderboard_scans_period,
                    (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND scan_type = 'info') as info_scans_period,
                    (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND scan_type = 'stackable') as stackable_scans_period,
                    (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND scan_type = 'cross_promo') as cross_promo_scans_period,
                    (SELECT COUNT(*) FROM game_plays WHERE business_id = ? AND created_at BETWEEN ? AND ?) as game_plays_period,
                    (SELECT COUNT(*) FROM redemptions WHERE business_id = ? AND redeemed_at BETWEEN ? AND ?) as redemptions_period,
                    (SELECT COALESCE(SUM(discount_amount), 0) FROM redemptions WHERE business_id = ? AND redeemed_at BETWEEN ? AND ?) as total_savings,

                    -- Prize (voucher) issuance + redemption: 1-to-1 performance metrics
                    (SELECT COUNT(*) FROM user_promo_tokens WHERE business_id = ? AND created_at BETWEEN ? AND ?) as prizes_issued_period,
                    (SELECT COUNT(*) FROM user_promo_tokens WHERE business_id = ? AND created_at BETWEEN ? AND ? AND redeemed_at IS NOT NULL) as prizes_redeemed_period,

                    -- Game-issued prizes: link token code to game_plays.game_data._user_promo_token_code
                    (SELECT COUNT(*) FROM user_promo_tokens upt
                        WHERE upt.business_id = ? AND upt.created_at BETWEEN ? AND ?
                          AND EXISTS (
                            SELECT 1 FROM game_plays gp
                              WHERE gp.business_id = upt.business_id
                                AND gp.user_id = upt.user_id
                                AND gp.game_data IS NOT NULL
                                AND JSON_UNQUOTE(JSON_EXTRACT(gp.game_data, '$._user_promo_token_code')) = upt.code
                          )
                    ) as game_prizes_issued_period,
                    (SELECT COUNT(*) FROM user_promo_tokens upt
                        WHERE upt.business_id = ? AND upt.created_at BETWEEN ? AND ? AND upt.redeemed_at IS NOT NULL
                          AND EXISTS (
                            SELECT 1 FROM game_plays gp
                              WHERE gp.business_id = upt.business_id
                                AND gp.user_id = upt.user_id
                                AND gp.game_data IS NOT NULL
                                AND JSON_UNQUOTE(JSON_EXTRACT(gp.game_data, '$._user_promo_token_code')) = upt.code
                          )
                    ) as game_prizes_redeemed_period,

                    -- Punch card funnel (separate from prize tokens)
                    (SELECT COALESCE(SUM(r.punches_added), 0) FROM redemptions r
                        JOIN promotions p ON p.id = r.promotion_id
                        WHERE r.business_id = ? AND r.redeemed_at BETWEEN ? AND ?
                          AND p.discount_type = 'punch_card'
                    ) as punch_stamps_period,
                    (SELECT COUNT(*) FROM redemptions r
                        JOIN promotions p ON p.id = r.promotion_id
                        WHERE r.business_id = ? AND r.redeemed_at BETWEEN ? AND ?
                          AND p.discount_type = 'punch_card'
                          AND r.card_completed = 1
                    ) as punch_completions_period,
                    (SELECT COUNT(DISTINCT r.customer_user_id) FROM redemptions r
                        JOIN promotions p ON p.id = r.promotion_id
                        WHERE r.business_id = ? AND r.redeemed_at BETWEEN ? AND ?
                          AND p.discount_type = 'punch_card'
                          AND r.customer_user_id IS NOT NULL
                    ) as punch_customers_period
            ", $bindings);

            if ($excludeQrId) {
                $excludeStats = \DB::selectOne("
                    SELECT 
                        COUNT(*) as scans_period,
                        SUM(CASE WHEN scan_type IS NULL OR scan_type = 'promotion' THEN 1 ELSE 0 END) as promotion_scans_period,
                        SUM(CASE WHEN scan_type = 'punch_card' THEN 1 ELSE 0 END) as punch_card_scans_period,
                        SUM(CASE WHEN scan_type = 'qrcade_game' THEN 1 ELSE 0 END) as game_scans_period,
                        SUM(CASE WHEN scan_type = 'qrcade_leaderboard' THEN 1 ELSE 0 END) as leaderboard_scans_period,
                        SUM(CASE WHEN scan_type = 'info' THEN 1 ELSE 0 END) as info_scans_period,
                        SUM(CASE WHEN scan_type = 'stackable' THEN 1 ELSE 0 END) as stackable_scans_period,
                        SUM(CASE WHEN scan_type = 'cross_promo' THEN 1 ELSE 0 END) as cross_promo_scans_period
                    FROM scans
                    WHERE qr_code_id = ? AND scanned_at BETWEEN ? AND ?
                ", [$excludeQrId, $startDateStr, $endDateStr]);

                $counts->scans_period = max(0, ($counts->scans_period ?? 0) - ($excludeStats->scans_period ?? 0));
                $counts->promotion_scans_period = max(0, ($counts->promotion_scans_period ?? 0) - ($excludeStats->promotion_scans_period ?? 0));
                $counts->punch_card_scans_period = max(0, ($counts->punch_card_scans_period ?? 0) - ($excludeStats->punch_card_scans_period ?? 0));
                $counts->game_scans_period = max(0, ($counts->game_scans_period ?? 0) - ($excludeStats->game_scans_period ?? 0));
                $counts->leaderboard_scans_period = max(0, ($counts->leaderboard_scans_period ?? 0) - ($excludeStats->leaderboard_scans_period ?? 0));
                $counts->info_scans_period = max(0, ($counts->info_scans_period ?? 0) - ($excludeStats->info_scans_period ?? 0));
                $counts->stackable_scans_period = max(0, ($counts->stackable_scans_period ?? 0) - ($excludeStats->stackable_scans_period ?? 0));
                $counts->cross_promo_scans_period = max(0, ($counts->cross_promo_scans_period ?? 0) - ($excludeStats->cross_promo_scans_period ?? 0));
                $counts->total_qr_codes = max(0, ($counts->total_qr_codes ?? 0) - 1);
            }

            // Stats
            $promotionScans = $counts->promotion_scans_period ?? 0;
            $punchCardScans = $counts->punch_card_scans_period ?? 0;
            $gameScans = $counts->game_scans_period ?? 0;
            $leaderboardScans = $counts->leaderboard_scans_period ?? 0;
            $infoScans = $counts->info_scans_period ?? 0;
            $stackableScans = $counts->stackable_scans_period ?? 0;
            $crossPromoScans = $counts->cross_promo_scans_period ?? 0;
            $gamePlays = $counts->game_plays_period ?? 0;
            $prizesIssued = $counts->prizes_issued_period ?? 0;
            $prizesRedeemed = $counts->prizes_redeemed_period ?? 0;
            $gamePrizesIssued = $counts->game_prizes_issued_period ?? 0;
            $gamePrizesRedeemed = $counts->game_prizes_redeemed_period ?? 0;
            $punchStamps = (int)($counts->punch_stamps_period ?? 0);
            $punchCompletions = (int)($counts->punch_completions_period ?? 0);
            $punchCustomers = (int)($counts->punch_customers_period ?? 0);
            
            $stats = [
                'total_qr_codes' => $counts->total_qr_codes ?? 0,
                'active_promotions' => $counts->active_promotions ?? 0,
                'scans_period' => $counts->scans_period ?? 0,
                'promotion_scans_period' => $promotionScans,
                'punch_card_scans_period' => $punchCardScans,
                'game_scans_period' => $gameScans,
                'leaderboard_scans_period' => $leaderboardScans,
                'info_scans_period' => $infoScans,
                'stackable_scans_period' => $stackableScans,
                'cross_promo_scans_period' => $crossPromoScans,
                'game_plays_period' => $gamePlays,
                'redemptions_period' => $counts->redemptions_period ?? 0,
                'prizes_issued_period' => $prizesIssued,
                'prizes_redeemed_period' => $prizesRedeemed,
                'prize_redemption_rate' => 0,
                'game_prizes_issued_period' => $gamePrizesIssued,
                'game_prizes_redeemed_period' => $gamePrizesRedeemed,
                'game_prize_redemption_rate' => 0,
                'punch_stamps_period' => $punchStamps,
                'punch_completions_period' => $punchCompletions,
                'punch_customers_period' => $punchCustomers,
                'punch_completion_rate' => 0,
                'total_savings' => $counts->total_savings ?? 0,
            ];

            // Prize-based redemption performance (cohort):
            // tokens issued in period that have been redeemed (any time).
            if ($prizesIssued > 0) {
                $stats['prize_redemption_rate'] = round(($prizesRedeemed / $prizesIssued) * 100, 1);
            }

            if ($gamePrizesIssued > 0) {
                $stats['game_prize_redemption_rate'] = round(($gamePrizesRedeemed / $gamePrizesIssued) * 100, 1);
            }

            if ($punchCustomers > 0) {
                $stats['punch_completion_rate'] = round(($punchCompletions / $punchCustomers) * 100, 1);
            }

            // Chart data - scans over time (optimized SQL GROUP BY instead of PHP grouping)
            $chartDataRaw = \DB::select("
                SELECT 
                    DATE(scanned_at) as date,
                    COUNT(*) as count
                FROM scans
                WHERE business_id = ? 
                    AND scanned_at BETWEEN ? AND ?
                    " . ($excludeQrId ? "AND (qr_code_id IS NULL OR qr_code_id != ?)" : "") . "
                GROUP BY DATE(scanned_at)
                ORDER BY date ASC
            ", $excludeQrId
                ? [$business->id, $startDateStr, $endDateStr, $excludeQrId]
                : [$business->id, $startDateStr, $endDateStr]
            );

            $chartData = [];
            foreach ($chartDataRaw as $row) {
                $chartData[$row->date] = $row->count;
            }

            // Recent activity - use select to limit columns
            $recentScans = $business->scans()
                ->select('id', 'qr_code_id', 'device_type', 'city', 'region', 'country', 'scanned_at')
                ->with(['qrCode' => fn($q) => $q->withTrashed()->select('id', 'name', 'code')])
                ->when($excludeQrId, fn($q) => $q->where(function ($sub) use ($excludeQrId) {
                    $sub->whereNull('qr_code_id')->orWhere('qr_code_id', '!=', $excludeQrId);
                }))
                ->orderByDesc('scanned_at')
                ->limit(10)
                ->get()
                ->map(fn($scan) => [
                    'id' => $scan->id,
                    'qr_code' => $scan->qrCode?->name ?? 'Deleted QR',
                    'device' => $scan->device_type,
                    'location' => collect([$scan->city, $scan->region, $scan->country])
                        ->filter(fn($v) => !empty($v))
                        ->implode(', '),
                    'time' => $scan->scanned_at->diffForHumans(),
                ]);

        $recentRedemptionsRaw = $business->redemptions()
            ->select('id', 'promotion_id', 'employee_id', 'redeemed_by_user_id', 'customer_user_id', 'user_promo_token_id', 'customer_identifier', 'customer_name', 'discount_amount', 'redeemed_at')
            ->with([
                'promotion' => fn($q) => $q->withTrashed()->select('id', 'name'),
                'employee:id,user_id',
                'employee.user:id,name',
                'redeemedByUser:id,name',
                'customerUser:id,name',
                'userPromoToken:id,user_id,code',
                'userPromoToken.user:id,name',
            ])
            ->orderByDesc('redeemed_at')
            ->limit(10)
            ->get();

        // Collect all potential token codes from legacy customer_identifier
        $legacyTokenCodes = [];
        foreach ($recentRedemptionsRaw as $r) {
            if (!$r->customerUser && !$r->userPromoToken && !$r->customer_name && !empty($r->customer_identifier)) {
                $normalized = strtoupper(str_replace('-', '', (string) $r->customer_identifier));
                if (str_starts_with($normalized, 'UP') && strlen($normalized) === 10) {
                    $legacyTokenCodes[] = $normalized;
                }
            }
        }
        
        $resolvedLegacyTokens = [];
        if (!empty($legacyTokenCodes)) {
            $resolvedLegacyTokens = UserPromoToken::query()
                ->whereRaw('REPLACE(UPPER(code), "-", "") IN (' . implode(',', array_fill(0, count($legacyTokenCodes), '?')) . ')', $legacyTokenCodes)
                ->with('user:id,name')
                ->get()
                ->keyBy(function($token) {
                    // key by normalized code
                    return strtoupper(str_replace('-', '', $token->code));
                });
        }

        $recentRedemptions = $recentRedemptionsRaw->map(function ($redemption) use ($resolvedLegacyTokens) {
                $customerName =
                    $redemption->customerUser?->name
                    ?? $redemption->userPromoToken?->user?->name
                    ?? $redemption->customer_name;

                // Legacy fallback: resolve customer by token code stored in customer_identifier (UP-xxxx-xxxx)
                if (!$customerName && !empty($redemption->customer_identifier)) {
                    $normalized = strtoupper(str_replace('-', '', (string) $redemption->customer_identifier));
                    if (str_starts_with($normalized, 'UP') && strlen($normalized) === 10) {
                        $token = $resolvedLegacyTokens->get($normalized);
                        $customerName = $token?->user?->name;
                    }
                }

                $staffName =
                    $redemption->employee?->user?->name
                    ?? $redemption->redeemedByUser?->name;

                return [
                    'id' => $redemption->id,
                    'promotion' => $redemption->promotion?->name ?? 'Deleted Promotion',
                    'customer' => $customerName ?: 'Unknown',
                    'staff' => $staffName ?: null,
                    'discount' => $redemption->discount_amount,
                    'time' => $redemption->redeemed_at->diffForHumans(),
                ];
            });

            // Top performing - select only needed columns
            $topQRCodes = $business->qrCodes()
                ->visibleToBusiness()
                ->withTrashed()
                ->select('id', 'name', 'code', 'total_scans', 'placement_location')
                ->where('code', '!=', OnboardingQr::CODE)
                ->orderByDesc('total_scans')
                ->limit(5)
                ->get();

            $topPromotions = $business->promotions()
                ->withTrashed()
                ->select('id', 'name', 'discount_type', 'total_redemptions', 'total_savings')
                ->orderByDesc('total_redemptions')
                ->limit(5)
                ->get();

            // Partnership & Pool Stats (optimized)
            $partnershipStats = $this->getPartnershipStats($business);

            // Onboarding data
            $onboardingService = new OnboardingService();
            $onboardingSteps = $onboardingService->getStepsForBusiness($business);
            $onboardingUpsellSteps = $onboardingService->getUpsellStepsForBusiness($business);
            $onboardingProgress = $business->getOnboardingProgress();
            $showOnboarding = $business->shouldShowOnboarding();

            return Inertia::render('Business/Dashboard', [
                'business' => $business->only(['id', 'name', 'logo_path', 'subscription_tier', 'trial_ends_at']),
                'stats' => $stats,
                'partnershipStats' => $partnershipStats,
                'chartData' => $chartData,
                'recentScans' => $recentScans,
                'recentRedemptions' => $recentRedemptions,
                'topQRCodes' => $topQRCodes,
                'topPromotions' => $topPromotions,
                'onboardingSteps' => $onboardingSteps,
                'onboardingUpsellSteps' => $onboardingUpsellSteps,
                'onboardingProgress' => $onboardingProgress,
                'showOnboarding' => $showOnboarding,
            ]);
        } catch (\Exception $e) {
            \Log::error('Business dashboard failed to load some data', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return best-effort data if any query fails
            return Inertia::render('Business/Dashboard', [
                'business' => $business->only(['id', 'name', 'logo_path', 'subscription_tier', 'trial_ends_at']),
                'stats' => $stats,
                'partnershipStats' => $partnershipStats,
                'chartData' => $chartData,
                'recentScans' => $recentScans,
                'recentRedemptions' => $recentRedemptions,
                'topQRCodes' => $topQRCodes,
                'topPromotions' => $topPromotions,
                'onboardingSteps' => $onboardingSteps,
                'onboardingUpsellSteps' => $onboardingUpsellSteps ?? [],
                'onboardingProgress' => $onboardingProgress,
                'showOnboarding' => $showOnboarding,
                'error' => 'Some dashboard data could not be loaded. Please refresh. (We still show partial data.)',
            ]);
        }
    }

    public function settings(Request $request)
    {
        $business = $request->user()->business;

        return Inertia::render('Business/Settings', [
            'business' => $business->load([]), // Ensure all fields are loaded
            'promotions' => $business
                ? $business->promotions()
                    ->withTrashed()
                    ->orderByDesc('created_at')
                    ->get(['id', 'name', 'discount_type', 'discount_value', 'starts_at', 'ends_at', 'is_active'])
                : [],
            'canFeaturePromo' => $business ? $business->canAccess('featured_promo') : false,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $business = $request->user()->business;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'business_hours' => 'nullable|array',
            'business_hours.*.day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'business_hours.*.open' => 'nullable|string|max:10',
            'business_hours.*.close' => 'nullable|string|max:10',
            'business_hours.*.closed' => 'nullable|boolean',
            'website' => 'nullable|url|max:255',
            'facebook_url' => 'nullable|url|max:255',
            'instagram_url' => 'nullable|url|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:2',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            'featured_promotion_id' => 'nullable|integer',
        ]);

        // Feature gate: White label branding (Enterprise only)
        if ((isset($validated['primary_color']) || isset($validated['secondary_color'])) && !$business->canAccess('white_label')) {
            // Allow color changes on all tiers, but white label full branding is Enterprise only
            // Colors can be used for basic customization on all tiers
        }

        // Featured promo selection (Growth+ only)
        if (array_key_exists('featured_promotion_id', $validated)) {
            $featuredPromotionId = $validated['featured_promotion_id'];
            unset($validated['featured_promotion_id']);

            if (!$business->canAccess('featured_promo')) {
                // Ignore silently; UI should gate, but backend is canonical.
                $featuredPromotionId = null;
            }

            if ($featuredPromotionId) {
                $ownsPromotion = $business->promotions()->withTrashed()->where('id', $featuredPromotionId)->exists();
                if (!$ownsPromotion) {
                    return back()->withErrors(['featured_promotion_id' => 'Invalid promotion selected.']);
                }
            }

            $settings = is_array($business->settings) ? $business->settings : [];
            $settings['featured_promotion_id'] = $featuredPromotionId ?: null;
            $business->settings = $settings;
            $business->save();
        }

        $business->update($validated);

        return back()->with('success', 'Settings updated successfully.');
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,gif,webp|max:2048',
        ]);

        $business = $request->user()->business;

        // Delete old logo if exists
        if ($business->logo_path) {
            \Storage::disk('public')->delete($business->logo_path);
        }

        // Store new logo
        $path = $request->file('logo')->store('logos', 'public');
        $business->update(['logo_path' => $path]);

        // Return JSON for AJAX requests, otherwise redirect
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Logo uploaded successfully.',
                'logo_url' => $business->logo_url,
            ]);
        }

        return back()->with('success', 'Logo uploaded successfully.');
    }

    public function removeLogo(Request $request)
    {
        $business = $request->user()->business;

        if ($business->logo_path) {
            \Storage::disk('public')->delete($business->logo_path);
            $business->update(['logo_path' => null]);
        }

        // Return JSON for AJAX requests, otherwise redirect
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Logo removed successfully.',
            ]);
        }

        return back()->with('success', 'Logo removed successfully.');
    }

    /**
     * Get partnership and deal pool statistics (optimized single query)
     */
    private function getPartnershipStats(Business $business): array
    {
        // Combine all partnership/pool counts into one query
        $stats = \DB::selectOne("
            SELECT 
                (SELECT COUNT(*) FROM business_partnerships 
                 WHERE (requester_business_id = ? OR partner_business_id = ?) 
                 AND status = 'accepted') as active_partners,
                (SELECT COUNT(*) FROM business_partnerships 
                 WHERE partner_business_id = ? AND status = 'pending') as pending_incoming,
                (SELECT COUNT(*) FROM business_partnerships 
                 WHERE requester_business_id = ? AND status = 'pending') as pending_outgoing,
                (SELECT COUNT(*) FROM stackable_entries 
                 WHERE business_id = ? AND is_active = 1 AND is_approved = 1) as active_pools,
                (SELECT COUNT(*) FROM stackable_entries 
                 WHERE business_id = ? AND is_approved = 0) as pending_pool_approvals
        ", [
            $business->id, $business->id,
            $business->id,
            $business->id,
            $business->id,
            $business->id,
        ]);

        // Pool reach calculation (only if needed)
        $poolReach = 0;
        if (($stats->active_pools ?? 0) > 0) {
            $poolReach = \DB::table('stackable_entries as se1')
                ->join('stackable_entries as se2', 'se1.stackable_pool_id', '=', 'se2.stackable_pool_id')
                ->where('se1.business_id', $business->id)
                ->where('se1.is_active', true)
                ->where('se1.is_approved', true)
                ->where('se2.is_active', true)
                ->where('se2.is_approved', true)
                ->where('se2.business_id', '!=', $business->id)
                ->distinct()
                ->count('se2.business_id');
        }

        return [
            'active_partners' => $stats->active_partners ?? 0,
            'pending_incoming' => $stats->pending_incoming ?? 0,
            'pending_outgoing' => $stats->pending_outgoing ?? 0,
            'active_pools' => $stats->active_pools ?? 0,
            'pending_pool_approvals' => $stats->pending_pool_approvals ?? 0,
            'pool_reach' => $poolReach,
        ];
    }
}


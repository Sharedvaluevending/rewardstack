<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Scan;
use App\Models\Redemption;
use App\Models\QRCode;
use App\Models\Promotion;
use App\Models\AIInsight;
use App\Models\UserPromoToken;
use App\Models\CrossPromotion;
use App\Models\BusinessPartnership;
use App\Models\MerchTag;
use App\Models\MerchReferralAward;
use App\Support\OnboardingQr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->user()->business;
        $excludeQrId = $this->getExcludedQrCodeId($business);
        $period = max(1, min(365, (int) $request->get('period', 30)));
        $startDate = Carbon::now()->subDays($period)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Overview stats (cache briefly)
        $cacheBase = 'biz:' . $business->id . ':analytics:' . $period;
        $ttl = now()->addSeconds(60);

        $stats = Cache::remember($cacheBase . ':stats', $ttl, fn () => $this->getOverviewStats($business, $startDate, $endDate, $excludeQrId));

        // Charts data (cache briefly)
        $scansOverTime = Cache::remember($cacheBase . ':scansOverTime', $ttl, fn () => $this->getScansOverTime($business, $startDate, $endDate, $excludeQrId));
        $redemptionsOverTime = Cache::remember($cacheBase . ':redemptionsOverTime', $ttl, fn () => $this->getRedemptionsOverTime($business, $startDate, $endDate));
        $deviceBreakdown = Cache::remember($cacheBase . ':deviceBreakdown', $ttl, fn () => $this->getDeviceBreakdown($business, $startDate, $endDate, $excludeQrId));
        $topQRCodes = Cache::remember($cacheBase . ':topQRCodes', $ttl, fn () => $this->getTopQRCodes($business, $startDate, $endDate));
        $topPromotions = Cache::remember($cacheBase . ':topPromotions', $ttl, fn () => $this->getTopPromotions($business, $startDate, $endDate));
        $hourlyDistribution = Cache::remember($cacheBase . ':hourlyDistribution', $ttl, fn () => $this->getHourlyDistribution($business, $startDate, $endDate, $excludeQrId));
        $locationBreakdown = Cache::remember($cacheBase . ':locationBreakdown', $ttl, fn () => $this->getLocationBreakdown($business, $startDate, $endDate, $excludeQrId));

        // AI Insights (cache a bit longer)
        $insights = Cache::remember($cacheBase . ':insights', now()->addMinutes(5), fn () => $this->getAIInsights($business, $excludeQrId));

        return Inertia::render('Business/Analytics/Index', [
            'stats' => $stats,
            'scansOverTime' => $scansOverTime,
            'redemptionsOverTime' => $redemptionsOverTime,
            'deviceBreakdown' => $deviceBreakdown,
            'topQRCodes' => $topQRCodes,
            'topPromotions' => $topPromotions,
            'hourlyDistribution' => $hourlyDistribution,
            'locationBreakdown' => $locationBreakdown,
            'insights' => $insights,
            'period' => $period,
        ]);
    }

    public function scans(Request $request)
    {
        return redirect()->route('business.analytics', $request->only('period'));
    }

    public function redemptions(Request $request)
    {
        return redirect()->route('business.analytics', $request->only('period'));
    }

    public function finance(Request $request)
    {
        $business = $request->user()->business;
        $period = max(1, min(365, (int) $request->get('period', 30)));
        $startDate = Carbon::now()->subDays($period)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $cacheBase = 'biz:' . $business->id . ':finance:' . $period;
        $ttl = now()->addSeconds(60);

        $financeStats = Cache::remember($cacheBase . ':stats', $ttl, function () use ($business, $startDate, $endDate) {
            $startDateStr = $startDate->format('Y-m-d H:i:s');
            $endDateStr = $endDate->format('Y-m-d H:i:s');
            $stats = DB::selectOne("
                SELECT 
                    COALESCE(SUM(final_amount), 0) as revenue,
                    COALESCE(SUM(discount_amount), 0) as cost,
                    COUNT(*) as redemptions
                FROM redemptions 
                WHERE business_id = ? AND redeemed_at BETWEEN ? AND ?
            ", [$business->id, $startDateStr, $endDateStr]);

            $roi = $stats->cost > 0 ? round($stats->revenue / $stats->cost, 2) : 0;
            $avgDiscount = $stats->redemptions > 0 ? round($stats->cost / $stats->redemptions, 2) : 0;

            return [
                'total_revenue' => (float)$stats->revenue,
                'total_cost' => (float)$stats->cost,
                'total_redemptions' => (int)$stats->redemptions,
                'roi' => (float)$roi,
                'avg_discount' => (float)$avgDiscount,
            ];
        });

        $promotionFinance = Cache::remember($cacheBase . ':promotions', $ttl, function () use ($business, $startDate, $endDate) {
            return $business->promotions()
                ->withTrashed()
                ->select('id', 'name', 'discount_type')
                ->withCount(['redemptions' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('redeemed_at', [$startDate, $endDate]);
                }])
                ->withSum(['redemptions' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('redeemed_at', [$startDate, $endDate]);
                }], 'final_amount')
                ->withSum(['redemptions' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('redeemed_at', [$startDate, $endDate]);
                }], 'discount_amount')
                ->get()
                ->filter(fn($p) => $p->redemptions_count > 0)
                ->map(function ($p) {
                    $revenue = (float)($p->redemptions_sum_final_amount ?? 0);
                    $cost = (float)($p->redemptions_sum_discount_amount ?? 0);
                    $totalValue = $revenue + $cost;
                    
                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'type' => $p->discount_type,
                        'redemptions' => $p->redemptions_count,
                        'revenue' => $revenue,
                        'cost' => $cost,
                        'roi' => $cost > 0 ? round($revenue / $cost, 2) : 0,
                        'efficiency' => $totalValue > 0 ? round(($cost / $totalValue) * 100, 1) : 0,
                    ];
                })
                ->sortByDesc('revenue')
                ->values();
        });

        $financeOverTime = Cache::remember($cacheBase . ':overTime', $ttl, function () use ($business, $startDate, $endDate) {
            $startDateStr = $startDate->format('Y-m-d H:i:s');
            $endDateStr = $endDate->format('Y-m-d H:i:s');
            $raw = DB::select("
                SELECT 
                    DATE(redeemed_at) as date,
                    SUM(final_amount) as revenue,
                    SUM(discount_amount) as cost
                FROM redemptions
                WHERE business_id = ? AND redeemed_at BETWEEN ? AND ?
                GROUP BY DATE(redeemed_at)
                ORDER BY date ASC
            ", [$business->id, $startDateStr, $endDateStr]);

            $lookup = [];
            foreach ($raw as $row) {
                $lookup[$row->date] = [
                    'revenue' => (float)$row->revenue,
                    'cost' => (float)$row->cost,
                ];
            }

            $result = [];
            $current = $startDate->copy();
            while ($current <= $endDate) {
                $dateKey = $current->format('Y-m-d');
                $result[] = [
                    'date' => $current->format('M d'),
                    'revenue' => ($lookup[$dateKey] ?? [])['revenue'] ?? 0,
                    'cost' => ($lookup[$dateKey] ?? [])['cost'] ?? 0,
                ];
                $current->addDay();
            }
            return $result;
        });

        return Inertia::render('Business/Analytics/Finance', [
            'stats' => $financeStats,
            'promotions' => $promotionFinance,
            'overTime' => $financeOverTime,
            'period' => $period,
        ]);
    }

    public function merch(Request $request)
    {
        $business = $request->user()->business;
        $period = max(1, min(365, (int) $request->get('period', 30)));
        $startDate = Carbon::now()->subDays($period)->startOfDay();
        $endDate = Carbon::now()->endOfDay();
        $status = $request->get('status', 'all');
        $query = trim((string) $request->get('q', ''));
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 25;

        $tagsQuery = MerchTag::with(['owner:id,name,avatar_path', 'qrCode:id,code,name', 'qrCode.merchReferralReward'])
            ->where('business_id', $business->id)
            ->orderByDesc('created_at');

        if ($status === 'claimed') {
            $tagsQuery->whereNotNull('owner_user_id');
        } elseif ($status === 'unclaimed') {
            $tagsQuery->whereNull('owner_user_id');
        }

        if (!empty($query)) {
            $tagsQuery->where(function ($q) use ($query) {
                $q->where('code', 'like', '%' . $query . '%')
                    ->orWhereHas('qrCode', function ($qr) use ($query) {
                        $qr->where('name', 'like', '%' . $query . '%')
                            ->orWhere('code', 'like', '%' . $query . '%');
                    })
                    ->orWhereHas('owner', function ($owner) use ($query) {
                        $owner->where('name', 'like', '%' . $query . '%');
                    });
            });
        }

        $tags = $tagsQuery
            ->paginate($perPage)
            ->withQueryString();

        $tagIds = $tags->getCollection()->pluck('id');

        $scanCounts = Scan::selectRaw('merch_tag_id, count(*) as total_scans, count(distinct session_id) as unique_scans, max(scanned_at) as last_scan_at')
            ->where('business_id', $business->id)
            ->whereIn('merch_tag_id', $tagIds)
            ->whereBetween('scanned_at', [$startDate, $endDate])
            ->groupBy('merch_tag_id')
            ->get()
            ->keyBy('merch_tag_id');

        $redemptionCounts = Redemption::selectRaw('scans.merch_tag_id, count(*) as redemptions, max(redemptions.redeemed_at) as last_redeemed_at')
            ->join('scans', 'scans.id', '=', 'redemptions.scan_id')
            ->where('scans.business_id', $business->id)
            ->whereIn('scans.merch_tag_id', $tagIds)
            ->whereBetween('redemptions.redeemed_at', [$startDate, $endDate])
            ->groupBy('scans.merch_tag_id')
            ->get()
            ->keyBy('merch_tag_id');

        $awardsByTag = MerchReferralAward::selectRaw('merch_tag_id, count(*) as awards')
            ->whereIn('merch_tag_id', $tagIds)
            ->whereBetween('awarded_at', [$startDate, $endDate])
            ->groupBy('merch_tag_id')
            ->get()
            ->keyBy('merch_tag_id');

        $items = $tags->getCollection()->map(function ($tag) use ($scanCounts, $redemptionCounts, $awardsByTag) {
            $scanStats = $scanCounts->get($tag->id);
            $redemptions = $redemptionCounts->get($tag->id);
            $awards = $awardsByTag->get($tag->id);
            $reward = $tag->qrCode?->merchReferralReward;

            return [
                'id' => $tag->id,
                'code' => $tag->code,
                'is_claimed' => (bool) $tag->owner_user_id,
                'claimed_at' => optional($tag->claimed_at)->toDateTimeString(),
                'owner' => $tag->owner ? [
                    'name' => $tag->owner->name,
                    'avatar_path' => $tag->owner->avatar_path,
                ] : null,
                'qr_code' => $tag->qrCode ? [
                    'name' => $tag->qrCode->name,
                    'code' => $tag->qrCode->code,
                ] : null,
                'stats' => [
                    'total_scans' => (int) ($scanStats->total_scans ?? 0),
                    'unique_scans' => (int) ($scanStats->unique_scans ?? 0),
                    'redemptions' => (int) ($redemptions->redemptions ?? 0),
                    'last_scan_at' => $scanStats->last_scan_at ?? null,
                    'last_redeemed_at' => $redemptions->last_redeemed_at ?? null,
                    'awards' => (int) ($awards->awards ?? 0),
                ],
                'reward' => $reward ? [
                    'type' => $reward->reward_type,
                    'value' => $reward->reward_value,
                    'description' => $reward->reward_description,
                    'redemptions_required' => (int) $reward->redemptions_required,
                ] : null,
            ];
        });

        $summaryBase = MerchTag::where('business_id', $business->id);
        $summary = [
            'total_tags' => (int) $summaryBase->count(),
            'claimed_tags' => (int) (clone $summaryBase)->whereNotNull('owner_user_id')->count(),
            // distinct('session_id') is MySQL-compatible; use COUNT(DISTINCT session_id) for Postgres
            'unique_scans' => (int) Scan::where('business_id', $business->id)
                ->whereNotNull('merch_tag_id')
                ->whereBetween('scanned_at', [$startDate, $endDate])
                ->distinct('session_id')
                ->count('session_id'),
            'redemptions' => (int) Redemption::join('scans', 'scans.id', '=', 'redemptions.scan_id')
                ->where('scans.business_id', $business->id)
                ->whereNotNull('scans.merch_tag_id')
                ->whereBetween('redemptions.redeemed_at', [$startDate, $endDate])
                ->count(),
            'awards' => (int) MerchReferralAward::whereHas('merchTag', function ($q) use ($business) {
                $q->where('business_id', $business->id);
            })->whereBetween('awarded_at', [$startDate, $endDate])->count(),
        ];

        return Inertia::render('Business/Analytics/Merch', [
            'items' => $tags->setCollection($items),
            'summary' => $summary,
            'period' => $period,
            'filters' => [
                'status' => $status,
                'q' => $query,
                'per_page' => $perPage,
            ],
        ]);
    }

    protected function getOverviewStats($business, $startDate, $endDate, ?int $excludeQrId = null): array
    {
        $previousStart = $startDate->copy()->subDays($startDate->diffInDays($endDate));
        $previousEnd = $startDate->copy()->subDay();

        // Current period (optimized single query)
        $startDateStr = $startDate->format('Y-m-d H:i:s');
        $endDateStr = $endDate->format('Y-m-d H:i:s');

        $range = [$business->id, $startDateStr, $endDateStr];
        $rangeBindings = [];
        // There are 18 "(... BETWEEN ? AND ?)" triplets in the query below.
        for ($i = 0; $i < 18; $i++) {
            array_push($rangeBindings, ...$range);
        }

        $currentStats = DB::selectOne("
            SELECT 
                (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND (scan_type IS NULL OR scan_type != 'blocked')) as scans,
                (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND (scan_type IS NULL OR scan_type = 'promotion')) as promotion_scans,
                (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND scan_type = 'punch_card') as punch_card_scans,
                (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND scan_type = 'qrcade_game') as game_scans,
                (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND scan_type = 'qrcade_leaderboard') as leaderboard_scans,
                (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND scan_type = 'info') as info_scans,
                (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND scan_type = 'stackable') as stackable_scans,
                (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND scan_type = 'cross_promo') as cross_promo_scans,
                (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND scan_type = 'blocked') as blocked_scans,
                (SELECT COUNT(DISTINCT session_id) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ? AND (scan_type IS NULL OR scan_type != 'blocked')) as unique_scans,
                (SELECT COUNT(*) FROM redemptions WHERE business_id = ? AND redeemed_at BETWEEN ? AND ?) as redemptions,
                (SELECT COALESCE(SUM(discount_amount), 0) FROM redemptions WHERE business_id = ? AND redeemed_at BETWEEN ? AND ?) as savings,
                (SELECT COALESCE(SUM(final_amount), 0) FROM redemptions WHERE business_id = ? AND redeemed_at BETWEEN ? AND ?) as revenue,

                -- Prize issuance (tokens) is the canonical 1-to-1 unit for redemption performance
                (SELECT COUNT(*) FROM user_promo_tokens WHERE business_id = ? AND created_at BETWEEN ? AND ?) as prizes_issued,
                (SELECT COUNT(*) FROM user_promo_tokens WHERE business_id = ? AND created_at BETWEEN ? AND ? AND redeemed_at IS NOT NULL) as prizes_redeemed,

                -- Punch card funnel (separate from prize tokens)
                (SELECT COALESCE(SUM(r.punches_added), 0) FROM redemptions r
                    JOIN promotions p ON p.id = r.promotion_id
                    WHERE r.business_id = ? AND r.redeemed_at BETWEEN ? AND ?
                      AND p.discount_type = 'punch_card'
                ) as punch_stamps,
                (SELECT COUNT(*) FROM redemptions r
                    JOIN promotions p ON p.id = r.promotion_id
                    WHERE r.business_id = ? AND r.redeemed_at BETWEEN ? AND ?
                      AND p.discount_type = 'punch_card'
                      AND r.card_completed = 1
                ) as punch_completions,
                (SELECT COUNT(DISTINCT r.customer_user_id) FROM redemptions r
                    JOIN promotions p ON p.id = r.promotion_id
                    WHERE r.business_id = ? AND r.redeemed_at BETWEEN ? AND ?
                      AND p.discount_type = 'punch_card'
                      AND r.customer_user_id IS NOT NULL
                ) as punch_customers
        ", $rangeBindings);

        $currentScans = $currentStats->scans ?? 0;
        $currentPromotionScans = $currentStats->promotion_scans ?? 0;
        $currentPunchCardScans = $currentStats->punch_card_scans ?? 0;
        $currentGameScans = $currentStats->game_scans ?? 0;
        $currentLeaderboardScans = $currentStats->leaderboard_scans ?? 0;
        $currentInfoScans = $currentStats->info_scans ?? 0;
        $currentStackableScans = $currentStats->stackable_scans ?? 0;
        $currentCrossPromoScans = $currentStats->cross_promo_scans ?? 0;
        $currentBlockedScans = $currentStats->blocked_scans ?? 0;
        $currentUniqueScans = $currentStats->unique_scans ?? 0;
        $currentRedemptions = $currentStats->redemptions ?? 0;
        $currentSavings = $currentStats->savings ?? 0;
        $currentRevenue = $currentStats->revenue ?? 0;
        $currentPrizesIssued = $currentStats->prizes_issued ?? 0;
        $currentPrizesRedeemed = $currentStats->prizes_redeemed ?? 0;
        $currentPrizeRedemptionRate = $currentPrizesIssued > 0 ? round(($currentPrizesRedeemed / $currentPrizesIssued) * 100, 1) : 0;
        $currentPunchStamps = (int)($currentStats->punch_stamps ?? 0);
        $currentPunchCompletions = (int)($currentStats->punch_completions ?? 0);
        $currentPunchCustomers = (int)($currentStats->punch_customers ?? 0);
        $currentPunchCompletionRate = $currentPunchCustomers > 0 ? round(($currentPunchCompletions / $currentPunchCustomers) * 100, 1) : 0;

        // Previous period for comparison (optimized single query)
        $previousStartStr = $previousStart->format('Y-m-d H:i:s');
        $previousEndStr = $previousEnd->format('Y-m-d H:i:s');
        $previousStats = DB::selectOne("
            SELECT 
                (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ?) as scans,
                (SELECT COUNT(*) FROM user_promo_tokens WHERE business_id = ? AND created_at BETWEEN ? AND ?) as prizes_issued,
                (SELECT COUNT(*) FROM redemptions WHERE business_id = ? AND redeemed_at BETWEEN ? AND ?) as redemptions,
                (SELECT COALESCE(SUM(discount_amount), 0) FROM redemptions WHERE business_id = ? AND redeemed_at BETWEEN ? AND ?) as savings,
                (SELECT COALESCE(SUM(final_amount), 0) FROM redemptions WHERE business_id = ? AND redeemed_at BETWEEN ? AND ?) as revenue
        ", [
            $business->id, $previousStartStr, $previousEndStr,
            $business->id, $previousStartStr, $previousEndStr,
            $business->id, $previousStartStr, $previousEndStr,
            $business->id, $previousStartStr, $previousEndStr,
            $business->id, $previousStartStr, $previousEndStr,
        ]);

        $previousScans = $previousStats->scans ?? 0;
        $previousPrizesIssued = $previousStats->prizes_issued ?? 0;
        $previousRedemptions = $previousStats->redemptions ?? 0;
        $previousSavings = $previousStats->savings ?? 0;
        $previousRevenue = $previousStats->revenue ?? 0;

        if ($excludeQrId) {
            $currentExclude = DB::selectOne("
                SELECT 
                    SUM(CASE WHEN scan_type IS NULL OR scan_type != 'blocked' THEN 1 ELSE 0 END) as scans,
                    SUM(CASE WHEN scan_type IS NULL OR scan_type = 'promotion' THEN 1 ELSE 0 END) as promotion_scans,
                    SUM(CASE WHEN scan_type = 'punch_card' THEN 1 ELSE 0 END) as punch_card_scans,
                    SUM(CASE WHEN scan_type = 'qrcade_game' THEN 1 ELSE 0 END) as game_scans,
                    SUM(CASE WHEN scan_type = 'qrcade_leaderboard' THEN 1 ELSE 0 END) as leaderboard_scans,
                    SUM(CASE WHEN scan_type = 'info' THEN 1 ELSE 0 END) as info_scans,
                    SUM(CASE WHEN scan_type = 'stackable' THEN 1 ELSE 0 END) as stackable_scans,
                    SUM(CASE WHEN scan_type = 'cross_promo' THEN 1 ELSE 0 END) as cross_promo_scans,
                    SUM(CASE WHEN scan_type = 'blocked' THEN 1 ELSE 0 END) as blocked_scans,
                    COUNT(DISTINCT CASE WHEN scan_type IS NULL OR scan_type != 'blocked' THEN session_id END) as unique_scans
                FROM scans
                WHERE qr_code_id = ? AND scanned_at BETWEEN ? AND ?
            ", [$excludeQrId, $startDateStr, $endDateStr]);

            $currentScans = max(0, $currentScans - ($currentExclude->scans ?? 0));
            $currentPromotionScans = max(0, $currentPromotionScans - ($currentExclude->promotion_scans ?? 0));
            $currentPunchCardScans = max(0, $currentPunchCardScans - ($currentExclude->punch_card_scans ?? 0));
            $currentGameScans = max(0, $currentGameScans - ($currentExclude->game_scans ?? 0));
            $currentLeaderboardScans = max(0, $currentLeaderboardScans - ($currentExclude->leaderboard_scans ?? 0));
            $currentInfoScans = max(0, $currentInfoScans - ($currentExclude->info_scans ?? 0));
            $currentStackableScans = max(0, $currentStackableScans - ($currentExclude->stackable_scans ?? 0));
            $currentCrossPromoScans = max(0, $currentCrossPromoScans - ($currentExclude->cross_promo_scans ?? 0));
            $currentBlockedScans = max(0, $currentBlockedScans - ($currentExclude->blocked_scans ?? 0));
            $currentUniqueScans = max(0, $currentUniqueScans - ($currentExclude->unique_scans ?? 0));

            $previousExclude = DB::selectOne("
                SELECT COUNT(*) as scans
                FROM scans
                WHERE qr_code_id = ? AND scanned_at BETWEEN ? AND ?
            ", [$excludeQrId, $previousStartStr, $previousEndStr]);
            $previousScans = max(0, $previousScans - ($previousExclude->scans ?? 0));
        }

        // Calculate changes
        $scansChange = $previousScans > 0 ? round((($currentScans - $previousScans) / $previousScans) * 100, 1) : 0;
        $prizesIssuedChange = $previousPrizesIssued > 0 ? round((($currentPrizesIssued - $previousPrizesIssued) / $previousPrizesIssued) * 100, 1) : 0;
        $redemptionsChange = $previousRedemptions > 0 ? round((($currentRedemptions - $previousRedemptions) / $previousRedemptions) * 100, 1) : 0;
        $savingsChange = $previousSavings > 0 ? round((($currentSavings - $previousSavings) / $previousSavings) * 100, 1) : 0;
        $revenueChange = $previousRevenue > 0 ? round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 1) : 0;

        return [
            'total_scans' => $currentScans,
            'promotion_scans' => $currentPromotionScans,
            'punch_card_scans' => $currentPunchCardScans,
            'game_scans' => $currentGameScans,
            'leaderboard_scans' => $currentLeaderboardScans,
            'info_scans' => $currentInfoScans,
            'stackable_scans' => $currentStackableScans,
            'cross_promo_scans' => $currentCrossPromoScans,
            'blocked_scans' => $currentBlockedScans,
            'scans_change' => $scansChange,
            'unique_scans' => $currentUniqueScans,
            'total_redemptions' => $currentRedemptions,
            'redemptions_change' => $redemptionsChange,
            'total_savings' => $currentSavings,
            'savings_change' => $savingsChange,
            'total_revenue' => $currentRevenue,
            'revenue_change' => $revenueChange,
            // Prize-based redemption performance
            'prizes_issued' => (int)$currentPrizesIssued,
            'prizes_issued_change' => $prizesIssuedChange,
            'prizes_redeemed' => (int)$currentPrizesRedeemed,
            'prize_redemption_rate' => $currentPrizeRedemptionRate,

            // Punch cards (separate funnel)
            'punch_stamps' => $currentPunchStamps,
            'punch_completions' => $currentPunchCompletions,
            'punch_customers' => $currentPunchCustomers,
            'punch_completion_rate' => $currentPunchCompletionRate,
            'active_qr_codes' => $business->qrCodes()->visibleToBusiness()->where('is_active', true)->count(),
            'active_promotions' => $business->promotions()->where('is_active', true)->count(),
        ];
    }

    protected function getScansOverTime($business, $startDate, $endDate, ?int $excludeQrId = null): array
    {
        $startDateStr = $startDate->format('Y-m-d H:i:s');
        $endDateStr = $endDate->format('Y-m-d H:i:s');
        // Optimized: Use SQL GROUP BY instead of loading all records into PHP
        $scansRaw = DB::select("
            SELECT 
                DATE(scanned_at) as date,
                COUNT(*) as count
            FROM scans
            WHERE business_id = ? 
                AND scanned_at BETWEEN ? AND ?
                AND (scan_type IS NULL OR scan_type != 'blocked')
                " . ($excludeQrId ? "AND (qr_code_id IS NULL OR qr_code_id != ?)" : "") . "
            GROUP BY DATE(scanned_at)
            ORDER BY date ASC
        ", $excludeQrId ? [$business->id, $startDateStr, $endDateStr, $excludeQrId] : [$business->id, $startDateStr, $endDateStr]);

        $scans = [];
        foreach ($scansRaw as $row) {
            $scans[$row->date] = $row->count;
        }

        // Fill in missing dates with zeros
        $result = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $result[] = [
                'date' => $current->format('M d'),
                'value' => $scans[$dateKey] ?? 0,
            ];
            $current->addDay();
        }

        return $result;
    }

    protected function getRedemptionsOverTime($business, $startDate, $endDate): array
    {
        $startDateStr = $startDate->format('Y-m-d H:i:s');
        $endDateStr = $endDate->format('Y-m-d H:i:s');
        // Optimized: Use SQL GROUP BY instead of loading all records into PHP
        $redemptionsRaw = DB::select("
            SELECT 
                DATE(redeemed_at) as date,
                COUNT(*) as count,
                COALESCE(SUM(discount_amount), 0) as savings
            FROM redemptions
            WHERE business_id = ? 
                AND redeemed_at BETWEEN ? AND ?
            GROUP BY DATE(redeemed_at)
            ORDER BY date ASC
        ", [$business->id, $startDateStr, $endDateStr]);

        $redemptions = [];
        foreach ($redemptionsRaw as $row) {
            $redemptions[$row->date] = [
                'count' => $row->count,
                'savings' => $row->savings,
            ];
        }

        $result = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $result[] = [
                'date' => $current->format('M d'),
                'count' => ($redemptions[$dateKey] ?? [])['count'] ?? 0,
                'savings' => ($redemptions[$dateKey] ?? [])['savings'] ?? 0,
            ];
            $current->addDay();
        }

        return $result;
    }

    protected function getDeviceBreakdown($business, $startDate, $endDate, ?int $excludeQrId = null): array
    {
        $query = $business->scans()
            ->selectRaw('device_type, COUNT(*) as count')
            ->whereBetween('scanned_at', [$startDate, $endDate])
            ->whereNotNull('device_type');
        if ($excludeQrId) {
            $query->where(function ($q) use ($excludeQrId) {
                $q->whereNull('qr_code_id')->orWhere('qr_code_id', '!=', $excludeQrId);
            });
        }
        return $query
            ->groupBy('device_type')
            ->get()
            ->map(fn($item) => [
                'name' => ucfirst($item->device_type ?? 'Unknown'),
                'value' => $item->count,
            ])
            ->toArray();
    }

    protected function getTopQRCodes($business, $startDate, $endDate, $limit = 5): array
    {
        try {
            return $business->qrCodes()
                ->visibleToBusiness()
                ->withTrashed()
                ->where('code', '!=', OnboardingQr::CODE)
                ->withCount(['scans' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('scanned_at', [$startDate, $endDate]);
                }])
                ->orderByDesc('scans_count')
                ->limit($limit)
                ->get()
                ->map(fn($qr) => [
                    'id' => $qr->id,
                    'name' => $qr->name,
                    'code' => $qr->code,
                    'scans' => $qr->scans_count,
                    'placement' => $qr->placement_location,
                ])
                ->toArray();
        } catch (\Exception $e) {
            // Fallback: use raw subquery for period-specific scan count if withCount fails
            $startDateStr = $startDate->format('Y-m-d H:i:s');
            $endDateStr = $endDate->format('Y-m-d H:i:s');
            return $business->qrCodes()
                ->visibleToBusiness()
                ->withTrashed()
                ->where('code', '!=', OnboardingQr::CODE)
                ->selectRaw('qr_codes.*, (SELECT COUNT(*) FROM scans WHERE scans.qr_code_id = qr_codes.id AND scans.scanned_at BETWEEN ? AND ?) as scans_count', [$startDateStr, $endDateStr])
                ->orderByDesc('scans_count')
                ->limit($limit)
                ->get()
                ->map(fn($qr) => [
                    'id' => $qr->id,
                    'name' => $qr->name,
                    'code' => $qr->code,
                    'scans' => (int) ($qr->scans_count ?? 0),
                    'placement' => $qr->placement_location,
                ])
                ->toArray();
        }
    }

    protected function getTopPromotions($business, $startDate, $endDate, $limit = 5): array
    {
        return $business->promotions()
            ->withTrashed()
            ->withCount(['redemptions' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('redeemed_at', [$startDate, $endDate]);
            }])
            ->withSum(['redemptions' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('redeemed_at', [$startDate, $endDate]);
            }], 'discount_amount')
            ->orderByDesc('redemptions_count')
            ->limit($limit)
            ->get()
            ->map(fn($promo) => [
                'id' => $promo->id,
                'name' => $promo->name,
                'type' => $promo->discount_type,
                'redemptions' => $promo->redemptions_count,
                'savings' => $promo->redemptions_sum_discount_amount ?? 0,
            ])
            ->toArray();
    }

    protected function getHourlyDistribution($business, $startDate, $endDate, ?int $excludeQrId = null): array
    {
        // Get scans and convert to Toronto timezone for hour extraction
        $scanQuery = $business->scans()->whereBetween('scanned_at', [$startDate, $endDate]);
        if ($excludeQrId) {
            $scanQuery->where(function ($q) use ($excludeQrId) {
                $q->whereNull('qr_code_id')->orWhere('qr_code_id', '!=', $excludeQrId);
            });
        }
        $hourly = $scanQuery->get()
            ->groupBy(function ($scan) {
                return (int)Carbon::parse($scan->scanned_at)->setTimezone('America/Toronto')->format('G');
            })
            ->map(function ($group) {
                return $group->count();
            })
            ->toArray();

        $result = [];
        for ($h = 0; $h < 24; $h++) {
            $result[] = [
                'hour' => sprintf('%02d:00', $h),
                'label' => $h === 0 ? '12 AM' : ($h < 12 ? "$h AM" : ($h === 12 ? '12 PM' : ($h - 12) . ' PM')),
                'value' => $hourly[$h] ?? 0,
            ];
        }

        return $result;
    }

    protected function getLocationBreakdown($business, $startDate, $endDate, ?int $excludeQrId = null): array
    {
        $query = $business->scans()
            ->selectRaw('city, COUNT(*) as count')
            ->whereBetween('scanned_at', [$startDate, $endDate])
            ->whereNotNull('city')
            ->groupBy('city')
            ->orderByDesc('count')
            ->limit(10);
        if ($excludeQrId) {
            $query->where(function ($q) use ($excludeQrId) {
                $q->whereNull('qr_code_id')->orWhere('qr_code_id', '!=', $excludeQrId);
            });
        }
        return $query
            ->get()
            ->map(fn($item) => [
                'city' => $item->city,
                'count' => $item->count,
            ])
            ->toArray();
    }

    protected function getAIInsights($business, ?int $excludeQrId = null): array
    {
        try {
            // Check if the business has access to AI insights at all
            if (!$business->canAccess('ai_insights')) {
                return [];
            }

            $tier = strtolower((string) ($business->subscription_tier ?? 'starter'));

            // Get recent insights or generate new ones
            $insights = $business->aiInsights()
                ->where(function ($query) {
                    $query->where('is_read', false)
                        ->orWhere('created_at', '>=', now()->subDays(7));
                })
                ->orderByDesc('created_at')
                ->limit(10) // Get more so we can filter
                ->get();

            if ($insights->isEmpty()) {
                // Generate insights based on data and tier
                $insights = $this->generateBasicInsights($business, $excludeQrId);
            }

            // Filter insights based on tier: Growth only gets "basic" categories
            // Categories: 'basic', 'advanced'
            if ($tier === 'growth') {
                $insights = $insights->filter(function($insight) {
                    // Default to basic if no category set
                    $category = $insight->category ?? 'basic';
                    return $category === 'basic';
                });
            }

            return $insights->map(fn($insight) => [
                'id' => $insight->id ?? null,
                'type' => $insight->type ?? 'recommendation',
                'title' => $insight->title,
                'description' => $insight->description ?? $insight->content ?? '',
                'priority' => $insight->priority ?? 'medium',
                'category' => $insight->category ?? 'basic',
            ])->take(5)->values()->toArray();
        } catch (\Exception $e) {
            Log::warning('AI insights failed to load', [
                'business_id' => $business->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    protected function generateBasicInsights($business, ?int $excludeQrId = null): \Illuminate\Support\Collection
    {
        $insights = collect();

        // ===== BASIC: PRIZE REDEMPTION PERFORMANCE (1-to-1) =====
        // Use issued prizes (user_promo_tokens) as the canonical denominator.
        $start = now()->subDays(30)->startOfDay();
        $end = now()->endOfDay();

        $issued = UserPromoToken::where('business_id', $business->id)
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $redeemed = UserPromoToken::where('business_id', $business->id)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('redeemed_at')
            ->count();

        if ($issued > 0) {
            $rate = ($redeemed / $issued) * 100;

            if ($rate < 25) {
                $insights->push((object)[
                    'type' => 'recommendation',
                    'category' => 'basic',
                    'title' => 'Low Prize Redemption Rate',
                    'content' => 'Your prize redemption rate is ' . round($rate, 1) . '%. Consider improving staff prompts at checkout and simplifying redemption steps so more issued prizes get redeemed.',
                ]);
            } elseif ($rate > 65) {
                $insights->push((object)[
                    'type' => 'success',
                    'category' => 'basic',
                    'title' => 'Excellent Prize Redemption!',
                    'content' => 'Your ' . round($rate, 1) . '% prize redemption rate is outstanding. Customers are actually using what they earn — keep it up!',
                ]);
            }
        }

        // ===== BASIC: INACTIVE QR CODES =====
        $inactiveQRs = $business->qrCodes()
            ->visibleToBusiness()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('last_scanned_at')
                    ->orWhere('last_scanned_at', '<', now()->subDays(14));
            })
            ->count();

        if ($inactiveQRs > 0) {
            $insights->push((object)[
                'type' => 'alert',
                'category' => 'basic',
                'title' => 'Inactive QR Codes',
                'content' => "You have $inactiveQRs QR codes that haven't been scanned in over 2 weeks. Consider moving them to higher-traffic locations or updating their promotions.",
            ]);
        }

        // ===== BASIC: PEAK HOURS =====
        $scanQuery = $business->scans()->where('scanned_at', '>=', now()->subDays(30));
        if ($excludeQrId) {
            $scanQuery->where(function ($q) use ($excludeQrId) {
                $q->whereNull('qr_code_id')->orWhere('qr_code_id', '!=', $excludeQrId);
            });
        }
        $hourlyScans = $scanQuery->get()
            ->groupBy(function ($scan) {
                return (int)Carbon::parse($scan->scanned_at)->setTimezone('America/Toronto')->format('G');
            })
            ->map(function ($group) {
                return $group->count();
            });
        
        $peakHour = null;
        if ($hourlyScans->isNotEmpty()) {
            $maxCount = $hourlyScans->max();
            $peakHourNum = $hourlyScans->search($maxCount);
            if ($peakHourNum !== false && $maxCount > 5) {
                $peakHour = (object)[
                    'hour' => (int)$peakHourNum,
                    'count' => $maxCount,
                ];
            }
        }

        if ($peakHour && $peakHour->count > 5) {
            $hour = $peakHour->hour;
            $label = $hour === 0 ? '12 AM' : ($hour < 12 ? "$hour AM" : ($hour === 12 ? '12 PM' : ($hour - 12) . ' PM'));
            $insights->push((object)[
                'type' => 'trend',
                'category' => 'basic',
                'title' => 'Peak Activity Time',
                'content' => "Your busiest scan time is around $label. Consider running flash promotions during this period for maximum impact.",
            ]);
        }

        // ===== ADVANCED: QRCADE GAME INSIGHTS =====
        try {
            $gameStats = \DB::table('game_plays')
                ->where('business_id', $business->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw("COUNT(*) as plays, SUM(CASE WHEN result = 'win' THEN 1 ELSE 0 END) as wins")
                ->first();

            if ($gameStats && $gameStats->plays > 0) {
                $winRate = ($gameStats->wins / $gameStats->plays) * 100;
                
                if ($winRate > 80) {
                    $insights->push((object)[
                        'type' => 'recommendation',
                        'category' => 'advanced',
                        'title' => 'Games May Be Too Easy',
                        'content' => 'Your QRcade games have an ' . round($winRate) . '% win rate. Consider increasing difficulty to make prizes feel more valuable.',
                    ]);
                } elseif ($winRate < 20) {
                    $insights->push((object)[
                        'type' => 'alert',
                        'category' => 'advanced',
                        'title' => 'Games May Be Too Hard',
                        'content' => 'Only ' . round($winRate) . '% of players win your QRcade games. Lower difficulty might increase customer satisfaction.',
                    ]);
                }

                // Top performing game
                $topGame = \DB::table('game_plays')
                    ->join('games', 'game_plays.game_id', '=', 'games.id')
                    ->where('game_plays.business_id', $business->id)
                    ->where('game_plays.created_at', '>=', now()->subDays(30))
                    ->groupBy('game_plays.game_id', 'games.name')
                    ->selectRaw('games.name, COUNT(*) as plays')
                    ->orderByDesc('plays')
                    ->first();

                if ($topGame) {
                    $insights->push((object)[
                        'type' => 'trend',
                        'category' => 'advanced',
                        'title' => 'Most Popular Game',
                        'content' => $topGame->name . ' is your most played game with ' . $topGame->plays . ' plays this month. Feature it prominently!',
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::debug('AI insights: QRCade game stats skipped (tables may not exist)', [
                'business_id' => $business->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        // ===== ADVANCED: PROMOTION TYPE PERFORMANCE =====
        try {
            $topPromoType = $business->redemptions()
                ->join('promotions', 'redemptions.promotion_id', '=', 'promotions.id')
                ->where('redeemed_at', '>=', now()->subDays(30))
                ->groupBy('promotions.discount_type')
                ->selectRaw('promotions.discount_type, COUNT(*) as count, SUM(redemptions.discount_amount) as total')
                ->orderByDesc('count')
                ->first();

            if ($topPromoType) {
                $typeLabels = [
                    'percentage' => 'Percentage discounts',
                    'fixed' => 'Fixed amount off',
                    'bogo' => 'Buy One Get One',
                    'free_item' => 'Free item promotions',
                ];
                $label = $typeLabels[$topPromoType->discount_type] ?? ucfirst($topPromoType->discount_type);
                $insights->push((object)[
                    'type' => 'trend',
                    'category' => 'advanced',
                    'title' => 'Best Performing Promo Type',
                    'content' => "$label are your most redeemed promotions ({$topPromoType->count} redemptions). Consider creating more of this type.",
                ]);
            }
        } catch (\Exception $e) {
            Log::debug('AI insights: promotion type performance skipped', [
                'business_id' => $business->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        // ===== BASIC: DEVICE INSIGHTS =====
        try {
            $scans = $business->scans()
                ->where('scanned_at', '>=', now()->subDays(30))
                ->count();

            $mobileRate = $business->scans()
                ->where('scanned_at', '>=', now()->subDays(30))
                ->whereIn('device_type', ['mobile', 'Mobile'])
                ->count();
            
            if ($scans > 0) {
                $mobilePercent = ($mobileRate / $scans) * 100;
                if ($mobilePercent > 90) {
                    $insights->push((object)[
                        'type' => 'trend',
                        'category' => 'basic',
                        'title' => 'Mobile-First Customers',
                        'content' => round($mobilePercent) . '% of your scans are from mobile devices. Ensure your landing pages are mobile-optimized!',
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::debug('AI insights: device insights skipped', [
                'business_id' => $business->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        // ===== ADVANCED: PLACEMENT PERFORMANCE =====
        try {
            $topPlacement = $business->qrCodes()
                ->visibleToBusiness()
                ->whereNotNull('placement_location')
                ->where('total_scans', '>', 0)
                ->orderByDesc('total_scans')
                ->first();

            $worstPlacement = $business->qrCodes()
                ->visibleToBusiness()
                ->whereNotNull('placement_location')
                ->where('is_active', true)
                ->orderBy('total_scans')
                ->first();

            if ($topPlacement && $worstPlacement && $topPlacement->id !== $worstPlacement->id) {
                $placementLabels = [
                    'front_desk' => 'Front Desk',
                    'table' => 'Table',
                    'window' => 'Window',
                    'receipt' => 'Receipt',
                    'merch' => 'Merchandise',
                ];
                $topLabel = $placementLabels[$topPlacement->placement_location] ?? ucfirst($topPlacement->placement_location);
                $worstLabel = $placementLabels[$worstPlacement->placement_location] ?? ucfirst($worstPlacement->placement_location);
                
                if ($topPlacement->total_scans > $worstPlacement->total_scans * 5) {
                    $insights->push((object)[
                        'type' => 'recommendation',
                        'category' => 'advanced',
                        'title' => 'Placement Opportunity',
                        'content' => "Your $topLabel QR gets " . $topPlacement->total_scans . " scans vs only " . $worstPlacement->total_scans . " for $worstLabel. Consider improving the $worstLabel placement or copying what works.",
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::debug('AI insights: placement performance skipped', [
                'business_id' => $business->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        // ===== ADVANCED: MERCH/WEARABLE INSIGHTS =====
        try {
            $merchScans = $business->qrCodes()
                ->visibleToBusiness()
                ->where('placement_location', 'merch')
                ->sum('total_scans');
            
            if ($merchScans > 0) {
                $insights->push((object)[
                    'type' => 'success',
                    'category' => 'advanced',
                    'title' => 'Wearable QR Success',
                    'content' => "Your merchandise QR codes have generated $merchScans scans! Customers wearing your brand are spreading your promotions.",
                ]);
            }
        } catch (\Exception $e) {
            Log::debug('AI insights: merch/wearable insights skipped', [
                'business_id' => $business->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        // ===== BASIC: DAY OF WEEK INSIGHTS =====
        try {
            $bestDay = $business->scans()
                ->selectRaw('DAYOFWEEK(scanned_at) as day, COUNT(*) as count')
                ->where('scanned_at', '>=', now()->subDays(30))
                ->groupBy('day')
                ->orderByDesc('count')
                ->first();

            if ($bestDay && $bestDay->count > 5) {
                $days = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $dayName = $days[$bestDay->day] ?? 'Unknown';
                $insights->push((object)[
                    'type' => 'trend',
                    'category' => 'basic',
                    'title' => 'Best Day for Engagement',
                    'content' => "$dayName is your busiest day with {$bestDay->count} scans. Schedule special promotions for this day!",
                ]);
            }
        } catch (\Exception $e) {
            Log::debug('AI insights: day of week insights skipped', [
                'business_id' => $business->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        // ===== ADVANCED: GROWTH TREND =====
        try {
            $thisWeek = $business->scans()->where('scanned_at', '>=', now()->subDays(7))->count();
            $lastWeek = $business->scans()->whereBetween('scanned_at', [now()->subDays(14), now()->subDays(7)])->count();
            
            if ($lastWeek > 0) {
                $growth = (($thisWeek - $lastWeek) / $lastWeek) * 100;
                if ($growth > 20) {
                    $insights->push((object)[
                        'type' => 'success',
                        'category' => 'advanced',
                        'title' => 'Engagement Growing!',
                        'content' => 'Your scans are up ' . round($growth) . '% compared to last week. Great momentum!',
                    ]);
                } elseif ($growth < -20) {
                    $insights->push((object)[
                        'type' => 'alert',
                        'category' => 'advanced',
                        'title' => 'Engagement Declining',
                        'content' => 'Your scans are down ' . abs(round($growth)) . '% from last week. Consider refreshing your promotions or trying QRcade games.',
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::debug('AI insights: growth trend skipped', [
                'business_id' => $business->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        // ===== ADVANCED: CROSS-PROMOTION OPPORTUNITIES =====
        try {
            $locationCount = $business->scans()
                ->whereNotNull('city')
                ->where('scanned_at', '>=', now()->subDays(30))
                ->distinct('city')
                ->count('city');

            if ($locationCount > 3) {
                $insights->push((object)[
                    'type' => 'recommendation',
                    'category' => 'advanced',
                    'title' => 'Cross-Promotion Opportunity',
                    'content' => "You're reaching customers in $locationCount different cities. Consider partnering with complementary businesses in these areas for cross-promotions!",
                ]);
            }
        } catch (\Exception $e) {
            Log::debug('AI insights: cross-promotion opportunities skipped', [
                'business_id' => $business->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        // Limit to top 10 most relevant insights (will be filtered by tier later)
        return $insights->take(10);
    }

    public function chartData(Request $request)
    {
        $business = $request->user()->business;
        $excludeQrId = $this->getExcludedQrCodeId($business);
        $type = $request->get('type', 'scans');
        $period = max(1, min(365, (int) $request->get('period', 30)));
        $startDate = Carbon::now()->subDays($period)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $data = match ($type) {
            'scans' => $this->getScansOverTime($business, $startDate, $endDate, $excludeQrId),
            'redemptions' => $this->getRedemptionsOverTime($business, $startDate, $endDate),
            'devices' => $this->getDeviceBreakdown($business, $startDate, $endDate, $excludeQrId),
            'hourly' => $this->getHourlyDistribution($business, $startDate, $endDate, $excludeQrId),
            default => [],
        };

        return response()->json($data);
    }

    public function export(Request $request)
    {
        $business = $request->user()->business;
        $excludeQrId = $this->getExcludedQrCodeId($business);
        $type = $request->get('type', 'scans');
        $period = max(1, min(365, (int) $request->get('period', 30)));
        $startDate = Carbon::now()->subDays($period)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $filename = "{$type}_export_" . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($business, $type, $startDate, $endDate, $excludeQrId) {
            $file = fopen('php://output', 'w');

            if ($type === 'scans') {
                fputcsv($file, ['Date', 'QR Code', 'Device', 'City', 'Browser']);
                
                $scanQuery = $business->scans()
                    ->with(['qrCode' => fn($q) => $q->withTrashed()->select('id', 'name')])
                    ->whereBetween('scanned_at', [$startDate, $endDate])
                    ->orderBy('scanned_at');
                if ($excludeQrId) {
                    $scanQuery->where(function ($q) use ($excludeQrId) {
                        $q->whereNull('qr_code_id')->orWhere('qr_code_id', '!=', $excludeQrId);
                    });
                }
                $scanQuery->chunk(1000, function ($scans) use ($file) {
                        foreach ($scans as $scan) {
                            fputcsv($file, [
                                $scan->scanned_at->format('Y-m-d H:i:s'),
                                $scan->qrCode?->name ?? 'Deleted QR',
                                $scan->device_type,
                                $scan->city,
                                $scan->browser,
                            ]);
                        }
                    });
            } else {
                fputcsv($file, ['Date', 'Promotion', 'Discount', 'Final Amount', 'Employee']);
                
                $business->redemptions()
                    ->with(['promotion' => fn($q) => $q->withTrashed()->select('id', 'name'), 'employee.user:id,name'])
                    ->whereBetween('redeemed_at', [$startDate, $endDate])
                    ->orderBy('redeemed_at')
                    ->chunk(1000, function ($redemptions) use ($file) {
                        foreach ($redemptions as $r) {
                            fputcsv($file, [
                                $r->redeemed_at->format('Y-m-d H:i:s'),
                                $r->promotion?->name ?? 'Deleted Promotion',
                                $r->discount_amount,
                                $r->final_amount,
                                $r->employee?->user?->name ?? 'Unknown',
                            ]);
                        }
                    });
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function report(Request $request)
    {
        $business = $request->user()->business;
        $excludeQrId = $this->getExcludedQrCodeId($business);
        $period = max(1, min(365, (int) $request->get('period', 30)));
        $startDate = Carbon::now()->subDays($period)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Reuse the same data sources as the Analytics overview.
        $stats = $this->getOverviewStats($business, $startDate, $endDate, $excludeQrId);
        $scansOverTime = $this->getScansOverTime($business, $startDate, $endDate, $excludeQrId);
        $redemptionsOverTime = $this->getRedemptionsOverTime($business, $startDate, $endDate);
        $deviceBreakdown = $this->getDeviceBreakdown($business, $startDate, $endDate, $excludeQrId);
        $topQRCodes = $this->getTopQRCodes($business, $startDate, $endDate);
        $topPromotions = $this->getTopPromotions($business, $startDate, $endDate);
        $hourlyDistribution = $this->getHourlyDistribution($business, $startDate, $endDate, $excludeQrId);
        $locationBreakdown = $this->getLocationBreakdown($business, $startDate, $endDate, $excludeQrId);
        $insights = $this->getAIInsights($business, $excludeQrId);

        return Inertia::render('Business/Analytics/Report', [
            'businessName' => $business->name ?? 'Business',
            'businessType' => $business->type,
            'period' => $period,
            'range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'generatedAt' => now()->format('Y-m-d H:i:s'),
            'stats' => $stats,
            'scansOverTime' => $scansOverTime,
            'redemptionsOverTime' => $redemptionsOverTime,
            'deviceBreakdown' => $deviceBreakdown,
            'topQRCodes' => $topQRCodes,
            'topPromotions' => $topPromotions,
            'hourlyDistribution' => $hourlyDistribution,
            'locationBreakdown' => $locationBreakdown,
            'insights' => $insights,
        ]);
    }
    
    /**
     * Partnerships analytics overview
     */
    public function partnerships(Request $request)
    {
        $business = $request->user()->business;
        $excludeQrId = $this->getExcludedQrCodeId($business);
        
        // Get all partnerships
        $partnerships = BusinessPartnership::forBusiness($business->id)
            ->accepted()
            ->with(['requesterBusiness', 'partnerBusiness'])
            ->get();
        
        // Get all cross-promos
        $crossPromos = CrossPromotion::forBusiness($business->id)
            ->with(['promotion1', 'promotion2', 'business1', 'business2'])
            ->get();
        
        // Calculate metrics
        $totalPartners = $partnerships->count();
        $activeCrossPromos = $crossPromos->where('is_active', true)->count();
        
        // Get QR codes for cross-promos
        $crossPromoQrIds = $crossPromos->pluck('id')->flatMap(function ($id) {
            return \App\Models\QRCode::where('cross_promotion_id', $id)->pluck('id');
        });

        $crossPromoPromotionIds = $crossPromos->pluck('promotion_1_id')->merge($crossPromos->pluck('promotion_2_id'))->filter();

        $totalScans = $crossPromoQrIds->isNotEmpty()
            ? Scan::where('business_id', $business->id)
                ->whereIn('qr_code_id', $crossPromoQrIds)
                ->when($excludeQrId, fn($q) => $q->where(function ($sub) use ($excludeQrId) {
                    $sub->whereNull('qr_code_id')->orWhere('qr_code_id', '!=', $excludeQrId);
                }))
                ->count()
            : 0;
        $totalClaims = $crossPromoQrIds->isNotEmpty() && $crossPromoPromotionIds->isNotEmpty()
            ? UserPromoToken::whereIn('promotion_id', $crossPromoPromotionIds)
                ->whereIn('qr_code_id', $crossPromoQrIds)
                ->count()
            : 0;
        
        // Partner performance ranking
        $partnerPerformance = [];
        foreach ($partnerships as $partnership) {
            $partner = $partnership->getOtherBusiness($business->id);
            $partnerCrossPromos = $crossPromos->filter(function ($cp) use ($partner) {
                return $cp->business_1_id === $partner->id || $cp->business_2_id === $partner->id;
            });
            
            $partnerQrIds = $partnerCrossPromos->pluck('id')->flatMap(function ($id) {
                return \App\Models\QRCode::where('cross_promotion_id', $id)->pluck('id');
            });

            $partnerScans = $partnerQrIds->isNotEmpty()
                ? Scan::where('business_id', $business->id)
                    ->whereIn('qr_code_id', $partnerQrIds)
                    ->when($excludeQrId, fn($q) => $q->where(function ($sub) use ($excludeQrId) {
                        $sub->whereNull('qr_code_id')->orWhere('qr_code_id', '!=', $excludeQrId);
                    }))
                    ->count()
                : 0;
            
            $partnerPerformance[] = [
                'partner' => [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'logo' => $partner->logo_url,
                ],
                'cross_promos_count' => $partnerCrossPromos->count(),
                'total_scans' => $partnerScans,
            ];
        }
        
        // Sort by performance
        usort($partnerPerformance, fn($a, $b) => $b['total_scans'] <=> $a['total_scans']);
        
        return Inertia::render('Business/Analytics/Partnerships', [
            'metrics' => [
                'total_partners' => $totalPartners,
                'active_cross_promos' => $activeCrossPromos,
                'total_scans' => $totalScans,
                'total_claims' => $totalClaims,
            ],
            'partnerPerformance' => $partnerPerformance,
            'crossPromos' => $crossPromos->map(function ($cp) {
                return [
                    'id' => $cp->id,
                    'name' => $cp->name,
                    'status' => $cp->status,
                    'is_active' => $cp->is_active,
                    'partner' => $cp->business1->id !== request()->user()->business->id 
                        ? $cp->business1->name 
                        : $cp->business2->name,
                ];
            }),
        ]);
    }

    protected function getExcludedQrCodeId($business): ?int
    {
        if (!$business) {
            return null;
        }

        return QRCode::query()
            ->where('business_id', $business->id)
            ->where('code', OnboardingQr::CODE)
            ->value('id');
    }
}


<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Services\DeepSeekAIService;
use App\Models\Scan;
use App\Models\Redemption;
use App\Models\QRCode;
use App\Models\Promotion;
use App\Models\GamePlay;
use App\Models\GameReward;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Jobs\GenerateAIInsightsForBusiness;
use App\Models\WeeklyAIInsight;
use App\Support\OnboardingQr;

class AIInsightsController extends Controller
{
    protected DeepSeekAIService $aiService;

    public function __construct(DeepSeekAIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Basic AI Insights page
     */
    public function basic(Request $request)
    {
        $business = $request->user()->business;
        $period = $request->get('period', '7');
        $startDate = Carbon::now()->subDays((int) $period)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $cacheKey = 'ai-insights:basic:' . $business->id . ':' . $period;

        $data = Cache::get($cacheKey);
        $generatedAt = null;

        if (!$data) {
            $stored = WeeklyAIInsight::getLatest($business->id, 'basic', (int) $period);
            if ($stored) {
                $data = $stored->payload;
                $generatedAt = $stored->generated_at->setTimezone('America/Toronto')->format('M j, g:i A');
                Cache::put($cacheKey, $data, now()->addDays(8));
            }
        } else {
            $stored = WeeklyAIInsight::getLatest($business->id, 'basic', (int) $period);
            if ($stored) {
                $generatedAt = $stored->generated_at->setTimezone('America/Toronto')->format('M j, g:i A');
            }
        }

        if (!$data) {
            return Inertia::render('Business/AIInsights/Basic', [
                'summary' => '',
                'quick_insights' => [],
                'action_items' => [],
                'stats' => $this->getOverviewStats($business, $startDate, $endDate),
                'previous_stats' => [],
                'top_qr_codes' => [],
                'top_promotions' => [],
                'device_breakdown' => [],
                'hourly_distribution' => [],
                'ai_enabled' => $this->aiService->isConfigured(),
                'period' => $period,
                'needs_generation' => true,
                'generated_at' => null,
            ]);
        }

        return Inertia::render('Business/AIInsights/Basic', array_merge($data, [
            'period' => $period,
            'needs_generation' => false,
            'generated_at' => $generatedAt,
        ]));
    }

    /**
     * Advanced AI Insights page
     */
    public function advanced(Request $request)
    {
        try {
            // Increase execution time limit for this request (disable limit in tests)
            if (app()->environment('testing')) {
                @set_time_limit(0);
            } else {
                @set_time_limit(120);
            }
            ini_set('memory_limit', '512M');

            $business = $request->user()->business;
            $period = $request->get('period', '7'); // Default to 7 days (weekly)
            $startDate = Carbon::now()->subDays((int) $period)->startOfDay();
            $endDate = Carbon::now()->endOfDay();

            $cacheKey = 'ai-insights:advanced:' . $business->id . ':' . $period;
            $errorKey = $cacheKey . ':error';

            $cachedData = Cache::get($cacheKey);
            $generatedAt = null;

            if (!$cachedData) {
                $stored = WeeklyAIInsight::getLatest($business->id, 'advanced', (int) $period);
                if ($stored) {
                    $cachedData = $stored->payload;
                    $generatedAt = $stored->generated_at->setTimezone('America/Toronto')->format('M j, g:i A');
                    Cache::put($cacheKey, $cachedData, now()->addDays(8));
                }
            } else {
                $stored = WeeklyAIInsight::getLatest($business->id, 'advanced', (int) $period);
                if ($stored) {
                    $generatedAt = $stored->generated_at->setTimezone('America/Toronto')->format('M j, g:i A');
                }
            }
            
            if ($cachedData) {
                return Inertia::render('Business/AIInsights/Advanced', array_merge($cachedData, [
                    'period' => $period,
                    'from_cache' => true,
                    'generated_at' => $generatedAt,
                ]));
            }

            // If no cache exists, check if we should generate synchronously (only for manual regenerate)
            $forceGenerate = $request->get('regenerate', false);
            
            if (!$forceGenerate) {
                return Inertia::render('Business/AIInsights/Advanced', [
                    'summary' => '',
                    'predictions' => [],
                    'recommendations' => [],
                    'customer_segments' => [],
                    'promotion_optimizations' => [],
                    'stats' => $this->getOverviewStats($business, $startDate, $endDate),
                    'scans_over_time' => [],
                    'redemptions_over_time' => [],
                    'promotions' => [],
                    'customer_data' => [],
                    'game_data' => [],
                    'ai_enabled' => $this->aiService->isConfigured(),
                    'period' => $period,
                    'needs_generation' => true,
                    'generated_at' => null,
                    'error' => Cache::get($errorKey),
                ]);
            }

            // Force generate (from regenerate button) - but with timeout protection
            try {
                $data = $this->generateAdvancedInsights($business, $startDate, $endDate, $period);
                
                // Cache and persist to DB (survives cache clears until next Monday)
                Cache::put($cacheKey, $data, now()->addDays(7));
                WeeklyAIInsight::store($business->id, 'advanced', (int) $period, $data);
                
                return Inertia::render('Business/AIInsights/Advanced', array_merge($data, [
                    'period' => $period,
                    'from_cache' => false,
                ]));
            } catch (\Exception $e) {
                Log::error('Failed to generate advanced insights synchronously', [
                    'business_id' => $business->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Return fallback with error message
                return Inertia::render('Business/AIInsights/Advanced', [
                    'summary' => 'Generation timed out or failed. Please try again later or wait for weekly automatic generation.',
                    'predictions' => [],
                    'recommendations' => [],
                    'customer_segments' => [],
                    'promotion_optimizations' => [],
                    'stats' => $this->getOverviewStats($business, $startDate, $endDate),
                    'scans_over_time' => [],
                    'redemptions_over_time' => [],
                    'promotions' => [],
                    'customer_data' => [],
                    'game_data' => [],
                    'ai_enabled' => $this->aiService->isConfigured(),
                    'period' => $period,
                    'error' => 'Generation failed. Please try again or wait for weekly generation.',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Advanced AI Insights page error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return fallback data
            try {
                $business = $request->user()->business;
                $period = $request->get('period', '7');
                $startDate = Carbon::now()->subDays((int) $period)->startOfDay();
                $endDate = Carbon::now()->endOfDay();

                return Inertia::render('Business/AIInsights/Advanced', [
                    'summary' => 'Unable to load advanced insights at this time. Please try again later.',
                    'predictions' => [],
                    'recommendations' => [],
                    'customer_segments' => [],
                    'promotion_optimizations' => [],
                    'stats' => $this->getOverviewStats($business, $startDate, $endDate),
                    'scans_over_time' => [],
                    'redemptions_over_time' => [],
                    'promotions' => [],
                    'customer_data' => [],
                    'game_data' => [],
                    'ai_enabled' => false,
                    'period' => $period,
                    'error' => 'Service temporarily unavailable',
                ]);
            } catch (\Exception $fallbackError) {
                // Last resort - minimal response
                return Inertia::render('Business/AIInsights/Advanced', [
                    'summary' => 'Service temporarily unavailable. Please try again later.',
                    'predictions' => [],
                    'recommendations' => [],
                    'customer_segments' => [],
                    'promotion_optimizations' => [],
                    'stats' => [],
                    'scans_over_time' => [],
                    'redemptions_over_time' => [],
                    'promotions' => [],
                    'customer_data' => [],
                    'game_data' => [],
                    'ai_enabled' => false,
                    'period' => '7',
                    'error' => 'Service temporarily unavailable',
                ]);
            }
        }
    }

    /**
     * Generate on-demand insights
     */
    public function generate(Request $request)
    {
        try {
            // Increase execution time limit for this request (disable limit in tests)
            if (app()->environment('testing')) {
                @set_time_limit(0);
            } else {
                @set_time_limit(120);
            }
            ini_set('memory_limit', '512M');

            $business = $request->user()->business;
            $type = $request->get('type', 'basic');
            $period = $request->get('period', '7');
            $startDate = Carbon::now()->subDays((int) $period)->startOfDay();
            $endDate = Carbon::now()->endOfDay();

            // Clear cache and regenerate
            $cacheKey = 'ai-insights:' . $type . ':' . $business->id . ':' . $period;
            $statusKey = $cacheKey . ':status';
            $errorKey = $cacheKey . ':error';
            Cache::forget($cacheKey);
            Cache::forget($statusKey);
            Cache::forget($errorKey);

            // Advanced insights can take long; always generate in the background to avoid HTTP timeouts.
            if ($type === 'advanced') {
                Cache::put($statusKey, [
                    'state' => 'queued',
                    'queued_at' => now()->toISOString(),
                ], now()->addMinutes(30));

                GenerateAIInsightsForBusiness::dispatch($business->id, 'advanced', (int) $period);

                // Inertia-friendly redirect back to the advanced page (it will auto-refresh until ready).
                return redirect()
                    ->route('business.ai-insights.advanced', ['period' => $period])
                    ->setStatusCode(303);
            }

            // Basic insights are fast enough to do synchronously.
            $data = $this->generateBasicInsights($business, $startDate, $endDate, $period);
            Cache::put($cacheKey, $data, now()->addDays(7));
            WeeklyAIInsight::store($business->id, 'basic', (int) $period, $data);

            // For Inertia requests, redirect back to the page. For programmatic calls, return JSON.
            if ($request->header('X-Inertia')) {
                return redirect()
                    ->route('business.ai-insights.basic', ['period' => $period])
                    ->setStatusCode(303);
            }

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Failed to generate AI insights on-demand', [
                'type' => $request->get('type'),
                'business_id' => $request->user()->business->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->header('X-Inertia')) {
                return back()
                    ->withErrors(['ai_insights' => 'Generation failed. Please try again later.'])
                    ->setStatusCode(303);
            }

            return response()->json([
                'success' => false,
                'error' => 'Generation failed. Please try again later.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate basic insights data
     */
    public function generateBasicInsights($business, $startDate, $endDate, $period): array
    {
        // Get current period stats
        $stats = $this->getOverviewStats($business, $startDate, $endDate);
        
        // Get previous period for comparison
        $previousStart = $startDate->copy()->subDays($startDate->diffInDays($endDate));
        $previousEnd = $startDate->copy()->subDay();
        $previousStats = $this->getOverviewStats($business, $previousStart, $previousEnd);

        // Get top performers
        $topQRCodes = $this->getTopQRCodes($business, $startDate, $endDate, 5);
        $topPromotions = $this->getTopPromotions($business, $startDate, $endDate, 5);
        $deviceBreakdown = $this->getDeviceBreakdown($business, $startDate, $endDate);
        $hourlyDistribution = $this->getHourlyDistribution($business, $startDate, $endDate);

        // Prepare data for AI
        $aiData = [
            'period_days' => $period,
            'current_stats' => $stats,
            'previous_stats' => $previousStats,
            'top_qr_codes' => $topQRCodes,
            'top_promotions' => $topPromotions,
            'device_breakdown' => $deviceBreakdown,
            'hourly_distribution' => $hourlyDistribution,
        ];

        // Generate AI summary
        $summary = '';
        $quickInsights = [];
        $actionItems = [];

        try {
            if ($this->aiService->isConfigured()) {
                // Generate summary
                $summary = $this->aiService->generateSummary($aiData, 'basic');

                // Generate quick insights
                $insightsPrompt = "Based on this business data (including financial ROI), identify the top 3-5 key insights. For each insight, provide:
1. A short title (max 10 words)
2. A brief description (1-2 sentences) highlighting financial impact if applicable
3. The type: 'success', 'warning', or 'opportunity'

Format as JSON array with keys: title, description, type.";
                
                $insightsResult = $this->aiService->generateInsight($insightsPrompt, $aiData);
                if ($insightsResult['success'] && $insightsResult['content']) {
                    $jsonMatch = [];
                    if (preg_match('/\[.*\]/s', $insightsResult['content'], $jsonMatch)) {
                        $parsed = json_decode($jsonMatch[0], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                            $quickInsights = $parsed;
                        }
                    }
                }

                // Generate action items
                $actionsPrompt = "Based on this business data, provide 3-5 actionable recommendations focused on improving ROI and customer engagement. For each, include:
1. Title (max 8 words)
2. Description (1 sentence)
3. Priority: 'high', 'medium', or 'low'

Format as JSON array with keys: title, description, priority.";
                
                $actionsResult = $this->aiService->generateInsight($actionsPrompt, $aiData);
                if ($actionsResult['success'] && $actionsResult['content']) {
                    $jsonMatch = [];
                    if (preg_match('/\[.*\]/s', $actionsResult['content'], $jsonMatch)) {
                        $parsed = json_decode($jsonMatch[0], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                            $actionItems = $parsed;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('AI Insights generation failed', [
                'error' => $e->getMessage(),
                'business_id' => $business->id,
            ]);
        }

        // Fallback to rule-based insights if AI fails
        if (empty($summary)) {
            $summary = $this->generateFallbackSummary($stats, $previousStats);
        }
        if (empty($quickInsights)) {
            $quickInsights = $this->generateFallbackInsights($stats, $topQRCodes, $topPromotions);
        }
        if (empty($actionItems)) {
            $actionItems = $this->generateFallbackActions($stats, $topQRCodes);
        }

        return [
            'summary' => $summary,
            'quick_insights' => $quickInsights,
            'action_items' => $actionItems,
            'stats' => $stats,
            'previous_stats' => $previousStats,
            'top_qr_codes' => $topQRCodes,
            'top_promotions' => $topPromotions,
            'device_breakdown' => $deviceBreakdown,
            'hourly_distribution' => $hourlyDistribution,
            'ai_enabled' => $this->aiService->isConfigured(),
        ];
    }

    /**
     * Generate advanced insights data
     */
    public function generateAdvancedInsights($business, $startDate, $endDate, $period): array
    {
        // Get comprehensive historical data
        $stats = $this->getOverviewStats($business, $startDate, $endDate);
        $scansOverTime = $this->getScansOverTime($business, $startDate, $endDate);
        $redemptionsOverTime = $this->getRedemptionsOverTime($business, $startDate, $endDate);
        
        // Get all promotions with performance data
        $allPromotions = $this->getAllPromotionsWithPerformance($business, $startDate, $endDate);
        
        // Get customer data for segmentation
        $customerData = $this->getCustomerSegmentationData($business, $startDate, $endDate);
        
        // Get game data if available
        $gameData = $this->getGameAnalytics($business, $startDate, $endDate);

        // Prepare data for AI
        $aiData = [
            'period_days' => $period,
            'stats' => $stats,
            'scans_over_time' => $scansOverTime,
            'redemptions_over_time' => $redemptionsOverTime,
            'promotions' => $allPromotions,
            'customer_data' => $customerData,
            'game_data' => $gameData,
        ];

        $predictions = [];
        $recommendations = [];
        $segments = [];
        $optimizations = [];
        $summary = '';

        try {
            if ($this->aiService->isConfigured()) {
                // Generate summary (with timeout handling)
                try {
                    $summary = $this->aiService->generateSummary($aiData, 'advanced');
                } catch (\Exception $e) {
                    Log::warning('Failed to generate advanced summary', [
                        'business_id' => $business->id,
                        'error' => $e->getMessage(),
                    ]);
                    $summary = '';
                }

                // Generate predictions (with timeout handling)
                try {
                    $predictionsResult = $this->aiService->generatePrediction($scansOverTime, 'scan_volume');
                    if ($predictionsResult['success']) {
                        $predictions = $predictionsResult;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to generate predictions', [
                        'business_id' => $business->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Generate recommendations (with timeout handling)
                try {
                    $recommendationsResult = $this->aiService->generateRecommendations($aiData);
                    if ($recommendationsResult['success']) {
                        $recommendations = $recommendationsResult['recommendations'] ?? [];
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to generate recommendations', [
                        'business_id' => $business->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Generate customer segments (with timeout handling)
                if (!empty($customerData)) {
                    try {
                        $segmentsResult = $this->aiService->analyzeCustomerSegments($customerData);
                        if ($segmentsResult['success']) {
                            $segments = $segmentsResult['segments'] ?? [];
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to generate customer segments', [
                            'business_id' => $business->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Generate promotion optimizations (with timeout handling)
                if (!empty($allPromotions)) {
                    try {
                        $optimizationsResult = $this->aiService->optimizePromotions($allPromotions);
                        if ($optimizationsResult['success']) {
                            $optimizations = $optimizationsResult['optimizations'] ?? [];
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to generate optimizations', [
                            'business_id' => $business->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Advanced AI Insights generation failed', [
                'error' => $e->getMessage(),
                'business_id' => $business->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Fallback summaries
        if (empty($summary)) {
            $summary = $this->generateFallbackSummary($stats, []);
        }

        return [
            'summary' => $summary,
            'predictions' => $predictions,
            'recommendations' => $recommendations,
            'customer_segments' => $segments,
            'promotion_optimizations' => $optimizations,
            'stats' => $stats,
            'scans_over_time' => $scansOverTime,
            'redemptions_over_time' => $redemptionsOverTime,
            'promotions' => $allPromotions,
            'customer_data' => $customerData,
            'game_data' => $gameData,
            'ai_enabled' => $this->aiService->isConfigured(),
        ];
    }

    // Helper methods (reused from AnalyticsController pattern)
    protected function getOverviewStats($business, $startDate, $endDate): array
    {
        // Keep AI stats aligned with the business dashboard/analytics meaning:
        // - scans are engagement
        // - redemption performance uses issued prizes (user_promo_tokens) as 1-to-1 denominator
        $row = DB::selectOne("
            SELECT
                (SELECT COUNT(*) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ?) as scans,
                (SELECT COUNT(DISTINCT session_id) FROM scans WHERE business_id = ? AND scanned_at BETWEEN ? AND ?) as unique_scans,
                (SELECT COUNT(*) FROM redemptions WHERE business_id = ? AND redeemed_at BETWEEN ? AND ?) as redemptions,
                (SELECT COALESCE(SUM(discount_amount), 0) FROM redemptions WHERE business_id = ? AND redeemed_at BETWEEN ? AND ?) as savings,
                (SELECT COALESCE(SUM(final_amount), 0) FROM redemptions WHERE business_id = ? AND redeemed_at BETWEEN ? AND ?) as revenue,

                (SELECT COUNT(*) FROM user_promo_tokens WHERE business_id = ? AND created_at BETWEEN ? AND ?) as prizes_issued,
                (SELECT COUNT(*) FROM user_promo_tokens WHERE business_id = ? AND created_at BETWEEN ? AND ? AND redeemed_at IS NOT NULL) as prizes_redeemed,

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
                ) as punch_completions
        ", [
            $business->id, $startDate, $endDate,
            $business->id, $startDate, $endDate,
            $business->id, $startDate, $endDate,
            $business->id, $startDate, $endDate,
            $business->id, $startDate, $endDate,
            $business->id, $startDate, $endDate,
            $business->id, $startDate, $endDate,
            $business->id, $startDate, $endDate,
            $business->id, $startDate, $endDate,
        ]);

        $currentScans = (int)($row->scans ?? 0);
        $currentUniqueScans = (int)($row->unique_scans ?? 0);
        $currentRedemptions = (int)($row->redemptions ?? 0);
        $currentSavings = (float)($row->savings ?? 0);
        $currentRevenue = (float)($row->revenue ?? 0);
        $currentPrizesIssued = (int)($row->prizes_issued ?? 0);
        $currentPrizesRedeemed = (int)($row->prizes_redeemed ?? 0);
        $prizeRedemptionRate = $currentPrizesIssued > 0 ? round(($currentPrizesRedeemed / $currentPrizesIssued) * 100, 1) : 0;
        $roi = $currentSavings > 0 ? round($currentRevenue / $currentSavings, 2) : 0;

        return [
            'total_scans' => $currentScans,
            'unique_scans' => $currentUniqueScans,
            'total_redemptions' => $currentRedemptions,
            'total_savings' => $currentSavings,
            'total_revenue' => $currentRevenue,
            'roi' => $roi,
            'prizes_issued' => $currentPrizesIssued,
            'prizes_redeemed' => $currentPrizesRedeemed,
            'prize_redemption_rate' => $prizeRedemptionRate,
            'punch_stamps' => (int)($row->punch_stamps ?? 0),
            'punch_completions' => (int)($row->punch_completions ?? 0),
            'active_qr_codes' => $business->qrCodes()->visibleToBusiness()->where('code', '!=', OnboardingQr::CODE)->where('is_active', true)->count(),
            'active_promotions' => $business->promotions()->where('is_active', true)->count(),
        ];
    }

    protected function getScansOverTime($business, $startDate, $endDate): array
    {
        $scans = $business->scans()
            ->whereBetween('scanned_at', [$startDate, $endDate])
            ->get()
            ->groupBy(function ($scan) {
                return Carbon::parse($scan->scanned_at)->setTimezone('America/Toronto')->format('Y-m-d');
            })
            ->map(function ($group) {
                return $group->count();
            })
            ->toArray();

        $result = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $result[] = [
                'date' => $current->format('Y-m-d'),
                'value' => $scans[$dateKey] ?? 0,
            ];
            $current->addDay();
        }

        return $result;
    }

    protected function getRedemptionsOverTime($business, $startDate, $endDate): array
    {
        $redemptions = $business->redemptions()
            ->whereBetween('redeemed_at', [$startDate, $endDate])
            ->get()
            ->groupBy(function ($redemption) {
                return Carbon::parse($redemption->redeemed_at)->setTimezone('America/Toronto')->format('Y-m-d');
            })
            ->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'savings' => $group->sum('discount_amount'),
                ];
            })
            ->toArray();

        $result = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $result[] = [
                'date' => $current->format('Y-m-d'),
                'count' => $redemptions[$dateKey]['count'] ?? 0,
                'savings' => $redemptions[$dateKey]['savings'] ?? 0,
            ];
            $current->addDay();
        }

        return $result;
    }

    protected function getTopQRCodes($business, $startDate, $endDate, $limit = 5): array
    {
        try {
            return $business->qrCodes()
                ->visibleToBusiness()
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
            return $business->qrCodes()
                ->visibleToBusiness()
                ->where('code', '!=', OnboardingQr::CODE)
                ->orderByDesc('total_scans')
                ->limit($limit)
                ->get()
                ->map(fn($qr) => [
                    'id' => $qr->id,
                    'name' => $qr->name,
                    'code' => $qr->code,
                    'scans' => $qr->total_scans ?? 0,
                    'placement' => $qr->placement_location,
                ])
                ->toArray();
        }
    }

    protected function getTopPromotions($business, $startDate, $endDate, $limit = 5): array
    {
        return $business->promotions()
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

    protected function getDeviceBreakdown($business, $startDate, $endDate): array
    {
        return $business->scans()
            ->selectRaw('device_type, COUNT(*) as count')
            ->whereBetween('scanned_at', [$startDate, $endDate])
            ->whereNotNull('device_type')
            ->groupBy('device_type')
            ->get()
            ->map(fn($item) => [
                'name' => ucfirst($item->device_type ?? 'Unknown'),
                'value' => $item->count,
            ])
            ->toArray();
    }

    protected function getHourlyDistribution($business, $startDate, $endDate): array
    {
        $hourly = $business->scans()
            ->whereBetween('scanned_at', [$startDate, $endDate])
            ->get()
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
                'hour' => $h,
                'value' => $hourly[$h] ?? 0,
            ];
        }

        return $result;
    }

    protected function getAllPromotionsWithPerformance($business, $startDate, $endDate): array
    {
        return $business->promotions()
            ->withCount(['redemptions' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('redeemed_at', [$startDate, $endDate]);
            }])
            ->withSum(['redemptions' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('redeemed_at', [$startDate, $endDate]);
            }], 'discount_amount')
            ->get()
            ->map(fn($promo) => [
                'id' => $promo->id,
                'name' => $promo->name,
                'type' => $promo->discount_type,
                'discount_value' => $promo->discount_value,
                'redemptions' => $promo->redemptions_count,
                'savings' => $promo->redemptions_sum_discount_amount ?? 0,
                'is_active' => $promo->is_active,
            ])
            ->toArray();
    }

    protected function getCustomerSegmentationData($business, $startDate, $endDate): array
    {
        // Get users who have interacted with this business (via scans or redemptions)
        $scanUserIds = Scan::where('business_id', $business->id)
            ->whereBetween('scanned_at', [$startDate, $endDate])
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->pluck('user_id');

        $redemptionUserIds = Redemption::where('business_id', $business->id)
            ->whereBetween('redeemed_at', [$startDate, $endDate])
            ->whereNotNull('customer_user_id')
            ->distinct('customer_user_id')
            ->pluck('customer_user_id');

        $userIds = $scanUserIds->merge($redemptionUserIds)->unique();

        if ($userIds->isEmpty()) {
            return [];
        }

        return User::whereIn('id', $userIds)
            ->get()
            ->map(function ($user) use ($business, $startDate, $endDate) {
                $scansCount = Scan::where('business_id', $business->id)
                    ->where('user_id', $user->id)
                    ->whereBetween('scanned_at', [$startDate, $endDate])
                    ->count();

                $redemptionsCount = Redemption::where('business_id', $business->id)
                    ->where('customer_user_id', $user->id)
                    ->whereBetween('redeemed_at', [$startDate, $endDate])
                    ->count();

                return [
                    'user_id' => $user->id,
                    'scans_count' => $scansCount,
                    'redemptions_count' => $redemptionsCount,
                    'total_savings' => $user->total_savings ?? 0,
                    'level' => $user->level ?? 1,
                    'xp' => $user->xp ?? 0,
                ];
            })
            ->toArray();
    }

    protected function getGameAnalytics($business, $startDate, $endDate): array
    {
        try {
            $plays = GamePlay::where('business_id', $business->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $rewards = GameReward::where('business_id', $business->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            return [
                'total_plays' => $plays->count(),
                'unique_players' => $plays->pluck('user_id')->unique()->count(),
                'avg_score' => $plays->avg('score') ?? 0,
                'win_rate' => $plays->count() > 0 ? ($plays->where('result', 'win')->count() / $plays->count()) * 100 : 0,
                'rewards_given' => $rewards->count(),
                'rewards_redeemed' => $rewards->where('status', 'redeemed')->count(),
                'reward_value' => $rewards->sum('discount_value'),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    // Fallback methods for when AI is unavailable
    protected function generateFallbackSummary($stats, $previousStats): string
    {
        $summary = "Your business has generated {$stats['total_scans']} scans and {$stats['total_redemptions']} redemptions. ";
        $summary .= "Your prize redemption rate is {$stats['prize_redemption_rate']}% ({$stats['prizes_redeemed']}/{$stats['prizes_issued']}). ";
        
        if (!empty($previousStats)) {
            $scanChange = $stats['total_scans'] - ($previousStats['total_scans'] ?? 0);
            if ($scanChange > 0) {
                $summary .= "Scans are up by {$scanChange} compared to the previous period.";
            } elseif ($scanChange < 0) {
                $summary .= "Scans are down by " . abs($scanChange) . " compared to the previous period.";
            }
        }
        
        return $summary;
    }

    protected function generateFallbackInsights($stats, $topQRCodes, $topPromotions): array
    {
        $insights = [];

        if (($stats['prize_redemption_rate'] ?? 0) < 25) {
            $insights[] = [
                'title' => 'Low Prize Redemption',
                'description' => "Your prize redemption rate is {$stats['prize_redemption_rate']}%. Focus on staff prompts and smoother redemption to increase usage of issued prizes.",
                'type' => 'warning',
            ];
        }

        if (isset($stats['roi']) && $stats['roi'] < 1.5 && $stats['total_redemptions'] > 5) {
            $insights[] = [
                'title' => 'Low ROI Alert',
                'description' => "Your promotions are costing almost as much as they generate ({$stats['roi']}x ROI). Consider reducing discount values.",
                'type' => 'warning',
            ];
        }

        if (!empty($topQRCodes)) {
            $insights[] = [
                'title' => 'Top Performing QR Code',
                'description' => "{$topQRCodes[0]['name']} has {$topQRCodes[0]['scans']} scans.",
                'type' => 'success',
            ];
        }

        if (!empty($topPromotions)) {
            $insights[] = [
                'title' => 'Best Promotion',
                'description' => "{$topPromotions[0]['name']} has {$topPromotions[0]['redemptions']} redemptions.",
                'type' => 'success',
            ];
        }

        return $insights;
    }

    protected function generateFallbackActions($stats, $topQRCodes): array
    {
        $actions = [];

        if (($stats['prize_redemption_rate'] ?? 0) < 25) {
            $actions[] = [
                'title' => 'Improve Prize Redemption',
                'description' => 'Train staff to prompt redemptions at checkout and ensure redemption is fast and obvious for customers.',
                'priority' => 'high',
            ];
        }

        if (($stats['active_qr_codes'] ?? 0) < 3) {
            $actions[] = [
                'title' => 'Add More QR Codes',
                'description' => 'Create additional QR codes to increase visibility.',
                'priority' => 'medium',
            ];
        }

        return $actions;
    }
}


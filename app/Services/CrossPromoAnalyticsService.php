<?php

namespace App\Services;

use App\Models\CrossPromotion;
use App\Models\Scan;
use App\Models\UserPromoToken;
use App\Models\Redemption;
use Illuminate\Support\Facades\DB;

class CrossPromoAnalyticsService
{
    /**
     * Get analytics for a cross-promotion
     */
    public function getAnalytics(CrossPromotion $crossPromo): array
    {
        // Get all QR codes for this cross-promo
        $qrCodeIds = $crossPromo->qrCodes()->pluck('id');
        $business1Id = $crossPromo->business_1_id;
        $business2Id = $crossPromo->business_2_id;
        
        // Scans
        $totalScans = Scan::whereIn('qr_code_id', $qrCodeIds)->count();
        $uniqueScans = (int) (Scan::whereIn('qr_code_id', $qrCodeIds)
            ->selectRaw('COUNT(DISTINCT user_id) as cnt')
            ->value('cnt') ?? 0);
        $scanCountsByBusiness = Scan::whereIn('qr_code_id', $qrCodeIds)
            ->selectRaw('business_id, COUNT(*) as count')
            ->groupBy('business_id')
            ->pluck('count', 'business_id');
        
        // Claims (UserPromoTokens created)
        $promo1Claims = UserPromoToken::where('promotion_id', $crossPromo->promotion_1_id)
            ->whereHas('qrCode', function ($q) use ($crossPromo) {
                $q->where('cross_promotion_id', $crossPromo->id);
            })
            ->count();
            
        $promo2Claims = UserPromoToken::where('promotion_id', $crossPromo->promotion_2_id)
            ->whereHas('qrCode', function ($q) use ($crossPromo) {
                $q->where('cross_promotion_id', $crossPromo->id);
            })
            ->count();
        
        $totalClaims = $promo1Claims + $promo2Claims;
        
        // Redemptions — use qr_code_id on the redemption row directly so we
        // include redemptions even when scan_id is NULL (employee-only flow, etc.)
        $promo1Redemptions = Redemption::where('promotion_id', $crossPromo->promotion_1_id)
            ->whereIn('qr_code_id', $qrCodeIds)
            ->count();
            
        $promo2Redemptions = Redemption::where('promotion_id', $crossPromo->promotion_2_id)
            ->whereIn('qr_code_id', $qrCodeIds)
            ->count();
        
        $totalRedemptions = $promo1Redemptions + $promo2Redemptions;
        $redemptionsByBusiness = Redemption::whereIn('promotion_id', [$crossPromo->promotion_1_id, $crossPromo->promotion_2_id])
            ->whereIn('qr_code_id', $qrCodeIds)
            ->selectRaw('business_id, COUNT(*) as count')
            ->groupBy('business_id')
            ->pluck('count', 'business_id');
        
        // Conversion rates
        $scanToClaimRate = $totalScans > 0 ? ($totalClaims / $totalScans) * 100 : 0;
        $claimToRedemptionRate = $totalClaims > 0 ? ($totalRedemptions / $totalClaims) * 100 : 0;
        $scanToRedemptionRate = $totalScans > 0 ? ($totalRedemptions / $totalScans) * 100 : 0;
        
        // Sequential chain completion (if sequential)
        $chainCompletionRate = 0;
        $chainCompletions = 0;
        $primaryClaims = $promo1Claims;
        $secondaryClaims = $promo2Claims;
        $primaryRedemptions = $promo1Redemptions;
        $secondaryRedemptions = $promo2Redemptions;
        if ($crossPromo->chain_mode === CrossPromotion::CHAIN_SEQUENTIAL) {
            $primaryId = $crossPromo->primary_promotion_id ?: $crossPromo->promotion_1_id;
            $secondaryId = ($primaryId === $crossPromo->promotion_1_id) 
                ? $crossPromo->promotion_2_id 
                : $crossPromo->promotion_1_id;
            
            // Users who redeemed primary
            $primaryRedeemers = Redemption::where('promotion_id', $primaryId)
                ->whereIn('qr_code_id', $qrCodeIds)
                ->distinct('customer_user_id')
                ->pluck('customer_user_id');
            
            // Users who also redeemed secondary
            $chainCompletions = Redemption::where('promotion_id', $secondaryId)
                ->whereIn('qr_code_id', $qrCodeIds)
                ->whereIn('customer_user_id', $primaryRedeemers)
                ->distinct('customer_user_id')
                ->count();
            
            $chainCompletionRate = $primaryRedeemers->count() > 0 
                ? ($chainCompletions / $primaryRedeemers->count()) * 100 
                : 0;

            // Re-map primary/secondary metrics to the actual primary/secondary ids for clarity
            if ($primaryId === $crossPromo->promotion_1_id) {
                $primaryClaims = $promo1Claims;
                $secondaryClaims = $promo2Claims;
                $primaryRedemptions = $promo1Redemptions;
                $secondaryRedemptions = $promo2Redemptions;
            } else {
                $primaryClaims = $promo2Claims;
                $secondaryClaims = $promo1Claims;
                $primaryRedemptions = $promo2Redemptions;
                $secondaryRedemptions = $promo1Redemptions;
            }
        }
        
        // Time-based analytics (last 30 days)
        $thirtyDaysAgo = now()->subDays(30);
        
        $scansLast30Days = Scan::whereIn('qr_code_id', $qrCodeIds)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
            
        $claimsLast30Days = UserPromoToken::whereIn('promotion_id', [
            $crossPromo->promotion_1_id,
            $crossPromo->promotion_2_id,
        ])
        ->whereHas('qrCode', function ($q) use ($crossPromo) {
            $q->where('cross_promotion_id', $crossPromo->id);
        })
        ->where('created_at', '>=', $thirtyDaysAgo)
        ->count();
        
        $redemptionsLast30Days = Redemption::whereIn('promotion_id', [
            $crossPromo->promotion_1_id,
            $crossPromo->promotion_2_id,
        ])
        ->whereIn('qr_code_id', $qrCodeIds)
        ->where('redeemed_at', '>=', $thirtyDaysAgo)
        ->count();
        
        return [
            'overview' => [
                'total_scans' => $totalScans,
                'unique_scans' => $uniqueScans,
                'total_claims' => $totalClaims,
                'total_redemptions' => $totalRedemptions,
                'scan_to_claim_rate' => round($scanToClaimRate, 2),
                'claim_to_redemption_rate' => round($claimToRedemptionRate, 2),
                'scan_to_redemption_rate' => round($scanToRedemptionRate, 2),
            ],
            'business_breakdown' => [
                'business_1' => [
                    'id' => $business1Id,
                    'name' => $crossPromo->business1?->name,
                    'scans' => (int) ($scanCountsByBusiness[$business1Id] ?? 0),
                    'claims' => $promo1Claims,
                    'redemptions' => $promo1Redemptions,
                    'scan_to_claim_rate' => $scanCountsByBusiness[$business1Id] ?? 0 ? round(($promo1Claims / $scanCountsByBusiness[$business1Id]) * 100, 2) : 0,
                    'claim_to_redemption_rate' => $promo1Claims > 0 ? round(($promo1Redemptions / $promo1Claims) * 100, 2) : 0,
                ],
                'business_2' => [
                    'id' => $business2Id,
                    'name' => $crossPromo->business2?->name,
                    'scans' => (int) ($scanCountsByBusiness[$business2Id] ?? 0),
                    'claims' => $promo2Claims,
                    'redemptions' => $promo2Redemptions,
                    'scan_to_claim_rate' => $scanCountsByBusiness[$business2Id] ?? 0 ? round(($promo2Claims / $scanCountsByBusiness[$business2Id]) * 100, 2) : 0,
                    'claim_to_redemption_rate' => $promo2Claims > 0 ? round(($promo2Redemptions / $promo2Claims) * 100, 2) : 0,
                ],
            ],
            'by_promotion' => [
                'promotion_1' => [
                    'id' => $crossPromo->promotion_1_id,
                    'name' => $crossPromo->promotion1->name ?? 'Unknown',
                    'claims' => $promo1Claims,
                    'redemptions' => $promo1Redemptions,
                    'conversion_rate' => $promo1Claims > 0 ? round(($promo1Redemptions / $promo1Claims) * 100, 2) : 0,
                ],
                'promotion_2' => [
                    'id' => $crossPromo->promotion_2_id,
                    'name' => $crossPromo->promotion2->name ?? 'Unknown',
                    'claims' => $promo2Claims,
                    'redemptions' => $promo2Redemptions,
                    'conversion_rate' => $promo2Claims > 0 ? round(($promo2Redemptions / $promo2Claims) * 100, 2) : 0,
                ],
            ],
            'chain_metrics' => $crossPromo->chain_mode === CrossPromotion::CHAIN_SEQUENTIAL ? [
                'chain_completions' => $chainCompletions,
                'chain_completion_rate' => round($chainCompletionRate, 2),
                'primary_claims' => $primaryClaims,
                'secondary_claims' => $secondaryClaims,
                'primary_redemptions' => $primaryRedemptions,
                'secondary_redemptions' => $secondaryRedemptions,
            ] : null,
            'recent_activity' => [
                'scans_last_30_days' => $scansLast30Days,
                'claims_last_30_days' => $claimsLast30Days,
                'redemptions_last_30_days' => $redemptionsLast30Days,
            ],
        ];
    }
    
    /**
     * Get daily metrics for chart
     */
    public function getDailyMetrics(CrossPromotion $crossPromo, int $days = 30): array
    {
        $qrCodeIds = $crossPromo->qrCodes()->pluck('id');
        $startDate = now()->subDays($days);
        
        $scansByDay = Scan::whereIn('qr_code_id', $qrCodeIds)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();
        
        $claimsByDay = UserPromoToken::whereIn('promotion_id', [
            $crossPromo->promotion_1_id,
            $crossPromo->promotion_2_id,
        ])
        ->whereHas('qrCode', function ($q) use ($crossPromo) {
            $q->where('cross_promotion_id', $crossPromo->id);
        })
        ->where('created_at', '>=', $startDate)
        ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
        ->groupBy('date')
        ->orderBy('date')
        ->pluck('count', 'date')
        ->toArray();
        
        $redemptionsByDay = Redemption::whereIn('promotion_id', [
            $crossPromo->promotion_1_id,
            $crossPromo->promotion_2_id,
        ])
        ->whereIn('qr_code_id', $qrCodeIds)
        ->where('redeemed_at', '>=', $startDate)
        ->selectRaw('DATE(redeemed_at) as date, COUNT(*) as count')
        ->groupBy('date')
        ->orderBy('date')
        ->pluck('count', 'date')
        ->toArray();
        
        // Build array for all dates
        $metrics = [];
        for ($i = $days; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $metrics[] = [
                'date' => $date,
                'scans' => $scansByDay[$date] ?? 0,
                'claims' => $claimsByDay[$date] ?? 0,
                'redemptions' => $redemptionsByDay[$date] ?? 0,
            ];
        }
        
        return $metrics;
    }
    
    /**
     * Calculate revenue share for a cross-promotion
     */
    public function calculateRevenueShare(CrossPromotion $crossPromo, int $businessId): array
    {
        $qrCodeIds = $crossPromo->qrCodes()->pluck('id');
        
        // Get redemptions for promotions in this cross-promo
        $redemptions = \App\Models\Redemption::whereIn('promotion_id', [
            $crossPromo->promotion_1_id,
            $crossPromo->promotion_2_id,
        ])
        ->whereIn('qr_code_id', $qrCodeIds)
        ->get();
        
        $myPromotionId = ($businessId === $crossPromo->business_1_id) 
            ? $crossPromo->promotion_1_id 
            : $crossPromo->promotion_2_id;
        
        $partnerPromotionId = ($businessId === $crossPromo->business_1_id) 
            ? $crossPromo->promotion_2_id 
            : $crossPromo->promotion_1_id;
        
        $myRedemptions = $redemptions->where('promotion_id', $myPromotionId);
        $partnerRedemptions = $redemptions->where('promotion_id', $partnerPromotionId);
        
        $myRevenue = $myRedemptions->sum('final_amount');
        $partnerRevenue = $partnerRedemptions->sum('final_amount');
        
        $revenueSharePercent = $crossPromo->revenue_share_percent ?? 50.0;
        
        // Calculate shared revenue
        $totalRevenue = $myRevenue + $partnerRevenue;
        $myShare = ($totalRevenue * $revenueSharePercent) / 100;
        $partnerShare = ($totalRevenue * (100 - $revenueSharePercent)) / 100;
        
        return [
            'my_revenue' => round($myRevenue, 2),
            'partner_revenue' => round($partnerRevenue, 2),
            'total_revenue' => round($totalRevenue, 2),
            'revenue_share_percent' => $revenueSharePercent,
            'my_share' => round($myShare, 2),
            'partner_share' => round($partnerShare, 2),
            'my_redemptions_count' => $myRedemptions->count(),
            'partner_redemptions_count' => $partnerRedemptions->count(),
        ];
    }
}

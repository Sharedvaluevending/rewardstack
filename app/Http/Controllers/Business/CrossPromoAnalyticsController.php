<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\CrossPromotion;
use App\Services\CrossPromoAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CrossPromoAnalyticsController extends Controller
{
    public function __construct(
        protected CrossPromoAnalyticsService $analyticsService
    ) {}
    
    /**
     * Show analytics for a cross-promotion
     */
    public function show(Request $request, CrossPromotion $crossPromotion)
    {
        $business = $request->user()->business;
        
        // Verify business owns this cross-promo
        if ($crossPromotion->business_1_id !== $business->id && $crossPromotion->business_2_id !== $business->id) {
            abort(403);
        }
        
        $partnerBusiness = $crossPromotion->getPartnerBusiness($business->id);
        $myPromotion = $crossPromotion->getMyPromotion($business->id);
        $partnerPromotion = $crossPromotion->getPartnerPromotion($business->id);
        
        $analytics = $this->analyticsService->getAnalytics($crossPromotion);
        $dailyMetrics = $this->analyticsService->getDailyMetrics($crossPromotion, 30);
        $revenueShare = $this->analyticsService->calculateRevenueShare($crossPromotion, $business->id);
        
        return Inertia::render('Business/Partnerships/CrossPromoAnalytics', [
            'crossPromo' => [
                'id' => $crossPromotion->id,
                'code' => $crossPromotion->code,
                'name' => $crossPromotion->name,
                'chain_mode' => $crossPromotion->chain_mode,
            ],
            'partner' => [
                'id' => $partnerBusiness->id,
                'name' => $partnerBusiness->name,
                'logo' => $partnerBusiness->logo_url,
            ],
            'my_promotion' => $myPromotion ? [
                'id' => $myPromotion->id,
                'name' => $myPromotion->name,
            ] : null,
            'partner_promotion' => $partnerPromotion ? [
                'id' => $partnerPromotion->id,
                'name' => $partnerPromotion->name,
            ] : null,
            'analytics' => $analytics,
            'dailyMetrics' => $dailyMetrics,
            'revenueShare' => $revenueShare,
        ]);
    }
}

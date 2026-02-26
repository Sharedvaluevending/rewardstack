<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Scan;
use App\Models\Redemption;
use App\Models\StackableEntry;
use App\Models\StackablePool;
use App\Models\UserPromoToken;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StackablePoolController extends Controller
{
    /**
     * Simplified Stackable Deals Page
     * Each business can have ONE promotion in the stackable pool
     */
    public function index(Request $request)
    {
        $business = $request->user()->business;

        // Get current stackable promotion
        $currentStackable = $business->promotions()
            ->where('is_stackable', true)
            ->where('is_active', true)
            ->first();

        // Get all active promotions that have a Stackable QR attached
        $promotions = $business->promotions()
            ->where('is_active', true)
            ->whereHas('qrCodes', fn ($q) => $q->where('type', 'stackable')->where('is_active', true))
            ->get(['id', 'name', 'description', 'discount_type', 'discount_value']);

        // Count total businesses in stackable pool (active, approved entries)
        $totalBusinesses = StackableEntry::where('is_active', true)
            ->where('is_approved', true)
            ->distinct('business_id')
            ->count('business_id');

        // Simple stackable performance snapshot (counts; redemptions only when a stackable promo is actually redeemed)
        $stackableStats = [
            'scans' => Scan::where('business_id', $business->id)
                ->where('scan_type', Scan::TYPE_STACKABLE)
                ->count(),
            'claims' => UserPromoToken::where('business_id', $business->id)
                ->whereHas('promotion', fn ($q) => $q->where('is_stackable', true))
                ->count(),
            'redemptions' => Redemption::where('business_id', $business->id)
                ->whereHas('promotion', fn ($q) => $q->where('is_stackable', true))
                ->count(),
        ];

        return Inertia::render('Business/StackablePools/Index', [
            'currentStackable' => $currentStackable,
            'promotions' => $promotions,
            'totalBusinesses' => $totalBusinesses,
            'businessCity' => $business->city,
            'subscriptionTier' => $business->subscription_tier ?? 'starter',
            'stackableStats' => $stackableStats,
        ]);
    }

    /**
     * Set/swap the stackable promotion
     */
    public function setStackable(Request $request)
    {
        $validated = $request->validate([
            'promotion_id' => 'required|exists:promotions,id',
        ]);

        $business = $request->user()->business;

        // Validate stackable requires Growth+ subscription
        $tier = $business->subscription_tier ?? 'starter';
        if (!in_array($tier, ['growth', 'pro', 'enterprise'])) {
            return back()->withErrors([
                'subscription' => 'Growth or higher subscription needed for Stackable Deals'
            ]);
        }

        // Verify promotion belongs to business
        $promotion = Promotion::where('id', $validated['promotion_id'])
            ->where('business_id', $business->id)
            ->firstOrFail();

        // Ensure promotion has an active Stackable QR attached
        $stackableQr = QRCode::where('business_id', $business->id)
            ->where('type', 'stackable')
            ->where('promotion_id', $promotion->id)
            ->where('is_active', true)
            ->first();
        if (!$stackableQr) {
            return back()->withErrors([
                'promotion_id' => 'Select a promotion that has an active Stackable QR code attached.',
            ]);
        }


        // Remove stackable from any other promotions
        $business->promotions()->where('is_stackable', true)->update(['is_stackable' => false]);

        // Set this one as stackable
        $promotion->update(['is_stackable' => true]);

        // Ensure shared pool exists
        $pool = StackablePool::firstOrCreate(
            ['code' => 'REVENUE-QR'],
            [
                'name' => 'Revenue QR Pool',
                'description' => 'Shared Revenue QR deals pool',
                'is_active' => true,
            ]
        );
        if (!$pool->is_active) {
            $pool->update(['is_active' => true]);
        }

        if ($stackableQr->stackable_pool_id !== $pool->id) {
            $stackableQr->update(['stackable_pool_id' => $pool->id]);
        }

        // Deactivate any existing entries for this business
        StackableEntry::where('stackable_pool_id', $pool->id)
            ->where('business_id', $business->id)
            ->update(['is_active' => false]);

        // Create/update entry for selected promotion
        StackableEntry::updateOrCreate(
            [
                'stackable_pool_id' => $pool->id,
                'business_id' => $business->id,
                'promotion_id' => $promotion->id,
            ],
            [
                'is_active' => true,
                'is_approved' => true,
                'approved_at' => now(),
            ]
        );

        return back()->with('success', 'Deal added to stackable pool!');
    }

    /**
     * Remove from stackable pool
     */
    public function removeStackable(Request $request)
    {
        $business = $request->user()->business;

        // Remove stackable from all promotions
        $business->promotions()->where('is_stackable', true)->update(['is_stackable' => false]);

        $pool = StackablePool::where('code', 'REVENUE-QR')->first();
        if ($pool) {
            StackableEntry::where('stackable_pool_id', $pool->id)
                ->where('business_id', $business->id)
                ->update(['is_active' => false]);
        }

        return back()->with('success', 'Deal removed from stackable pool.');
    }

    /**
     * API: Get all stackable deals sorted by distance
     * Used when customer scans the stackable QR code
     */
    public function getStackableDeals(Request $request)
    {
        $userLat = $request->get('latitude');
        $userLng = $request->get('longitude');

        $query = Promotion::where('is_stackable', true)
            ->where('is_active', true)
            ->with(['business:id,name,logo_path,city,latitude,longitude,address_line1']);

        $promotions = $query->get();

        // Calculate distances and sort
        $deals = $promotions->map(function ($promo) use ($userLat, $userLng) {
            $business = $promo->business;
            $distance = null;

            if ($userLat && $userLng && $business->latitude && $business->longitude) {
                $distance = $this->calculateDistance(
                    $userLat, $userLng,
                    $business->latitude, $business->longitude
                );
            }

            return [
                'id' => $promo->id,
                'name' => $promo->name,
                'description' => $promo->description,
                'discount_type' => $promo->discount_type,
                'discount_value' => $promo->discount_value,
                'display_description' => $promo->getDisplayDescription(),
                'business' => [
                    'id' => $business->id,
                    'name' => $business->name,
                    'logo' => $business->logo_url,
                    'city' => $business->city,
                    'address' => $business->address_line1,
                ],
                'distance_miles' => $distance,
                'distance_text' => $distance !== null 
                    ? ($distance < 0.1 ? 'Very close!' : round($distance, 1) . ' mi away')
                    : null,
            ];
        })
        ->sortBy('distance_miles')
        ->values();

        return response()->json([
            'deals' => $deals,
            'total' => $deals->count(),
        ]);
    }

    /**
     * Calculate distance in miles using Haversine formula
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 3959; // miles

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlon / 2) ** 2;
        $c = 2 * asin(sqrt($a));

        return $earthRadius * $c;
    }
}

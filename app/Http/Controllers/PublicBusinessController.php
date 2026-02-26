<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Promotion;
use App\Models\StackableEntry;
use App\Services\BusinessCustomerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicBusinessController extends Controller
{
    public function show(Request $request, Business $business)
    {
        if (!$business->is_active) {
            abort(404);
        }

        $settings = is_array($business->settings) ? $business->settings : [];
        $featuredPromotionId = isset($settings['featured_promotion_id']) ? (int) $settings['featured_promotion_id'] : null;

        $featuredPromotion = null;
        if ($featuredPromotionId && $business->canAccess('featured_promo')) {
            $featuredPromotion = Promotion::query()
                ->where('id', $featuredPromotionId)
                ->where('business_id', $business->id)
                ->where('is_active', true)
                ->first();
        }

        $promotions = $business->promotions()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'name',
                'description',
                'discount_type',
                'discount_value',
                'starts_at',
                'ends_at',
                'rules',
                'punches_required',
                'reward_value',
                'buy_quantity',
                'get_quantity',
                'for_price',
            ]);

        $stackableEntries = StackableEntry::query()
            ->active()
            ->where('business_id', $business->id)
            ->with(['pool:id,name,city,region,is_active,starts_at,ends_at', 'promotion:id,name,discount_type,discount_value'])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit(10)
            ->get();

        // Directions URL (prefer lat/lng if present, otherwise use address string)
        $directionsUrl = null;
        if ($business->latitude && $business->longitude) {
            $directionsUrl = 'https://www.google.com/maps/search/?api=1&query=' . $business->latitude . ',' . $business->longitude;
        } else {
            $parts = array_filter([
                $business->address_line1,
                $business->city,
                $business->state,
                $business->postal_code,
            ], fn ($v) => is_string($v) && trim($v) !== '');
            if (count($parts) > 0) {
                $directionsUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode(implode(', ', $parts));
            }
        }

        $isSubscribed = false;
        if ($request->user() && in_array($request->user()->role, ['customer', 'user'], true)) {
            $isSubscribed = app(BusinessCustomerService::class)->isSubscribed((int) $business->id, (int) $request->user()->id);
        }

        return Inertia::render('Public/Business', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'type' => $business->type,
                'description' => $business->description,
                'logo_url' => $business->logo_url,
                'primary_color' => $business->primary_color,
                'secondary_color' => $business->secondary_color,
                'website' => $business->website,
                'phone' => $business->phone,
                'email' => $business->email,
                'facebook_url' => $business->facebook_url,
                'instagram_url' => $business->instagram_url,
                'address_line1' => $business->address_line1,
                'address_line2' => $business->address_line2,
                'city' => $business->city,
                'state' => $business->state,
                'postal_code' => $business->postal_code,
                'business_hours' => $business->business_hours,
                'directions_url' => $directionsUrl,
            ],
            'isSubscribed' => $isSubscribed,
            'featuredPromo' => $featuredPromotion ? [
                'id' => $featuredPromotion->id,
                'name' => $featuredPromotion->name,
                'description' => $featuredPromotion->description,
                'display_value' => $featuredPromotion->getDisplayDescription(),
                'starts_at' => $featuredPromotion->starts_at?->format('M d, Y'),
                'ends_at' => $featuredPromotion->ends_at?->format('M d, Y'),
            ] : null,
            'promotions' => $promotions->map(fn (Promotion $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'display_value' => $p->getDisplayDescription(),
                'starts_at' => $p->starts_at?->format('M d, Y'),
                'ends_at' => $p->ends_at?->format('M d, Y'),
            ])->values(),
            'stackables' => $stackableEntries->map(fn ($e) => [
                'pool' => [
                    'name' => $e->pool?->name,
                    'city' => $e->pool?->city,
                    'region' => $e->pool?->region,
                ],
                'promotion' => [
                    'name' => $e->promotion?->name,
                    'display_value' => $e->promotion?->getDisplayDescription(),
                ],
                'is_featured' => (bool) $e->is_featured,
            ])->values(),
        ]);
    }
}


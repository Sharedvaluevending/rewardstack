<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\PunchCard;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PromotionController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->user()->business;

        // Handle case where user doesn't have a business yet
        if (!$business) {
            return Inertia::render('Business/Promotions/Index', [
                'promotions' => ['data' => [], 'last_page' => 1, 'current_page' => 1],
            ]);
        }

        try {
            $promotions = $business->promotions()
                ->withCount('redemptions')
                ->withCount('qrCodes')
                ->orderByDesc('created_at')
                ->paginate(12);
        } catch (\Exception $e) {
            // Fallback without counts if relationship fails
            $promotions = $business->promotions()
                ->orderByDesc('created_at')
                ->paginate(12);
        }

        return Inertia::render('Business/Promotions/Index', [
            'promotions' => $promotions,
        ]);
    }

    public function create()
    {
        $business = request()->user()->business;
        return Inertia::render('Business/Promotions/Create', [
            'discountTypes' => Promotion::discountTypes(),
            'punchIconOptions' => Promotion::punchIconOptions(),
            'subscriptionTier' => $business->subscription_tier ?? 'starter',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'terms' => 'nullable|string|max:2000',
            'discount_type' => 'required|string',
            'discount_value' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($request) {
                    // For percentage, happy_hour, and first_time discounts, ensure value doesn't exceed 100 when percentage-based
                    if (in_array($request->discount_type, ['percentage', 'happy_hour', 'first_time']) && $request->input('rules.first_time_kind', 'percentage') === 'percentage' && $value > 100) {
                        $discountTypeName = $request->discount_type === 'percentage'
                            ? 'Percentage'
                            : ($request->discount_type === 'happy_hour' ? 'Happy hour' : 'First time');
                        $fail("{$discountTypeName} discounts cannot exceed 100%.");
                    }
                },
            ],
            'tiers' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->discount_type === 'tiered') {
                        if (empty($value) || !is_array($value)) {
                            return $fail('At least one tier is required.');
                        }
                        foreach ($value as $idx => $tier) {
                            $min = $tier['min_spend'] ?? null;
                            $disc = $tier['discount'] ?? null;
                            if (!is_numeric($min) || $min <= 0) {
                                return $fail("Tier " . ($idx + 1) . " requires a minimum spend greater than 0.");
                            }
                            if (!is_numeric($disc) || $disc <= 0) {
                                return $fail("Tier " . ($idx + 1) . " requires a percent off greater than 0.");
                            }
                        }
                    }
                }
            ],
            'tiers' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->discount_type === 'tiered') {
                        if (empty($value) || !is_array($value)) {
                            return $fail('At least one tier is required.');
                        }
                        foreach ($value as $idx => $tier) {
                            $min = $tier['min_spend'] ?? null;
                            $disc = $tier['discount'] ?? null;
                            if (!is_numeric($min) || $min <= 0) {
                                return $fail("Tier " . ($idx + 1) . " requires a minimum spend greater than 0.");
                            }
                            if (!is_numeric($disc) || $disc <= 0) {
                                return $fail("Tier " . ($idx + 1) . " requires a percent off greater than 0.");
                            }
                        }
                    }
                }
            ],
            'buy_quantity' => 'nullable|integer|min:1',
            'get_quantity' => 'nullable|integer|min:1',
            'for_price' => 'nullable|numeric|min:0',
            'punches_required' => 'nullable|integer|min:1|max:50',
            'reward_value' => 'nullable|numeric|min:0',
            'punch_icon' => 'nullable|string|max:50',
            'tiers' => 'nullable|array',
            'original_price' => 'nullable|numeric|min:0',
            'minimum_purchase' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'punch_card_max_cards_per_user' => 'nullable|integer|min:1',
            'punch_card_total_cards_limit' => 'nullable|integer|min:1',
            'punch_card_max_punches_per_day' => 'nullable|integer|min:1',
            'rules' => 'nullable|array',
            'rules.max_redemptions_total' => 'nullable|integer',
            'rules.max_redemptions_per_user' => 'nullable|integer',
            'rules.max_per_day' => 'nullable|integer',
            'rules.max_per_week' => 'nullable|integer',
            'rules.max_per_month' => 'nullable|integer',
            'rules.portal_multiple_scans' => 'nullable|boolean',
            'rules.respect_punch_limits' => 'nullable|boolean',
            'rules.valid_days' => 'nullable|array',
            'rules.valid_hours' => 'nullable|array',
            'rules.buy_item_prices' => 'nullable|array',
            'rules.get_item_prices' => 'nullable|array',
            'rules.first_time_kind' => 'nullable|string|in:percentage,fixed',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
            'is_stackable' => 'boolean',
        ]);

        $business = $request->user()->business;

        // Clean up rules payload (avoid persisting empty time windows that would block redemption).
        if (isset($validated['rules']) && is_array($validated['rules'])) {
            if (isset($validated['rules']['valid_hours']) && is_array($validated['rules']['valid_hours'])) {
                $start = $validated['rules']['valid_hours']['start'] ?? null;
                $end = $validated['rules']['valid_hours']['end'] ?? null;
                if (empty($start) || empty($end)) {
                    unset($validated['rules']['valid_hours']);
                }
            }

            // Ensure flags are boolean
            if (isset($validated['rules']['portal_multiple_scans'])) {
                $validated['rules']['portal_multiple_scans'] = (bool) $validated['rules']['portal_multiple_scans'];
            }
            if (isset($validated['rules']['respect_punch_limits'])) {
                $validated['rules']['respect_punch_limits'] = (bool) $validated['rules']['respect_punch_limits'];
            }

            // Clean up old fields
            unset($validated['rules']['portal_max_per_user']);
        }

        // Enforce plan limit for promotion creation (hard limit; -1 means unlimited)
        $promoLimit = $business->getLimit('promotions');
        if ($promoLimit !== -1) {
            $currentCount = $business->promotions()->count();
            if ($currentCount >= $promoLimit) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'limit' => "Promotion limit reached ({$promoLimit}). Please upgrade your plan to create more promotions.",
                    ]);
            }
        }
        
        // Validate stackable promotions require Growth+ subscription
        if ($validated['is_stackable'] ?? false) {
            $tier = $business->subscription_tier ?? 'starter';
            if (!in_array($tier, ['growth', 'pro', 'enterprise'])) {
                return back()->withErrors([
                    'is_stackable' => 'Growth or higher subscription needed for Stackable Deals'
                ]);
            }
            
            // If setting as stackable, unset any other stackable promotion for this business
            $business->promotions()->where('is_stackable', true)->update(['is_stackable' => false]);
        }
        
        unset($validated['business_id']);
        $promotion = $business->promotions()->create($validated);

        // Auto-complete onboarding step
        $business->completeOnboardingStep('create_promotion');

        return redirect()->route('business.promotions.show', $promotion)
            ->with('success', 'Promotion created successfully!');
    }

    public function show(Promotion $promotion)
    {
        $business = request()->user()->business;

        // Ensure the promotion belongs to the current user's business
        if ($promotion->business_id !== $business->id) {
            abort(403, 'You do not have permission to view this promotion.');
        }

        $promotion->loadCount('redemptions');
        $promotion->load('qrCodes:id,name,code,promotion_id');

        // Get recent redemptions
        $recentRedemptions = $promotion->redemptions()
            ->with('employee.user:id,name')
            ->orderByDesc('redeemed_at')
            ->limit(10)
            ->get();

        return Inertia::render('Business/Promotions/Show', [
            'promotion' => $promotion,
            'recentRedemptions' => $recentRedemptions,
        ]);
    }

    public function edit(Promotion $promotion)
    {
        $business = request()->user()->business;

        // Ensure the promotion belongs to the current user's business
        if ($promotion->business_id !== $business->id) {
            abort(403, 'You do not have permission to edit this promotion.');
        }

        return Inertia::render('Business/Promotions/Edit', [
            'promotion' => $promotion,
            'discountTypes' => Promotion::discountTypes(),
            'punchIconOptions' => Promotion::punchIconOptions(),
            'subscriptionTier' => $business->subscription_tier ?? 'starter',
        ]);
    }

    public function update(Request $request, Promotion $promotion)
    {
        $business = request()->user()->business;

        // Ensure the promotion belongs to the current user's business
        if ($promotion->business_id !== $business->id) {
            abort(403, 'You do not have permission to update this promotion.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'terms' => 'nullable|string|max:2000',
            'discount_type' => 'required|string',
            'discount_value' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($request) {
                    // For percentage and happy_hour discounts, ensure value doesn't exceed 100
                    if (in_array($request->discount_type, ['percentage', 'happy_hour', 'first_time']) && $value > 100) {
                        $discountTypeName = $request->discount_type === 'percentage' ? 'Percentage' : ($request->discount_type === 'happy_hour' ? 'Happy hour' : 'First time');
                        $fail("{$discountTypeName} discounts cannot exceed 100%.");
                    }
                },
            ],
            'buy_quantity' => 'nullable|integer|min:1',
            'get_quantity' => 'nullable|integer|min:1',
            'for_price' => 'nullable|numeric|min:0',
            'punches_required' => 'nullable|integer|min:1|max:50',
            'reward_value' => 'nullable|numeric|min:0',
            'punch_icon' => 'nullable|string|max:50',
            'tiers' => 'nullable|array',
            'original_price' => 'nullable|numeric|min:0',
            'minimum_purchase' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'punch_card_max_cards_per_user' => 'nullable|integer|min:1',
            'punch_card_total_cards_limit' => 'nullable|integer|min:1',
            'punch_card_max_punches_per_day' => 'nullable|integer|min:1',
            'rules' => 'nullable|array',
            'rules.max_redemptions_total' => 'nullable|integer',
            'rules.max_redemptions_per_user' => 'nullable|integer',
            'rules.max_per_day' => 'nullable|integer',
            'rules.max_per_week' => 'nullable|integer',
            'rules.max_per_month' => 'nullable|integer',
            'rules.portal_multiple_scans' => 'nullable|boolean',
            'rules.respect_punch_limits' => 'nullable|boolean',
            'rules.valid_days' => 'nullable|array',
            'rules.valid_hours' => 'nullable|array',
            'rules.buy_item_prices' => 'nullable|array',
            'rules.get_item_prices' => 'nullable|array',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
            'is_stackable' => 'boolean',
        ]);

        // Clean up rules payload (avoid persisting empty time windows that would block redemption).
        if (isset($validated['rules']) && is_array($validated['rules'])) {
            if (isset($validated['rules']['valid_hours']) && is_array($validated['rules']['valid_hours'])) {
                $start = $validated['rules']['valid_hours']['start'] ?? null;
                $end = $validated['rules']['valid_hours']['end'] ?? null;
                if (empty($start) || empty($end)) {
                    unset($validated['rules']['valid_hours']);
                }
            }

            // Ensure flags are boolean
            if (isset($validated['rules']['portal_multiple_scans'])) {
                $validated['rules']['portal_multiple_scans'] = (bool) $validated['rules']['portal_multiple_scans'];
            }
            if (isset($validated['rules']['respect_punch_limits'])) {
                $validated['rules']['respect_punch_limits'] = (bool) $validated['rules']['respect_punch_limits'];
            }

            // Clean up old fields
            unset($validated['rules']['portal_max_per_user']);
        }

        // Validate stackable promotions require Growth+ subscription
        if ($validated['is_stackable'] ?? false) {
            $tier = $business->subscription_tier ?? 'starter';
            if (!in_array($tier, ['growth', 'pro', 'enterprise'])) {
                return back()->withErrors([
                    'is_stackable' => 'Growth or higher subscription needed for Stackable Deals'
                ]);
            }
        }
        
        // If setting as stackable, unset any other stackable promotion for this business
        if (($validated['is_stackable'] ?? false) && !$promotion->is_stackable) {
            $promotion->business->promotions()
                ->where('id', '!=', $promotion->id)
                ->where('is_stackable', true)
                ->update(['is_stackable' => false]);
        }

        unset($validated['business_id']);
        $promotion->update($validated);

        return redirect()->route('business.promotions.show', $promotion)
            ->with('success', 'Promotion updated successfully!');
    }

    public function checkDelete(Promotion $promotion)
    {
        $business = request()->user()->business;
        abort_unless($promotion->business_id === $business->id, 403);

        return response()->json([
            'warnings' => $this->getPromotionDependencies($promotion),
        ]);
    }

    public function destroy(Promotion $promotion)
    {
        $business = request()->user()->business;

        if ($promotion->business_id !== $business->id) {
            abort(403, 'You do not have permission to delete this promotion.');
        }

        $promotion->delete();

        return redirect()->route('business.promotions.index')
            ->with('success', 'Promotion deleted successfully!');
    }

    protected function getPromotionDependencies(Promotion $promotion): array
    {
        $warnings = [];

        $qrCodes = QRCode::where('promotion_id', $promotion->id)->get(['id', 'name']);
        if ($qrCodes->count() > 0) {
            $names = $qrCodes->pluck('name')->filter()->implode(', ');
            $warnings[] = "{$qrCodes->count()} QR code(s) linked: {$names}. Their promotion link will be removed.";
        }

        $tokenCount = \App\Models\UserPromoToken::where('promotion_id', $promotion->id)->whereNull('redeemed_at')->count();
        if ($tokenCount > 0) {
            $warnings[] = "{$tokenCount} active user voucher(s) will be permanently deleted.";
        }

        $redemptionCount = \App\Models\Redemption::where('promotion_id', $promotion->id)->count();
        if ($redemptionCount > 0) {
            $warnings[] = "{$redemptionCount} redemption record(s) will be permanently deleted.";
        }

        $punchCardCount = PunchCard::where('promotion_id', $promotion->id)->count();
        if ($punchCardCount > 0) {
            $warnings[] = "{$punchCardCount} active punch card(s) for customers will lose their progress.";
        }

        $gameRewardCount = \App\Models\GameReward::where('promotion_id', $promotion->id)
            ->whereIn('status', [\App\Models\GameReward::STATUS_AVAILABLE, \App\Models\GameReward::STATUS_CLAIMED])
            ->count();
        if ($gameRewardCount > 0) {
            $warnings[] = "{$gameRewardCount} active game reward(s) / leaderboard prize(s) reference this promotion.";
        }

        $leaderboardCount = \App\Models\Leaderboard::where('promotion_id', $promotion->id)->count();
        if ($leaderboardCount > 0) {
            $warnings[] = "{$leaderboardCount} leaderboard(s) use this promotion as a prize.";
        }

        $crmCount = \App\Models\CrmCampaign::where('promotion_id', $promotion->id)->count();
        if ($crmCount > 0) {
            $warnings[] = "{$crmCount} CRM campaign(s) reference this promotion.";
        }

        return $warnings;
    }

    public function toggle(Promotion $promotion)
    {
        $business = request()->user()->business;

        // Ensure the promotion belongs to the current user's business
        if ($promotion->business_id !== $business->id) {
            abort(403, 'You do not have permission to update this promotion.');
        }

        $promotion->update(['is_active' => !$promotion->is_active]);

        return back()->with('success', 
            $promotion->is_active ? 'Promotion activated!' : 'Promotion deactivated!'
        );
    }

    public function templates()
    {
        $templates = [
            [
                'name' => '10% Off Everything',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'description' => 'Get 10% off your entire purchase',
            ],
            [
                'name' => '$5 Off Your Order',
                'discount_type' => 'fixed_amount',
                'discount_value' => 5,
                'description' => 'Save $5 on your next order',
            ],
            [
                'name' => 'Buy One Get One Free',
                'discount_type' => 'bogo',
                'description' => 'Buy any item and get another free',
            ],
            [
                'name' => 'Buy 2 Get 1 Free',
                'discount_type' => 'buy_x_get_y',
                'buy_quantity' => 2,
                'get_quantity' => 1,
                'description' => 'Buy 2 items, get the 3rd free',
            ],
            [
                'name' => 'Loyalty Punch Card',
                'discount_type' => 'punch_card',
                'punches_required' => 10,
                'description' => 'Buy 10 get 1 free',
            ],
            [
                'name' => 'Happy Hour 20% Off',
                'discount_type' => 'happy_hour',
                'discount_value' => 20,
                'description' => 'Special happy hour pricing',
                'rules' => [
                    'valid_hours' => ['start' => '16:00', 'end' => '18:00'],
                ],
            ],
            [
                'name' => 'First Time Customer 15% Off',
                'discount_type' => 'first_time',
                'discount_value' => 15,
                'description' => 'Welcome! Enjoy 15% off your first visit',
                'rules' => [
                    'max_redemptions_per_user' => 1,
                ],
            ],
            [
                'name' => 'Spend $50 Get 10% Off',
                'discount_type' => 'tiered',
                'tiers' => [
                    ['min_spend' => 50, 'discount' => 10],
                    ['min_spend' => 100, 'discount' => 15],
                    ['min_spend' => 150, 'discount' => 20],
                ],
                'description' => 'The more you spend, the more you save',
            ],
        ];

        return Inertia::render('Business/Promotions/Templates', [
            'templates' => $templates,
        ]);
    }

    public function ideas()
    {
        // Ideas are now handled entirely in the Vue component for easier maintenance
        return Inertia::render('Business/Promotions/Ideas');
    }
}


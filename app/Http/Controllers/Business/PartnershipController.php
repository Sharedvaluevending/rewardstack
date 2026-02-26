<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessPartnership;
use App\Models\CrossPromotion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Models\Promotion;
use App\Services\CrossPromoRulesService;

class PartnershipController extends Controller
{
    public function __construct(
        protected CrossPromoRulesService $rulesService
    ) {}
    
    /**
     * Partnerships & Cross-Promo Management
     */
    public function index(Request $request)
    {
        $business = $request->user()->business;

        // Get all partnerships for this business
        $partnerships = BusinessPartnership::forBusiness($business->id)
            ->with(['requesterBusiness', 'partnerBusiness'])
            ->latest()
            ->get()
            ->map(function ($partnership) use ($business) {
                $isRequester = $partnership->requester_business_id === $business->id;
                $otherBusiness = $isRequester ? $partnership->partnerBusiness : $partnership->requesterBusiness;
                
                return [
                    'id' => $partnership->id,
                    'status' => $partnership->status,
                    'is_requester' => $isRequester,
                    'message' => $partnership->message,
                    'response_message' => $partnership->response_message,
                    'responded_at' => $partnership->responded_at?->toISOString(),
                    'created_at' => $partnership->created_at->toISOString(),
                    'partner' => [
                        'id' => $otherBusiness->id,
                        'name' => $otherBusiness->name,
                        'logo' => $otherBusiness->logo,
                        'category' => $otherBusiness->type, // Use 'type' instead of 'category'
                        'city' => $otherBusiness->city,
                    ],
                ];
            });

        // Get active cross-promotions
        $crossPromos = CrossPromotion::forBusiness($business->id)
            ->with(['business1', 'business2', 'promotion1', 'promotion2', 'qrCodes'])
            ->active()
            ->get()
            ->map(function ($promo) use ($business) {
                $partnerBusiness = $promo->getPartnerBusiness($business->id);
                $myPromotion = $promo->getMyPromotion($business->id);
                $partnerPromotion = $promo->getPartnerPromotion($business->id);
                
                // Find QR codes created by partner for this cross-promo
                $partnerQrCode = $promo->qrCodes()
                    ->where('business_id', $partnerBusiness->id)
                    ->where('cross_promotion_id', $promo->id)
                    ->first();

                return [
                    'id' => $promo->id,
                    'code' => $promo->code,
                    'name' => $promo->name,
                    'display_mode' => $promo->display_mode,
                    'revenue_share_percent' => $promo->revenue_share_percent,
                    'expires_at' => $promo->expires_at?->toISOString(),
                    'partner_qr_code' => $partnerQrCode ? [
                        'id' => $partnerQrCode->id,
                        'code' => $partnerQrCode->code,
                    ] : null,
                    'partner' => [
                        'id' => $partnerBusiness->id,
                        'name' => $partnerBusiness->name,
                        'logo' => $partnerBusiness->logo_url,
                    ],
                    'my_promotion' => $myPromotion ? [
                        'id' => $myPromotion->id,
                        'name' => $myPromotion->name,
                        'discount_type' => $myPromotion->discount_type,
                    ] : null,
                    'partner_promotion' => $partnerPromotion ? [
                        'id' => $partnerPromotion->id,
                        'name' => $partnerPromotion->name,
                        'discount_type' => $partnerPromotion->discount_type,
                    ] : null,
                ];
            });

        // Pending requests TO this business (for notification badge)
        $pendingRequests = $partnerships->filter(fn($p) => 
            $p['status'] === 'pending' && !$p['is_requester']
        )->count();

        // Get all other businesses for the dropdown
        $allBusinesses = Business::where('id', '!=', $business->id)
            ->where('is_active', true)
            ->select('id', 'name', 'logo_path', 'type', 'city')
            ->orderBy('name')
            ->get()
            ->map(function ($b) use ($partnerships) {
                // Find if there's an existing partnership
                $partnership = $partnerships->first(fn($p) => $p['partner']['id'] === $b->id);
                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'logo' => $b->logo_url,
                    'category' => $b->type, // Use 'type' instead of 'category'
                    'city' => $b->city,
                    'partnership_status' => $partnership['status'] ?? null,
                    'partnership_id' => $partnership['id'] ?? null,
                ];
            });

        // Active promotions for this business (used when creating/accepting cross-promos)
        $myPromotions = $business->promotions()
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'discount_type']);

        // Pending cross-promo requests (mutual flow)
        $pendingIncomingCrossPromos = CrossPromotion::forBusiness($business->id)
            ->where('status', CrossPromotion::STATUS_PENDING)
            ->where('business_2_id', $business->id)
            ->with(['business1:id,name,logo_path', 'promotion1:id,name,discount_type,rules,is_active,ends_at'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($promo) use ($business) {
                return [
                    'id' => $promo->id,
                    'code' => $promo->code,
                    'name' => $promo->name,
                    'display_mode' => $promo->display_mode,
                    'revenue_share_percent' => $promo->revenue_share_percent,
                    'rules_status' => $promo->rules_status,
                    'cross_promo_rules' => $promo->cross_promo_rules,
                    'expires_at' => $promo->expires_at?->toISOString(),
                    'starts_at' => $promo->starts_at?->toISOString(),
                    'usage_limit' => $promo->usage_limit,
                    'requester' => [
                        'id' => $promo->business1?->id,
                        'name' => $promo->business1?->name,
                        'logo' => $promo->business1?->logo_url,
                    ],
                    'their_promotion' => $promo->promotion1 ? [
                        'id' => $promo->promotion1->id,
                        'name' => $promo->promotion1->name,
                        'discount_type' => $promo->promotion1->discount_type,
                        'rules' => $promo->promotion1->rules,
                        'is_active' => (bool) $promo->promotion1->is_active,
                        'ends_at' => $promo->promotion1->ends_at?->toISOString(),
                    ] : null,
                ];
            });

        $pendingOutgoingCrossPromos = CrossPromotion::forBusiness($business->id)
            ->where('status', CrossPromotion::STATUS_PENDING)
            ->where('business_1_id', $business->id)
            ->with(['business2:id,name,logo_path', 'promotion1:id,name,discount_type'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($promo) {
                return [
                    'id' => $promo->id,
                    'code' => $promo->code,
                    'name' => $promo->name,
                    'display_mode' => $promo->display_mode,
                    'revenue_share_percent' => $promo->revenue_share_percent,
                    'partner' => [
                        'id' => $promo->business2?->id,
                        'name' => $promo->business2?->name,
                        'logo' => $promo->business2?->logo_url,
                    ],
                    'my_promotion' => $promo->promotion1 ? [
                        'id' => $promo->promotion1->id,
                        'name' => $promo->promotion1->name,
                        'discount_type' => $promo->promotion1->discount_type,
                    ] : null,
                ];
            });

        return Inertia::render('Business/Partnerships/Index', [
            'partnerships' => $partnerships,
            'crossPromos' => $crossPromos,
            'pendingRequests' => $pendingRequests,
            'displayModes' => CrossPromotion::displayModes(),
            'chainModes' => CrossPromotion::chainModes(),
            'rulesStatuses' => [
                'use_promotion_rules' => 'Use Promotion Rules',
                'pending_agreement' => 'Pending Agreement',
                'agreed' => 'Agreed',
                'overridden' => 'Overridden',
            ],
            'allBusinesses' => $allBusinesses,
            'myPromotions' => $myPromotions,
            'pendingIncomingCrossPromos' => $pendingIncomingCrossPromos,
            'pendingOutgoingCrossPromos' => $pendingOutgoingCrossPromos,
        ]);
    }

    /**
     * Search businesses for partnership
     */
    public function searchBusinesses(Request $request)
    {
        $business = $request->user()->business;
        $search = $request->get('search', '');
        $perPage = max(10, min(50, (int) $request->get('per_page', 10)));

        $query = Business::where('id', '!=', $business->id)
            ->where('is_active', true)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('city', 'like', "%{$search}%")
                      ->orWhere('type', 'like', "%{$search}%");
                });
            })
            ->select('id', 'name', 'logo_path', 'type', 'city')
            ->orderBy('name');

        $businesses = $query->paginate($perPage);

        // Preload partnership statuses for current page
        $pageIds = $businesses->pluck('id');
        $existingPartnerships = BusinessPartnership::forBusiness($business->id)
            ->where(function ($q) use ($pageIds) {
                $q->whereIn('requester_business_id', $pageIds)
                  ->orWhereIn('partner_business_id', $pageIds);
            })
            ->get()
            ->keyBy(function ($p) use ($business) {
                return $p->requester_business_id === $business->id
                    ? $p->partner_business_id
                    : $p->requester_business_id;
            });

        return response()->json([
            'data' => $businesses->map(function ($b) use ($existingPartnerships) {
                $partnership = $existingPartnerships->get($b->id);
                return [
                    ...$b->toArray(),
                    'logo' => $b->logo_url,
                    'partnership_status' => $partnership?->status,
                    'partnership_id' => $partnership?->id,
                ];
            }),
            'pagination' => [
                'current_page' => $businesses->currentPage(),
                'last_page' => $businesses->lastPage(),
                'per_page' => $businesses->perPage(),
                'total' => $businesses->total(),
                'next_page_url' => $businesses->nextPageUrl(),
                'prev_page_url' => $businesses->previousPageUrl(),
            ],
        ]);
    }

    /**
     * Send partnership request
     */
    public function sendRequest(Request $request)
    {
        $validated = $request->validate([
            'partner_business_id' => 'required|exists:businesses,id',
            'message' => 'nullable|string|max:500',
        ]);

        $business = $request->user()->business;

        // Check if partnership already exists
        $existing = BusinessPartnership::where(function ($q) use ($business, $validated) {
            $q->where('requester_business_id', $business->id)
              ->where('partner_business_id', $validated['partner_business_id']);
        })->orWhere(function ($q) use ($business, $validated) {
            $q->where('requester_business_id', $validated['partner_business_id'])
              ->where('partner_business_id', $business->id);
        })->first();

        if ($existing) {
            if ($existing->status === 'pending') {
                return back()->withErrors(['partner_business_id' => 'A partnership request is already pending.']);
            }
            if ($existing->status === 'accepted') {
                return back()->withErrors(['partner_business_id' => 'You are already partners with this business.']);
            }
            // If declined/cancelled, delete and create new
            $existing->delete();
        }

        $partnership = BusinessPartnership::create([
            'requester_business_id' => $business->id,
            'partner_business_id' => $validated['partner_business_id'],
            'message' => $validated['message'],
            'status' => BusinessPartnership::STATUS_PENDING,
        ]);

        $partnerBusiness = \App\Models\Business::find($validated['partner_business_id']);
        if ($partnerBusiness && $partnerBusiness->owner) {
            $partnerBusiness->owner->notify(new \App\Notifications\PartnershipRequestReceived($business));
        }

        return back()->with('success', 'Partnership request sent!');
    }

    /**
     * Accept partnership request
     */
    public function acceptRequest(Request $request, BusinessPartnership $partnership)
    {
        $business = $request->user()->business;

        // Only the partner (not requester) can accept
        if ($partnership->partner_business_id !== $business->id) {
            abort(403);
        }

        if (!$partnership->isPending()) {
            return back()->withErrors(['error' => 'This request is no longer pending.']);
        }

        $partnership->accept($request->get('message'));

        if ($partnership->requesterBusiness && $partnership->requesterBusiness->owner) {
            $partnership->requesterBusiness->owner->notify(new \App\Notifications\PartnershipAccepted($business));
        }

        return back()->with('success', 'Partnership accepted!');
    }

    /**
     * Decline partnership request
     */
    public function declineRequest(Request $request, BusinessPartnership $partnership)
    {
        $business = $request->user()->business;

        // Only the partner (not requester) can decline
        if ($partnership->partner_business_id !== $business->id) {
            abort(403);
        }

        if (!$partnership->isPending()) {
            return back()->withErrors(['error' => 'This request is no longer pending.']);
        }

        $partnership->decline($request->get('message'));

        return back()->with('success', 'Partnership declined.');
    }

    /**
     * Cancel partnership request (requester only)
     */
    public function cancelRequest(Request $request, BusinessPartnership $partnership)
    {
        $business = $request->user()->business;

        // Only the requester can cancel
        if ($partnership->requester_business_id !== $business->id) {
            abort(403);
        }

        $partnership->cancel();

        return back()->with('success', 'Partnership request cancelled.');
    }

    /**
     * Remove an accepted partnership
     */
    public function destroy(Request $request, BusinessPartnership $partnership)
    {
        $business = $request->user()->business;

        // Verify ownership
        if ($partnership->requester_business_id !== $business->id && $partnership->partner_business_id !== $business->id) {
            abort(403);
        }

        // Deactivate all cross-promotions between these two businesses
        $partner = $partnership->getOtherBusiness($business->id);
        
        CrossPromotion::where(function($q) use ($business, $partner) {
                $q->where('business_1_id', $business->id)->where('business_2_id', $partner->id);
            })
            ->orWhere(function($q) use ($business, $partner) {
                $q->where('business_1_id', $partner->id)->where('business_2_id', $business->id);
            })
            ->update(['is_active' => false, 'status' => CrossPromotion::STATUS_DECLINED]);

        $partnership->delete();

        if ($partner && $partner->owner) {
            $partner->owner->notify(new \App\Notifications\PartnershipEnded($business));
        }

        return back()->with('success', 'Partnership ended.');
    }

    /**
     * Create cross-promotion with partner
     */
    public function createCrossPromo(Request $request)
    {
        $validated = $request->validate([
            'partner_business_id' => 'required|exists:businesses,id',
            'name' => 'required|string|max:255',
            'my_promotion_id' => 'required|exists:promotions,id',
            'display_mode' => 'required|in:split,alternating,random',
            'chain_mode' => 'nullable|in:open,sequential',
            'primary_promotion_id' => 'nullable|exists:promotions,id',
            'expires_at' => 'nullable|date|after_or_equal:today',
            'starts_at' => 'nullable|date|before_or_equal:expires_at',
            'usage_limit' => 'nullable|integer|min:1',
        ]);

        $business = $request->user()->business;

        // Verify my promotion belongs to this business
        $myPromotion = Promotion::where('id', $validated['my_promotion_id'])
            ->where('business_id', $business->id)
            ->firstOrFail();

        // Guard against circular cross-promos: if an active/pending cross-promo
        // already exists between these two businesses (in either direction), block creation.
        $existingCrossPromo = CrossPromotion::where(function ($q) use ($business, $validated) {
                $q->where('business_1_id', $business->id)
                  ->where('business_2_id', $validated['partner_business_id']);
            })
            ->orWhere(function ($q) use ($business, $validated) {
                $q->where('business_1_id', $validated['partner_business_id'])
                  ->where('business_2_id', $business->id);
            })
            ->whereIn('status', [CrossPromotion::STATUS_PENDING, CrossPromotion::STATUS_ACCEPTED])
            ->first();

        if ($existingCrossPromo) {
            return back()->withErrors([
                'partner_business_id' => 'An active or pending partner deal already exists with this business. Please manage the existing deal instead.',
            ]);
        }

        // Verify partnership is accepted
        $partnership = BusinessPartnership::forBusiness($business->id)
            ->where(function ($q) use ($validated) {
                $q->where('requester_business_id', $validated['partner_business_id'])
                  ->orWhere('partner_business_id', $validated['partner_business_id']);
            })
            ->accepted()
            ->first();

        if (!$partnership) {
            return back()->withErrors(['partner_business_id' => 'You must have an accepted partnership first.']);
        }

        // Only the partnership requester can initiate a partner deal
        if ((int) $partnership->requester_business_id !== (int) $business->id) {
            return back()->withErrors([
                'partner_business_id' => 'Only the business that requested the partnership can create the partner deal.',
            ]);
        }

        // Determine primary promotion for chain mode
        $primaryPromotionId = null;
        $chainMode = $validated['chain_mode'] ?? 'open';
        
        if ($chainMode === 'sequential') {
            // If user selected their own promotion as primary
            if (!empty($validated['primary_promotion_id']) && (int)$validated['primary_promotion_id'] === (int)$validated['my_promotion_id']) {
                $primaryPromotionId = (int)$validated['my_promotion_id'];
            }
        }

        // Rules are locked to the requester’s promotion rules
        $crossPromoRules = null;
        $rulesStatus = CrossPromotion::RULES_USE_PROMOTION_RULES;

        // Mutual flow:
        // - requester chooses their offer now (promotion_1_id)
        // - partner chooses their offer on accept (promotion_2_id)
        $crossPromo = CrossPromotion::create([
            'code' => Str::random(8),
            'name' => $validated['name'],
            'business_1_id' => $business->id,
            'business_2_id' => (int) $validated['partner_business_id'],
            'requested_by_business_id' => $business->id,
            'promotion_1_id' => (int) $validated['my_promotion_id'],
            'promotion_2_id' => null,
            'display_mode' => $validated['display_mode'],
            'chain_mode' => $chainMode,
            'primary_promotion_id' => $primaryPromotionId,
            'revenue_share_percent' => 0,
            'cross_promo_rules' => $crossPromoRules,
            'rules_status' => $rulesStatus,
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'status' => CrossPromotion::STATUS_PENDING,
            'is_active' => false,
        ]);

        $partnerBusiness = \App\Models\Business::find($validated['partner_business_id']);
        if ($partnerBusiness && $partnerBusiness->owner) {
            $partnerBusiness->owner->notify(new \App\Notifications\CrossPromoRequestReceived($business, $validated['name']));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'crossPromo' => [
                    'id' => $crossPromo->id,
                    'code' => $crossPromo->code,
                    'status' => $crossPromo->status,
                ],
            ]);
        }

        return back()->with('success', 'Cross-promotion request sent! Your partner must accept and choose their offer.');
    }

    /**
     * Accept a pending cross-promo request (partner chooses their offer).
     */
    public function acceptCrossPromo(Request $request, CrossPromotion $crossPromotion)
    {
        $validated = $request->validate([
            'my_promotion_id' => 'required|exists:promotions,id',
            'agree' => 'accepted',
        ]);

        $business = $request->user()->business;

        // Only the invited partner (business_2) can accept
        if ((int) $crossPromotion->business_2_id !== (int) $business->id) {
            abort(403);
        }

        if ($crossPromotion->status !== CrossPromotion::STATUS_PENDING) {
            return back()->withErrors(['error' => 'This cross-promo is no longer pending.']);
        }

        // Verify selected promotion belongs to accepting business
        $myPromotion = Promotion::where('id', $validated['my_promotion_id'])
            ->where('business_id', $business->id)
            ->firstOrFail();

        // Partner promo must still be valid/active
        if ($crossPromotion->promotion1 && !$crossPromotion->promotion1->isCurrentlyValid()) {
            return back()->withErrors(['error' => 'Partner promotion is inactive or expired. Ask them to update it before accepting.']);
        }

        // Rules are locked to the requester’s promotion rules
        $rulesStatus = CrossPromotion::RULES_USE_PROMOTION_RULES;
        $crossPromoRules = null;
        
        // Auto-calculate expiration if not set (use earliest promotion expiration)
        $expiresAt = $crossPromotion->expires_at;
        if (!$expiresAt && $crossPromotion->promotion1 && $myPromotion) {
            $promo1Expires = $crossPromotion->promotion1->ends_at;
            $promo2Expires = $myPromotion->ends_at;
            
            if ($promo1Expires && $promo2Expires) {
                $expiresAt = $promo1Expires->lt($promo2Expires) ? $promo1Expires : $promo2Expires;
            } elseif ($promo1Expires) {
                $expiresAt = $promo1Expires;
            } elseif ($promo2Expires) {
                $expiresAt = $promo2Expires;
            }
        }

        $crossPromotion->update([
            'promotion_2_id' => (int) $validated['my_promotion_id'],
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
            'accepted_at' => now(),
            'declined_at' => null,
            'cross_promo_rules' => $crossPromoRules,
            'rules_status' => $rulesStatus,
            'expires_at' => $expiresAt,
            // If sequential and primary is not set (meaning partner goes first), set it now
            'primary_promotion_id' => ($crossPromotion->chain_mode === 'sequential' && is_null($crossPromotion->primary_promotion_id))
                ? (int) $validated['my_promotion_id']
                : $crossPromotion->primary_promotion_id,
        ]);
        
        // Notify requester
        $requesterBusiness = $crossPromotion->business1;
        if ($requesterBusiness && $requesterBusiness->owner) {
            $requesterBusiness->owner->notify(new \App\Notifications\CrossPromoAccepted($crossPromotion));
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Cross-promotion accepted!');
    }

    /**
     * Decline a pending cross-promo request.
     */
    public function declineCrossPromo(Request $request, CrossPromotion $crossPromotion)
    {
        $business = $request->user()->business;

        // Only the invited partner (business_2) can decline
        if ((int) $crossPromotion->business_2_id !== (int) $business->id) {
            abort(403);
        }

        if ($crossPromotion->status !== CrossPromotion::STATUS_PENDING) {
            return back()->withErrors(['error' => 'This cross-promo is no longer pending.']);
        }

        $crossPromotion->update([
            'status' => CrossPromotion::STATUS_DECLINED,
            'is_active' => false,
            'declined_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Cross-promotion declined.');
    }

    /**
     * Get accepted partners for QR code creation dropdown
     */
    public function getAcceptedPartners(Request $request)
    {
        $business = $request->user()->business;

        $partners = BusinessPartnership::forBusiness($business->id)
            ->accepted()
            ->with(['requesterBusiness', 'partnerBusiness'])
            ->get()
            ->map(function ($partnership) use ($business) {
                $partner = $partnership->getOtherBusiness($business->id);
                return [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'logo' => $partner->logo,
                    'category' => $partner->type, // Use 'type' instead of 'category'
                ];
            });

        return response()->json(['partners' => $partners]);
    }
    
    /**
     * Edit cross-promo (name, display mode, chain mode, expiration)
     */
    public function editCrossPromo(Request $request, CrossPromotion $crossPromotion)
    {
        $business = $request->user()->business;
        
        // Verify business owns this cross-promo
        if ($crossPromotion->business_1_id !== $business->id && $crossPromotion->business_2_id !== $business->id) {
            abort(403);
        }
        
        $partnerBusiness = $crossPromotion->getPartnerBusiness($business->id);
        $myPromotion = $crossPromotion->getMyPromotion($business->id);
        $partnerPromotion = $crossPromotion->getPartnerPromotion($business->id);
        
        return Inertia::render('Business/Partnerships/EditCrossPromo', [
            'crossPromo' => [
                'id' => $crossPromotion->id,
                'code' => $crossPromotion->code,
                'name' => $crossPromotion->name,
                'display_mode' => $crossPromotion->display_mode,
                'chain_mode' => $crossPromotion->chain_mode,
                'primary_promotion_id' => $crossPromotion->primary_promotion_id,
                'rules_status' => $crossPromotion->rules_status,
                'cross_promo_rules' => $crossPromotion->cross_promo_rules,
                'starts_at' => $crossPromotion->starts_at?->toISOString(),
                'expires_at' => $crossPromotion->expires_at?->toISOString(),
                'usage_limit' => $crossPromotion->usage_limit,
                'is_active' => $crossPromotion->is_active,
            ],
            'partner' => [
                'id' => $partnerBusiness->id,
                'name' => $partnerBusiness->name,
            ],
            'my_promotion' => $myPromotion ? [
                'id' => $myPromotion->id,
                'name' => $myPromotion->name,
            ] : null,
            'partner_promotion' => $partnerPromotion ? [
                'id' => $partnerPromotion->id,
                'name' => $partnerPromotion->name,
            ] : null,
            'displayModes' => CrossPromotion::displayModes(),
            'chainModes' => CrossPromotion::chainModes(),
        ]);
    }
    
    /**
     * Update cross-promo
     */
    public function updateCrossPromo(Request $request, CrossPromotion $crossPromotion)
    {
        $business = $request->user()->business;
        
        // Verify business owns this cross-promo
        if ($crossPromotion->business_1_id !== $business->id && $crossPromotion->business_2_id !== $business->id) {
            abort(403);
        }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'display_mode' => 'required|in:split,alternating,random',
            'chain_mode' => 'nullable|in:open,sequential',
            'expires_at' => 'nullable|date|after_or_equal:today',
            'starts_at' => 'nullable|date|before_or_equal:expires_at',
            'usage_limit' => 'nullable|integer|min:1',
        ]);
        
        $crossPromotion->update($validated);
        
        return back()->with('success', 'Partner Deal Chain updated successfully!');
    }
    
    /**
     * Pause cross-promo
     */
    public function pauseCrossPromo(Request $request, CrossPromotion $crossPromotion)
    {
        $business = $request->user()->business;
        
        if ($crossPromotion->business_1_id !== $business->id && $crossPromotion->business_2_id !== $business->id) {
            abort(403);
        }
        
        $crossPromotion->update(['is_active' => false]);
        
        return back()->with('success', 'Partner Deal Chain paused.');
    }
    
    /**
     * Resume cross-promo
     */
    public function resumeCrossPromo(Request $request, CrossPromotion $crossPromotion)
    {
        $business = $request->user()->business;
        
        if ($crossPromotion->business_1_id !== $business->id && $crossPromotion->business_2_id !== $business->id) {
            abort(403);
        }
        
        // Check if expired
        if ($crossPromotion->expires_at && $crossPromotion->expires_at->isPast()) {
            return back()->withErrors(['error' => 'Cannot resume expired cross-promo.']);
        }
        
        $crossPromotion->update(['is_active' => true]);
        
        return back()->with('success', 'Partner Deal Chain resumed.');
    }
    
    /**
     * Rules negotiation page
     */
    public function rulesNegotiation(Request $request, CrossPromotion $crossPromotion)
    {
        $business = $request->user()->business;
        
        if ($crossPromotion->business_1_id !== $business->id && $crossPromotion->business_2_id !== $business->id) {
            abort(403);
        }
        
        $partnerBusiness = $crossPromotion->getPartnerBusiness($business->id);
        $promo1 = $crossPromotion->promotion1;
        $promo2 = $crossPromotion->promotion2;
        
        // Detect conflicts
        $conflicts = [];
        if ($promo1 && $promo2) {
            $conflicts = $this->rulesService->detectConflicts($promo1, $promo2);
        }
        
        return Inertia::render('Business/Partnerships/RulesNegotiation', [
            'crossPromo' => [
                'id' => $crossPromotion->id,
                'name' => $crossPromotion->name,
                'rules_status' => $crossPromotion->rules_status,
                'cross_promo_rules' => $crossPromotion->cross_promo_rules,
            ],
            'partner' => [
                'id' => $partnerBusiness->id,
                'name' => $partnerBusiness->name,
            ],
            'promotion1' => $promo1 ? [
                'id' => $promo1->id,
                'name' => $promo1->name,
                'rules' => $promo1->rules,
                'rules_formatted' => $this->rulesService->formatRulesForDisplay($promo1->rules ?? []),
            ] : null,
            'promotion2' => $promo2 ? [
                'id' => $promo2->id,
                'name' => $promo2->name,
                'rules' => $promo2->rules,
                'rules_formatted' => $this->rulesService->formatRulesForDisplay($promo2->rules ?? []),
            ] : null,
            'conflicts' => $conflicts,
            'merged_rules' => $promo1 && $promo2 ? $this->rulesService->mergeRules($promo1, $promo2) : [],
        ]);
    }
    
    /**
     * Agree on rules
     */
    public function agreeRules(Request $request, CrossPromotion $crossPromotion)
    {
        $business = $request->user()->business;
        
        if ($crossPromotion->business_1_id !== $business->id && $crossPromotion->business_2_id !== $business->id) {
            abort(403);
        }
        
        $validated = $request->validate([
            'agreed_rules' => 'required|array',
        ]);
        
        $rulesErrors = $this->rulesService->validateRules($validated['agreed_rules']);
        if (!empty($rulesErrors)) {
            return back()->withErrors(['agreed_rules' => implode(', ', $rulesErrors)]);
        }
        
        $crossPromotion->update([
            'cross_promo_rules' => $validated['agreed_rules'],
            'rules_status' => CrossPromotion::RULES_AGREED,
        ]);
        
        return back()->with('success', 'Rules agreed! Cross-promo is now active.');
    }
    
    /**
     * Partner analytics
     */
    public function partnerAnalytics(Request $request, BusinessPartnership $partnership)
    {
        $business = $request->user()->business;
        
        if ($partnership->requester_business_id !== $business->id && $partnership->partner_business_id !== $business->id) {
            abort(403);
        }
        
        $partnerBusiness = $partnership->getOtherBusiness($business->id);
        
        // Get all cross-promos with this partner
        $crossPromos = CrossPromotion::forBusiness($business->id)
            ->where(function ($q) use ($partnerBusiness) {
                $q->where('business_1_id', $partnerBusiness->id)
                  ->orWhere('business_2_id', $partnerBusiness->id);
            })
            ->with(['promotion1', 'promotion2'])
            ->get();
        
        // Calculate metrics
        $totalScans = \App\Models\Scan::whereHas('qrCode', function ($q) use ($crossPromos) {
            $q->whereIn('cross_promotion_id', $crossPromos->pluck('id'));
        })->count();
        
        $totalClaims = \App\Models\UserPromoToken::whereIn('promotion_id', 
            $crossPromos->pluck('promotion_1_id')->merge($crossPromos->pluck('promotion_2_id'))->filter()
        )->count();
        
        return Inertia::render('Business/Partnerships/PartnerAnalytics', [
            'partnership' => [
                'id' => $partnership->id,
                'created_at' => $partnership->created_at->toISOString(),
            ],
            'partner' => [
                'id' => $partnerBusiness->id,
                'name' => $partnerBusiness->name,
                'logo' => $partnerBusiness->logo_url,
            ],
            'crossPromos' => $crossPromos->map(function ($cp) {
                return [
                    'id' => $cp->id,
                    'name' => $cp->name,
                    'status' => $cp->status,
                    'is_active' => $cp->is_active,
                ];
            }),
            'metrics' => [
                'total_cross_promos' => $crossPromos->count(),
                'active_cross_promos' => $crossPromos->where('is_active', true)->count(),
                'total_scans' => $totalScans,
                'total_claims' => $totalClaims,
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\QRCode;
use App\Models\Promotion;
use App\Models\Game;
use App\Models\QRCodeGame;
use App\Models\CrossPromotion;
use App\Models\MerchReferralReward;
use App\Models\MerchTag;
use App\Models\Leaderboard;
use App\Models\StackablePool;
use App\Models\SubscriptionPlan;
use App\Services\QRGeneratorService;
use App\Support\BusinessCardQr;
use App\Support\OnboardingQr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class QRCodeController extends Controller
{
    protected QRGeneratorService $qrService;

    public function __construct(QRGeneratorService $qrService)
    {
        $this->qrService = $qrService;
    }

    public function index(Request $request)
    {
        $business = $request->user()->business;

        // Handle case where user doesn't have a business yet
        if (!$business) {
            return Inertia::render('Business/QRCodes/Index', [
                'qrCodes' => ['data' => [], 'links' => [], 'last_page' => 1],
            ]);
        }

        $leaderboardPrizesOnly = $request->boolean('leaderboard_prizes');

        try {
            $qrCodesQuery = $business->qrCodes()
                ->with([
                    'promotion:id,name,discount_type',
                    'qrCodeGames:id,qr_code_id,game_id'
                ])
                ->whereNotIn('code', [OnboardingQr::CODE, BusinessCardQr::CODE])
                ->orderByDesc('created_at');

            if ($leaderboardPrizesOnly) {
                // Show ONLY leaderboard-prize QR codes
                $qrCodesQuery->where('intended_use', \App\Models\QRCode::INTENDED_USE_LEADERBOARD_PRIZE);
            } elseif (!$request->boolean('show_internal')) {
                // Hide internal/auto-generated codes (leaderboard_prize intended_use) by default to reduce clutter.
                $qrCodesQuery->where('intended_use', '!=', \App\Models\QRCode::INTENDED_USE_LEADERBOARD_PRIZE);
            }

            $qrCodes = $qrCodesQuery->paginate(12);
        } catch (\Exception $e) {
            \Log::error('Failed to load QR codes', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);
            $qrCodes = ['data' => [], 'links' => [], 'last_page' => 1];
        }

        return Inertia::render('Business/QRCodes/Index', [
            'qrCodes' => $qrCodes,
            'leaderboardPrizesOnly' => $leaderboardPrizesOnly,
        ]);
    }

    public function create(Request $request)
    {
        $business = $request->user()->business;

        $promotions = $business->promotions()
            ->where('is_active', true)
            ->get(['id', 'name', 'discount_type', 'discount_value']);

        // Get only games the business has enabled (cache briefly for faster form load)
        $availableGames = cache()->remember(
            'qr_create_available_games_' . $business->id,
            now()->addSeconds(15),
            function () use ($business) {
                $enabledGameIds = \App\Models\BusinessGame::where('business_id', $business->id)
                    ->where('is_enabled', true)
                    ->pluck('game_id');

                return Game::where('is_active', true)
                    ->whereIn('id', $enabledGameIds)
                    ->orderBy('tier')
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug', 'tier', 'category', 'thumbnail', 'description']);
            }
        );

        // Get accepted partners for cross-promotions (cache briefly for faster form load)
        $partners = cache()->remember(
            'qr_create_partners_' . $business->id,
            now()->addSeconds(15),
            function () use ($business) {
                return \App\Models\BusinessPartnership::forBusiness($business->id)
                    ->accepted()
                    ->with(['requesterBusiness:id,name,logo_path,type', 'partnerBusiness:id,name,logo_path,type'])
                    ->get()
                    ->map(function ($partnership) use ($business) {
                        $partner = $partnership->getOtherBusiness($business->id);
                        return [
                            'id' => $partner->id,
                            'name' => $partner->name,
                            'logo' => $partner->logo_url,
                            'category' => $partner->type ?? 'Business',
                        ];
                    });
            }
        );

        $acceptedCrossPromos = cache()->remember(
            'qr_create_cross_promos_' . $business->id,
            now()->addSeconds(15),
            function () use ($business) {
                return CrossPromotion::forBusiness($business->id)
                    ->active()
                    ->with(['business1:id,name,logo_path', 'business2:id,name,logo_path', 'promotion1:id,name,discount_type', 'promotion2:id,name,discount_type'])
                    ->orderByDesc('created_at')
                    ->get()
                    ->map(function ($promo) use ($business) {
                        $partnerBusiness = $promo->getPartnerBusiness($business->id);
                        $myPromotion = $promo->getMyPromotion($business->id);
                        $partnerPromotion = $promo->getPartnerPromotion($business->id);

                        return [
                            'id' => $promo->id,
                            'code' => $promo->code,
                            'name' => $promo->name,
                            'display_mode' => $promo->display_mode,
                            'rules_status' => $promo->rules_status,
                            'cross_promo_rules' => $promo->cross_promo_rules,
                            'expires_at' => $promo->expires_at?->toISOString(),
                            'starts_at' => $promo->starts_at?->toISOString(),
                            'usage_limit' => $promo->usage_limit,
                            'partner' => [
                                'id' => $partnerBusiness?->id,
                                'name' => $partnerBusiness?->name,
                                'logo' => $partnerBusiness?->logo_url,
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
            }
        );

        // Get active leaderboards for linking QRcade Leaderboard QR codes
        $leaderboards = Leaderboard::where('business_id', $business->id)
            ->where('is_active', true)
            ->with('game:id,name')
            ->get(['id', 'name', 'type', 'game_id', 'reset_frequency']);

        return Inertia::render('Business/QRCodes/Create', [
            'promotions' => $promotions,
            'availableGames' => $availableGames,
            'leaderboards' => $leaderboards,
            'partners' => $partners,
            'acceptedCrossPromos' => $acceptedCrossPromos,
            'placementOptions' => $this->getPlacementOptions(),
            'moduleShapes' => $this->getModuleShapes(),
            'finderShapes' => $this->getFinderShapes(),
            'fonts' => $this->getFonts(),
            'businessLogo' => $business->logo_url, // Pass logo for promotion QR codes
            'subscriptionTier' => $business->subscription_tier ?? 'starter',
        ]);
    }

    public function store(Request $request)
    {
        $business = $request->user()->business;

        if (!$business) {
            \Log::error('No business found for user creating QR code', [
                'user_id' => $request->user()->id
            ]);
            abort(403, 'Business account required');
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|in:static,dynamic,promotion,stackable,cross_promo,qrcade,qrcade_leaderboard,level_exclusive,merch_referral',
                'intended_use' => 'nullable|in:public,leaderboard_prize',
                'confirm_internal' => 'nullable|accepted_if:intended_use,leaderboard_prize',
                'destination_url' => [
                    'nullable',
                    'url',
                    'max:500',
                    Rule::requiredIf(fn () => in_array($request->input('type'), ['static', 'dynamic'], true)),
                ],
                'promotion_id' => [
                    'nullable',
                    Rule::exists('promotions', 'id')->where('business_id', $business->id),
                    Rule::requiredIf(fn () => in_array($request->input('type'), ['stackable', 'level_exclusive'], true)),
                ],
                'reward_type' => [
                    'nullable',
                    'string',
                    'in:percent,amount,free_item',
                    Rule::requiredIf(fn () => $request->input('type') === 'merch_referral'),
                ],
                'reward_value' => 'nullable|numeric|min:0',
                'reward_item_value' => 'nullable|numeric|min:0',
                'reward_description' => 'nullable|string|max:255',
                'redemptions_required' => [
                    'nullable',
                    'integer',
                    'min:1',
                    Rule::requiredIf(fn () => $request->input('type') === 'merch_referral'),
                ],
                'cross_promotion_id' => [
                    'nullable',
                    'integer',
                    'exists:cross_promotions,id',
                    Rule::requiredIf(fn () => $request->input('type') === 'cross_promo'),
                ],
                'required_level' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:50',
                    Rule::requiredIf(fn () => $request->input('type') === 'level_exclusive'),
                ],
                'placement_location' => 'nullable|string|max:100',
                'placement_description' => 'nullable|string|max:500',
                'design' => 'nullable|array',
                'game_ids' => [
                    'nullable',
                    'array',
                    Rule::requiredIf(fn () => in_array($request->input('type'), ['qrcade', 'qrcade_leaderboard'], true)),
                ],
                'game_ids.*' => 'exists:games,id',
                'leaderboard_id' => [
                    'nullable',
                    Rule::exists('leaderboards', 'id')->where('business_id', $business->id),
                    Rule::requiredIf(fn () => $request->input('type') === 'qrcade_leaderboard'),
                ],
                'add_tag' => 'nullable|boolean',
                'add_tag_quantity' => 'nullable|integer|min:1|max:50',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('QR Code validation failed', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            throw $e;
        }

        // Enforce plan limit for QR code creation (hard limit; -1 means unlimited)
        $qrLimit = $business->getLimit('qr_codes');
        if ($qrLimit !== -1) {
            $currentCount = $business->qrCodes()
                ->whereNotIn('code', [OnboardingQr::CODE, BusinessCardQr::CODE])
                ->count();
            if ($currentCount >= $qrLimit) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'limit' => "QR code limit reached ({$qrLimit}). Please upgrade your plan to create more QR codes.",
                    ]);
            }
        }

        // Validate stackable QR codes require Growth+ subscription
        if ($validated['type'] === 'stackable') {
            $tier = $business->subscription_tier ?? 'starter';
            if (!in_array($tier, ['growth', 'pro', 'enterprise'])) {
                return back()->withErrors([
                    'subscription' => 'Growth or higher subscription needed for Stackable QR Codes'
                ]);
            }
        }

        // Validate merch referral QR codes require Growth+ subscription
        if ($validated['type'] === 'merch_referral') {
            $tier = $business->subscription_tier ?? 'starter';
            $plan = SubscriptionPlan::where('slug', $tier)->first();
            $features = is_array($plan?->features) ? $plan->features : [];
            $enabled = (bool) ($features['merch_referral_qr'] ?? in_array($tier, ['growth', 'pro', 'enterprise'], true));
            if (!$enabled) {
                return back()->withErrors([
                    'subscription' => 'Growth or higher subscription needed for Merch Referral QR Codes'
                ]);
            }
        }

        // Leaderboard QR codes can only attach a single game
        if ($validated['type'] === 'qrcade_leaderboard') {
            $gameIds = $validated['game_ids'] ?? [];
            if (is_array($gameIds) && count($gameIds) > 1) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'game_ids' => 'Leaderboard QR codes can only have one game selected.',
                    ]);
            }
        }

        // Validate game_ids are enabled for this business
        if (!empty($validated['game_ids'])) {
            $enabledGameIds = \App\Models\BusinessGame::where('business_id', $business->id)
                ->where('is_enabled', true)
                ->pluck('game_id')
                ->toArray();
            $invalidGames = array_diff($validated['game_ids'], $enabledGameIds);
            if (!empty($invalidGames)) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'game_ids' => 'One or more selected games are not enabled for your business.',
                    ]);
            }
        }

        // Validate promotion belongs to business (exists rule already scopes to business,
        // but we still need to load the model for the punch_card check below)
        if (!empty($validated['promotion_id'])) {
            $promotion = Promotion::where('id', $validated['promotion_id'])
                ->where('business_id', $business->id)
                ->first();

            if (!$promotion) {
                return back()->withInput()->withErrors([
                    'promotion_id' => 'The selected promotion is invalid.',
                ]);
            }

            if ($validated['type'] === 'merch_referral' && ($promotion->discount_type ?? null) === Promotion::TYPE_PUNCH_CARD) {
                return back()->withErrors([
                    'promotion_id' => 'Punch cards cannot be used as the gateway promo for Merch Referral QR codes.',
                ]);
            }
        }

        if ($validated['type'] === 'cross_promo') {
            $crossPromo = CrossPromotion::query()
                ->active()
                ->where('id', $validated['cross_promotion_id'])
                ->where(function ($q) use ($business) {
                    $q->where('business_1_id', $business->id)
                        ->orWhere('business_2_id', $business->id);
                })
                ->first();

            if (!$crossPromo) {
                return back()->withInput()->withErrors([
                    'cross_promotion_id' => 'The selected partner deal is not available for your business.',
                ]);
            }
        }

        if (($validated['type'] ?? null) === 'stackable') {
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
            $validated['stackable_pool_id'] = $pool->id;
        }

        try {
            DB::beginTransaction();

            $merchTagCode = null;
            $merchTagUrl = null;
            $merchTags = [];

            // Remove non-model fields from validated data before creating QR code
            $gameIds = $validated['game_ids'] ?? [];
            $leaderboardId = $validated['leaderboard_id'] ?? null;
            unset($validated['game_ids']);
            unset($validated['leaderboard_id']);
            unset($validated['confirm_internal']);
            unset($validated['add_tag']);
            unset($validated['add_tag_quantity']);
            unset($validated['promotion_ids']);

            $rewardPayload = null;
            if (($validated['type'] ?? null) === 'merch_referral') {
                $rewardPayload = [
                    'reward_type' => $validated['reward_type'] ?? null,
                    'reward_value' => $validated['reward_value'] ?? null,
                    'reward_item_value' => $validated['reward_item_value'] ?? null,
                    'reward_description' => $validated['reward_description'] ?? null,
                    'redemptions_required' => $validated['redemptions_required'] ?? null,
                ];
            }
            unset($validated['reward_type'], $validated['reward_value'], $validated['reward_item_value'], $validated['reward_description'], $validated['redemptions_required']);

            if (empty($validated['intended_use'])) {
                $validated['intended_use'] = \App\Models\QRCode::INTENDED_USE_PUBLIC;
            }

            $design = $validated['design'] ?? [];
            if (!is_array($design)) {
                $design = [];
            }
            $setManualPrize = ($validated['type'] ?? null) === 'promotion'
                && ($validated['intended_use'] ?? null) === \App\Models\QRCode::INTENDED_USE_LEADERBOARD_PRIZE;
            $existingSource = $design['leaderboard_prize_source'] ?? null;
            if ($setManualPrize && $existingSource !== 'auto') {
                $design['leaderboard_prize_source'] = 'manual';
            } elseif (!$setManualPrize && $existingSource === 'manual') {
                unset($design['leaderboard_prize_source']);
            }
            if (array_key_exists('design', $validated) || $setManualPrize) {
                $validated['design'] = $design;
            }

            // Set is_level_exclusive flag based on type
            if ($validated['type'] === 'level_exclusive') {
                $validated['is_level_exclusive'] = true;
            } else {
                $validated['is_level_exclusive'] = false;
            }

            // Leaderboard-challenge QR codes are game-only: they should never have an instant-win promotion attached.
            if (($validated['type'] ?? null) === 'qrcade_leaderboard') {
                $validated['promotion_id'] = null;
            }

            if (($validated['type'] ?? null) === 'merch_referral') {
                if (empty($validated['promotion_id'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'promotion_id' => 'Select a gateway promotion for merch referrals.',
                    ]);
                }
                if (empty($rewardPayload['reward_type'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'reward_type' => 'Reward type is required.',
                    ]);
                }
                if (($rewardPayload['reward_type'] ?? null) !== 'free_item' && empty($rewardPayload['reward_value'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'reward_value' => 'Reward value is required unless type is free item.',
                    ]);
                }
                if (($rewardPayload['reward_type'] ?? null) === 'percent' && ($rewardPayload['reward_value'] ?? 0) > 100) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'reward_value' => 'Percentage cannot exceed 100%.',
                    ]);
                }
                if (($rewardPayload['reward_type'] ?? null) === 'free_item' && empty($rewardPayload['reward_description'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'reward_description' => 'Describe the free item reward.',
                    ]);
                }
            }

            $qrCode = $business->qrCodes()->create($validated);

            if (($validated['type'] ?? null) === 'merch_referral' && $rewardPayload) {
                $rewardPromotion = $this->createOrUpdateMerchReferralPromotion(
                    $business,
                    $qrCode,
                    $rewardPayload
                );

                $rewardDesign = is_array($qrCode->design) ? $qrCode->design : [];
                $rewardDesign['auto_created'] = true;
                $rewardDesign['leaderboard_prize_source'] = 'auto';

                $rewardQr = QRCode::create([
                    'business_id' => $business->id,
                    'name' => 'Ambassador Reward - ' . $qrCode->name,
                    'type' => 'promotion',
                    'promotion_id' => $rewardPromotion->id,
                    'intended_use' => QRCode::INTENDED_USE_LEADERBOARD_PRIZE,
                    'design' => $rewardDesign,
                    'is_active' => true,
                ]);

                $this->generateAndStoreQRImage($rewardQr);

                MerchReferralReward::create([
                    'business_id' => $business->id,
                    'qr_code_id' => $qrCode->id,
                    'reward_promotion_id' => $rewardPromotion->id,
                    'reward_qr_code_id' => $rewardQr->id,
                    'reward_type' => $rewardPayload['reward_type'],
                    'reward_value' => $rewardPayload['reward_value'],
                    'reward_item_value' => $rewardPayload['reward_item_value'],
                    'reward_description' => $rewardPayload['reward_description'],
                    'redemptions_required' => (int) $rewardPayload['redemptions_required'],
                    'is_active' => true,
                ]);

                if ($request->boolean('add_tag')) {
                    $count = (int) $request->input('add_tag_quantity', 1);
                    $count = max(1, min($count, 50));
                    for ($i = 0; $i < $count; $i++) {
                        $tag = MerchTag::create([
                            'code' => MerchTag::generateUniqueCode(),
                            'business_id' => $business->id,
                            'qr_code_id' => $qrCode->id,
                            'order_id' => null,
                            'order_item_id' => null,
                            'is_active' => true,
                        ]);
                        $design = $qrCode->getDesignWithDefaults();
                        $imagePath = $this->qrService->generateFile(
                            url('/m/' . $tag->code),
                            $design,
                            'png',
                            1200
                        );
                        $tag->update(['qr_image_path' => $imagePath]);
                        $merchTags[] = [
                            'code' => $tag->code,
                            'url' => url('/m/' . $tag->code),
                            'image_url' => url('storage/' . $imagePath),
                        ];
                    }
                    if (!empty($merchTags)) {
                        $merchTagCode = $merchTags[0]['code'];
                        $merchTagUrl = $merchTags[0]['url'];
                    }
                }
            }

            // Attach games to QR code
            if (!empty($gameIds)) {
                foreach ($gameIds as $gameId) {
                    QRCodeGame::create([
                        'qr_code_id' => $qrCode->id,
                        'game_id' => $gameId,
                        'business_id' => $business->id,
                        'leaderboard_id' => ($qrCode->type === 'qrcade_leaderboard') ? $leaderboardId : null,
                        'is_active' => true,
                        'promotion_id' => $validated['promotion_id'] ?? null,
                        // Leaderboard QR codes should run in leaderboard mode (no instant win prize on scan).
                        'win_mode' => ($qrCode->type === 'qrcade_leaderboard') ? \App\Models\QRCodeGame::WIN_MODE_LEADERBOARD : \App\Models\QRCodeGame::WIN_MODE_ALWAYS,
                    ]);
                }

                // Ensure legacy flag is set so /play/{code} doesn't redirect back to /promo/{code}.
                // (Some older code paths rely on game_enabled; treat any QR with games as enabled.)
                try {
                    $qrCode->forceFill(['game_enabled' => true])->saveQuietly();
                } catch (\Exception $e) {
                    // Column may not exist (legacy); silently skip
                    \Log::debug('game_enabled column unavailable, skipping', ['error' => $e->getMessage()]);
                }
            }

            // Generate and store QR code image
            $this->generateAndStoreQRImage($qrCode);

            // Auto-complete onboarding steps
            $business->completeOnboardingStep('create_qr_code');
            
            // If promotion is attached, complete that step too
            if ($qrCode->promotion_id) {
                $business->completeOnboardingStep('attach_promotion_to_qr');
            }

            DB::commit();

            $redirect = redirect()->route('business.qr-codes.create')
                ->with('success', 'QR code created successfully!');
            if ($merchTagCode !== null) {
                $redirect->with('merch_tag_code', $merchTagCode)->with('merch_tag_url', $merchTagUrl);
            }
            if (!empty($merchTags)) {
                $redirect->with('merch_tags', $merchTags);
            }
            return $redirect;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('QR Code creation failed', [
                'user_id' => $request->user()->id,
                'business_id' => $business->id,
                'validated_data' => $validated,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create QR code: ' . $e->getMessage());
        }
    }

    public function show(QRCode $qrCode)
    {
        $business = request()->user()->business;

        // Ensure the QR code belongs to the current user's business
        if ($qrCode->business_id !== $business->id) {
            abort(403, 'You do not have permission to view this QR code.');
        }

        $qrCode->load('promotion:id,name,discount_type,discount_value', 'merchReferralReward');

        // Get design with defaults
        $design = $qrCode->getDesignWithDefaults();

        // Generate preview
        $preview = $this->qrService->generate(
            $qrCode->getScanUrl(),
            $design
        );

        return Inertia::render('Business/QRCodes/Show', [
            'qrCode' => $qrCode,
            'preview' => $preview,
        ]);
    }

    public function edit(QRCode $qrCode)
    {
        $business = request()->user()->business;

        // Ensure the QR code belongs to the current user's business
        if ($qrCode->business_id !== $business->id) {
            abort(403, 'You do not have permission to edit this QR code.');
        }

        $business = $qrCode->business;

        $promotions = $business->promotions()
            ->where('is_active', true)
            ->get(['id', 'name', 'discount_type', 'discount_value']);

        return Inertia::render('Business/QRCodes/Edit', [
            'qrCode' => $qrCode,
            'promotions' => $promotions,
            'placementOptions' => $this->getPlacementOptions(),
            'moduleShapes' => $this->getModuleShapes(),
            'finderShapes' => $this->getFinderShapes(),
            'fonts' => $this->getFonts(),
            'businessLogo' => $business->logo_url, // Pass logo for promotion QR codes
        ]);
    }

    public function update(Request $request, QRCode $qrCode)
    {
        $business = request()->user()->business;

        // Ensure the QR code belongs to the current user's business
        if ($qrCode->business_id !== $business->id) {
            abort(403, 'You do not have permission to update this QR code.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // Type is immutable in the UI; allow it through for legacy clients but do not require it.
            // Include all supported types so editing/design updates never fail silently.
            'type' => 'sometimes|in:static,dynamic,promotion,stackable,cross_promo,qrcade,qrcade_leaderboard,level_exclusive,merch_referral',
            'intended_use' => 'nullable|in:public,leaderboard_prize',
            'confirm_internal' => 'nullable|accepted_if:intended_use,leaderboard_prize',
            'destination_url' => 'nullable|url|max:500',
            'promotion_id' => [
                'nullable',
                Rule::exists('promotions', 'id')->where('business_id', $business->id),
                Rule::requiredIf(fn () => in_array($qrCode->type ?? $request->input('type'), ['stackable', 'level_exclusive'], true)),
            ],
            'reward_type' => 'nullable|string|in:percent,amount,free_item',
            'reward_value' => 'nullable|numeric|min:0',
            'reward_item_value' => 'nullable|numeric|min:0',
            'reward_description' => 'nullable|string|max:255',
            'redemptions_required' => 'nullable|integer|min:1',
            'required_level' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
                Rule::requiredIf(fn () => $qrCode->type === 'level_exclusive'),
            ],
            'placement_location' => 'nullable|string|max:100',
            'placement_description' => 'nullable|string|max:500',
            'design' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        // Never allow changing the QR code type via update (it's immutable in the UI).
        unset($validated['type']);
        unset($validated['confirm_internal']);

        $rewardPayload = null;
        if ($qrCode->type === 'merch_referral') {
            $rewardPayload = [
                'reward_type' => $validated['reward_type'] ?? null,
                'reward_value' => $validated['reward_value'] ?? null,
                'reward_item_value' => $validated['reward_item_value'] ?? null,
                'reward_description' => $validated['reward_description'] ?? null,
                'redemptions_required' => $validated['redemptions_required'] ?? null,
            ];
        }
        unset($validated['reward_type'], $validated['reward_value'], $validated['reward_item_value'], $validated['reward_description'], $validated['redemptions_required']);

        if (empty($validated['intended_use'])) {
            $validated['intended_use'] = $qrCode->intended_use ?? \App\Models\QRCode::INTENDED_USE_PUBLIC;
        }

        $design = $validated['design'] ?? $qrCode->design ?? [];
        if (!is_array($design)) {
            $design = [];
        }
        $setManualPrize = $qrCode->type === 'promotion'
            && ($validated['intended_use'] ?? null) === \App\Models\QRCode::INTENDED_USE_LEADERBOARD_PRIZE;
        $existingSource = $design['leaderboard_prize_source'] ?? null;
        if ($setManualPrize && $existingSource !== 'auto') {
            $design['leaderboard_prize_source'] = 'manual';
        } elseif (!$setManualPrize && $existingSource === 'manual') {
            unset($design['leaderboard_prize_source']);
        }
        if (array_key_exists('design', $validated) || $setManualPrize || $existingSource === 'manual') {
            $validated['design'] = $design;
        }

        if ($qrCode->type === 'stackable') {
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
            $validated['stackable_pool_id'] = $pool->id;
        }

        // Ensure is_level_exclusive is set correctly if this is a level_exclusive QR code
        if ($qrCode->type === 'level_exclusive') {
            $validated['is_level_exclusive'] = true;
        }

        // Leaderboard-challenge QR codes are game-only: never allow attaching an instant-win promotion.
        if ($qrCode->type === 'qrcade_leaderboard') {
            $validated['promotion_id'] = null;
        }

        if ($qrCode->type === 'merch_referral') {
            if (empty($validated['promotion_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'promotion_id' => 'Select a gateway promotion for merch referrals.',
                ]);
            }
            $promotion = Promotion::where('id', $validated['promotion_id'])
                ->where('business_id', $business->id)
                ->firstOrFail();
            if (($promotion->discount_type ?? null) === Promotion::TYPE_PUNCH_CARD) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'promotion_id' => 'Punch cards cannot be used as the gateway promo for Merch Referral QR codes.',
                ]);
            }
            if (($rewardPayload['reward_type'] ?? null) !== 'free_item' && empty($rewardPayload['reward_value'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reward_value' => 'Reward value is required unless type is free item.',
                ]);
            }
            if (($rewardPayload['reward_type'] ?? null) === 'free_item' && empty($rewardPayload['reward_description'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reward_description' => 'Describe the free item reward.',
                ]);
            }
            if (empty($rewardPayload['redemptions_required'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'redemptions_required' => 'Redemptions required is required.',
                ]);
            }
        }

        $qrCode->update($validated);

        if ($qrCode->type === 'merch_referral' && $rewardPayload) {
            $rewardModel = $qrCode->merchReferralReward;
            $rewardPromotion = $this->createOrUpdateMerchReferralPromotion(
                $business,
                $qrCode,
                $rewardPayload,
                $rewardModel?->rewardPromotion
            );

            $rewardQr = $rewardModel?->rewardQrCode;
            $rewardDesign = is_array($qrCode->design) ? $qrCode->design : [];
            $rewardDesign['auto_created'] = true;
            $rewardDesign['leaderboard_prize_source'] = 'auto';

            if ($rewardQr) {
                $rewardQr->update([
                    'name' => 'Ambassador Reward - ' . $qrCode->name,
                    'promotion_id' => $rewardPromotion->id,
                    'design' => $rewardDesign,
                    'is_active' => true,
                ]);
                $this->generateAndStoreQRImage($rewardQr);
            } else {
                $rewardQr = QRCode::create([
                    'business_id' => $business->id,
                    'name' => 'Ambassador Reward - ' . $qrCode->name,
                    'type' => 'promotion',
                    'promotion_id' => $rewardPromotion->id,
                    'intended_use' => QRCode::INTENDED_USE_LEADERBOARD_PRIZE,
                    'design' => $rewardDesign,
                    'is_active' => true,
                ]);
                $this->generateAndStoreQRImage($rewardQr);
            }

            $qrCode->merchReferralReward()->updateOrCreate(
                ['qr_code_id' => $qrCode->id],
                [
                    'business_id' => $business->id,
                    'reward_promotion_id' => $rewardPromotion->id,
                    'reward_qr_code_id' => $rewardQr->id,
                    'reward_type' => $rewardPayload['reward_type'],
                    'reward_value' => $rewardPayload['reward_value'],
                    'reward_item_value' => $rewardPayload['reward_item_value'],
                    'reward_description' => $rewardPayload['reward_description'],
                    'redemptions_required' => (int) $rewardPayload['redemptions_required'],
                    'is_active' => true,
                ]
            );
        }
        
        // Auto-complete onboarding step if promotion is attached
        if (!empty($validated['promotion_id'])) {
            $business->completeOnboardingStep('attach_promotion_to_qr');
        }

        // Regenerate QR code image if design changed
        if ($request->has('design')) {
            $this->generateAndStoreQRImage($qrCode);
        }

        return redirect()->route('business.qr-codes.show', $qrCode)
            ->with('success', 'QR code updated successfully!');
    }

    public function checkDelete(QRCode $qrCode)
    {
        $business = request()->user()->business;
        abort_unless($qrCode->business_id === $business->id, 403);

        return response()->json([
            'warnings' => $this->getQrCodeDependencies($qrCode),
        ]);
    }

    public function destroy(QRCode $qrCode)
    {
        $business = request()->user()->business;

        if ($qrCode->business_id !== $business->id) {
            abort(403, 'You do not have permission to delete this QR code.');
        }

        $qrCode->delete();

        return redirect()->route('business.qr-codes.index')
            ->with('success', 'QR code deleted successfully!');
    }

    protected function getQrCodeDependencies(QRCode $qrCode): array
    {
        $warnings = [];

        $scanCount = \App\Models\Scan::where('qr_code_id', $qrCode->id)->count();
        if ($scanCount > 0) {
            $warnings[] = "{$scanCount} scan record(s) will be permanently deleted.";
        }

        $tokenCount = \App\Models\UserPromoToken::where('qr_code_id', $qrCode->id)->whereNull('redeemed_at')->count();
        if ($tokenCount > 0) {
            $warnings[] = "{$tokenCount} active user voucher(s) will be permanently deleted.";
        }

        $gameCount = QRCodeGame::where('qr_code_id', $qrCode->id)->count();
        if ($gameCount > 0) {
            $warnings[] = "{$gameCount} game configuration(s) linked to this QR will be deleted.";
        }

        $leaderboardCount = Leaderboard::where('qr_code_id', $qrCode->id)->count();
        if ($leaderboardCount > 0) {
            $warnings[] = "{$leaderboardCount} leaderboard(s) reference this QR as a prize.";
        }

        $merchTagCount = MerchTag::where('qr_code_id', $qrCode->id)->count();
        if ($merchTagCount > 0) {
            $warnings[] = "{$merchTagCount} merch tag(s) linked to this QR will be deleted.";
        }

        $savedCount = \App\Models\SavedQRCode::where('qr_code_id', $qrCode->id)->count();
        if ($savedCount > 0) {
            $warnings[] = "{$savedCount} customer(s) have this QR saved to their wallet.";
        }

        if ($qrCode->promotion) {
            $warnings[] = "Linked promotion: \"{$qrCode->promotion->name}\". The promotion itself will not be deleted, but its link to this QR will be removed.";
        }

        return $warnings;
    }

    public function duplicate(QRCode $qrCode)
    {
        $business = request()->user()->business;

        // Ensure the QR code belongs to the current user's business
        if ($qrCode->business_id !== $business->id) {
            abort(403, 'You do not have permission to duplicate this QR code.');
        }

        $newQRCode = $qrCode->replicate();
        $newQRCode->name = $qrCode->name . ' (Copy)';
        $newQRCode->code = QRCode::generateUniqueCode();
        $newQRCode->total_scans = 0;
        $newQRCode->unique_scans = 0;
        $newQRCode->last_scanned_at = null;
        $newQRCode->save();

        $this->generateAndStoreQRImage($newQRCode);

        return redirect()->route('business.qr-codes.show', $newQRCode)
            ->with('success', 'QR code duplicated successfully!');
    }

    public function download(QRCode $qrCode, string $format = 'png')
    {
        $business = request()->user()->business;

        // Ensure the QR code belongs to the current user's business
        if ($qrCode->business_id !== $business->id) {
            abort(403, 'You do not have permission to download this QR code.');
        }

        $validFormats = ['png', 'svg', 'pdf'];
        if (!in_array($format, $validFormats)) {
            $format = 'png';
        }

        $path = $this->qrService->generateFile(
            $qrCode->getScanUrl(),
            $qrCode->getDesignWithDefaults(),
            $format
        );

        $filename = str_replace(' ', '_', $qrCode->name) . '.' . $format;

        $business->completeOnboardingStep('download_qr_code');

        return Storage::disk('public')->download($path, $filename);
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'data' => 'required|string|max:500',
            'design' => 'nullable|array',
        ]);

        try {
            // Return raw PNG binary for faster transfer (no base64 overhead)
            $png = $this->qrService->generateBinary(
                $validated['data'],
                $validated['design'] ?? []
            );

            return response($png, 200, [
                'Content-Type' => 'image/png',
                'Content-Length' => strlen($png),
                'Cache-Control' => 'no-store',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'preview' => null,
                'error' => 'Failed to generate preview: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function generateAndStoreQRImage(QRCode $qrCode): void
    {
        try {
            // Get design with defaults
            $design = $qrCode->getDesignWithDefaults();
            
            $path = $this->qrService->generateFile(
                $qrCode->getScanUrl(),
                $design
            );

            // Store path in design JSON
            $savedDesign = $qrCode->design ?? [];
            $savedDesign['generated_path'] = $path;
            
            // Use updateQuietly to avoid triggering events that might interfere
            $qrCode->updateQuietly(['design' => $savedDesign]);
            
            // Refresh to ensure we have the latest data
            $qrCode->refresh();
        } catch (\Exception $e) {
            \Log::error('Failed to generate and store QR code image', [
                'qr_code_id' => $qrCode->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function createOrUpdateMerchReferralPromotion($business, QRCode $qrCode, array $rewardPayload, ?Promotion $existing = null): Promotion
    {
        $rewardType = $rewardPayload['reward_type'] ?? null;
        $rewardValue = $rewardPayload['reward_value'] ?? null;
        $rewardItemValue = $rewardPayload['reward_item_value'] ?? null;
        $rewardDescription = $rewardPayload['reward_description'] ?? null;

        $discountType = Promotion::TYPE_FIXED_AMOUNT;
        $discountValue = null;

        if ($rewardType === 'percent') {
            $discountType = Promotion::TYPE_PERCENTAGE;
            $discountValue = $rewardValue;
        } elseif ($rewardType === 'amount') {
            $discountType = Promotion::TYPE_FIXED_AMOUNT;
            $discountValue = $rewardValue;
        } elseif ($rewardType === 'free_item') {
            // Represent free item as 100% off; redemption amount can be entered at redemption time.
            $discountType = Promotion::TYPE_PERCENTAGE;
            $discountValue = 100;
        }

        $payload = [
            'business_id' => $business->id,
            'name' => 'Ambassador Reward - ' . $qrCode->name,
            'description' => $rewardDescription,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'original_price' => $rewardItemValue,
            'minimum_purchase' => null,
            'maximum_discount' => null,
            'rules' => [
                'max_redemptions_per_user' => 1,
            ],
            'is_active' => true,
        ];

        if ($existing) {
            $existing->update($payload);
            return $existing;
        }

        return Promotion::create($payload);
    }

    protected function getPlacementOptions(): array
    {
        return [
            'front_desk' => 'Front Desk / Counter',
            'window' => 'Store Window',
            'table' => 'Table Tent',
            'menu' => 'Menu',
            'receipt' => 'Receipt',
            'business_card' => 'Business Card',
            'flyer' => 'Flyer / Poster',
            'product' => 'Product Packaging',
            'merch' => 'Merchandise',
            'social' => 'Social Media',
            'email' => 'Email Signature',
            'website' => 'Website',
            'other' => 'Other',
        ];
    }

    protected function getModuleShapes(): array
    {
        return [
            'square' => 'Square',
            'rounded' => 'Rounded',
            'dots' => 'Dots',
            'diamond' => 'Diamond',
            'star' => 'Star',
            'heart' => 'Heart',
        ];
    }

    protected function getFinderShapes(): array
    {
        return [
            'square' => 'Square',
            'rounded' => 'Rounded',
            'circle' => 'Circle',
            'leaf' => 'Leaf',
        ];
    }

    protected function getFonts(): array
    {
        return [
            // Clean / modern
            'Inter' => 'Inter (Clean Sans)',
            'Poppins' => 'Poppins (Rounded Sans)',
            // Serif
            'Playfair Display' => 'Playfair Display (Serif)',
            'Merriweather' => 'Merriweather (Serif)',
            // Condensed / headline
            'Oswald' => 'Oswald (Condensed)',
            'Bebas Neue' => 'Bebas Neue (Caps)',
            // Script
            'Pacifico' => 'Pacifico (Script)',
            'Dancing Script' => 'Dancing Script (Script)',
            // Playful / handwritten
            'Permanent Marker' => 'Permanent Marker (Marker)',
            'Indie Flower' => 'Indie Flower (Handwritten)',
            'Amatic SC' => 'Amatic SC (Tall Handwritten)',
            'Fredoka One' => 'Fredoka One (Rounded Playful)',
        ];
    }
}


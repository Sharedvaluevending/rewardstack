<?php

namespace App\Http\Controllers;

use App\Models\QRCode;
use App\Models\QRCodeGame;
use App\Models\Scan;
use App\Models\Promotion;
use App\Models\StackablePool;
use App\Models\CrossPromotion;
use App\Models\Leaderboard;
use App\Services\BusinessCustomerService;
use App\Services\ScanService;
use App\Services\PromoClaimService;
use App\Support\BusinessCardQr;
use App\Support\OnboardingQr;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Jenssegers\Agent\Agent;

class ScanController extends Controller
{
    public function __construct(
        protected PromoClaimService $promoClaimService,
        protected ScanService $scanService,
        protected BusinessCustomerService $businessCustomerService
    ) {}

    /**
     * Handle a QR code scan
     */
    public function scan(Request $request, string $code)
    {
        // Special-case legacy/marketing links that should land on the homepage.
        // If the code ever gets created as a real QRCode in the DB, the normal scan flow will take over.
        $legacyCodes = config('scan.legacy_home_redirect_codes', ['AaaAVH8b']);
        $codeNormalized = strtolower(trim($code));
        $isLegacyCode = !empty($legacyCodes) && in_array($codeNormalized, array_map('strtolower', $legacyCodes), true);

        if ($isLegacyCode) {
            $exists = QRCode::query()
                ->whereRaw('LOWER(TRIM(code)) = ?', [$codeNormalized])
                ->exists();

            if (!$exists) {
                return redirect()->route('home');
            }
        }

        // Load QR code with game relationship (case-insensitive lookup)
        $qrCode = QRCode::byCode($code)
            ->with([
                'qrCodeGames.game',
                'crossPromotion.business1:id,name,logo_path,primary_color',
                'crossPromotion.business2:id,name,logo_path,primary_color',
                'crossPromotion.promotion1:id,name,description,terms,discount_type,discount_value,business_id,starts_at,ends_at',
                'crossPromotion.promotion2:id,name,description,terms,discount_type,discount_value,business_id,starts_at,ends_at',
            ])
            ->first();

        if (!$qrCode) {
            return Inertia::render('Public/ScanError', [
                'message' => 'QR code not found',
            ]);
        }

        if (!$qrCode->isValid()) {
            return Inertia::render('Public/ScanError', [
                'message' => $qrCode->isExpired() ? 'This QR code has expired' : 'This QR code is no longer active',
            ]);
        }

        // Check if the owning business is still active (not soft-deleted or deactivated).
        // This prevents showing promotion details for defunct businesses.
        $qrCode->loadMissing('business');
        if (!$qrCode->business || $qrCode->business->trashed() || !$qrCode->business->is_active) {
            return Inertia::render('Public/ScanError', [
                'message' => 'This business is no longer active.',
            ]);
        }

        // Track onboarding flyer scans for conversion analytics.
        if ($qrCode->code === OnboardingQr::CODE) {
            $request->session()->put('onboarding_qr_code', OnboardingQr::CODE);
            $request->session()->put('onboarding_qr_scanned_at', now()->toIsoString());
        }

        // Track business card QR scans.
        if ($qrCode->code === BusinessCardQr::CODE) {
            $request->session()->put('business_card_qr_code', BusinessCardQr::CODE);
            $request->session()->put('business_card_qr_scanned_at', now()->toIsoString());
        }

        // Internal prize/config QR codes should never be scanned by customers.
        // This prevents accidental "early prize redemption" when a promo QR is intended only as a leaderboard prize.
        $staffAuth = $this->getStaffAuthorization($request, $qrCode->business_id);
        if (($qrCode->intended_use ?? \App\Models\QRCode::INTENDED_USE_PUBLIC) === \App\Models\QRCode::INTENDED_USE_LEADERBOARD_PRIZE) {
            if (!$staffAuth) {
                return Inertia::render('Public/ScanError', [
                    'message' => 'This QR code is an internal leaderboard prize and should not be scanned. Ask the business for the game QR code instead.',
                ]);
            }
        }

        $levelGateMessage = null;

        // For leaderboard QR codes, check if the challenge is permanently over
        // (promotion expired or non-resetting leaderboard with period ended).
        if ($qrCode->type === 'qrcade_leaderboard') {
            $leaderboardEnded = false;
            $endedMessage = 'This leaderboard challenge has ended.';

            // Find leaderboards linked to this QR code's business/games
            $qrCodeGame = QRCodeGame::where('qr_code_id', $qrCode->id)
                ->where('is_active', true)
                ->first();

            $lb = null;
            if ($qrCodeGame && $qrCodeGame->leaderboard_id) {
                $lb = Leaderboard::find($qrCodeGame->leaderboard_id);
            }
            if (!$lb) {
                $lb = Leaderboard::active()
                    ->where('business_id', $qrCode->business_id)
                    ->where(function ($q) use ($qrCodeGame) {
                        $q->where('type', Leaderboard::TYPE_LOCATION);
                        if ($qrCodeGame && $qrCodeGame->game_id) {
                            $q->orWhere(function ($sq) use ($qrCodeGame) {
                                $sq->where('type', Leaderboard::TYPE_GAME_SPECIFIC)
                                    ->where('game_id', $qrCodeGame->game_id);
                            });
                        }
                    })
                    ->first();
            }

            if ($lb) {
                // Check if the attached promotion has expired
                $lb->loadMissing('promotion');
                $promo = $lb->promotion;
                if ($promo && $promo->ends_at && $promo->ends_at->isPast()) {
                    $leaderboardEnded = true;
                    $endedMessage = 'This leaderboard challenge has ended.';
                }

                // For non-resetting leaderboards, check if the period is over
                if (!$leaderboardEnded && $lb->reset_frequency === Leaderboard::RESET_NEVER
                    && $lb->current_period_end && $lb->current_period_end->isPast()) {
                    $leaderboardEnded = true;
                }

                // Check if the leaderboard itself has been deactivated
                if (!$leaderboardEnded && !$lb->is_active) {
                    $leaderboardEnded = true;
                }
            }

            if ($leaderboardEnded) {
                return Inertia::render('Public/ScanError', [
                    'message' => $endedMessage,
                ]);
            }
        }

        // For regular QRcade (play-to-win), check if the prize promotion has expired.
        // If so, block the scan — the game's purpose was to win a prize that no longer exists.
        if ($qrCode->type === 'qrcade') {
            $qrCodeGames = QRCodeGame::where('qr_code_id', $qrCode->id)
                ->where('is_active', true)
                ->get();

            // Only block if every active game config has an expired prize
            $allExpired = $qrCodeGames->isNotEmpty() && $qrCodeGames->every(function ($qcg) {
                // Games with no promotion attached are "for fun" — don't block them
                if (!$qcg->promotion_id && $qcg->win_mode !== QRCodeGame::WIN_MODE_TIERED) {
                    return false;
                }
                return $qcg->isPrizeExpired();
            });

            if ($allExpired) {
                return Inertia::render('Public/ScanError', [
                    'message' => 'This game\'s prize promotion has ended.',
                ]);
            }
        }

        // Check if QR code has an active game attached - games take priority
        try {
            $activeGame = $qrCode->getActiveGame();
            if ($activeGame) {
                // Ensure game relationship is loaded
                if (!$activeGame->relationLoaded('game')) {
                    $activeGame->load('game');
                }
                
                if ($activeGame->game && $activeGame->game->is_active && $activeGame->isAvailableNow()) {
                    // Fun-only mode for games (no scan/plays tracked, no rewards) if user has reached reward limits.
                    $user = $request->user();
                    if ($user && method_exists($user, 'isCustomer') && $user->isCustomer()) {
                        $gameService = app(\App\Services\GameService::class);
                        $fun = $gameService->getFunOnlyStatus($qrCode, $activeGame->game, $user);
                        if (($fun['fun_only'] ?? false)) {
                            // Still record the scan event (user history + business analytics),
                            // but the play itself will be fun-only (no XP/badges/leaderboards).
                            $this->recordScan(
                                $request,
                                $qrCode,
                                ($qrCode->type === 'qrcade_leaderboard') ? Scan::TYPE_QRCADE_LEADERBOARD : Scan::TYPE_QRCADE_GAME
                            );
                            return $this->handleGameScan($request, $qrCode, $activeGame);
                        }
                    }

                    // Eligible: record scan and continue into game
                    $this->recordScan(
                        $request,
                        $qrCode,
                        ($qrCode->type === 'qrcade_leaderboard') ? Scan::TYPE_QRCADE_LEADERBOARD : Scan::TYPE_QRCADE_GAME
                    );
                    return $this->handleGameScan($request, $qrCode, $activeGame);
                } else {
                    \Log::info('Game scan skipped', [
                        'qr_code_id' => $qrCode->id,
                        'qr_code_game_id' => $activeGame->id,
                        'game_exists' => $activeGame->game ? true : false,
                        'game_active' => $activeGame->game ? $activeGame->game->is_active : false,
                        'available_now' => $activeGame->isAvailableNow(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error checking for game in QR scan', [
                'qr_code_id' => $qrCode->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // If this is a promotion and the logged-in customer has already reached their per-user limit,
        // record the scan as "blocked" for demand insight (excluded from main scan counts by type),
        // then redirect to the promo page which will show the limit-reached message.
        $user = $request->user();
        if ($user && method_exists($user, 'isCustomer') && $user->isCustomer()) {
            if (in_array($qrCode->type, ['promotion', 'level_exclusive', 'merch_referral'], true)) {
                $qrCode->loadMissing('promotion');
                $promo = $qrCode->promotion;
                if ($promo) {
                    $canRedeem = $promo->canRedeem(null, $user->id, $this->getMerchReferralRedeemOptions($qrCode));
                    if (!($canRedeem['allowed'] ?? false) && ($canRedeem['limit_reached'] ?? false)) {
                        $this->recordScan($request, $qrCode, Scan::TYPE_BLOCKED);
                        return redirect()->route('promotion.show', $qrCode->code);
                    }
                }
            }
        }

        // Redirect based on QR type
        switch ($qrCode->type) {
            case 'static':
            case 'dynamic':
                // Track as non-redeemable "info" scan (does not count toward redemption performance).
                $this->recordScan($request, $qrCode, Scan::TYPE_INFO);
                if (empty($qrCode->destination_url)) {
                    return Inertia::render('Public/ScanError', [
                        'message' => 'This QR code does not have a destination URL configured.',
                    ]);
                }
                return redirect()->away($qrCode->destination_url);
            
            case 'promotion':
            case 'level_exclusive':
            case 'merch_referral':
                // Track punch cards distinctly so businesses can see stamp/completion funnels separately.
                $qrCode->loadMissing('promotion');
                $isPunchCard = (($qrCode->promotion?->discount_type ?? null) === Promotion::TYPE_PUNCH_CARD);
                $this->recordScan($request, $qrCode, $isPunchCard ? Scan::TYPE_PUNCH_CARD : Scan::TYPE_PROMOTION);
                return redirect()->route('promotion.show', $qrCode->code);
            
            case 'stackable':
                $this->recordScan($request, $qrCode, Scan::TYPE_STACKABLE);
                return $this->handleStackableScan($request, $qrCode);

            case 'cross_promo':
                $this->recordScan($request, $qrCode, Scan::TYPE_CROSS_PROMO);
                return $this->handleCrossPromoScan($request, $qrCode);
            
            default:
                // Fallback behavior: prefer games if available, otherwise treat as destination/info.
                // Use getActiveGame() as single source of truth (avoids hasActiveGames + getActiveGame redundancy)
                $game = $qrCode->getActiveGame();
                if ($game) {
                    // Ensure game relationship is loaded
                    if (!$game->relationLoaded('game')) {
                        $game->load('game');
                    }

                    if ($game->game && $game->game->is_active) {
                        $this->recordScan(
                            $request,
                            $qrCode,
                            ($qrCode->type === 'qrcade_leaderboard') ? Scan::TYPE_QRCADE_LEADERBOARD : Scan::TYPE_QRCADE_GAME
                        );
                        return $this->handleGameScan($request, $qrCode, $game);
                    }
                }

                $this->recordScan($request, $qrCode, Scan::TYPE_INFO);
                if (empty($qrCode->destination_url)) {
                    return Inertia::render('Public/ScanError', [
                        'message' => 'This QR code does not have a destination URL configured.',
                    ]);
                }
                return redirect()->away($qrCode->destination_url);
        }
    }

    protected function handleCrossPromoScan(Request $request, QRCode $qrCode)
    {
        $crossPromo = $qrCode->crossPromotion()->withTrashed()->first();
        if (!$crossPromo) {
            return Inertia::render('Public/ScanError', [
                'message' => 'This partner deal is not configured for this QR code (it may have been removed).',
            ]);
        }

        if ($crossPromo->trashed()) {
            return Inertia::render('Public/ScanError', [
                'message' => 'This partner deal is no longer available.',
            ]);
        }

        // Ensure related promos/businesses include soft-deleted rows for stability
        $crossPromo->load([
            'promotion1' => fn($q) => $q->withTrashed(),
            'promotion2' => fn($q) => $q->withTrashed(),
            'business1',
            'business2',
        ]);

        // Only allow accepted + active cross-promos
        if (($crossPromo->status ?? null) !== CrossPromotion::STATUS_ACCEPTED || !$crossPromo->is_active) {
            return Inertia::render('Public/ScanError', [
                'message' => 'This cross-promotion is not active yet.',
            ]);
        }

        if (!$crossPromo->promotion1 || !$crossPromo->promotion2) {
            return Inertia::render('Public/ScanError', [
                'message' => 'Cross-promotion offers are not fully set up yet.',
            ]);
        }

        // Check if either partner promotion has been soft-deleted
        if ($crossPromo->promotion1->trashed() || $crossPromo->promotion2->trashed()) {
            return Inertia::render('Public/ScanError', [
                'message' => 'One of the offers in this partner deal is no longer available.',
            ]);
        }

        $b1 = $crossPromo->business1;
        $b2 = $crossPromo->business2;

        // Check if either partner business is inactive or deleted
        if (!$b1 || !$b1->is_active || !$b2 || !$b2->is_active) {
            return Inertia::render('Public/ScanError', [
                'message' => 'One of the businesses in this partner deal is no longer active.',
            ]);
        }

        // Check if cross-promo is valid (not expired, started, active)
        if (!$crossPromo->isValid()) {
            return Inertia::render('Public/ScanError', [
                'message' => 'This cross-promotion is not currently active.',
            ]);
        }

        // Check if both underlying promotions have expired
        $promo1Expired = $crossPromo->promotion1->ends_at && $crossPromo->promotion1->ends_at->isPast();
        $promo2Expired = $crossPromo->promotion2->ends_at && $crossPromo->promotion2->ends_at->isPast();
        if ($promo1Expired && $promo2Expired) {
            return Inertia::render('Public/ScanError', [
                'message' => 'Both offers in this partner deal have expired.',
            ]);
        }
        
        // Check usage limit
        if ($crossPromo->hasReachedUsageLimit()) {
            return Inertia::render('Public/ScanError', [
                'message' => 'This cross-promotion has reached its usage limit.',
            ]);
        }
        
        // Auto-deactivate if expired
        $crossPromo->checkAndDeactivateIfExpired();
        $crossPromo->refresh();

        // Chain Logic
        $user = $request->user();
        $isPromo1Locked = false;
        $isPromo2Locked = false;
        $primaryId = $crossPromo->primary_promotion_id ?: $crossPromo->promotion_1_id;

        if ($crossPromo->chain_mode === 'sequential') {
            // Determine primary and secondary
            $isPromo1Primary = ($primaryId === $crossPromo->promotion_1_id);
            
            // Check if primary is redeemable (not expired, not at limit, valid time)
            $primaryRedeemable = $crossPromo->isPrimaryRedeemable($user?->id);
            
            // Check if primary is already redeemed by user
            $primaryRedeemed = false;
            if ($user) {
                $primaryRedeemed = \App\Models\UserPromoToken::where('user_id', $user->id)
                    ->where('promotion_id', $primaryId)
                    ->whereNotNull('redeemed_at')
                    ->exists();
            }
            
            // If primary is not redeemable (expired, at limit, invalid time), unlock secondary
            // This prevents secondary from being locked forever
            $shouldUnlockSecondary = !$primaryRedeemable;
            
            // Lock the secondary if primary not redeemed AND primary is still redeemable
            if ($isPromo1Primary) {
                $isPromo2Locked = !$primaryRedeemed && !$shouldUnlockSecondary;
            } else {
                $isPromo1Locked = !$primaryRedeemed && !$shouldUnlockSecondary;
            }
        }

        // Get chain progress for sequential chains
        $chainProgress = null;
        if ($crossPromo->chain_mode === 'sequential' && $user) {
            $primaryRedeemed = \App\Models\UserPromoToken::where('user_id', $user->id)
                ->where('promotion_id', $primaryId)
                ->whereNotNull('redeemed_at')
                ->exists();
            
            $secondaryId = ($primaryId === $crossPromo->promotion_1_id) 
                ? $crossPromo->promotion_2_id 
                : $crossPromo->promotion_1_id;
            
            $secondaryRedeemed = \App\Models\UserPromoToken::where('user_id', $user->id)
                ->where('promotion_id', $secondaryId)
                ->whereNotNull('redeemed_at')
                ->exists();
            
            $chainProgress = [
                'step' => $primaryRedeemed ? ($secondaryRedeemed ? 2 : 1) : 0,
                'total_steps' => 2,
                'primary_redeemed' => $primaryRedeemed,
                'secondary_redeemed' => $secondaryRedeemed,
            ];
        }

        // Per-user tokens (reuse existing active token)
        $tokenService = app(\App\Services\UserPromoTokenService::class);
        $token1 = $user ? $tokenService->createForCrossPromo($user, $qrCode, $crossPromo->promotion1) : null;
        $token2 = $user ? $tokenService->createForCrossPromo($user, $qrCode, $crossPromo->promotion2) : null;

        // Availability statuses (per promo)
        $availability1 = $crossPromo->promotion1
            ? $crossPromo->promotion1->canRedeem(null, $user?->id)
            : ['allowed' => false, 'reason' => 'Promotion not found'];
        $availability2 = $crossPromo->promotion2
            ? $crossPromo->promotion2->canRedeem(null, $user?->id)
            : ['allowed' => false, 'reason' => 'Promotion not found'];

        // Subscription state for both businesses
        $subscription1 = null;
        $subscription2 = null;
        if ($user && $b1) {
            $subscription1 = \App\Models\BusinessCustomerSubscription::where('business_id', $b1->id)
                ->where('user_id', $user->id)
                ->whereNotNull('subscribed_at')
                ->whereNull('unsubscribed_at')
                ->first();
        }
        if ($user && $b2) {
            $subscription2 = \App\Models\BusinessCustomerSubscription::where('business_id', $b2->id)
                ->where('user_id', $user->id)
                ->whereNotNull('subscribed_at')
                ->whereNull('unsubscribed_at')
                ->first();
        }

        return Inertia::render('Public/CrossPromo', [
            'qrCode' => $qrCode->only(['id', 'code', 'name']),
            'crossPromo' => [
                'id' => $crossPromo->id,
                'code' => $crossPromo->code,
                'name' => $crossPromo->name,
                'display_mode' => $crossPromo->display_mode,
                'chain_mode' => $crossPromo->chain_mode,
                'primary_promotion_id' => $primaryId,
                'chain_progress' => $chainProgress,
            ],
            'business1' => [
                'id' => $b1?->id,
                'name' => $b1?->name,
                'logo_url' => $b1?->logo_url,
                'primary_color' => $b1?->primary_color ?: '#7C3AED',
            ],
            'promotion1' => [
                'id' => $crossPromo->promotion1->id,
                'name' => $crossPromo->promotion1->name,
                'description' => $crossPromo->promotion1->description,
                'terms' => $crossPromo->promotion1->terms,
                'discount_type' => $crossPromo->promotion1->discount_type,
                'display_value' => $crossPromo->promotion1->getDisplayDescription(),
                'starts_at' => $crossPromo->promotion1->starts_at?->format('M d, Y'),
                'ends_at' => $crossPromo->promotion1->ends_at?->format('M d, Y'),
                'is_locked' => $isPromo1Locked,
            ],
            'business2' => [
                'id' => $b2?->id,
                'name' => $b2?->name,
                'logo_url' => $b2?->logo_url,
                'primary_color' => $b2?->primary_color ?: '#0EA5E9',
            ],
            'promotion2' => [
                'id' => $crossPromo->promotion2->id,
                'name' => $crossPromo->promotion2->name,
                'description' => $crossPromo->promotion2->description,
                'terms' => $crossPromo->promotion2->terms,
                'discount_type' => $crossPromo->promotion2->discount_type,
                'display_value' => $crossPromo->promotion2->getDisplayDescription(),
                'starts_at' => $crossPromo->promotion2->starts_at?->format('M d, Y'),
                'ends_at' => $crossPromo->promotion2->ends_at?->format('M d, Y'),
                'is_locked' => $isPromo2Locked,
            ],
            'token1' => $token1 ? [
                'code' => $token1->code,
                'qr_image_url' => $token1->qr_image_path ? asset('storage/' . $token1->qr_image_path) : null,
            ] : null,
            'token2' => $token2 ? [
                'code' => $token2->code,
                'qr_image_url' => $token2->qr_image_path ? asset('storage/' . $token2->qr_image_path) : null,
            ] : null,
            'subscription1' => $subscription1 ? ['is_subscribed' => true] : ['is_subscribed' => false],
            'subscription2' => $subscription2 ? ['is_subscribed' => true] : ['is_subscribed' => false],
            'staffAuth' => $this->getStaffAuthorization($request, $qrCode->business_id),
            'availability1' => [
                'status' => ($availability1['allowed'] ?? false) ? 'active' : 'unavailable',
                'message' => ($availability1['allowed'] ?? false) ? 'Available' : ($availability1['reason'] ?? 'Unavailable'),
            ],
            'availability2' => [
                'status' => ($availability2['allowed'] ?? false) ? 'active' : 'unavailable',
                'message' => ($availability2['allowed'] ?? false) ? 'Available' : ($availability2['reason'] ?? 'Unavailable'),
            ],
        ]);
    }

    /**
     * Show a promotion page (for customer viewing)
     * Auto-detects if viewer is an authorized employee/business owner
     */
    public function showPromotion(Request $request, string $code)
    {
        // Normalize customer token codes (handle spaces/case)
        $originalCode = $code;
        $normalizedCode = strtoupper(str_replace([" ", "\u{00A0}"], '', $code));
        if (preg_match('/^UP-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $normalizedCode)) {
            $code = $normalizedCode;
        } elseif (preg_match('/^UP([A-Z0-9]{4})([A-Z0-9]{4})$/', $normalizedCode, $m)) {
            $code = "UP-{$m[1]}-{$m[2]}";
        }
        if ($code !== $originalCode) {
            return redirect()->route('promotion.show', ['code' => $code]);
        }

        $qrCode = QRCode::byCode($code)
            ->withTrashed()
            ->with(['promotion' => fn($q) => $q->withTrashed(), 'business:id,name,logo_path,primary_color,secondary_color'])
            ->first();

        $userPromoToken = null;
        $levelGateMessage = null;

        // Internal prize/config QR codes should not be viewed/redeemed by customers.
        if ($qrCode && ($qrCode->intended_use ?? \App\Models\QRCode::INTENDED_USE_PUBLIC) === \App\Models\QRCode::INTENDED_USE_LEADERBOARD_PRIZE) {
            $staffAuth = $this->getStaffAuthorization($request, $qrCode->business_id);
            if (!$staffAuth) {
                return Inertia::render('Public/ScanError', [
                    'message' => 'This QR code is an internal leaderboard prize and should not be scanned. Ask the business for the game QR code instead.',
                ]);
            }
        }

        // If this is a QRcade/game QR code, /promo/{code} should NOT show "Oops".
        // Redirect into the game flow instead.
        // Skip redirect when PlayController already bounced us back (no_game_redirect) to avoid loops.
        if ($qrCode && !$request->has('no_game_redirect') && (in_array($qrCode->type, ['qrcade', 'qrcade_leaderboard'], true) || $qrCode->getActiveGame())) {
            return redirect()->to('/play/' . $qrCode->code);
        }

        // If this is a cross-promo QR, reuse the cross-promo view
        if ($qrCode && $qrCode->type === 'cross_promo') {
            return $this->handleCrossPromoScan($request, $qrCode);
        }
        
        // If not found as QR code, check if it's a customer promo code
        if (!$qrCode || !$qrCode->promotion) {
            // Try to resolve as a user promo token (handles cross-promo Get Deal tokens)
            $candidateCodes = [$code, $normalizedCode];
            // If we derived a canonical UP-XXXX-XXXX, include it
            if (preg_match('/^UP-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code) === 0 && preg_match('/^UP-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $normalizedCode) === 0) {
                if (preg_match('/^UP([A-Z0-9]{4})([A-Z0-9]{4})$/', $normalizedCode, $m)) {
                    $candidateCodes[] = "UP-{$m[1]}-{$m[2]}";
                }
            }

            $userPromoToken = \App\Models\UserPromoToken::query()
                ->where(function ($q) use ($candidateCodes) {
                    foreach ($candidateCodes as $c) {
                        $q->orWhere('code', $c);
                    }
                })
                ->with([
                    'qrCode' => fn($q) => $q->withTrashed(),
                    'qrCode.promotion' => fn($q) => $q->withTrashed(),
                    'qrCode.business:id,name,logo_path,primary_color,secondary_color',
                ])
                ->first();

            if ($userPromoToken && $userPromoToken->qrCode) {
                // Use the QR code from the token, even if the QR itself doesn't have a promotion attached
                // (cross-promo tokens carry the promotion via the token relationships)
                $qrCode = $userPromoToken->qrCode;
            } else {
                return Inertia::render('Public/ScanError', [
                    'message' => 'Promotion not found. Your personal deal code could not be located.',
                ]);
            }
        } elseif ($request->user()) {
            // Always use the token service for logged-in customers.
            // This guarantees:
            // - One-time promos reuse the same active token until redeemed.
            // - Multi-scan promos can create a new token per scan (up to the per-user limit).
            if ($qrCode->promotion) {
                $userPromoTokenService = app(\App\Services\UserPromoTokenService::class);
                $userPromoToken = $userPromoTokenService->ensure($request->user(), $qrCode);
            }
        }

        // If QR code is missing promotion/business but token has them, prefer token relationships
        if ($userPromoToken) {
            if (!$qrCode->promotion && $userPromoToken->promotion) {
                $qrCode->setRelation('promotion', $userPromoToken->promotion);
                $qrCode->promotion_id = $userPromoToken->promotion->id;
            }
            if (!$qrCode->business && $userPromoToken->business) {
                $qrCode->setRelation('business', $userPromoToken->business);
                $qrCode->business_id = $userPromoToken->business->id;
            }
        }

        $promotion = $qrCode->promotion;
        $business = $qrCode->business;

        // Check if promotion is available (not deleted and active)
        $isAvailable = $promotion && !$promotion->trashed() && $promotion->is_active;

        // Check if current user is authorized to redeem (employee or business owner)
        $staffAuth = $this->getStaffAuthorization($request, $qrCode->business_id);

        // Increment view count if available and not being viewed by staff
        if ($isAvailable && !$staffAuth) {
            $promotion->increment('total_views');
        }

        $skipScan = $request->boolean('no_scan') || $request->query('source') === 'portal';

        // Ensure a scan event is recorded when a customer visits the promo page directly.
        // This keeps "Recent Activity" populated even if the user lands on /promo/{code} instead of /s/{code}.
        if (!$skipScan && $request->user() && !$staffAuth && in_array($request->user()->role, ['customer', 'user'], true)) {
            if (in_array($qrCode->type, ['promotion', 'level_exclusive', 'merch_referral', 'stackable'], true)) {
                $hasScan = Scan::where('user_id', $request->user()->id)
                    ->where('qr_code_id', $qrCode->id)
                    ->exists();

                if (!$hasScan) {
                    $isPunchCard = (($promotion?->discount_type ?? null) === Promotion::TYPE_PUNCH_CARD);
                    $this->recordScan($request, $qrCode, $isPunchCard ? Scan::TYPE_PUNCH_CARD : Scan::TYPE_PROMOTION);
                }
            }
        }

        // Check if promotion is currently valid (for redemption logic)
        // For logged-in users, use their customer identifier to check per-user limits
        $customerIdentifier = null;
        if ($userPromoToken) {
            $customerIdentifier = $userPromoToken->code;
        }
        $redeemOptions = array_merge(
            $this->getMerchReferralRedeemOptions($qrCode),
            ['current_qr_code_id' => $qrCode->id]
        );

        $canRedeem = $promotion
            ? $promotion->canRedeem($customerIdentifier, $request->user()?->id, $redeemOptions)
            : ['allowed' => false, 'reason' => 'Promotion not found'];

        // Get user level for display + level gate messaging
        $userLevel = $request->user()?->level ?? null;
        if ($qrCode->required_level && !$qrCode->requiresLevel($userLevel)) {
            if ($request->user()) {
                $levelGateMessage = "This promotion requires Level {$qrCode->required_level}. You are currently Level {$userLevel}. Keep scanning, playing games, and redeeming to level up!";
            } else {
                $levelGateMessage = 'Sign in to check if you meet the level requirement for this promotion.';
            }
        }

        // Reliable portal linking:
        // - If guest, remember this promo so after login/register we can auto-save it.
        // - If logged in (customer), immediately save/attach so it shows in Portal Scans + Saved.
        if ($isAvailable && !$levelGateMessage) {
            if (!$request->user()) {
                $this->promoClaimService->rememberPendingPromo($request, $qrCode->code);
            } elseif (!$skipScan) {
                $this->promoClaimService->claimPromo($request, $request->user(), $qrCode);
            }
        }

        // Get punch card progress if this is a punch card promotion
        $punchCardProgress = null;
        if ($promotion && $promotion->discount_type === 'punch_card') {
            $punchCardProgress = $this->getPunchCardProgress($request, $promotion);
        }

        // Get logged in user's avatar if present
        $userAvatar = null;
        if ($request->user()) {
            $userAvatar = [
                'name' => $request->user()->name,
                'avatar_url' => $request->user()->avatar_url,
            ];
        }

        // Check if user has saved this QR code
        $isSaved = false;
        if ($request->user()) {
            $isSaved = \App\Models\SavedQRCode::where('user_id', $request->user()->id)
                ->where('qr_code_id', $qrCode->id)
                ->exists();
            
            // Auto-cleanup: if promotion is no longer available, remove it from saved list
            if (!$isAvailable && $isSaved) {
                \App\Models\SavedQRCode::where('user_id', $request->user()->id)
                    ->where('qr_code_id', $qrCode->id)
                    ->delete();
                $isSaved = false;
            }
        }

        // If user has a customer promo token, use that code and QR instead of the original
        $displayQrCode = [
            'id' => $qrCode->id,
            'code' => $qrCode->code,
            'name' => $qrCode->name,
            'image_url' => $qrCode->image_url,
            'required_level' => $qrCode->required_level,
        ];
        
        if ($userPromoToken) {
            // Use customer promo code and QR image
            $displayQrCode['code'] = $userPromoToken->code;
            $displayQrCode['image_url'] = $userPromoToken->qr_image_url;
            $displayQrCode['is_customer_promo'] = true;
        }

        // Explicit opt-in: is this user subscribed to this business' emails?
        $isSubscribed = false;
        if ($request->user() && in_array($request->user()->role, ['customer', 'user'], true)) {
            $isSubscribed = $this->businessCustomerService->isSubscribed((int) $business->id, (int) $request->user()->id);
        }

        return Inertia::render('Public/Promotion', [
            'qrCode' => $displayQrCode,
            'isSaved' => $isSaved,
            'userLevel' => $userLevel,
            'isAvailable' => $isAvailable,
            'isSubscribed' => $isSubscribed,
            'promotion' => $promotion ? [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'description' => $promotion->description,
                'terms' => $promotion->terms,
                'discount_type' => $promotion->discount_type,
                'display_value' => $promotion->getDisplayDescription(),
                'original_price' => $promotion->original_price,
                'final_price' => $promotion->getFinalPrice(),
                'discount_value' => $promotion->discount_value,
                'minimum_purchase' => $promotion->minimum_purchase,
                'starts_at' => $promotion->starts_at?->format('M d, Y'),
                'ends_at' => $promotion->ends_at?->format('M d, Y'),
                // Punch card specific fields
                'punches_required' => $promotion->punches_required,
                'punch_icon' => $promotion->getPunchIcon(),
                'reward_value' => $promotion->reward_value,
                'needs_calculator' => $promotion->needsCalculator(),
                'calculator_prefills' => $promotion->getCalculatorPrefills(),
            ] : null,
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'logo_url' => $business->logo_url,
                'primary_color' => $business->primary_color,
                'secondary_color' => $business->secondary_color,
            ],
            // Plan gate: Pro/Enterprise can remove platform branding from customer-facing pages.
            'showPlatformBranding' => !($business->canAccess('remove_branding') || $business->canAccess('white_label')),
            'canRedeem' => $levelGateMessage ? false : $canRedeem['allowed'],
            'redeemMessage' => $levelGateMessage ?? $canRedeem['reason'],
            // Staff authorization - only present if user is authorized
            'staffAuth' => $staffAuth,
            // Punch card progress - shows how many punches the customer has
            'punchCardProgress' => $punchCardProgress,
            // Logged in user's avatar for personalization
            'userAvatar' => $userAvatar,
        ]);
    }

    /**
     * Get punch card progress for a customer
     */
    protected function getPunchCardProgress(Request $request, $promotion): ?array
    {
        $user = $request->user();
        $punchCard = null;
        $customerIdentifier = null;
        
        // Account-only punch cards:
        // If user is logged in, show their progress from their user_id.
        if ($user) {
            $punchCard = $promotion->punchCards()
                ->where('user_id', $user->id)
                ->first();
            
            // If found, use the customer identifier from the card
            if ($punchCard) {
                $customerIdentifier = $punchCard->customer_identifier;
            }
        }

        return [
            'total_required' => $promotion->punches_required,
            'current_punches' => $punchCard?->punches ?? 0,
            'completed_cards' => $punchCard?->completed_cards ?? 0,
            'last_punch_at' => $punchCard?->last_punch_at?->diffForHumans(),
            'icon' => $promotion->getPunchIcon(),
            'has_identifier' => !empty($user),
            'punch_card_id' => $punchCard?->id,
        ];
    }

    /**
     * Check if current user is authorized staff for this business
     */
    protected function getStaffAuthorization(Request $request, int $businessId): ?array
    {
        $user = $request->user();
        
        if (!$user) {
            return null;
        }

        // Check if user is the business owner
        // Business owners are identified via the owned Business relation (users table has no business_id).
        if ($user->role === 'business' && $user->business?->id === $businessId) {
            return [
                'type' => 'owner',
                'name' => $user->name,
                'canRedeem' => true,
            ];
        }

        // Check if user is an employee of this business
        if ($user->role === 'employee') {
            $employee = \App\Models\Employee::where('user_id', $user->id)
                ->where('business_id', $businessId)
                ->where('is_active', true)
                ->first();

            if ($employee && $employee->can_redeem) {
                return [
                    'type' => 'employee',
                    'name' => $user->name,
                    'employeeId' => $employee->id,
                    'canRedeem' => true,
                ];
            }
        }

        return null;
    }

    /**
     * Handle game QR code scans - redirect to game play page
     */
    protected function handleGameScan(Request $request, QRCode $qrCode, QRCodeGame $qrCodeGame)
    {
        // Ensure game relationship is loaded
        if (!$qrCodeGame->relationLoaded('game')) {
            $qrCodeGame->load('game');
        }
        
        $game = $qrCodeGame->game;
        
        if (!$game) {
            \Log::error('Game not found for QR code game', [
                'qr_code_id' => $qrCode->id,
                'qr_code_game_id' => $qrCodeGame->id,
                'game_id' => $qrCodeGame->game_id,
            ]);
            return Inertia::render('Public/ScanError', [
                'message' => 'The game associated with this QR code could not be found.',
            ]);
        }
        
        if (!$game->is_active) {
            return Inertia::render('Public/ScanError', [
                'message' => 'The game associated with this QR code is currently unavailable.',
            ]);
        }

        // Only leaderboard QR codes should show leaderboard previews before play.
        // Normal QRcade should remain a fast "scan -> play" flow.
        if ($qrCode->type !== 'qrcade_leaderboard') {
            return redirect()->route('play.game', [
                'code' => $qrCode->code,
                'game' => $game->slug,
            ]);
        }

        // For leaderboard QR codes: if there are active leaderboards for this business/game,
        // send the player to the game-select page first so they can see the leaderboard preview before playing.
        // Prefer explicit leaderboard link from qr_code_games, fall back to broadcast matching.
        $hasLeaderboards = false;
        if ($qrCodeGame->leaderboard_id) {
            $hasLeaderboards = Leaderboard::active()->where('id', $qrCodeGame->leaderboard_id)->exists();
        }
        if (!$hasLeaderboards) {
            $hasLeaderboards = Leaderboard::active()
                ->where('business_id', $qrCode->business_id)
                ->where(function ($q) use ($game) {
                    $q->where('type', Leaderboard::TYPE_LOCATION)
                      ->orWhere(function ($qq) use ($game) {
                          $qq->where('type', Leaderboard::TYPE_GAME_SPECIFIC)
                             ->where('game_id', $game->id);
                      });
                })
                ->exists();
        }

        if ($hasLeaderboards) {
            return redirect()->route('play.index', [
                'code' => $qrCode->code,
            ]);
        }

        // Otherwise, keep the fast path and jump straight into the game.
        return redirect()->route('play.game', [
            'code' => $qrCode->code,
            'game' => $game->slug,
        ]);
    }

    /**
     * Handle stackable pool scans - show all businesses in the pool
     */
    protected function handleStackableScan(Request $request, QRCode $qrCode)
    {
        $pool = $qrCode->stackablePool;

        if (!$pool) {
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
            if ($qrCode->stackable_pool_id !== $pool->id) {
                $qrCode->update(['stackable_pool_id' => $pool->id]);
            }
        }

        if (!$pool) {
            return Inertia::render('Public/ScanError', [
                'message' => 'Stackable pool not found',
            ]);
        }

        if (!$pool->isCurrentlyActive()) {
            return Inertia::render('Public/ScanError', [
                'message' => 'This deal pool is not currently active.',
            ]);
        }

        $qrCode->loadMissing('business');
        $business = $qrCode->business;

        $entries = $pool->entries()
            ->where('is_active', true)
            ->where('is_approved', true)
            ->whereHas('business', fn ($q) => $q->where('is_active', true))
            ->whereHas('promotion', fn ($q) => $q->where('is_active', true)
                ->where(fn ($pq) => $pq->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                ->where(fn ($pq) => $pq->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            )
            ->with(['business:id,name,logo_path,city', 'promotion'])
            ->orderBy('is_featured', 'desc')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($entry) => $entry->business && $entry->promotion)
            ->values();

        $stackableQrByPromotion = QRCode::whereIn('promotion_id', $entries->pluck('promotion_id'))
            ->where('type', 'stackable')
            ->where('is_active', true)
            ->get()
            ->keyBy('promotion_id');

        return Inertia::render('Public/StackablePool', [
            'qrCode' => $qrCode->only(['id', 'code', 'name']),
            'showPlatformBranding' => $business ? !($business->canAccess('remove_branding') || $business->canAccess('white_label')) : true,
            'pool' => [
                'name' => $pool->name,
                'description' => $pool->description,
                'city' => $pool->city,
            ],
            'entries' => $entries->map(fn($entry) => [
                'business' => [
                    'name' => $entry->business->name,
                    'logo_url' => $entry->business->logo_url,
                    'city' => $entry->business->city,
                ],
                'promotion' => [
                    'name' => $entry->promotion->name,
                    'display_value' => $entry->promotion->getDisplayDescription(),
                    'qr_code' => $stackableQrByPromotion->get($entry->promotion_id)?->code,
                ],
                'is_featured' => $entry->is_featured,
            ])
            ->filter(fn ($entry) => !empty($entry['promotion']['qr_code']))
            ->values(),
        ]);
    }

    protected function getMerchReferralRedeemOptions(QRCode $qrCode): array
    {
        if (($qrCode->type ?? null) !== 'merch_referral') {
            return [];
        }

        return [
            'force_max_redemptions_per_user' => 1,
            'ignore_time_limits' => true,
            'ignore_total_limit' => true,
            'ignore_daily_limit' => true,
            'ignore_time_window' => true,
        ];
    }

    /**
     * Record a scan event
     */
    protected function recordScan(Request $request, QRCode $qrCode, ?string $scanType = null): Scan
    {
        $agent = new Agent();
        $agent->setUserAgent($request->userAgent());

        $sessionId = $request->session()->getId();
        $userId = $request->user()?->id;
        if ($qrCode->code === OnboardingQr::CODE) {
            // Onboarding flyer QR should never attach to a user in portal history.
            $userId = null;
        }
        $merchTagId = $request->session()->pull('merch_tag_id');
        if ($merchTagId) {
            $merchTagId = \App\Models\MerchTag::where('id', $merchTagId)
                ->where('is_active', true)
                ->exists()
                ? (int) $merchTagId
                : null;
        }

        // Portal stacking policy (per promo):
        // - Default: 1 active voucher per user per promo in portal (refreshes latest scan until used)
        // - If portal_multiple_scans is enabled: allow multiple active scans up to max_redemptions_per_user
        $maxPerUser = 1;
        if ($userId && in_array($qrCode->type, ['promotion', 'level_exclusive', 'merch_referral'], true)) {
            $qrCode->loadMissing('promotion');
            $promo = $qrCode->promotion;
            if ($promo) {
                $rules = is_array($promo->rules) ? $promo->rules : [];
                $multipleAllowed = $rules['portal_multiple_scans'] ?? false;

                if ($qrCode->type === 'merch_referral') {
                    $maxPerUser = 1;
                } elseif ($multipleAllowed) {
                    $limit = (int)($rules['max_redemptions_per_user'] ?? 0);
                    $maxPerUser = ($limit > 0) ? $limit : 50; // Safety cap of 50 if unlimited
                } else {
                    $maxPerUser = 1;
                }

                // Never stack punch cards in portal
                if (($promo->discount_type ?? null) === Promotion::TYPE_PUNCH_CARD) {
                    $maxPerUser = 1;
                }
            }
        }

        // If user has already hit their per-user redemption limit for this promotion,
        // still record the scan for BUSINESS analytics, but do not attach it to the user's portal.
        // This prevents "limit reached" scans from cluttering Recent Activity.
        if ($userId && in_array($qrCode->type, ['promotion', 'level_exclusive', 'merch_referral'], true)) {
            $promo = $qrCode->promotion;
            if ($promo) {
                $rules = is_array($promo->rules) ? $promo->rules : [];
                $limit = $qrCode->type === 'merch_referral' ? 1 : (int) ($rules['max_redemptions_per_user'] ?? 0);
                $isPunchCard = (($promo->discount_type ?? null) === Promotion::TYPE_PUNCH_CARD);
                $respectPunchLimits = (bool) ($rules['respect_punch_limits'] ?? false);

                if ($limit > 0 && (!$isPunchCard || $respectPunchLimits)) {
                    $tokenCodes = \App\Models\UserPromoToken::where('user_id', $userId)
                        ->where('promotion_id', $promo->id)
                        ->pluck('code')
                        ->filter()
                        ->values();

                    $userRedemptions = \App\Models\Redemption::where('promotion_id', $promo->id)
                        ->where(function ($q) use ($userId, $tokenCodes) {
                            $q->where('customer_user_id', $userId);
                            if ($tokenCodes->count() > 0) {
                                $q->orWhereIn('customer_identifier', $tokenCodes->all());
                            }
                        })
                        ->count();

                    if ($userRedemptions >= $limit) {
                        // Detach from user portal by nulling the user_id for this scan row.
                        $userId = null;
                    }
                }
            }
        }

        // ... existing geo code ...
        $lat = $request->get('lat');
        $lng = $request->get('lng');

        $latitude = is_numeric($lat) ? (float) $lat : null;
        $longitude = is_numeric($lng) ? (float) $lng : null;

        // Default scan type based on QR type (used if caller didn't pass an explicit scan type).
        $defaultScanType = Scan::TYPE_INFO;
        if (in_array($qrCode->type, ['qrcade', 'qrcade_leaderboard'], true)) {
            $defaultScanType = ($qrCode->type === 'qrcade_leaderboard') ? Scan::TYPE_QRCADE_LEADERBOARD : Scan::TYPE_QRCADE_GAME;
        } elseif (in_array($qrCode->type, ['stackable'], true)) {
            $defaultScanType = Scan::TYPE_STACKABLE;
        } elseif (in_array($qrCode->type, ['cross_promo'], true)) {
            $defaultScanType = Scan::TYPE_CROSS_PROMO;
        } elseif (in_array($qrCode->type, ['promotion', 'level_exclusive', 'merch_referral'], true)) {
            $defaultScanType = Scan::TYPE_PROMOTION;
        } elseif (in_array($qrCode->type, ['static', 'dynamic'], true)) {
            $defaultScanType = Scan::TYPE_INFO;
        }

        $payload = [
            'qr_code_id' => $qrCode->id,
            'merch_tag_id' => $merchTagId,
            'business_id' => $qrCode->business_id,
            'user_id' => $userId,
            'scan_type' => $scanType ?? $defaultScanType,
            'session_id' => $sessionId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_type' => $agent->isMobile() ? 'mobile' : ($agent->isTablet() ? 'tablet' : 'desktop'),
            'browser' => $agent->browser(),
            'os' => $agent->platform(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'city' => $request->get('city'),
            'region' => $request->get('region'),
            'country' => $request->get('country'),
            'referrer' => $request->header('referer'),
            'utm_source' => $request->get('utm_source'),
            'utm_medium' => $request->get('utm_medium'),
            'utm_campaign' => $request->get('utm_campaign'),
            'scanned_at' => now(),
        ];

        $skipXp = $request->boolean('no_xp') || ($request->get('source') === 'stackable');

        // Logged-in behavior: respect per-promo stacking logic.
        if ($userId) {
            if ($qrCode->type === 'qrcade_leaderboard') {
                // Leaderboard QR: one scan per leaderboard period.
                // While the period is open, re-scans refresh the existing scan row
                // so Recent Activity shows one clean "View Leaderboard" entry.
                // When a new period starts, a fresh scan is created.
                $existingPeriodScan = null;
                $qrCodeGame = \App\Models\QRCodeGame::where('qr_code_id', $qrCode->id)
                    ->where('is_active', true)
                    ->first();

                if ($qrCodeGame) {
                    $lb = null;
                    if ($qrCodeGame->leaderboard_id) {
                        $lb = \App\Models\Leaderboard::active()->find($qrCodeGame->leaderboard_id);
                    }
                    if (!$lb) {
                        $lb = \App\Models\Leaderboard::active()
                            ->where('business_id', $qrCode->business_id)
                            ->where(function ($q) use ($qrCodeGame) {
                                $q->where(function ($qq) use ($qrCodeGame) {
                                    $qq->where('type', \App\Models\Leaderboard::TYPE_GAME_SPECIFIC)
                                       ->where('game_id', $qrCodeGame->game_id);
                                })->orWhere('type', \App\Models\Leaderboard::TYPE_LOCATION);
                            })
                            ->orderByRaw("CASE WHEN type = 'game_specific' THEN 0 ELSE 1 END")
                            ->first();
                    }

                    if ($lb && $lb->current_period_start) {
                        // Find existing scan created during the current leaderboard period.
                        $existingPeriodScan = Scan::where('user_id', $userId)
                            ->where('qr_code_id', $qrCode->id)
                            ->where('scan_type', Scan::TYPE_QRCADE_LEADERBOARD)
                            ->where('created_at', '>=', $lb->current_period_start)
                            ->orderByDesc('created_at')
                            ->first();
                    }
                }

                if ($existingPeriodScan) {
                    $existingPeriodScan->update($payload);
                    $scan = $existingPeriodScan;
                } else {
                    $scan = Scan::create($payload);
                }
            } elseif ($qrCode->type === 'qrcade') {
                // Regular QRcade (play-to-win): 10-minute dedup window.
                // Each play attempt after 10 min = new scan entry.
                $recentQrcadeScan = Scan::where('user_id', $userId)
                    ->where('qr_code_id', $qrCode->id)
                    ->where('created_at', '>=', now()->subMinutes(10))
                    ->orderByDesc('created_at')
                    ->first();

                if ($recentQrcadeScan) {
                    $recentQrcadeScan->update($payload);
                    $scan = $recentQrcadeScan;
                } else {
                    $scan = Scan::create($payload);
                }
            } else {
                // Every scan event = one row for a complete audit trail.
                $scan = Scan::create($payload);
            }

            $isBlocked = ($scan->scan_type ?? null) === Scan::TYPE_BLOCKED;

            // Update QR code stats ONLY for newly created scan rows.
            // If we just "refreshed" an existing scan row (re-scan / reopen before redeem),
            // do NOT increment business analytics to avoid inflating scan->redeem conversion rates.
            // Blocked scans are excluded: they track demand, not real engagement.
            if ($scan->wasRecentlyCreated && !$isBlocked) {
                $qrCode->recordScan($sessionId);
            }
            
            // Award XP for scan (10 XP, only for new scans of this QR)
            if ($scan->wasRecentlyCreated && !$skipXp && !$isBlocked) {
                $user = \App\Models\User::find($userId);
                if ($user) {
                    app(\App\Services\XpService::class)->awardForScan($user, $scan, $qrCode);
                }
            }

            // Ambassador XP: award the merch owner on unique merch scans.
            if ($scan->wasRecentlyCreated && !$isBlocked && $scan->merch_tag_id) {
                $tag = \App\Models\MerchTag::with('owner')
                    ->where('id', $scan->merch_tag_id)
                    ->where('is_active', true)
                    ->first();

                if ($tag && $tag->owner && $tag->owner->id !== $scan->user_id && $this->isMerchUniqueScan($scan)) {
                    app(\App\Services\XpService::class)->awardForMerchScan($tag->owner, $scan);
                }
            }

            // CRM customer cache: update last seen for this business/customer and optionally increment scan count.
            // IMPORTANT: Only count a scan when a new scan row was created (same logic as business analytics).
            // Blocked scans don't count toward CRM engagement.
            $this->businessCustomerService->recordScan($scan, $scan->wasRecentlyCreated && !$isBlocked);

            return $scan;
        }

        $scan = Scan::create($payload);
        // Guests always create a new scan row, but keep the same "only on create" guard for consistency.
        if ($scan->wasRecentlyCreated) {
            $qrCode->recordScan($sessionId);
        }

        // Ambassador XP: award the merch owner on unique merch scans (guest scanners).
        if ($scan->wasRecentlyCreated && $scan->merch_tag_id) {
            $tag = \App\Models\MerchTag::with('owner')
                ->where('id', $scan->merch_tag_id)
                ->where('is_active', true)
                ->first();

            if ($tag && $tag->owner && $this->isMerchUniqueScan($scan)) {
                app(\App\Services\XpService::class)->awardForMerchScan($tag->owner, $scan);
            }
        }

        // Guest-friendly: remember this scan so if the user signs in right after playing,
        // it will appear in Portal -> Recent Activity.
        // (Promos already have PromoClaimService, but QRcade scans need this for reliable portal history.)
        $this->scanService->storeAnonymousScan($scan);

        return $scan;
    }

    protected function isMerchUniqueScan(Scan $scan): bool
    {
        if (!$scan->merch_tag_id) {
            return false;
        }

        $query = Scan::where('merch_tag_id', $scan->merch_tag_id)
            ->where('id', '!=', $scan->id);

        if ($scan->user_id) {
            $query->where('user_id', $scan->user_id);
        } elseif ($scan->session_id) {
            $query->where('session_id', $scan->session_id);
        } else {
            // No reliable de-dupe key; treat as unique.
            return true;
        }

        return !$query->exists();
    }

    /**
     * Claim a specific offer from a cross-promotion.
     * Generates a UserPromoToken and redirects to the redemption page.
     */
    public function claimCrossPromoOffer(Request $request, \App\Models\CrossPromotion $crossPromotion, Promotion $promotion)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login', ['redirect_to' => $request->path()]);
        }

        // Resolve with trashed to give proper error messages instead of 404
        $crossPromotion = \App\Models\CrossPromotion::withTrashed()->findOrFail($crossPromotion->id);
        $promotion = Promotion::withTrashed()->findOrFail($promotion->id);

        if ($crossPromotion->trashed()) {
            return back()->with('error', 'This partner deal has been removed.');
        }

        // Validate promo belongs to cross-promo
        if ($promotion->id !== $crossPromotion->promotion_1_id && $promotion->id !== $crossPromotion->promotion_2_id) {
            abort(404, 'Promotion not part of this deal.');
        }

        // Validate cross-promo state (accepted, active, within date window, under usage limit)
        if (!$crossPromotion->isValid()) {
            return back()->with('error', 'This partner deal is no longer active.');
        }

        if ($crossPromotion->hasReachedUsageLimit()) {
            return back()->with('error', 'This partner deal has reached its usage limit.');
        }

        if ($promotion->trashed()) {
            return back()->with('error', 'This offer is no longer available.');
        }

        // Check if locked
        if ($crossPromotion->isPromotionLocked($promotion->id, $user->id)) {
            return back()->with('error', 'This offer is locked! Redeem the other offer first.');
        }

        // Get QR Code context (first associated with this cross promo)
        $qrCode = $crossPromotion->qrCodes()->withTrashed()->first();
        if (!$qrCode) {
             return back()->with('error', 'No QR code is set up for this partner deal yet. Ask the business to create one.');
        }

        // Create Token
        $tokenService = app(\App\Services\UserPromoTokenService::class);
        $token = $tokenService->createForCrossPromo($user, $qrCode, $promotion);

        // Redirect to redemption page
        return redirect()->route('promotion.show', ['code' => $token->code]);
    }

    /**
     * Update the most recent scan record (for this browser session + QR code) with consent-based geo.
     *
     * This avoids double-recording scans while still allowing client-side geolocation after the
     * scan lands on an internal RewardStack page (promo/cross-promo/stackable/game).
     */
    public function updateScanGeo(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:8',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'city' => 'nullable|string|max:120',
            'region' => 'nullable|string|max:120',
            'country' => 'nullable|string|max:120',
        ]);

        $qrCode = QRCode::byCode($validated['code'])->first();
        if (!$qrCode) {
            return response()->json(['success' => false, 'error' => 'QR code not found'], 404);
        }

        $sessionId = $request->session()->getId();
        if (!$sessionId) {
            return response()->json(['success' => false, 'error' => 'No session'], 400);
        }

        // Update only the latest scan in a short window to avoid poisoning older rows
        $baseQuery = Scan::where('qr_code_id', $qrCode->id)
            ->where('scanned_at', '>=', now()->subMinutes(10))
            ->orderByDesc('scanned_at');

        // Prefer exact browser session match
        $scan = (clone $baseQuery)
            ->where('session_id', $sessionId)
            ->first();

        // Fallback: if session cookies are blocked/rotated, try IP match
        if (!$scan) {
            $scan = (clone $baseQuery)
                ->where('ip_address', $request->ip())
                ->first();
        }

        if (!$scan) {
            return response()->json(['success' => false, 'error' => 'No recent scan found'], 404);
        }

        $scan->update([
            'latitude' => isset($validated['lat']) ? (float) $validated['lat'] : $scan->latitude,
            'longitude' => isset($validated['lng']) ? (float) $validated['lng'] : $scan->longitude,
            'city' => $validated['city'] ?? $scan->city,
            'region' => $validated['region'] ?? $scan->region,
            'country' => $validated['country'] ?? $scan->country,
        ]);

        return response()->json(['success' => true]);
    }
}


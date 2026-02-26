<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GamePlay;
use App\Models\GameReward;
use App\Models\GameSession;
use App\Models\PunchCard;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\QRCodeGame;
use App\Models\SavedQRCode;
use App\Models\User;
use App\Models\Badge;
use App\Models\Leaderboard;
use App\Models\LeaderboardEntry;
use App\Models\BusinessGame;
use App\Services\LocationLockService;
use App\Services\PrizeService;
use App\Services\QRGeneratorService;
use App\Services\CustomerCodeService;
use App\Notifications\PortalPromoTokenAwarded;
use Illuminate\Support\Str;

class GameService
{
    protected LocationLockService $locationService;
    protected PrizeService $prizeService;
    protected QRGeneratorService $qrGenerator;
    protected CustomerCodeService $customerCodeService;

    public function __construct(LocationLockService $locationService, PrizeService $prizeService, QRGeneratorService $qrGenerator, CustomerCodeService $customerCodeService)
    {
        $this->locationService = $locationService;
        $this->prizeService = $prizeService;
        $this->qrGenerator = $qrGenerator;
        $this->customerCodeService = $customerCodeService;
    }

    /**
     * Determine whether this user can earn ANY redeemable reward from this QR + game right now.
     * If not, we run in "fun only" mode:
     * - allow play
     * - do NOT record plays/leaderboards/xp
     * - do NOT award prizes
     */
    public function getFunOnlyStatus(QRCode $qrCode, Game $game, ?User $user): array
    {
        $qrCodeGame = QRCodeGame::query()
            ->where('qr_code_id', $qrCode->id)
            ->where('game_id', $game->id)
            ->where('is_active', true)
            ->first();

        if (!$qrCodeGame) {
            return ['fun_only' => false, 'reason' => null];
        }

        $promotionIds = collect();
        if (!empty($qrCodeGame->promotion_id)) {
            $promotionIds->push((int) $qrCodeGame->promotion_id);
        }
        $tierRewards = is_array($qrCodeGame->tier_rewards) ? $qrCodeGame->tier_rewards : [];
        foreach ($tierRewards as $pid) {
            if ($pid) {
                $promotionIds->push((int) $pid);
            }
        }

        // Fallback: if the game row is missing a promotion_id but the QR itself has one, treat that as the prize source.
        if ($promotionIds->isEmpty() && $qrCode->promotion_id) {
            $promotionIds->push((int) $qrCode->promotion_id);
        }

        // Leaderboard challenges use Leaderboard->promotion_id (or Leaderboard->qr_code_id->promotion_id) as the prize source.
        // Treat that as a "prize promotion" for fun-only gating so:
        // - if no prize is configured, it becomes true fun-only (no XP/badges/leaderboards)
        // - if prize is configured and user hit per-user limits, it becomes fun-only
        if ($qrCodeGame->win_mode === QRCodeGame::WIN_MODE_LEADERBOARD) {
            // Prefer explicit leaderboard link, fall back to broadcast matching
            $leaderboard = null;
            if ($qrCodeGame->leaderboard_id) {
                $leaderboard = Leaderboard::active()->find($qrCodeGame->leaderboard_id);
            }
            if (!$leaderboard) {
                $leaderboard = Leaderboard::active()
                    ->where('business_id', $qrCode->business_id)
                    ->where(function ($q) use ($game) {
                        $q->where(function ($qq) use ($game) {
                            $qq->where('type', Leaderboard::TYPE_GAME_SPECIFIC)->where('game_id', $game->id);
                        })->orWhere(function ($qq) {
                            $qq->where('type', Leaderboard::TYPE_LOCATION);
                        });
                    })
                    ->orderByRaw("CASE WHEN type = 'game_specific' THEN 0 ELSE 1 END")
                    ->first();
            }

            if ($leaderboard) {
                $pid = (int) ($leaderboard->promotion_id ?? 0);
                if ($pid <= 0 && $leaderboard->qr_code_id) {
                    $lbQr = QRCode::query()->withTrashed()->find($leaderboard->qr_code_id);
                    $pid = (int) ($lbQr?->promotion_id ?? 0);
                }
                if ($pid > 0) {
                    $promotionIds->push($pid);
                }
            }
        }

        $promotionIds = $promotionIds->filter()->unique()->values();

        // If no promotions are configured as prizes, run in true fun-only mode.
        // This prevents XP/badge/level farming on "play-only" QR codes.
        if ($promotionIds->count() === 0) {
            return [
                'fun_only' => true,
                'reason' => 'Just for fun: no rewards are configured for this QR code.',
            ];
        }

        // Guests can still play normally when prizes exist (no XP is awarded until account is attached).
        // Fun-only should only apply to guests when there are no prizes configured (handled above).
        if (!$user) {
            return ['fun_only' => false, 'reason' => null];
        }

        $promotions = Promotion::query()
            ->whereIn('id', $promotionIds->all())
            ->get()
            ->keyBy('id');

        foreach ($promotionIds as $pid) {
            $promo = $promotions->get($pid);
            if (!$promo) {
                continue;
            }

            // Allow wins even if the promo is not currently redeemable (time window / inactive);
            // redemption-time checks will enforce validity. Only gate on per-user limits here.
            if (!$promo->hasReachedPerUserLimit($user->id)) {
                return ['fun_only' => false, 'reason' => null];
            }
        }

        // Check if user has unredeemed rewards — if so, prompt them to redeem first
        $hasUnredeemed = GameReward::where('user_id', $user->id)
            ->whereIn('promotion_id', $promotionIds->all())
            ->whereIn('status', [GameReward::STATUS_AVAILABLE, GameReward::STATUS_CLAIMED])
            ->exists();

        $reason = $hasUnredeemed
            ? "Redeem your existing reward in My Scans first to be eligible for another win. You can still play for fun and climb the leaderboard!"
            : "You've already won your max rewards for this challenge. You can still play for fun and climb the leaderboard!";

        return [
            'fun_only' => true,
            'reason' => $reason,
        ];
    }

    /**
     * Start a new game session from a QR code scan
     */
    public function startSession(
        QRCode $qrCode,
        Game $game,
        ?User $user = null,
        array $locationData = [],
        array $deviceData = []
    ): GameSession {
        $session = GameSession::create([
            'session_token' => Str::uuid(),
            'user_id' => $user?->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $qrCode->business_id,
            'location_status' => GameSession::LOCATION_PENDING,
            'latitude' => $locationData['latitude'] ?? null,
            'longitude' => $locationData['longitude'] ?? null,
            'accuracy' => $locationData['accuracy'] ?? null,
            'ip_address' => $deviceData['ip'] ?? request()->ip(),
            'user_agent' => $deviceData['user_agent'] ?? request()->userAgent(),
            'device_type' => $this->detectDeviceType($deviceData['user_agent'] ?? request()->userAgent()),
            'status' => GameSession::STATUS_ACTIVE,
            'expires_at' => now()->addHour(),
        ]);

        // Verify location if required
        if ($qrCode->location_lock_type !== 'none') {
            $this->verifySessionLocation($session, $qrCode, $locationData);
        } else {
            $session->verifyLocation();
        }

        return $session;
    }

    /**
     * Verify the session location based on QR code requirements
     */
    public function verifySessionLocation(GameSession $session, QRCode $qrCode, array $locationData): bool
    {
        $verified = $this->locationService->verify($qrCode, $locationData);

        if ($verified) {
            $session->update([
                'location_status' => GameSession::LOCATION_VERIFIED,
                'location_verified_at' => now(),
                'wifi_ssid_detected' => $locationData['wifi_ssid'] ?? null,
                'nfc_tag_detected' => $locationData['nfc_tag'] ?? null,
            ]);
        } else {
            $session->update([
                'location_status' => GameSession::LOCATION_FAILED,
            ]);
        }

        return $verified;
    }

    /**
     * Start playing the game
     */
    public function startPlaying(GameSession $session): GamePlay
    {
        $session->markAsPlaying();

        return GamePlay::create([
            'game_session_id' => $session->id,
            'user_id' => $session->user_id,
            'game_id' => $session->game_id,
            'business_id' => $session->business_id,
            'qr_code_id' => $session->qr_code_id,
            'is_practice' => (bool) $session->is_practice,
            'started_at' => now(),
        ]);
    }

    /**
     * Submit game score and process results
     */
    public function submitScore(GamePlay $gamePlay, int $score, array $gameData = []): array
    {
        // Idempotency: if this play was already finalized, do not award XP/tokens again.
        // Return the stored outcome + any stored promo token code.
        if ($gamePlay->completed_at) {
            $data = is_array($gamePlay->game_data) ? $gamePlay->game_data : [];
            $tokenCode = $data['_user_promo_token_code'] ?? null;

            return [
                'score' => (int) ($gamePlay->score ?? $score),
                'result' => $gamePlay->result,
                'tier' => $gamePlay->reward_tier,
                'won' => ($gamePlay->result === GamePlay::RESULT_WIN),
                'reward' => null,
                'punch_card' => null,
                'promotion_qr_code' => null,
                'user_promo_token' => $tokenCode ? ['code' => $tokenCode] : null,
                'is_high_score' => (bool) $gamePlay->is_high_score,
                'is_personal_best' => (bool) $gamePlay->is_personal_best,
                'badges_earned' => [],
                'xp_earned' => 0,
            ];
        }

        $score = $this->normalizeScore($gamePlay, $score, $gameData);
        $submittedScore = $score;

        // Cap score to the game's max_score (if defined) to prevent manipulation.
        // Most arcade-style games have a natural ceiling; this enforces it server-side.
        $game = $gamePlay->game;
        $maxScore = $game?->max_score;
        if ($maxScore && $maxScore > 0 && $score > $maxScore) {
            \Illuminate\Support\Facades\Log::warning('Score exceeds game max_score, capping', [
                'game_id' => $game->id,
                'submitted' => $score,
                'max_score' => $maxScore,
                'user_id' => $gamePlay->user_id,
            ]);
            $score = $maxScore;
        }

        // Absolute safety ceiling: no game should ever exceed 999,999 points.
        // This catches games without a max_score configured.
        $absoluteCeiling = 999999;
        if ($score > $absoluteCeiling) {
            \Illuminate\Support\Facades\Log::warning('Score exceeds absolute ceiling, capping', [
                'game_id' => $game?->id,
                'submitted' => $score,
                'ceiling' => $absoluteCeiling,
                'user_id' => $gamePlay->user_id,
            ]);
            $score = $absoluteCeiling;
        }

        $duration = $gamePlay->started_at->diffInSeconds(now());

        // Server-side time limit enforcement: reject scores submitted long after the
        // session should have ended. A 30-second grace buffer accounts for network
        // latency and client processing time.
        $gameTimeLimit = $game?->time_limit;
        if ($gameTimeLimit && $gameTimeLimit > 0) {
            $graceSeconds = 30;
            $maxAllowedDuration = $gameTimeLimit + $graceSeconds;

            if ($duration > $maxAllowedDuration) {
                \Illuminate\Support\Facades\Log::warning('Game session expired: score submitted after time limit', [
                    'game_play_id' => $gamePlay->id,
                    'game_id' => $game->id,
                    'time_limit' => $gameTimeLimit,
                    'actual_duration' => $duration,
                    'user_id' => $gamePlay->user_id,
                ]);

                $gamePlay->update([
                    'score' => 0,
                    'duration_seconds' => $duration,
                    'game_data' => $gameData,
                    'result' => GamePlay::RESULT_COMPLETE,
                    'reward_tier' => null,
                    'is_high_score' => false,
                    'is_personal_best' => false,
                    'is_suspicious' => true,
                    'suspicious_reason' => 'Score submitted after session time limit expired',
                    'completed_at' => now(),
                ]);

                $gamePlay->gameSession->markAsCompleted();

                return [
                    'score' => 0,
                    'result' => GamePlay::RESULT_COMPLETE,
                    'tier' => null,
                    'won' => false,
                    'leaderboard' => null,
                    'reward' => null,
                    'punch_card' => null,
                    'promotion_qr_code' => null,
                    'user_promo_token' => null,
                    'is_high_score' => false,
                    'is_personal_best' => false,
                    'badges_earned' => [],
                    'xp_earned' => 0,
                    'expired' => true,
                    'rewards_disabled' => false,
                    'rewards_disabled_reason' => null,
                ];
            }
        }

        // Practice mode: record score for display but skip all rewards/leaderboards/XP
        $isPractice = (bool) $gamePlay->is_practice;
        if ($isPractice) {
            $gamePlay->update([
                'score' => $score,
                'duration_seconds' => $duration,
                'game_data' => $gameData,
                'result' => GamePlay::RESULT_COMPLETE,
                'reward_tier' => null,
                'is_high_score' => false,
                'is_personal_best' => false,
                'is_suspicious' => false,
                'completed_at' => now(),
            ]);

            $gamePlay->gameSession->markAsCompleted();

            return [
                'score' => $score,
                'result' => GamePlay::RESULT_COMPLETE,
                'tier' => null,
                'won' => false,
                'leaderboard' => null,
                'reward' => null,
                'punch_card' => null,
                'promotion_qr_code' => null,
                'user_promo_token' => null,
                'is_high_score' => false,
                'is_personal_best' => false,
                'badges_earned' => [],
                'xp_earned' => 0,
                'is_practice' => true,
                'rewards_disabled' => false,
                'rewards_disabled_reason' => null,
            ];
        }

        // Check for suspicious activity (use submitted score before capping to detect cheating)
        $suspicious = $this->checkForCheating($gamePlay, $submittedScore, $duration, $gameData);

        // Determine result (respect fun-only: no wins/prizes)
        $funOnly = (bool) $gamePlay->gameSession?->fun_only;
        $result = $this->determineResult($gamePlay, $score);
        if ($funOnly) {
            // Force non-winning result while still recording score + stats
            $result['result'] = GamePlay::RESULT_COMPLETE;
            $result['won'] = false;
            $result['tier'] = null;
            $result['promotion_id'] = null;
            $result['reason'] = null;
        }

        // Special case: punch card promotions should award a punch on puzzle completion
        // even if score thresholds are not configured as "wins".
        $qrCodeGame = QRCodeGame::where('qr_code_id', $gamePlay->qr_code_id)
            ->where('game_id', $gamePlay->game_id)
            ->first();

        if ($qrCodeGame && $qrCodeGame->promotion_id) {
            $promo = Promotion::find($qrCodeGame->promotion_id);
            if ($promo && $promo->discount_type === Promotion::TYPE_PUNCH_CARD) {
                $completed = (bool)($gameData['perfect'] ?? false);
                if ($completed) {
                    $result['result'] = GamePlay::RESULT_WIN;
                    $result['won'] = true;
                    $result['promotion_id'] = $qrCodeGame->promotion_id;
                }
            }
        }

        // Merge server-side metadata into stored game_data (avoid trusting client for computed fields).
        $serverData = is_array($result['server_game_data'] ?? null) ? $result['server_game_data'] : [];
        $mergedGameData = array_merge($gameData, $serverData);

        // Update the game play record
        $gamePlay->update([
            'score' => $score,
            'duration_seconds' => $duration,
            'game_data' => $mergedGameData,
            'result' => $result['result'],
            'reward_tier' => $result['tier'],
            'is_high_score' => $gamePlay->checkForHighScore(),
            'is_personal_best' => $gamePlay->checkForPersonalBest(),
            'is_suspicious' => $suspicious['is_suspicious'],
            'suspicious_reason' => $suspicious['reason'],
            'completed_at' => now(),
        ]);

        // Mark session as completed
        $gamePlay->gameSession->markAsCompleted();

        // Update user stats
        $xpEarned = 0;
        if ($gamePlay->user) {
            $xpEarned = $gamePlay->user->recordGamePlay($gamePlay);
        } else {
            // Store anonymous game play in session for later association
            $this->storeAnonymousGamePlay($gamePlay);
        }

        // Process reward
        $reward = null;
        $punchCardAward = null;
        $promotionQrCode = null;
        $userPromoToken = null;

        if ($result['won'] && !$suspicious['is_suspicious']) {
            $promotionId = $result['promotion_id'] ?? null;
            $promotion = $promotionId ? Promotion::find($promotionId) : null;

            // Resolve a QR code for the promotion (used for phone redemption + portal visibility)
            if ($promotion) {
                $sourceQr = QRCode::find($gamePlay->qr_code_id);
                $promotionQrCode = $this->ensurePromotionQRCode($promotion, $sourceQr);

                // Auto-save the promotion QR code to the user's portal (if logged in)
                if ($promotionQrCode && $gamePlay->user_id) {
                    SavedQRCode::firstOrCreate(
                        ['user_id' => $gamePlay->user_id, 'qr_code_id' => $promotionQrCode->id],
                        ['saved_at' => now()]
                    );
                }
            }

            if ($promotion && $promotion->discount_type === Promotion::TYPE_PUNCH_CARD) {
                // For punch cards: do NOT auto-stamp here. Just issue a token/QR so staff can redeem/stamp.
                if ($promotionQrCode && $gamePlay->user) {
                    $tokenService = app(\App\Services\UserPromoTokenService::class);
                    $punchCardToken = $tokenService->ensure($gamePlay->user, $promotionQrCode);

                    // Persist token code on the play for portal linking (do not surface as a "reward" banner)
                    $existingData = is_array($gamePlay->game_data) ? $gamePlay->game_data : [];
                    $existingCode = $existingData['_user_promo_token_code'] ?? null;
                    if (!$existingCode && $punchCardToken?->code) {
                        $existingData['_user_promo_token_code'] = $punchCardToken->code;
                        $gamePlay->updateQuietly(['game_data' => $existingData]);
                    }

                    // Surface the token so the results screen can show the saved banner.
                    $userPromoToken = $punchCardToken;
                }
            } else {
                // For non-punch promotions: generate the same short, staff-redeemable code as a normal promo scan (UP-XXXX-XXXX)
                // and avoid the separate "claim reward" flow.
                if ($promotionQrCode && $gamePlay->user) {
                    $tokenService = app(\App\Services\UserPromoTokenService::class);
                    $userPromoToken = $tokenService->ensure($gamePlay->user, $promotionQrCode);

                    // Persist token code on the play so retries can return the same token without double-counting.
                    $existingData = is_array($gamePlay->game_data) ? $gamePlay->game_data : [];
                    $existingCode = $existingData['_user_promo_token_code'] ?? null;
                    if (!$existingCode && $userPromoToken?->code) {
                        $existingData['_user_promo_token_code'] = $userPromoToken->code;
                        $gamePlay->updateQuietly(['game_data' => $existingData]);
                        // Keep user win stats consistent with historical GameReward behavior.
                        $gamePlay->user->increment('total_rewards_won');
                        if (in_array($gamePlay->user->role, ['user', 'customer'], true)) {
                            $gamePlay->user->notify(new PortalPromoTokenAwarded($userPromoToken));
                        }
                    }
                }
            }
        }

        // Update leaderboards (still update in fun-only mode so scores show, but no prizes are tied)
        $this->updateLeaderboards($gamePlay);

        // Check for badges/achievements
        $badges = $this->checkAchievements($gamePlay);

        // Resolve the promotion name for the frontend (tiered wins, play-to-win, etc.)
        $wonPromotionName = null;
        $wonPromotionId = null;
        if ($result['won'] && !empty($result['promotion_id'])) {
            $wonPromo = isset($promotion) && $promotion ? $promotion : Promotion::find($result['promotion_id']);
            $wonPromotionName = $wonPromo?->name;
            $wonPromotionId = (int) $result['promotion_id'];
        }

        return [
            'score' => $score,
            'result' => $result['result'],
            'tier' => $result['tier'],
            'won' => $result['won'],
            'won_promotion_name' => $wonPromotionName,
            'won_promotion_id' => $wonPromotionId,
            'leaderboard' => $result['leaderboard'] ?? null,
            'reward' => $reward,
            'punch_card' => $punchCardAward,
            'promotion_qr_code' => $promotionQrCode ? [
                'id' => $promotionQrCode->id,
                'code' => $promotionQrCode->code,
                'name' => $promotionQrCode->name,
            ] : null,
            'user_promo_token' => $userPromoToken ? [
                'code' => $userPromoToken->code,
            ] : null,
            'is_high_score' => $gamePlay->is_high_score,
            'is_personal_best' => $gamePlay->is_personal_best,
            'badges_earned' => $badges,
            'xp_earned' => $xpEarned,
            'is_practice' => false,
            'rewards_disabled' => $funOnly,
            'rewards_disabled_reason' => $funOnly ? ($gamePlay->gameSession?->fun_only_reason ?? 'Rewards disabled for this QR code.') : null,
        ];
    }

    /**
     * Ensure a promotion has a dedicated promotion-type QR code (with generated image) for redemption.
     * This is important for QRcade wins so the user can see a QR image + short code in their portal.
     */
    protected function ensurePromotionQRCode(Promotion $promotion, ?QRCode $sourceQrCode = null): ?QRCode
    {
        // IMPORTANT:
        // Promotions can have QRcade (game) QR codes that reference promotion_id.
        // For wins + portal redemption, we need a dedicated PROMOTION-type QR so /promo/{code} works
        // and does not redirect back into /play/{code}.
        $existing = $promotion->qrCodes()
            ->where('type', 'promotion')
            ->select(['id', 'code', 'name', 'design'])
            ->first();
        $sourceDesign = null;
        if ($sourceQrCode && method_exists($sourceQrCode, 'getDesignWithDefaults')) {
            $candidate = $sourceQrCode->getDesignWithDefaults();
            if (is_array($candidate) && count($candidate) > 0) {
                unset($candidate['generated_path']);
                $sourceDesign = $candidate;
            }
        }

        if ($existing) {
            $existingDesign = is_array($existing->design) ? $existing->design : [];
            $autoCreated = (bool)($existingDesign['auto_created'] ?? false);
            if ($autoCreated && $sourceDesign) {
                $updatedDesign = array_merge($sourceDesign, [
                    'auto_created' => true,
                    'leaderboard_prize_source' => 'auto',
                ]);
                $existing->updateQuietly(['design' => $updatedDesign]);
                $path = $this->qrGenerator->generateFile(
                    $existing->getScanUrl(),
                    $existing->getDesignWithDefaults(),
                    'png'
                );
                $design = $existing->design ?? [];
                $design['generated_path'] = $path;
                $existing->updateQuietly(['design' => $design]);
                return $existing->fresh(['promotion']);
            }
            return $existing;
        }

        // Create a promotion QR code automatically
        $autoDesign = is_array($sourceDesign) ? $sourceDesign : [];
        $autoDesign['auto_created'] = true;
        $autoDesign['leaderboard_prize_source'] = 'auto';
        $qrCode = QRCode::create([
            'business_id' => $promotion->business_id,
            'name' => $promotion->name . ' (Promo)',
            'type' => 'promotion',
            'promotion_id' => $promotion->id,
            'destination_url' => null,
            // Mark as internal so it doesn't clutter the business QR list; still usable for portal redemption.
            'intended_use' => \App\Models\QRCode::INTENDED_USE_LEADERBOARD_PRIZE,
            'design' => $autoDesign,
            'is_active' => true,
        ]);

        // Generate and persist a QR image path so the promo page + portal can show it immediately.
        $path = $this->qrGenerator->generateFile(
            $qrCode->getScanUrl(),
            $qrCode->getDesignWithDefaults(),
            'png'
        );

        $design = $qrCode->design ?? [];
        $design['generated_path'] = $path;
        $qrCode->updateQuietly(['design' => $design]);

        return $qrCode->fresh(['promotion']);
    }

    /**
     * Award a single punch to a user's punch card promotion.
     * Returns updated punch card progress for the frontend.
     */
    protected function awardPunchCardPunch(Promotion $promotion, ?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        $customerCode = $this->customerCodeService->getOrCreate($user);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($promotion, $user, $customerCode) {
            // Find existing punch card by user_id OR customer_identifier (legacy/edge cases)
            // Lock the row to prevent concurrent punch operations from racing.
            $punchCard = PunchCard::where('promotion_id', $promotion->id)
                ->where(function ($q) use ($user, $customerCode) {
                    $q->where('user_id', $user->id);
                    if ($customerCode) {
                        $q->orWhere('customer_identifier', $customerCode);
                    }
                })
                ->lockForUpdate()
                ->first();

            if (!$punchCard) {
                $punchCard = PunchCard::create([
                    'promotion_id' => $promotion->id,
                    'user_id' => $user->id,
                    'customer_identifier' => $customerCode,
                    'punches' => 0,
                    'completed_cards' => 0,
                ]);
            } else {
                // Ensure it is linked to the account going forward
                if (!$punchCard->user_id) {
                    $punchCard->update(['user_id' => $user->id, 'customer_identifier' => $customerCode]);
                }
            }

            // Guard: don't punch beyond punches_required (race condition safety)
            $punchesRequired = (int) ($promotion->punches_required ?? 0);
            if ($punchesRequired > 0 && $punchCard->punches >= $punchesRequired) {
                // Card is already full -- treat as completion without adding a punch
                $punchCard->update([
                    'punches' => 0,
                    'completed_cards' => $punchCard->completed_cards + 1,
                    'last_punch_at' => now(),
                ]);

                return [
                    'promotion_id' => $promotion->id,
                    'total_required' => $punchesRequired,
                    'current_punches' => 0,
                    'completed_cards' => (int) $punchCard->completed_cards,
                    'last_punch_at' => $punchCard->last_punch_at?->diffForHumans(),
                    'card_completed' => true,
                ];
            }

            $punchCard->increment('punches');
            $punchCard->update(['last_punch_at' => now()]);

            $cardCompleted = false;
            if ($punchesRequired > 0 && $punchCard->punches >= $punchesRequired) {
                $punchCard->update([
                    'punches' => 0,
                    'completed_cards' => $punchCard->completed_cards + 1,
                ]);
                $cardCompleted = true;
            }

            return [
                'promotion_id' => $promotion->id,
                'total_required' => $punchesRequired,
                'current_punches' => (int) $punchCard->punches,
                'completed_cards' => (int) $punchCard->completed_cards,
                'last_punch_at' => $punchCard->last_punch_at?->diffForHumans(),
                'card_completed' => $cardCompleted,
            ];
        });
    }

    /**
     * Normalize score when the client reports 0 but game data indicates progress.
     */
    protected function normalizeScore(GamePlay $gamePlay, int $score, array $gameData = []): int
    {
        if ($score > 0) {
            return $score;
        }

        $gameType = $gamePlay->game?->type;
        if ($gameType !== Game::TYPE_SNAKE) {
            return $score;
        }

        $foodEaten = isset($gameData['foodEaten']) ? (int) $gameData['foodEaten'] : 0;
        $length = isset($gameData['length']) ? (int) $gameData['length'] : 0;

        if ($foodEaten <= 0 && $length > 1) {
            $foodEaten = max(0, $length - 1);
        }

        if ($foodEaten <= 0) {
            return $score;
        }

        // Snake scoring: sum_{i=1..n} (10 + 2i) = n^2 + 11n
        return (int) (($foodEaten * $foodEaten) + (11 * $foodEaten));
    }

    /**
     * Determine the game result based on QR code game config
     */
    protected function determineResult(GamePlay $gamePlay, int $score): array
    {
        $qrCodeGame = QRCodeGame::where('qr_code_id', $gamePlay->qr_code_id)
            ->where('game_id', $gamePlay->game_id)
            ->first();

        if (!$qrCodeGame) {
            return [
                'result' => GamePlay::RESULT_COMPLETE,
                'tier' => null,
                'won' => false,
                'promotion_id' => null,
                'leaderboard' => null,
                'server_game_data' => [],
            ];
        }

        // Leaderboard challenges:
        // Do NOT mark the play as a "win" until prizes are actually awarded at period end.
        // Instead, compute the player's projected rank and return it as metadata for UI.
        if ($qrCodeGame->win_mode === QRCodeGame::WIN_MODE_LEADERBOARD) {
            $leaderboardMeta = null;
            $serverData = [];

            if ($gamePlay->user) {
                [$rank, $leaderboard] = $this->getLeaderboardPositionForPlay($gamePlay, $score);

                $config = is_array($qrCodeGame->prize_config) ? $qrCodeGame->prize_config : [];
                $threshold = (int)($config['leaderboard_position'] ?? 3);
                $threshold = $threshold > 0 ? $threshold : 3;

                $leaderboardMeta = [
                    'rank' => $rank,
                    'threshold' => $threshold,
                    'in_prize_position' => ($rank !== null && $rank <= $threshold),
                    'leaderboard_id' => $leaderboard?->id,
                    'leaderboard_name' => $leaderboard?->name,
                    'period_key' => $leaderboard?->getCurrentPeriodKey(),
                ];

                $serverData['_leaderboard'] = $leaderboardMeta;
            }

            return [
                'result' => GamePlay::RESULT_COMPLETE,
                'tier' => null,
                'won' => false,
                'promotion_id' => null,
                'leaderboard' => $leaderboardMeta,
                'server_game_data' => $serverData,
            ];
        }

        // Calculate duration for speedrun mode
        $duration = null;
        if ($qrCodeGame->win_mode === QRCodeGame::WIN_MODE_TIME && $gamePlay->started_at) {
            $duration = $gamePlay->started_at->diffInSeconds(now());
        }

        // Get leaderboard position for leaderboard win mode
        $leaderboardPosition = null;
        if ($qrCodeGame->win_mode === QRCodeGame::WIN_MODE_LEADERBOARD && $gamePlay->user) {
            $leaderboardPosition = $this->getLeaderboardPositionForGame($gamePlay, $score);
        }

        $rewardResult = $qrCodeGame->determineReward($score, $duration, $leaderboardPosition);

        // Fallback: if a promotion exists on the QR but the QRCodeGame is missing promotion_id,
        // attach it so play-to-win always issues a prize/token.
        if (($rewardResult['won'] ?? false) && empty($rewardResult['promotion_id']) && $qrCodeGame->win_mode !== QRCodeGame::WIN_MODE_TIERED) {
            $qrCode = QRCode::find($gamePlay->qr_code_id);
            if ($qrCode && $qrCode->promotion_id) {
                $rewardResult['promotion_id'] = $qrCode->promotion_id;
                $rewardResult['reason'] = $rewardResult['reason'] ?: 'Play-to-win fallback (QR promotion)';
            }
        }

        return [
            'result' => $rewardResult['won'] ? GamePlay::RESULT_WIN : GamePlay::RESULT_COMPLETE,
            'tier' => $rewardResult['tier'],
            'won' => $rewardResult['won'],
            'promotion_id' => $rewardResult['promotion_id'],
            'leaderboard' => null,
            'server_game_data' => [],
        ];
    }

    /**
     * Get user's current leaderboard position for a game
     * This calculates what the rank would be AFTER this score is added
     * We temporarily update the leaderboard, get the rank, then let updateLeaderboards() do the full update
     */
    protected function getLeaderboardPositionForGame(GamePlay $gamePlay, int $score): ?int
    {
        if (!$gamePlay->user) {
            return null;
        }

        // Prefer explicit leaderboard link from QR code game
        $leaderboard = null;
        $qrCodeGame = QRCodeGame::where('qr_code_id', $gamePlay->qr_code_id)
            ->where('game_id', $gamePlay->game_id)
            ->where('is_active', true)
            ->first();

        if ($qrCodeGame && $qrCodeGame->leaderboard_id) {
            $leaderboard = Leaderboard::active()->find($qrCodeGame->leaderboard_id);
        }

        // Fallback: find game-specific leaderboard for this game and business
        if (!$leaderboard) {
            $leaderboard = Leaderboard::active()
                ->where('type', Leaderboard::TYPE_GAME_SPECIFIC)
                ->where('game_id', $gamePlay->game_id)
                ->where('business_id', $gamePlay->business_id)
                ->first();
        }

        // Fallback: location leaderboard (all games) if no game-specific leaderboard exists
        if (!$leaderboard) {
            $leaderboard = Leaderboard::active()
                ->where('type', Leaderboard::TYPE_LOCATION)
                ->where('business_id', $gamePlay->business_id)
                ->first();
        }

        if (!$leaderboard) {
            return null;
        }

        $periodKey = $leaderboard->getCurrentPeriodKey();
        
        // Get existing entry
        $entry = LeaderboardEntry::firstOrNew([
            'leaderboard_id' => $leaderboard->id,
            'user_id' => $gamePlay->user->id,
            'period_key' => $periodKey,
        ]);

        // Calculate projected score after this play (without saving)
        $currentScore = $entry->score ?? 0;
        $gamesPlayed = $entry->games_played ?? 0;
        
        $projectedScore = match ($leaderboard->score_type) {
            Leaderboard::SCORE_HIGHEST => max($currentScore, $score),
            Leaderboard::SCORE_CUMULATIVE => $currentScore + $score,
            Leaderboard::SCORE_AVERAGE => $gamesPlayed > 0 
                ? round(($currentScore * $gamesPlayed + $score) / ($gamesPlayed + 1))
                : $score,
            default => $currentScore,
        };

        // Get all entries for this period
        $allEntries = LeaderboardEntry::where('leaderboard_id', $leaderboard->id)
            ->where('period_key', $periodKey)
            ->get();

        // Calculate rank by counting entries with higher scores
        // For ties, earlier entries rank higher
        $rank = 1;
        $userCreatedAt = $entry->created_at ?? now();
        
        foreach ($allEntries as $otherEntry) {
            if ($otherEntry->user_id === $gamePlay->user->id) {
                continue; // Skip self
            }
            
            if ($otherEntry->score > $projectedScore) {
                $rank++;
            } elseif ($otherEntry->score === $projectedScore) {
                // Tie-breaker: earlier entry gets better rank
                if ($otherEntry->created_at && $otherEntry->created_at < $userCreatedAt) {
                    $rank++;
                }
            }
        }

        return $rank;
    }

    /**
     * Returns [rank, leaderboard] for a play, or [null, null] if no leaderboard applies.
     */
    protected function getLeaderboardPositionForPlay(GamePlay $gamePlay, int $score): array
    {
        if (!$gamePlay->user) {
            return [null, null];
        }

        // Prefer explicit leaderboard link from QR code game
        $leaderboard = null;
        $qrCodeGame = QRCodeGame::where('qr_code_id', $gamePlay->qr_code_id)
            ->where('game_id', $gamePlay->game_id)
            ->where('is_active', true)
            ->first();

        if ($qrCodeGame && $qrCodeGame->leaderboard_id) {
            $leaderboard = Leaderboard::active()->find($qrCodeGame->leaderboard_id);
        }

        // Fallback: prefer game-specific leaderboard, fall back to location.
        if (!$leaderboard) {
            $leaderboard = Leaderboard::active()
                ->where('type', Leaderboard::TYPE_GAME_SPECIFIC)
                ->where('game_id', $gamePlay->game_id)
                ->where('business_id', $gamePlay->business_id)
                ->first();
        }

        if (!$leaderboard) {
            $leaderboard = Leaderboard::active()
                ->where('type', Leaderboard::TYPE_LOCATION)
                ->where('business_id', $gamePlay->business_id)
                ->first();
        }

        if (!$leaderboard) {
            return [null, null];
        }

        // Reuse existing rank calc logic.
        $rank = $this->getLeaderboardPositionForGame($gamePlay, $score);
        return [$rank, $leaderboard];
    }

    /**
     * Award a prize to the player
     */
    public function awardPrize(GamePlay $gamePlay, array $result): ?GameReward
    {
        if (!$result['won'] || !$result['promotion_id']) {
            return null;
        }

        return $this->prizeService->createReward(
            $gamePlay,
            $result['promotion_id'],
            $result['tier']
        );
    }

    /**
     * Create a leaderboard prize reward for a leaderboard entry
     */
    public function createLeaderboardPrizeReward(LeaderboardEntry $entry, Promotion $promotion): ?GameReward
    {
        $qrCodeId = $entry->leaderboard->qr_code_id;
        
        // Robustness: If leaderboard has no specific QR code, find/create one for the session constraint
        if (!$qrCodeId) {
            $qrCodeId = QRCode::where('business_id', $entry->business_id)->first()?->id;
            if (!$qrCodeId) {
                $qrCode = QRCode::create([
                    'business_id' => $entry->business_id,
                    'code' => QRCode::generateUniqueCode(),
                    'type' => 'static', // Use valid type
                    'name' => 'System Placeholder',
                ]);
                $qrCodeId = $qrCode->id;
            }
        }

        // Create a dummy/system session for this prize award (required by FK constraints)
        $session = GameSession::create([
            'session_token' => (string) Str::uuid(),
            'user_id' => $entry->user_id,
            'qr_code_id' => $qrCodeId,
            'game_id' => $entry->leaderboard->game_id, // Assuming game-specific for now, or needs fallback
            'business_id' => $entry->business_id,
            'status' => GameSession::STATUS_COMPLETED,
            'location_status' => GameSession::LOCATION_VERIFIED,
        ]);

        // Create placeholder GamePlay for leaderboard prize
        $gamePlay = GamePlay::create([
            'game_session_id' => $session->id,
            'user_id' => $entry->user_id,
            'business_id' => $entry->business_id,
            'game_id' => $entry->leaderboard->game_id ?? null,
            'qr_code_id' => $qrCodeId,
            'score' => $entry->score,
            'result' => GamePlay::RESULT_WIN,
            'duration_seconds' => 0,
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
        ]);
        
        // Create reward with leaderboard entry reference
        return $this->prizeService->createReward(
            $gamePlay,
            $promotion->id,
            null, // no tier for leaderboard prizes
            $entry // pass leaderboard entry for per-period limit check
        );
    }

    /**
     * Update relevant leaderboards
     */
    protected function updateLeaderboards(GamePlay $gamePlay): void
    {
        if (!$gamePlay->user) {
            return;
        }

        // Option A: Instant-win QRcade plays should NOT update leaderboards.
        // If this QR+game has an immediate prize configured, treat it as a "one-off win" experience, not a leaderboard challenge.
        $qrCodeGame = QRCodeGame::where('qr_code_id', $gamePlay->qr_code_id)
            ->where('game_id', $gamePlay->game_id)
            ->where('is_active', true)
            ->first();

        if ($qrCodeGame) {
            $hasImmediatePrize = !empty($qrCodeGame->promotion_id);
            $tierRewards = is_array($qrCodeGame->tier_rewards) ? $qrCodeGame->tier_rewards : [];
            $hasTierPrizes = collect($tierRewards)->filter()->count() > 0;

            // Allow leaderboard updates if the win mode is explicitly "leaderboard",
            // even if a prize is attached (because the prize is for the leaderboard winner).
            $isLeaderboardMode = $qrCodeGame->win_mode === QRCodeGame::WIN_MODE_LEADERBOARD;

            if (($hasImmediatePrize || $hasTierPrizes) && !$isLeaderboardMode) {
                return;
            }
        }

        // ── Explicit leaderboard link (Phase 1+) ──
        // If this QR code game has an explicit leaderboard_id, route the score
        // directly to that leaderboard instead of broadcasting to all matches.
        if ($qrCodeGame && $qrCodeGame->leaderboard_id) {
            $leaderboard = Leaderboard::active()->find($qrCodeGame->leaderboard_id);

            if ($leaderboard) {
                // Advance through all missed periods so the leaderboard is current.
                // Each call to reset() advances one period; loop until caught up.
                $resetGuard = 0;
                while ($leaderboard->needsReset() && $resetGuard < 400) {
                    $leaderboard->reset();
                    $resetGuard++;
                }

                $leaderboard->updateUserScore(
                    $gamePlay->user,
                    $gamePlay->score,
                    1,
                    $gamePlay->result === GamePlay::RESULT_WIN
                );
            }

            // Even with an explicit link, also update location/global leaderboards
            // (they aggregate all games at a location, so they should still be updated).
            $this->updateBroadcastLeaderboards($gamePlay, true);
            return;
        }

        // ── Fallback: broadcast to all matching leaderboards (legacy) ──
        $this->updateBroadcastLeaderboards($gamePlay, false);
    }

    /**
     * Broadcast score to location/global leaderboards (and optionally game-specific ones).
     *
     * @param bool $skipGameSpecific When true, skip game_specific leaderboards (already handled by explicit link).
     */
    protected function updateBroadcastLeaderboards(GamePlay $gamePlay, bool $skipGameSpecific): void
    {
        // Check if leaderboard is enabled for this specific game
        $businessGame = \App\Models\BusinessGame::where('business_id', $gamePlay->business_id)
            ->where('game_id', $gamePlay->game_id)
            ->first();
        
        // If leaderboard is explicitly disabled for this game, skip location leaderboards
        // But still allow game_specific leaderboards (they explicitly target this game)
        $skipLocationLeaderboards = $businessGame && $businessGame->leaderboard_enabled === false;

        // Get applicable leaderboards
        $leaderboards = Leaderboard::active()
            ->where(function ($query) use ($gamePlay, $skipLocationLeaderboards, $skipGameSpecific) {
                // Game-specific leaderboards: include if they match this game
                // (skip if already handled by explicit leaderboard_id link)
                if (!$skipGameSpecific) {
                    $query->where(function ($q) use ($gamePlay) {
                        $q->where('type', Leaderboard::TYPE_GAME_SPECIFIC)
                          ->where('game_id', $gamePlay->game_id)
                          ->where('business_id', $gamePlay->business_id);
                    });
                }
                
                // Location leaderboards: only include if leaderboard is enabled for this game
                if (!$skipLocationLeaderboards) {
                    $method = $skipGameSpecific ? 'where' : 'orWhere';
                    $query->$method(function ($q) use ($gamePlay) {
                        $q->where('type', Leaderboard::TYPE_LOCATION)
                          ->where('business_id', $gamePlay->business_id);
                    });
                }
                
                // Global leaderboards: include if leaderboard is enabled for this game
                if (!$skipLocationLeaderboards) {
                    $query->orWhere('type', Leaderboard::TYPE_GLOBAL);
                }
            })
            ->get();

        foreach ($leaderboards as $leaderboard) {
            // Advance through all missed periods so the leaderboard is current.
            $resetGuard = 0;
            while ($leaderboard->needsReset() && $resetGuard < 400) {
                $leaderboard->reset();
                $resetGuard++;
            }

            $leaderboard->updateUserScore(
                $gamePlay->user,
                $gamePlay->score,
                1,
                $gamePlay->result === GamePlay::RESULT_WIN
            );
        }
    }

    /**
     * Check for and award achievements/badges
     */
    public function checkAchievements(GamePlay $gamePlay): array
    {
        $earnedBadges = [];

        if (!$gamePlay->user) {
            return $earnedBadges;
        }

        $user = $gamePlay->user->fresh();

        // Get available badges for this game/business
        $badges = Badge::active()
            ->available()
            ->where(function ($query) use ($gamePlay) {
                $query->whereNull('game_id')
                    ->orWhere('game_id', $gamePlay->game_id);
            })
            ->where(function ($query) use ($gamePlay) {
                $query->whereNull('business_id')
                    ->orWhere('business_id', $gamePlay->business_id);
            })
            ->get();

        foreach ($badges as $badge) {
            if ($badge->checkRequirements($user, $gamePlay)) {
                $userBadge = $badge->awardTo($user, $gamePlay, $gamePlay->business_id);
                if ($userBadge) {
                    $earnedBadges[] = [
                        'id' => $badge->id,
                        'name' => $badge->name,
                        'icon' => $badge->icon,
                        'rarity' => $badge->rarity,
                        'points' => $badge->points,
                    ];
                }
            }
        }

        return $earnedBadges;
    }

    /**
     * Check for potential cheating/suspicious activity
     */
    protected function checkForCheating(GamePlay $gamePlay, int $score, int $duration, array $gameData): array
    {
        $suspicious = false;
        $reason = null;

        $game = $gamePlay->game;

        // Check if score is impossibly high.
        // Use the game's configured max_score when available; otherwise apply a sensible
        // fallback ceiling so the check is never silently skipped.
        $maxScore = $game->max_score ?: 100000; // Fallback: no legitimate game should exceed 100k
        if ($score > $maxScore) {
            $suspicious = true;
            $reason = $game->max_score
                ? 'Score exceeds maximum possible'
                : 'Score exceeds safety ceiling (max_score not configured)';
        }

        // Negative scores are never legitimate
        if ($score < 0) {
            $suspicious = true;
            $reason = 'Negative score submitted';
        }

        // Check if game was completed too quickly (only for games with time limits)
        if ($game->time_limit && $duration < 2) {
            $suspicious = true;
            $reason = 'Game completed impossibly fast';
        }

        // Check for rapid replays
        $recentPlays = GamePlay::where('user_id', $gamePlay->user_id)
            ->where('game_id', $gamePlay->game_id)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();

        if ($recentPlays > 10) {
            $suspicious = true;
            $reason = 'Too many plays in short period';
        }

        return [
            'is_suspicious' => $suspicious,
            'reason' => $reason,
        ];
    }

    /**
     * Detect device type from user agent
     */
    protected function detectDeviceType(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        if (str_contains($userAgent, 'mobile') || str_contains($userAgent, 'android')) {
            return 'mobile';
        }

        if (str_contains($userAgent, 'tablet') || str_contains($userAgent, 'ipad')) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * Check if user has completed today's daily puzzle game (e.g., Word Search)
     */
    public function hasCompletedDailyPuzzle(?User $user, Game $game, int $businessId): array
    {
        // Daily puzzle games that should be limited to one win per day
        $dailyPuzzleTypes = [
            Game::TYPE_WORD_SEARCH,
        ];

        if (!in_array($game->type, $dailyPuzzleTypes)) {
            return [
                'is_daily_puzzle' => false,
                'completed_today' => false,
                'can_play' => true,
            ];
        }

        $todayStart = now()->startOfDay();
        $dailySeed = now()->format('Y-m-d');

        // Check by user if logged in
        if ($user) {
            $completedToday = GamePlay::where('user_id', $user->id)
                ->where('game_id', $game->id)
                ->where('business_id', $businessId)
                ->where('result', GamePlay::RESULT_WIN)
                ->where('created_at', '>=', $todayStart)
                ->exists();

            if ($completedToday) {
                return [
                    'is_daily_puzzle' => true,
                    'completed_today' => true,
                    'can_play' => false,
                    'daily_seed' => $dailySeed,
                    'message' => 'Great job! You completed today\'s puzzle. Come back tomorrow for a new challenge!',
                    'next_puzzle_at' => now()->addDay()->startOfDay()->toISOString(),
                ];
            }
        }

        return [
            'is_daily_puzzle' => true,
            'completed_today' => false,
            'can_play' => true,
            'daily_seed' => $dailySeed,
        ];
    }

    /**
     * Validate daily puzzle submission
     */
    public function validateDailyPuzzle(GamePlay $gamePlay, array $gameData): array
    {
        $game = $gamePlay->game;

        // Only validate daily puzzle types
        if ($game->type !== Game::TYPE_WORD_SEARCH) {
            return ['valid' => true];
        }

        // Check if user already won today (prevents replay attacks)
        if ($gamePlay->user_id) {
            $alreadyWonToday = GamePlay::where('user_id', $gamePlay->user_id)
                ->where('game_id', $game->id)
                ->where('business_id', $gamePlay->business_id)
                ->where('result', GamePlay::RESULT_WIN)
                ->where('created_at', '>=', now()->startOfDay())
                ->where('id', '!=', $gamePlay->id)
                ->exists();

            if ($alreadyWonToday) {
                return [
                    'valid' => false,
                    'reason' => 'Already completed today\'s puzzle',
                ];
            }
        }

        // Validate the daily seed matches today
        $expectedSeed = now()->format('Y-m-d');
        $submittedSeed = $gameData['dailySeed'] ?? null;

        if ($submittedSeed && $submittedSeed !== $expectedSeed) {
            return [
                'valid' => false,
                'reason' => 'Invalid puzzle date',
            ];
        }

        // Validate the expected words match what the server generates for today
        $expectedWords = $this->generateDailyWordSearchWords($expectedSeed);
        $submittedWords = $gameData['expectedWords'] ?? [];

        if (!empty($submittedWords)) {
            sort($expectedWords);
            sort($submittedWords);

            if ($expectedWords !== $submittedWords) {
                return [
                    'valid' => false,
                    'reason' => 'Invalid puzzle configuration',
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Generate the daily word search words using seeded random (must match frontend algorithm)
     */
    protected function generateDailyWordSearchWords(string $seed): array
    {
        $allWords = ['DISCOUNT', 'REWARD', 'PLAY', 'WIN', 'GAME', 'PRIZE', 'BONUS', 'SAVE', 'DEAL', 'FREE', 'CASH', 'EARN', 'LUCKY', 'SCORE', 'POINTS', 'OFFER', 'VALUE', 'PROMO', 'TOKEN', 'JACKPOT'];
        
        // Seeded shuffle - must match frontend algorithm
        $hash = 0;
        for ($i = 0; $i < strlen($seed); $i++) {
            $char = ord($seed[$i]);
            $hash = (($hash << 5) - $hash) + $char;
            $hash = $hash & 0x7fffffff;
        }
        $seedValue = abs($hash);

        // Shuffle using same algorithm as frontend
        $shuffled = $allWords;
        for ($i = count($shuffled) - 1; $i > 0; $i--) {
            $seedValue = ($seedValue * 1103515245 + 12345) & 0x7fffffff;
            $random = $seedValue / 0x7fffffff;
            $j = (int)floor($random * ($i + 1));
            $temp = $shuffled[$i];
            $shuffled[$i] = $shuffled[$j];
            $shuffled[$j] = $temp;
        }

        return array_slice($shuffled, 0, 5);
    }

    /**
     * Store anonymous game play in session for later association with user account
     */
    protected function storeAnonymousGamePlay(GamePlay $gamePlay): void
    {
        $session = session();
        $anonymousPlays = $session->get('anonymous_game_plays', []);

        // Store essential game play data for later association
        $anonymousPlays[] = [
            'game_play_id' => $gamePlay->id,
            'game_session_id' => $gamePlay->game_session_id,
            'business_id' => $gamePlay->business_id,
            'game_id' => $gamePlay->game_id,
            'qr_code_id' => $gamePlay->qr_code_id,
            'score' => $gamePlay->score,
            'result' => $gamePlay->result,
            'reward_tier' => $gamePlay->reward_tier,
            'completed_at' => $gamePlay->completed_at,
            'has_reward' => $gamePlay->gameReward ? true : false,
            'reward_id' => $gamePlay->gameReward?->id,
        ];

        // Limit to last 10 anonymous plays to prevent session bloat
        if (count($anonymousPlays) > 10) {
            array_shift($anonymousPlays);
        }

        $session->put('anonymous_game_plays', $anonymousPlays);
    }

    /**
     * Associate anonymous game plays with a user account after they sign in
     */
    public function associateAnonymousGamePlays(User $user): int
    {
        $session = session();
        $anonymousPlays = $session->get('anonymous_game_plays', []);
        $associatedCount = 0;

        foreach ($anonymousPlays as $playData) {
            // Update the game play record to associate with the user
            $gamePlay = GamePlay::find($playData['game_play_id']);
            if ($gamePlay && !$gamePlay->user_id) {
                $gamePlay->update(['user_id' => $user->id]);

                // Update user stats
                $user->recordGamePlay($gamePlay);

                // Re-check for personal bests now that user is associated
                $gamePlay->update([
                    'is_personal_best' => $gamePlay->checkForPersonalBest(),
                ]);

                $associatedCount++;
            }
        }

        // Clear the anonymous plays from session
        $session->forget('anonymous_game_plays');

        return $associatedCount;
    }

    /**
     * Get aggregate game stats for a business
     */
    public function getBusinessGameStats(int $businessId, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $plays = GamePlay::where('business_id', $businessId)
            ->where('created_at', '>=', $startDate)
            ->get();

        $totalPlays = $plays->count();
        $uniquePlayers = $plays->pluck('user_id')->filter()->unique()->count();
        
        // Count wins (plays that resulted in a win)
        $wins = $plays->where('result', GamePlay::RESULT_WIN)->count();
        
        // Calculate win rate
        $winRate = $totalPlays > 0 ? round(($wins / $totalPlays) * 100, 1) : 0;

        // Calculate average score
        $avgScore = $plays->avg('score') ?? 0;

        // Get total rewards claimed from these plays
        // Note: This relies on GamePlay having a 'gameReward' relation or similar logic.
        // Since we don't have a direct 'rewards' collection on hand without querying, 
        // we can query GameReward separately or infer from wins if needed.
        // Let's query GameReward for accuracy on *claimed* rewards.
        $rewardsClaimed = GameReward::where('business_id', $businessId)
            ->where('created_at', '>=', $startDate)
            ->count();

        // Calculate engagement/scan-to-play conversion if we had scan data here, 
        // but for now just return play stats.

        return [
            'total_plays' => $totalPlays,
            'unique_players' => $uniquePlayers,
            'total_wins' => $wins,
            'win_rate' => $winRate,
            'avg_score' => round($avgScore),
            'rewards_generated' => $rewardsClaimed,
        ];
    }
}

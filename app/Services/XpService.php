<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\GamePlay;
use App\Models\GameReward;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Redemption;
use App\Models\Scan;
use App\Models\User;
use App\Models\UserXpEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class XpService
{
    public const SOURCE_SCAN = 'scan';
    public const SOURCE_GAME = 'game';
    public const SOURCE_REDEMPTION = 'redemption';
    public const SOURCE_REWARD_REDEMPTION = 'reward_redemption';
    public const SOURCE_BADGE = 'badge';
    public const SOURCE_MERCH_SCAN = 'merch_scan';
    public const SOURCE_MERCH_REDEMPTION = 'merch_redemption';

    /**
     * Pure function helper for tuning/debugging (no DB writes).
     */
    public function estimateRedemptionXpFromSavings(float $savings): int
    {
        return $this->calculateRedemptionXpFromSavings($savings);
    }

    /**
     * Award XP for a NEW scan event (supplemental; constrained by non-monetary daily cap).
     */
    public function awardForScan(User $user, Scan $scan, QRCode $qrCode, ?Carbon $at = null): int
    {
        $at = $at ?: now();
        $raw = (int) config('xp.scan.xp', 5);
        $allowed = $this->applyNonMonetaryDailyCap($user, $raw, $at);

        if ($allowed <= 0) {
            return 0;
        }

        return $this->persistAward($user, [
            'source' => self::SOURCE_SCAN,
            'amount' => $allowed,
            'qr_code_id' => $qrCode->id,
            'promotion_id' => $qrCode->promotion_id ? (int) $qrCode->promotion_id : null,
            'context' => [
                'scan_id' => $scan->id,
                'scan_type' => $scan->scan_type,
            ],
            'at' => $at,
        ]);
    }

    /**
     * Award XP to an ambassador for a unique merch scan.
     */
    public function awardForMerchScan(User $user, Scan $scan, ?Carbon $at = null): int
    {
        $at = $at ?: now();
        $raw = (int) config('xp.merch.scan_xp', 50);
        $allowed = $this->applyNonMonetaryDailyCap($user, $raw, $at);

        if ($allowed <= 0) {
            return 0;
        }

        return $this->persistAward($user, [
            'source' => self::SOURCE_MERCH_SCAN,
            'amount' => $allowed,
            'qr_code_id' => $scan->qr_code_id ? (int) $scan->qr_code_id : null,
            'promotion_id' => null,
            'context' => [
                'scan_id' => $scan->id,
                'merch_tag_id' => $scan->merch_tag_id,
            ],
            'at' => $at,
        ]);
    }

    /**
     * Award XP to an ambassador when a merch-referred redemption happens.
     */
    public function awardForMerchRedemption(User $user, Redemption $redemption, ?Carbon $at = null): int
    {
        $at = $at ?: ($redemption->redeemed_at ?: now());
        $raw = (int) config('xp.merch.redemption_xp', 250);

        if ($raw <= 0) {
            return 0;
        }

        return $this->persistAward($user, [
            'source' => self::SOURCE_MERCH_REDEMPTION,
            'amount' => $raw,
            'promotion_id' => $redemption->promotion_id ? (int) $redemption->promotion_id : null,
            'qr_code_id' => $redemption->qr_code_id ? (int) $redemption->qr_code_id : null,
            'context' => [
                'redemption_id' => $redemption->id,
                'scan_id' => $redemption->scan_id,
            ],
            'at' => $at,
        ]);
    }

    /**
     * Award XP for a game play (supplemental; constrained by non-monetary daily cap).
     *
     * NOTE: Callers should apply anti-farming gating (e.g. first play today or personal best).
     */
    public function awardForGamePlay(User $user, GamePlay $gamePlay, ?Carbon $at = null): int
    {
        $at = $at ?: now();
        $score = (int) ($gamePlay->score ?? 0);

        $divisor = max(1, (int) config('xp.game.score_divisor', 40));
        $min = (int) config('xp.game.min_per_award', 10);
        $max = (int) config('xp.game.max_per_award', 100);

        $raw = $score > 0 ? (int) round($score / $divisor) : $min;
        $raw = max($min, min($max, $raw));

        $allowed = $this->applyNonMonetaryDailyCap($user, $raw, $at);
        if ($allowed <= 0) {
            return 0;
        }

        return $this->persistAward($user, [
            'source' => self::SOURCE_GAME,
            'amount' => $allowed,
            'qr_code_id' => $gamePlay->qr_code_id ? (int) $gamePlay->qr_code_id : null,
            'promotion_id' => null,
            'game_id' => $gamePlay->game_id ? (int) $gamePlay->game_id : null,
            'context' => [
                'game_play_id' => $gamePlay->id,
                'score' => $score,
                'is_personal_best' => (bool) $gamePlay->is_personal_best,
            ],
            'at' => $at,
        ]);
    }

    /**
     * Award XP for a promotion redemption, scaled by effective savings (primary progression source).
     */
    public function awardForRedemption(User $user, Redemption $redemption, ?Promotion $promotion = null, ?Carbon $at = null): int
    {
        $at = $at ?: ($redemption->redeemed_at ?: now());
        $promotion = $promotion ?: $redemption->promotion;

        $savings = $this->calculateEffectiveSavingsForRedemption($redemption, $promotion);
        $raw = $this->calculateRedemptionXpFromSavings($savings);

        // Diminishing returns: repeated redemptions of the same promo on the same day.
        if ($promotion && $promotion->id) {
            $raw = $this->applySamePromoRepeatFactor($user, $raw, $at, (int) $promotion->id);
        }

        if ($raw <= 0) {
            return 0;
        }

        return $this->persistAward($user, [
            'source' => self::SOURCE_REDEMPTION,
            'amount' => $raw,
            'promotion_id' => $promotion?->id,
            'qr_code_id' => $redemption->qr_code_id ? (int) $redemption->qr_code_id : null,
            'context' => [
                'redemption_id' => $redemption->id,
                'discount_amount' => (float) ($redemption->discount_amount ?? 0),
                'original_amount' => (float) ($redemption->original_amount ?? 0),
                'final_amount' => (float) ($redemption->final_amount ?? 0),
                'effective_savings' => $savings,
                'discount_type' => $promotion?->discount_type,
                'card_completed' => (bool) ($redemption->card_completed ?? false),
            ],
            'at' => $at,
        ]);
    }

    /**
     * Award XP when a GameReward is redeemed. Uses reward.discount_value as savings proxy.
     */
    public function awardForRewardRedemption(User $user, GameReward $reward, ?Promotion $promotion = null, ?Carbon $at = null): int
    {
        $at = $at ?: ($reward->redeemed_at ?: now());
        $promotion = $promotion ?: $reward->promotion;

        $savings = (float) ($reward->discount_value ?? 0);
        if ($savings <= 0 && $promotion && $promotion->discount_type === Promotion::TYPE_PUNCH_CARD) {
            $savings = (float) ($promotion->reward_value ?? 0);
        }

        $raw = $this->calculateRedemptionXpFromSavings($savings);
        if ($promotion && $promotion->id) {
            $raw = $this->applySamePromoRepeatFactor($user, $raw, $at, (int) $promotion->id);
        }

        if ($raw <= 0) {
            return 0;
        }

        return $this->persistAward($user, [
            'source' => self::SOURCE_REWARD_REDEMPTION,
            'amount' => $raw,
            'promotion_id' => $promotion?->id,
            'qr_code_id' => null,
            'context' => [
                'game_reward_id' => $reward->id,
                'discount_value' => (float) ($reward->discount_value ?? 0),
                'effective_savings' => $savings,
                'reward_type' => $reward->reward_type,
            ],
            'at' => $at,
        ]);
    }

    /**
     * Award XP for earning a badge (not capped; badges are non-repeatable by design).
     */
    public function awardForBadge(User $user, Badge $badge, ?int $businessId = null, ?Carbon $at = null): int
    {
        $at = $at ?: now();
        $xpPerPoint = (int) config('xp.badge.xp_per_point', 10);
        $raw = max(0, (int) ($badge->points ?? 0) * $xpPerPoint);

        if ($raw <= 0) {
            return 0;
        }

        return $this->persistAward($user, [
            'source' => self::SOURCE_BADGE,
            'amount' => $raw,
            'badge_id' => $badge->id,
            'promotion_id' => null,
            'qr_code_id' => null,
            'context' => [
                'badge_slug' => $badge->slug,
                'badge_points' => (int) ($badge->points ?? 0),
                'business_id' => $businessId,
            ],
            'at' => $at,
        ]);
    }

    private function calculateEffectiveSavingsForRedemption(Redemption $redemption, ?Promotion $promotion): float
    {
        $discount = (float) ($redemption->discount_amount ?? 0);
        $discount = max(0.0, $discount);

        // Punch card: only final prize redemption should have savings; fall back to configured reward_value if needed.
        if ($promotion && $promotion->discount_type === Promotion::TYPE_PUNCH_CARD) {
            $isFinal = (bool) ($redemption->card_completed ?? false);
            if (!$isFinal) {
                return 0.0;
            }
            $rewardValue = (float) ($promotion->reward_value ?? 0);
            return max($discount, max(0.0, $rewardValue));
        }

        return $discount;
    }

    private function calculateRedemptionXpFromSavings(float $savings): int
    {
        $savings = max(0.0, $savings);
        if ($savings <= 0) {
            return 0;
        }

        $base = (int) config('xp.redemption.base', 250);
        $k = (int) config('xp.redemption.k', 2000);
        $max = (int) config('xp.redemption.max_per_award', 10000);

        $xp = (int) round($base + ($k * sqrt($savings)));
        $xp = max(0, min($max, $xp));

        return $xp;
    }

    private function applyNonMonetaryDailyCap(User $user, int $rawAmount, Carbon $at): int
    {
        $rawAmount = max(0, $rawAmount);
        if ($rawAmount === 0) {
            return 0;
        }

        $cap = (int) config('xp.non_monetary.daily_cap', 250);
        if ($cap <= 0) {
            return 0;
        }

        $occurredOn = $at->toDateString();
        $already = (int) UserXpEvent::query()
            ->where('user_id', $user->id)
            ->where('occurred_on', $occurredOn)
            ->whereIn('source', [self::SOURCE_SCAN, self::SOURCE_GAME])
            ->sum('amount');

        $remaining = $cap - $already;
        if ($remaining <= 0) {
            return 0;
        }

        return min($rawAmount, $remaining);
    }

    private function applySamePromoRepeatFactor(User $user, int $rawAmount, Carbon $at, int $promotionId): int
    {
        $rawAmount = max(0, $rawAmount);
        if ($rawAmount === 0) {
            return 0;
        }

        $occurredOn = $at->toDateString();
        $alreadyCount = (int) UserXpEvent::query()
            ->where('user_id', $user->id)
            ->where('occurred_on', $occurredOn)
            ->where('promotion_id', $promotionId)
            ->whereIn('source', [self::SOURCE_REDEMPTION, self::SOURCE_REWARD_REDEMPTION])
            ->count();

        if ($alreadyCount <= 0) {
            return $rawAmount;
        }

        $factor = (float) config('xp.redemption.repeat_factor_same_promo_same_day', 0.25);
        $min = (int) config('xp.redemption.repeat_min_xp', 100);
        $discounted = (int) round($rawAmount * max(0.0, $factor));

        return max(0, max($min, $discounted));
    }

    /**
     * Persist the XP award and update user xp/level atomically.
     *
     * Expected payload keys:
     * - source (string), amount (int), at (Carbon)
     * - optional: promotion_id, qr_code_id, game_id, badge_id, context (array|null)
     */
    private function persistAward(User $user, array $payload): int
    {
        $amount = (int) ($payload['amount'] ?? 0);
        if ($amount <= 0) {
            return 0;
        }

        /** @var Carbon $at */
        $at = $payload['at'] ?? now();
        $occurredOn = $at->toDateString();

        return (int) DB::transaction(function () use ($user, $payload, $amount, $occurredOn, $at) {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();
            if (!$lockedUser) {
                return 0;
            }

            UserXpEvent::create([
                'user_id' => $lockedUser->id,
                'source' => (string) $payload['source'],
                'amount' => $amount,
                'promotion_id' => isset($payload['promotion_id']) && $payload['promotion_id'] ? (int) $payload['promotion_id'] : null,
                'qr_code_id' => isset($payload['qr_code_id']) && $payload['qr_code_id'] ? (int) $payload['qr_code_id'] : null,
                'game_id' => isset($payload['game_id']) && $payload['game_id'] ? (int) $payload['game_id'] : null,
                'badge_id' => isset($payload['badge_id']) && $payload['badge_id'] ? (int) $payload['badge_id'] : null,
                'context' => isset($payload['context']) ? $payload['context'] : null,
                'occurred_on' => $occurredOn,
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            $lockedUser->addXp($amount);
            return $amount;
        });
    }
}


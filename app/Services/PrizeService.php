<?php

namespace App\Services;

use App\Models\GamePlay;
use App\Models\GameReward;
use App\Models\LeaderboardEntry;
use App\Models\Promotion;
use App\Models\QRCodeGame;
use App\Services\RewardCodeService;

class PrizeService
{
    /**
     * Create a reward from a game play
     */
    public function createReward(GamePlay $gamePlay, int $promotionId, ?string $tier = null, ?LeaderboardEntry $leaderboardEntry = null): ?GameReward
    {
        $promotion = Promotion::find($promotionId);
        
        if (!$promotion) {
            return null;
        }

        // Prevent duplicate rewards for the same game play
        $existingReward = GameReward::where('game_play_id', $gamePlay->id)
            ->where('promotion_id', $promotionId)
            ->first();
        
        if ($existingReward) {
            // Reward already exists for this game play and promotion
            \Log::info('Reward creation blocked: duplicate game play reward', [
                'game_play_id' => $gamePlay->id,
                'promotion_id' => $promotionId,
                'existing_reward_id' => $existingReward->id,
            ]);
            return $existingReward; // Return existing reward instead of creating duplicate
        }

        // Check promotion limits before creating reward
        if ($gamePlay->user_id) {
            $rules = $promotion->rules ?? [];
            
            // Check max_redemptions_per_user limit (global lifetime cap).
            // Counts unredeemed GameRewards (AVAILABLE/CLAIMED) + actual Redemptions
            // to enforce the promo rules consistently for both leaderboard and regular prizes.
            if (isset($rules['max_redemptions_per_user']) && $rules['max_redemptions_per_user'] > 0) {
                $unredeemedRewards = GameReward::where('user_id', $gamePlay->user_id)
                    ->where('promotion_id', $promotionId)
                    ->whereIn('status', [GameReward::STATUS_AVAILABLE, GameReward::STATUS_CLAIMED])
                    ->count();

                $redeemedCount = \App\Models\Redemption::where('promotion_id', $promotionId)
                    ->where('customer_user_id', $gamePlay->user_id)
                    ->count();

                $totalCount = $unredeemedRewards + $redeemedCount;

                if ($totalCount >= $rules['max_redemptions_per_user']) {
                    \Log::info('Reward creation blocked: user limit reached', [
                        'user_id' => $gamePlay->user_id,
                        'promotion_id' => $promotionId,
                        'unredeemed_rewards' => $unredeemedRewards,
                        'redeemed_count' => $redeemedCount,
                        'total' => $totalCount,
                        'max_allowed' => $rules['max_redemptions_per_user'],
                        'is_leaderboard_prize' => $leaderboardEntry !== null,
                    ]);
                    return null;
                }
            }
        }

        // Determine reward details based on promotion type
        $rewardDetails = $this->getRewardDetails($promotion, $tier);

        return GameReward::create([
            'reward_code' => app(RewardCodeService::class)->generateUniqueCode(),
            'game_play_id' => $gamePlay->id,
            'leaderboard_entry_id' => $leaderboardEntry?->id,
            'user_id' => $gamePlay->user_id,
            'business_id' => $gamePlay->business_id,
            'promotion_id' => $promotionId,
            'reward_type' => $rewardDetails['type'],
            'tier' => $tier,
            'discount_value' => $rewardDetails['discount_value'],
            'free_item' => $rewardDetails['free_item'],
            'description' => $rewardDetails['description'],
            'status' => GameReward::STATUS_AVAILABLE,
            'expires_at' => $this->calculateExpiry($promotion),
            'next_visit_only' => $promotion->rules['next_visit_only'] ?? false,
            'valid_from' => now(),
            'valid_until' => $promotion->ends_at,
        ]);
    }

    /**
     * Get reward details based on promotion type
     */
    protected function getRewardDetails(Promotion $promotion, ?string $tier): array
    {
        $details = [
            'type' => GameReward::TYPE_PERCENTAGE,
            'discount_value' => null,
            'free_item' => null,
            'description' => $promotion->description,
        ];

        // Apply tier modifiers if applicable
        $tierMultiplier = match ($tier) {
            'gold' => 1.0,
            'silver' => 0.75,
            'bronze' => 0.5,
            default => 1.0,
        };

        switch ($promotion->discount_type) {
            case Promotion::TYPE_PERCENTAGE:
                $details['type'] = GameReward::TYPE_PERCENTAGE;
                $details['discount_value'] = round($promotion->discount_value * $tierMultiplier, 2);
                $details['description'] = "{$details['discount_value']}% off your purchase";
                break;

            case Promotion::TYPE_FIXED_AMOUNT:
                $details['type'] = GameReward::TYPE_FIXED;
                $details['discount_value'] = round($promotion->discount_value * $tierMultiplier, 2);
                $details['description'] = "\${$details['discount_value']} off your purchase";
                break;

            case Promotion::TYPE_BOGO:
            case Promotion::TYPE_BUY_X_GET_Y:
                $details['type'] = GameReward::TYPE_FREE_ITEM;
                $details['free_item'] = $promotion->name;
                $details['description'] = $promotion->getDisplayDescription();
                break;

            default:
                $details['description'] = $promotion->name;
        }

        return $details;
    }

    /**
     * Calculate reward expiry date
     */
    protected function calculateExpiry(Promotion $promotion): \Carbon\Carbon
    {
        // Check promotion rules for custom expiry
        $rules = $promotion->rules ?? [];
        
        if (isset($rules['reward_expires_days'])) {
            return now()->addDays($rules['reward_expires_days']);
        }

        // Default: 30 days or promotion end date, whichever is sooner
        $defaultExpiry = now()->addDays(30);
        
        if ($promotion->ends_at && $promotion->ends_at->lt($defaultExpiry)) {
            return $promotion->ends_at;
        }

        return $defaultExpiry;
    }

    /**
     * Calculate reward based on score with probability
     */
    public function calculateReward(QRCodeGame $qrCodeGame, int $score): array
    {
        return $qrCodeGame->determineReward($score);
    }

    /**
     * Apply win probability for random mode
     */
    public function applyProbability(int $probability): bool
    {
        return rand(1, 100) <= $probability;
    }

    /**
     * Determine tier based on score
     */
    public function determineTier(int $score, array $scoreTiers): ?string
    {
        if (empty($scoreTiers)) {
            return null;
        }

        // Sort tiers by score requirement (highest first)
        arsort($scoreTiers);

        foreach ($scoreTiers as $tier => $requiredScore) {
            if ($score >= $requiredScore) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * Get mystery prize (random from available rewards)
     */
    public function getMysteryPrize(int $businessId): ?array
    {
        $promotions = Promotion::where('business_id', $businessId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->get();

        if ($promotions->isEmpty()) {
            return null;
        }

        $promotion = $promotions->random();

        return [
            'promotion_id' => $promotion->id,
            'name' => $promotion->name,
            'description' => $promotion->getDisplayDescription(),
        ];
    }

    /**
     * Create a badge-only reward (no discount)
     */
    public function createBadgeReward(GamePlay $gamePlay, string $badgeName): GameReward
    {
        return GameReward::create([
            'reward_code' => app(RewardCodeService::class)->generateUniqueCode(),
            'game_play_id' => $gamePlay->id,
            'user_id' => $gamePlay->user_id,
            'business_id' => $gamePlay->business_id,
            'reward_type' => GameReward::TYPE_BADGE,
            'description' => "Badge: {$badgeName}",
            'status' => GameReward::STATUS_REDEEMED, // Badges are auto-redeemed
            'redeemed_at' => now(),
        ]);
    }

    /**
     * Get reward statistics for a business
     */
    public function getRewardStats(int $businessId, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $rewards = GameReward::where('business_id', $businessId)
            ->where('created_at', '>=', $startDate)
            ->get();

        $redeemed = $rewards->where('status', GameReward::STATUS_REDEEMED);
        $expired = $rewards->where('status', GameReward::STATUS_EXPIRED);

        return [
            'total_given' => $rewards->count(),
            'total_redeemed' => $redeemed->count(),
            'total_expired' => $expired->count(),
            'redemption_rate' => $rewards->count() > 0 
                ? round(($redeemed->count() / $rewards->count()) * 100, 1) 
                : 0,
            'total_value_given' => $rewards->sum('discount_value'),
            'total_value_redeemed' => $redeemed->sum('discount_value'),
            'by_tier' => [
                'gold' => $rewards->where('tier', 'gold')->count(),
                'silver' => $rewards->where('tier', 'silver')->count(),
                'bronze' => $rewards->where('tier', 'bronze')->count(),
                // Not all rewards use tiers; these help avoid confusion in analytics
                'participation' => $rewards->where('tier', 'participation')->count(),
                'untiered' => $rewards->whereNull('tier')->count(),
            ],
            'by_type' => [
                'percentage' => $rewards->where('reward_type', GameReward::TYPE_PERCENTAGE)->count(),
                'fixed' => $rewards->where('reward_type', GameReward::TYPE_FIXED)->count(),
                'free_item' => $rewards->where('reward_type', GameReward::TYPE_FREE_ITEM)->count(),
            ],
        ];
    }
}


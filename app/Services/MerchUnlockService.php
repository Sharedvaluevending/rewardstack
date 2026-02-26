<?php

namespace App\Services;

use App\Models\MerchUnlockRule;
use App\Models\UserMerchUnlock;
use App\Models\Order;
use App\Models\User;
use App\Models\Badge;

class MerchUnlockService
{
    /**
     * Process an order and apply any merch unlocks
     */
    public function processOrder(Order $order): array
    {
        $unlocks = [];
        $user = $order->user;

        if (!$user) {
            return $unlocks;
        }

        foreach ($order->items as $item) {
            $rules = MerchUnlockRule::where('product_id', $item->product_id)
                ->where('is_active', true)
                ->get();

            foreach ($rules as $rule) {
                $unlock = $this->createUnlock($user, $order, $rule);
                if ($unlock) {
                    $unlocks[] = $unlock;
                }
            }
        }

        return $unlocks;
    }

    /**
     * Create a user merch unlock
     */
    protected function createUnlock(User $user, Order $order, MerchUnlockRule $rule): ?UserMerchUnlock
    {
        // Check if already has this unlock
        $existing = UserMerchUnlock::where('user_id', $user->id)
            ->where('merch_unlock_rule_id', $rule->id)
            ->where('status', 'active')
            ->first();

        if ($existing && $rule->duration_type === 'permanent') {
            return null; // Already has permanent unlock
        }

        return UserMerchUnlock::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'merch_unlock_rule_id' => $rule->id,
            'status' => 'pending', // Will activate on delivery
        ]);
    }

    /**
     * Handle Printful delivery webhook
     */
    public function handleDelivery(string $printfulOrderId): void
    {
        $unlocks = UserMerchUnlock::where('printful_order_id', $printfulOrderId)
            ->where('status', 'pending')
            ->get();

        foreach ($unlocks as $unlock) {
            $unlock->markDelivered();
            
            // Award any exclusive badges
            $rule = $unlock->merchUnlockRule;
            if ($rule->unlock_type === MerchUnlockRule::UNLOCK_EXCLUSIVE_BADGE && $rule->badge_id) {
                $badge = Badge::find($rule->badge_id);
                if ($badge) {
                    $badge->awardTo($unlock->user);
                }
            }
        }
    }

    /**
     * Check if user has access to a game through merch
     */
    public function hasGameAccess(User $user, int $gameId): bool
    {
        return UserMerchUnlock::where('user_id', $user->id)
            ->active()
            ->whereHas('merchUnlockRule', function ($query) use ($gameId) {
                $query->where('game_id', $gameId)
                    ->orWhereHas('gamePack.games', function ($q) use ($gameId) {
                        $q->where('games.id', $gameId);
                    });
            })
            ->exists();
    }

    /**
     * Get user's active unlocks
     */
    public function getActiveUnlocks(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return UserMerchUnlock::where('user_id', $user->id)
            ->active()
            ->with('merchUnlockRule.game', 'merchUnlockRule.gamePack')
            ->get();
    }

    /**
     * Get reward multiplier for user (from merch unlocks)
     */
    public function getRewardMultiplier(User $user): float
    {
        $multiplier = 1.0;

        $unlocks = UserMerchUnlock::where('user_id', $user->id)
            ->active()
            ->whereHas('merchUnlockRule', function ($query) {
                $query->whereNotNull('reward_multiplier');
            })
            ->with('merchUnlockRule')
            ->get();

        foreach ($unlocks as $unlock) {
            if ($unlock->merchUnlockRule->reward_multiplier) {
                $multiplier = max($multiplier, $unlock->merchUnlockRule->reward_multiplier);
            }
        }

        return $multiplier;
    }

    /**
     * Get extra plays per day from merch unlocks
     */
    public function getExtraPlaysPerDay(User $user): int
    {
        return UserMerchUnlock::where('user_id', $user->id)
            ->active()
            ->whereHas('merchUnlockRule', function ($query) {
                $query->whereNotNull('extra_plays_per_day');
            })
            ->with('merchUnlockRule')
            ->get()
            ->sum(fn($u) => $u->merchUnlockRule->extra_plays_per_day ?? 0);
    }

    /**
     * Expire old unlocks
     */
    public function expireOldUnlocks(): int
    {
        return UserMerchUnlock::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Get available merch with unlock benefits
     */
    public function getMerchWithUnlocks(): \Illuminate\Database\Eloquent\Collection
    {
        return MerchUnlockRule::where('is_active', true)
            ->with(['product', 'game', 'gamePack', 'badge'])
            ->get()
            ->groupBy('product_id')
            ->map(function ($rules) {
                $product = $rules->first()->product;
                $product->unlocks = $rules->map(fn($r) => $r->getUnlockDescription());
                return $product;
            });
    }
}


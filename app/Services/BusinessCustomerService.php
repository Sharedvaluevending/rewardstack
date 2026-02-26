<?php

namespace App\Services;

use App\Models\BusinessCustomer;
use App\Models\BusinessCustomerSubscription;
use App\Models\Redemption;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BusinessCustomerService
{
    /**
     * Record a scan engagement for a logged-in customer.
     */
    public function recordScan(Scan $scan, bool $countAsScan = true): void
    {
        if (!$scan->business_id || !$scan->user_id) {
            return;
        }

        $user = User::find($scan->user_id);
        if (!$user) {
            return;
        }

        $now = now();

        $row = BusinessCustomer::firstOrCreate(
            ['business_id' => (int) $scan->business_id, 'user_id' => (int) $scan->user_id],
            [
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'scans_count' => 0,
                'saved_count' => 0,
                'redemptions_count' => 0,
                'game_plays_count' => 0,
                'rewards_won_count' => 0,
                'rewards_redeemed_count' => 0,
                'lifetime_savings' => 0,
            ]
        );

        if ($countAsScan) {
            $row->scans_count = (int) ($row->scans_count ?? 0) + 1;
        }
        $row->last_seen_at = $now;
        $row->level_at_last_seen = $user->level;
        $row->xp_at_last_seen = $user->xp;
        if ($scan->city) $row->city = $scan->city;
        if ($scan->region) $row->region = $scan->region;
        if ($scan->country) $row->country = $scan->country;
        $row->save();
    }

    public function recordSaved(int $businessId, int $userId): void
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        $now = now();

        $row = BusinessCustomer::firstOrCreate(
            ['business_id' => (int) $businessId, 'user_id' => (int) $userId],
            [
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'scans_count' => 0,
                'saved_count' => 0,
                'redemptions_count' => 0,
                'game_plays_count' => 0,
                'rewards_won_count' => 0,
                'rewards_redeemed_count' => 0,
                'lifetime_savings' => 0,
            ]
        );

        $row->saved_count = (int) ($row->saved_count ?? 0) + 1;
        $row->last_seen_at = $now;
        $row->level_at_last_seen = $user->level;
        $row->xp_at_last_seen = $user->xp;
        $row->save();
    }

    public function recordRedemption(Redemption $redemption): void
    {
        if (!$redemption->business_id || !$redemption->customer_user_id) {
            return;
        }

        $user = User::find($redemption->customer_user_id);
        if (!$user) {
            return;
        }

        $now = now();
        $discount = (float) ($redemption->discount_amount ?? 0);

        $row = BusinessCustomer::firstOrCreate(
            ['business_id' => (int) $redemption->business_id, 'user_id' => (int) $redemption->customer_user_id],
            [
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'scans_count' => 0,
                'saved_count' => 0,
                'redemptions_count' => 0,
                'game_plays_count' => 0,
                'rewards_won_count' => 0,
                'rewards_redeemed_count' => 0,
                'lifetime_savings' => 0,
            ]
        );

        $row->redemptions_count = (int) ($row->redemptions_count ?? 0) + 1;
        $row->lifetime_savings = (float) ($row->lifetime_savings ?? 0) + $discount;
        $row->last_seen_at = $now;
        $row->last_redeemed_at = $now;
        $row->level_at_last_seen = $user->level;
        $row->xp_at_last_seen = $user->xp;
        $row->save();
    }

    public function isSubscribed(int $businessId, int $userId): bool
    {
        return BusinessCustomerSubscription::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->whereNotNull('subscribed_at')
            ->whereNull('unsubscribed_at')
            ->exists();
    }

    public function subscribe(int $businessId, int $userId, ?string $source = null, ?string $ip = null, ?string $ua = null): BusinessCustomerSubscription
    {
        $sub = BusinessCustomerSubscription::firstOrNew([
            'business_id' => $businessId,
            'user_id' => $userId,
        ]);

        $sub->subscribed_at = $sub->subscribed_at ?: now();
        $sub->unsubscribed_at = null;
        $sub->source = $source ?: $sub->source;
        $sub->ip_address = $ip ?: $sub->ip_address;
        $sub->user_agent = $ua ?: $sub->user_agent;
        $sub->save();

        return $sub;
    }

    public function unsubscribe(int $businessId, int $userId, ?string $source = null): void
    {
        BusinessCustomerSubscription::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->update([
                'unsubscribed_at' => now(),
                'source' => $source ?: DB::raw('source'),
            ]);
    }
}


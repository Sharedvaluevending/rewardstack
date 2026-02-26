<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Notifications\ReferralCommissionEarned;
use Illuminate\Support\Facades\Log;

class ReferralCommissionService
{
    /**
     * Create commission when business pays for subscription
     */
    public function createCommissionForPayment(Business $business, float $amount, string $subscriptionPeriod = null, ?string $stripeInvoiceId = null): ?ReferralCommission
    {
        // Find active referral for this business
        $referral = Referral::where('business_id', $business->id)
            ->where('status', Referral::STATUS_ACTIVE)
            ->first();

        if (!$referral) {
            return null; // No referral, no commission
        }

        // Use current period if not provided
        if (!$subscriptionPeriod) {
            $subscriptionPeriod = now()->format('Y-m'); // e.g., "2024-12"
        }

        // Check if commission already exists for this period
        $existing = ReferralCommission::where('referral_id', $referral->id)
            ->where('subscription_period', $subscriptionPeriod)
            ->first();

        if ($existing) {
            Log::info("Commission already exists for business {$business->id}, period {$subscriptionPeriod}");
            return $existing;
        }

        // Calculate commission
        $commissionRate = $referral->commission_rate; // Percentage (e.g., 10.00 = 10%)
        $commissionAmount = ($amount * $commissionRate) / 100;

        // Mark referral as converted if this is first payment
        if (!$referral->converted_at) {
            $referral->update(['converted_at' => now()]);
        }

        // Create commission record
        $commission = ReferralCommission::create([
            'referral_id' => $referral->id,
            'referrer_id' => $referral->referrer_id,
            'business_id' => $business->id,
            'subscription_period' => $subscriptionPeriod,
            'stripe_invoice_id' => $stripeInvoiceId,
            'business_payment' => $amount,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'status' => ReferralCommission::STATUS_PENDING,
        ]);

        // Notify referrer (async)
        try {
            $referral->referrer?->notify(new ReferralCommissionEarned($commission));
        } catch (\Throwable $e) {
            Log::warning('Failed to send referral commission email', [
                'referrer_id' => $referral->referrer_id,
                'commission_id' => $commission->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info("Created referral commission", [
            'referral_id' => $referral->id,
            'referrer_id' => $referral->referrer_id,
            'business_id' => $business->id,
            'amount' => $amount,
            'commission' => $commissionAmount,
            'period' => $subscriptionPeriod,
        ]);

        return $commission;
    }

    /**
     * Auto-approve commissions (or keep pending for manual review)
     */
    public function approveCommission(ReferralCommission $commission): bool
    {
        $commission->update(['status' => ReferralCommission::STATUS_APPROVED]);
        return true;
    }

    /**
     * Get total pending commissions for a referrer
     */
    public function getPendingCommissions(int $referrerId): float
    {
        return ReferralCommission::where('referrer_id', $referrerId)
            ->where('status', ReferralCommission::STATUS_PENDING)
            ->sum('commission_amount');
    }

    /**
     * Get total approved commissions ready for payout
     */
    public function getApprovedCommissions(int $referrerId): float
    {
        return ReferralCommission::where('referrer_id', $referrerId)
            ->where('status', ReferralCommission::STATUS_APPROVED)
            ->sum('commission_amount');
    }
}


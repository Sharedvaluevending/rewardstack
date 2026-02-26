<?php

namespace Tests\Feature\Console;

use App\Models\ReferralCommission;
use App\Models\Referral;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApproveReferralCommissionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reports_none_when_no_pending_commissions(): void
    {
        $this->artisan('referrals:approve-commissions')
            ->expectsOutputToContain('No commissions ready for approval')
            ->assertExitCode(0);
    }

    public function test_command_approves_pending_commissions_older_than_30_days(): void
    {
        $referrer = User::factory()->create();
        $businessOwner = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create(['user_id' => $businessOwner->id]);
        $referral = Referral::create([
            'business_id' => $business->id,
            'referrer_id' => $referrer->id,
            'business_user_id' => $businessOwner->id,
            'referral_code' => 'TEST-' . uniqid(),
            'status' => Referral::STATUS_ACTIVE,
            'commission_rate' => 10.0,
        ]);
        $commission = ReferralCommission::create([
            'referral_id' => $referral->id,
            'referrer_id' => $referrer->id,
            'business_id' => $business->id,
            'subscription_period' => now()->subDays(60)->format('Y-m'),
            'business_payment' => 100,
            'commission_rate' => 10,
            'commission_amount' => 10,
            'status' => ReferralCommission::STATUS_PENDING,
        ]);
        \Illuminate\Support\Facades\DB::table('referral_commissions')
            ->where('id', $commission->id)
            ->update(['created_at' => now()->subDays(31)]);

        $this->artisan('referrals:approve-commissions')->assertExitCode(0);

        $commission->refresh();
        $this->assertSame(ReferralCommission::STATUS_APPROVED, $commission->status);
    }

    public function test_command_skips_pending_commissions_under_30_days(): void
    {
        $referrer = User::factory()->create();
        $businessOwner = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create(['user_id' => $businessOwner->id]);
        $referral = Referral::create([
            'business_id' => $business->id,
            'referrer_id' => $referrer->id,
            'business_user_id' => $businessOwner->id,
            'referral_code' => 'TEST-' . uniqid(),
            'status' => Referral::STATUS_ACTIVE,
            'commission_rate' => 10.0,
        ]);
        ReferralCommission::create([
            'referral_id' => $referral->id,
            'referrer_id' => $referrer->id,
            'business_id' => $business->id,
            'subscription_period' => now()->format('Y-m'),
            'business_payment' => 100,
            'commission_rate' => 10,
            'commission_amount' => 10,
            'status' => ReferralCommission::STATUS_PENDING,
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('referrals:approve-commissions')
            ->expectsOutputToContain('No commissions ready for approval')
            ->assertExitCode(0);
    }
}

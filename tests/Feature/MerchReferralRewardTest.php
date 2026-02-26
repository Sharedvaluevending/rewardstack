<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\MerchReferralAward;
use App\Models\MerchReferralReward;
use App\Models\MerchTag;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Redemption;
use App\Models\Scan;
use App\Models\User;
use App\Services\MerchReferralRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchReferralRewardTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_awards_repeatably_at_unique_redemption_thresholds()
    {
        $business = Business::factory()->create();
        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
        ]);
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'promotion_id' => $promotion->id,
            'type' => 'merch_referral',
        ]);

        $reward = MerchReferralReward::create([
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'reward_type' => 'percent',
            'reward_value' => 20,
            'reward_description' => '20% off',
            'redemptions_required' => 2,
            'is_active' => true,
        ]);

        $owner = User::factory()->create(['role' => 'customer']);
        $tag = MerchTag::create([
            'code' => 'TAGA1234',
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'owner_user_id' => $owner->id,
            'claimed_at' => now(),
            'is_active' => true,
        ]);

        $service = app(MerchReferralRewardService::class);

        $customers = User::factory()->count(4)->create(['role' => 'customer']);
        foreach ($customers as $customer) {
            $scan = Scan::create([
                'qr_code_id' => $qrCode->id,
                'merch_tag_id' => $tag->id,
                'business_id' => $business->id,
                'user_id' => $customer->id,
                'session_id' => 'sess-' . $customer->id,
                'scanned_at' => now(),
            ]);

            $redemption = Redemption::create([
                'promotion_id' => $promotion->id,
                'qr_code_id' => $qrCode->id,
                'business_id' => $business->id,
                'scan_id' => $scan->id,
                'customer_user_id' => $customer->id,
                'redeemed_at' => now(),
            ]);

            $service->evaluateForRedemption($redemption, $scan);
        }

        $this->assertEquals(2, MerchReferralAward::where('merch_tag_id', $tag->id)->count());
        $this->assertEquals($reward->id, MerchReferralAward::first()->reward_id);
    }

    /** @test */
    public function it_does_not_award_for_duplicate_customers()
    {
        $business = Business::factory()->create();
        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
        ]);
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'promotion_id' => $promotion->id,
            'type' => 'merch_referral',
        ]);

        MerchReferralReward::create([
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'reward_type' => 'amount',
            'reward_value' => 5,
            'reward_description' => '$5 off',
            'redemptions_required' => 2,
            'is_active' => true,
        ]);

        $owner = User::factory()->create(['role' => 'customer']);
        $tag = MerchTag::create([
            'code' => 'TAGB1234',
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'owner_user_id' => $owner->id,
            'claimed_at' => now(),
            'is_active' => true,
        ]);

        $customer = User::factory()->create(['role' => 'customer']);
        $service = app(MerchReferralRewardService::class);

        for ($i = 0; $i < 2; $i++) {
            $scan = Scan::create([
                'qr_code_id' => $qrCode->id,
                'merch_tag_id' => $tag->id,
                'business_id' => $business->id,
                'user_id' => $customer->id,
                'session_id' => 'sess-' . $customer->id,
                'scanned_at' => now(),
            ]);

            $redemption = Redemption::create([
                'promotion_id' => $promotion->id,
                'qr_code_id' => $qrCode->id,
                'business_id' => $business->id,
                'scan_id' => $scan->id,
                'customer_user_id' => $customer->id,
                'redeemed_at' => now(),
            ]);

            $service->evaluateForRedemption($redemption, $scan);
        }

        $this->assertEquals(0, MerchReferralAward::where('merch_tag_id', $tag->id)->count());
    }
}

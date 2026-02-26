<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Models\MerchReferralReward;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Notifications\PortalMerchReferralAwarded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalMerchReferralAwardedTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_database_channel(): void
    {
        $business = Business::factory()->create();
        $qr = QRCode::factory()->create(['business_id' => $business->id]);
        $reward = MerchReferralReward::create([
            'business_id' => $business->id,
            'qr_code_id' => $qr->id,
            'reward_type' => 'percent',
            'reward_value' => 15,
            'reward_description' => '15% off',
            'redemptions_required' => 1,
            'is_active' => true,
        ]);
        $notification = new PortalMerchReferralAwarded($reward, 1);
        $this->assertSame(['database'], $notification->via(new \stdClass()));
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $business = Business::factory()->create();
        $qr = QRCode::factory()->create(['business_id' => $business->id]);
        $reward = MerchReferralReward::create([
            'business_id' => $business->id,
            'qr_code_id' => $qr->id,
            'reward_type' => 'percent',
            'reward_value' => 20,
            'reward_description' => '20% off next order',
            'redemptions_required' => 2,
            'is_active' => true,
        ]);
        $notification = new PortalMerchReferralAwarded($reward, 1);
        $data = $notification->toArray(new \stdClass());

        $this->assertSame('Merch referral earned: 20% off next order (1 reward)', $data['message']);
        $this->assertSame('/portal/merch', $data['action_url']);
        $this->assertSame('merch_referral_reward', $data['type']);
        $this->assertSame('🧢', $data['icon']);
        $this->assertSame($reward->id, $data['reward_id']);
        $this->assertSame(1, $data['count']);
    }

    public function test_to_array_uses_rewards_plural_when_count_greater_than_one(): void
    {
        $business = Business::factory()->create();
        $qr = QRCode::factory()->create(['business_id' => $business->id]);
        $reward = MerchReferralReward::create([
            'business_id' => $business->id,
            'qr_code_id' => $qr->id,
            'reward_type' => 'amount',
            'reward_value' => 5,
            'reward_description' => '$5 off',
            'redemptions_required' => 1,
            'is_active' => true,
        ]);
        $notification = new PortalMerchReferralAwarded($reward, 3);
        $data = $notification->toArray(new \stdClass());
        $this->assertSame('Merch referral earned: $5 off (3 rewards)', $data['message']);
        $this->assertSame(3, $data['count']);
    }

    public function test_to_array_uses_fallback_when_reward_description_null(): void
    {
        $business = Business::factory()->create();
        $qr = QRCode::factory()->create(['business_id' => $business->id]);
        $reward = MerchReferralReward::create([
            'business_id' => $business->id,
            'qr_code_id' => $qr->id,
            'reward_type' => 'percent',
            'reward_value' => 10,
            'reward_description' => null,
            'redemptions_required' => 1,
            'is_active' => true,
        ]);
        $notification = new PortalMerchReferralAwarded($reward, 1);
        $data = $notification->toArray(new \stdClass());
        $this->assertStringContainsString('Merch referral reward', $data['message']);
    }
}

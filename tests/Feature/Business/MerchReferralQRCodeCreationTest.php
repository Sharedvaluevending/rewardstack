<?php

namespace Tests\Feature\Business;

use App\Models\Business;
use App\Models\MerchReferralReward;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Services\QRGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchReferralQRCodeCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_can_create_merch_referral_qr_and_reward_qr(): void
    {
        // Avoid file IO: QRCodeController->generateAndStoreQRImage uses QRGeneratorService::generateFile
        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andReturn('qrcodes/fake.png');
        });

        $business = Business::factory()->create([
            'is_testing_account' => true,
            'subscription_tier' => 'growth',
        ]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $gatewayPromo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 10,
        ]);

        $this->actingAs($owner)
            ->post('/business/qr-codes', [
                'name' => 'Merch Referral QR',
                'type' => 'merch_referral',
                'promotion_id' => $gatewayPromo->id,
                'reward_type' => 'amount',
                'reward_value' => 5,
                'reward_item_value' => 20,
                'reward_description' => 'Ambassador $5 off merch',
                'redemptions_required' => 3,
                'design' => [
                    'size' => 300,
                    'margin' => 10,
                    'background_color' => '#ffffff',
                    'module_color' => '#111111',
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $gatewayQr = QRCode::query()
            ->where('business_id', $business->id)
            ->where('type', 'merch_referral')
            ->firstOrFail();

        $this->assertSame($gatewayPromo->id, $gatewayQr->promotion_id);
        $this->assertSame('qrcodes/fake.png', ($gatewayQr->design['generated_path'] ?? null));

        $reward = MerchReferralReward::query()
            ->where('business_id', $business->id)
            ->where('qr_code_id', $gatewayQr->id)
            ->firstOrFail();

        $this->assertSame('amount', $reward->reward_type);
        $this->assertSame(5.0, (float) $reward->reward_value);
        $this->assertSame(3, (int) $reward->redemptions_required);

        $rewardQr = QRCode::findOrFail($reward->reward_qr_code_id);
        $this->assertSame(QRCode::INTENDED_USE_LEADERBOARD_PRIZE, $rewardQr->intended_use);
        $this->assertSame('promotion', $rewardQr->type);
        $this->assertSame('qrcodes/fake.png', ($rewardQr->design['generated_path'] ?? null));
    }

    public function test_merch_referral_qr_creation_requires_growth_or_feature_flag(): void
    {
        $business = Business::factory()->create([
            'is_testing_account' => true,
            'subscription_tier' => 'starter',
        ]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $gatewayPromo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->post('/business/qr-codes', [
                'name' => 'Merch Referral QR',
                'type' => 'merch_referral',
                'promotion_id' => $gatewayPromo->id,
                'reward_type' => 'amount',
                'reward_value' => 5,
                'reward_description' => 'Reward',
                'redemptions_required' => 3,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['subscription']);
    }
}


<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\Promotion;
use App\Models\Redemption;
use App\Models\QRCode;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionCanRedeemTest extends TestCase
{
    use RefreshDatabase;

    protected function makePromotion(array $overrides = []): Promotion
    {
        $business = Business::factory()->create();

        return Promotion::create(array_merge([
            'business_id' => $business->id,
            'name' => 'Promo',
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 5,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'total_redemptions' => 0,
            'rules' => [],
        ], $overrides));
    }

    public function test_inactive_not_started_expired_all_block(): void
    {
        $promo = $this->makePromotion(['is_active' => false]);
        $res = $promo->canRedeem();
        $this->assertFalse($res['allowed']);
        $this->assertStringContainsString('no longer active', $res['reason']);

        $promo = $this->makePromotion(['starts_at' => now()->addDay()]);
        $res = $promo->canRedeem();
        $this->assertFalse($res['allowed']);
        $this->assertStringContainsString('not started', $res['reason']);

        $promo = $this->makePromotion(['ends_at' => now()->subDay()]);
        $res = $promo->canRedeem();
        $this->assertFalse($res['allowed']);
        $this->assertStringContainsString('expired', $res['reason']);
    }

    public function test_total_and_per_user_limits(): void
    {
        $promo = $this->makePromotion([
            'total_redemptions' => 5,
            'rules' => ['max_redemptions_total' => 5, 'max_redemptions_per_user' => 1],
        ]);

        $res = $promo->canRedeem('CUST1');
        $this->assertFalse($res['allowed']);
        $this->assertStringContainsString('Maximum redemptions reached', $res['reason']);

        // Reset total, enforce per-user limit
        $promo->update(['total_redemptions' => 0]);
        $user = User::factory()->create();
        $qr = QRCode::create([
            'business_id' => $promo->business_id,
            'code' => 'QR1',
            'name' => 'QR1',
            'type' => 'promotion',
            'promotion_id' => $promo->id,
            'is_active' => true,
        ]);
        $token = UserPromoToken::create([
            'user_id' => $user->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $promo->business_id,
            'code' => 'TOK-1',
        ]);

        Redemption::create([
            'promotion_id' => $promo->id,
            'qr_code_id' => $qr->id,
            'business_id' => $promo->business_id,
            'customer_user_id' => $user->id,
            'customer_identifier' => $token->code,
            'discount_amount' => 5,
            'final_amount' => 5,
            'redeemed_at' => now(),
        ]);

        $res = $promo->canRedeem($token->code, $user->id);
        $this->assertFalse($res['allowed']);
        $this->assertStringContainsString('redemption limit', $res['reason']);
    }

    public function test_punch_card_respects_limits_only_when_flagged(): void
    {
        $promo = $this->makePromotion([
            'discount_type' => Promotion::TYPE_PUNCH_CARD,
            'rules' => [
                'respect_punch_limits' => false,
                'max_redemptions_total' => 1,
            ],
        ]);

        // Should ignore limits when respect_punch_limits is false
        $res = $promo->canRedeem();
        $this->assertTrue($res['allowed']);

        // When enabled, limits apply
        $promo->update([
            'rules' => [
                'respect_punch_limits' => true,
                'max_redemptions_total' => 0,
            ],
        ]);
        $res = $promo->canRedeem();
        $this->assertFalse($res['allowed']);
        $this->assertStringContainsString('Maximum redemptions reached', $res['reason']);
    }

    public function test_per_user_limit_without_identifier_allows(): void
    {
        $promo = $this->makePromotion([
            'rules' => ['max_redemptions_per_user' => 1],
        ]);

        $res = $promo->canRedeem(null, null);
        $this->assertTrue($res['allowed']);
    }
}


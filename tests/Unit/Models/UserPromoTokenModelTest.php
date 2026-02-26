<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPromoTokenModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_image_url_returns_null_when_no_path(): void
    {
        $user = User::factory()->create();
        $qr = QRCode::factory()->promotion()->create(['promotion_id' => Promotion::factory()->create()->id]);
        $token = UserPromoToken::create([
            'user_id' => $user->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $qr->promotion_id,
            'business_id' => $qr->business_id,
            'code' => 'UP-ABCD-1234',
        ]);
        $this->assertNull($token->qr_image_url);
    }

    public function test_qr_image_url_returns_asset_path_when_path_set(): void
    {
        $user = User::factory()->create();
        $qr = QRCode::factory()->promotion()->create(['promotion_id' => Promotion::factory()->create()->id]);
        $token = UserPromoToken::create([
            'user_id' => $user->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $qr->promotion_id,
            'business_id' => $qr->business_id,
            'code' => 'UP-ABCD-1234',
            'qr_image_path' => 'tokens/test.png',
        ]);
        $this->assertStringContainsString('storage/tokens/test.png', $token->qr_image_url);
    }

    public function test_relationships_exist(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create(['business_id' => $business->id]);
        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
        ]);
        $token = UserPromoToken::create([
            'user_id' => $user->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'code' => 'UP-ABCD-1234',
        ]);

        $this->assertTrue($token->user->is($user));
        $this->assertTrue($token->qrCode->is($qr));
        $this->assertTrue($token->promotion->is($promo));
        $this->assertTrue($token->business->is($business));
    }

    public function test_redeemed_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create();
        $qr = QRCode::factory()->promotion()->create(['promotion_id' => Promotion::factory()->create()->id]);
        $token = UserPromoToken::create([
            'user_id' => $user->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $qr->promotion_id,
            'business_id' => $qr->business_id,
            'code' => 'UP-ABCD-1234',
            'redeemed_at' => now(),
        ]);
        $token->refresh();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $token->redeemed_at);
    }
}

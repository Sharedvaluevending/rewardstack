<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\User;
use App\Models\UserPromoToken;
use App\Notifications\PortalPromoTokenAwarded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalPromoTokenAwardedTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_database_channel(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $qr = QRCode::factory()->create(['business_id' => $business->id]);
        $promo = Promotion::factory()->create(['business_id' => $business->id]);
        $token = UserPromoToken::create([
            'user_id' => $user->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'code' => 'UP-ABCD-1234',
        ]);
        $notification = new PortalPromoTokenAwarded($token);
        $this->assertSame(['database'], $notification->via(new \stdClass()));
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $qr = QRCode::factory()->create(['business_id' => $business->id]);
        $promo = Promotion::factory()->create(['business_id' => $business->id, 'name' => 'Summer Sale']);
        $token = UserPromoToken::create([
            'user_id' => $user->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'code' => 'UP-WXYZ-5678',
        ]);
        $notification = new PortalPromoTokenAwarded($token);
        $data = $notification->toArray($user);

        $this->assertSame('You won a reward: Summer Sale', $data['message']);
        $this->assertSame('/promo/UP-WXYZ-5678?source=portal', $data['action_url']);
        $this->assertSame('promo_token_awarded', $data['type']);
        $this->assertSame('🎁', $data['icon']);
        $this->assertSame('UP-WXYZ-5678', $data['token_code']);
        $this->assertSame($promo->id, $data['promotion_id']);
    }

    public function test_to_array_uses_fallback_when_promotion_name_empty(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $qr = QRCode::factory()->create(['business_id' => $business->id]);
        $promo = Promotion::factory()->create(['business_id' => $business->id, 'name' => '']);
        $token = UserPromoToken::create([
            'user_id' => $user->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'code' => 'UP-AAAA-2222',
        ]);
        $notification = new PortalPromoTokenAwarded($token);
        $data = $notification->toArray($user);
        $this->assertSame('You won a reward: Reward', $data['message']);
    }
}

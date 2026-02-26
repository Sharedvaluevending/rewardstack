<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\SavedQRCode;
use App\Models\User;
use App\Models\UserPromoToken;
use App\Services\QRGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPromotionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_promotion_and_views_increment(): void
    {
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(10),
            'total_views' => 0,
        ]);
        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'is_active' => true,
        ]);

        $this->get('/promo/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/Promotion')
                    ->where('promotion.id', $promo->id)
                    ->where('business.id', $business->id)
            );

        $this->get('/promo/' . $qr->code)->assertStatus(200);

        $promo->refresh();
        $this->assertSame(2, (int) $promo->total_views);
    }

    public function test_logged_in_customer_viewing_promo_creates_saved_qr_and_customer_token(): void
    {
        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andReturn('qrcodes/test.png');
        });

        $customer = User::factory()->create(['role' => 'customer', 'level' => 1]);
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(10),
        ]);
        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'is_active' => true,
        ]);

        $this->actingAs($customer)->get('/promo/' . $qr->code)->assertStatus(200);

        $this->assertSame(1, SavedQRCode::query()->where('user_id', $customer->id)->where('qr_code_id', $qr->id)->count());
        $this->assertSame(1, UserPromoToken::query()->where('user_id', $customer->id)->where('qr_code_id', $qr->id)->whereNull('redeemed_at')->count());

        // Second view reuses active token; should not create duplicates.
        $this->actingAs($customer)->get('/promo/' . $qr->code)->assertStatus(200);
        $this->assertSame(1, SavedQRCode::query()->where('user_id', $customer->id)->where('qr_code_id', $qr->id)->count());
        $this->assertSame(1, UserPromoToken::query()->where('user_id', $customer->id)->where('qr_code_id', $qr->id)->whereNull('redeemed_at')->count());
    }

    public function test_required_level_blocks_guests_and_low_level_users(): void
    {
        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andReturn('qrcodes/test.png');
        });

        $business = Business::factory()->create();
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(10),
        ]);
        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'is_active' => true,
            'required_level' => 5,
        ]);

        $this->get('/promo/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/Promotion')
                    ->where('redeemMessage', 'Sign in to check if you meet the level requirement for this promotion.')
                    ->where('canRedeem', false)
            );

        $low = User::factory()->create(['role' => 'customer', 'level' => 3]);
        $this->actingAs($low)->get('/promo/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/Promotion')
                    ->where('redeemMessage', 'This promotion requires Level 5. You are currently Level 3. Keep scanning, playing games, and redeeming to level up!')
                    ->where('canRedeem', false)
            );
    }
}


<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CrossPromotion;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\User;
use App\Models\UserPromoToken;
use App\Services\QRGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicCrossPromoClaimFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_cross_promo_scan_renders_and_claim_creates_token_and_redirects(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        // Avoid writing QR images to disk in tests.
        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andReturn('qrcodes/test.png');
        });

        $business1 = Business::factory()->create();
        $business2 = Business::factory()->create();

        $promo1 = Promotion::factory()->create([
            'business_id' => $business1->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);
        $promo2 = Promotion::factory()->create([
            'business_id' => $business2->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);

        $crossPromo = CrossPromotion::create([
            'code' => 'CPROMO01',
            'name' => 'Cross Promo',
            'business_1_id' => $business1->id,
            'business_2_id' => $business2->id,
            'promotion_1_id' => $promo1->id,
            'promotion_2_id' => $promo2->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);

        $qr = QRCode::create([
            'business_id' => $business1->id,
            'code' => 'XPRO1234',
            'name' => 'Cross Promo QR',
            'type' => 'cross_promo',
            'cross_promotion_id' => $crossPromo->id,
            'is_active' => true,
        ]);

        $this->get('/s/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/CrossPromo')
                    ->where('crossPromo.id', $crossPromo->id)
                    ->where('promotion1.id', $promo1->id)
                    ->where('promotion2.id', $promo2->id)
            );

        $customer = User::factory()->create(['role' => 'customer']);

        $claim = $this->actingAs($customer)->post(route('cross-promo.claim', [
            'crossPromotion' => $crossPromo->id,
            'promotion' => $promo1->id,
        ]));

        $claim->assertStatus(302);

        $token = UserPromoToken::query()
            ->where('user_id', $customer->id)
            ->where('promotion_id', $promo1->id)
            ->whereNull('redeemed_at')
            ->first();

        $this->assertNotNull($token);
        $this->assertSame($qr->id, (int) $token->qr_code_id);
        $this->assertSame('qrcodes/test.png', $token->qr_image_path);

        $claim->assertRedirect('/promo/' . $token->code);
    }

    public function test_claim_is_idempotent_and_reuses_existing_unredeemed_token(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andReturn('qrcodes/test.png');
        });

        $business1 = Business::factory()->create();
        $business2 = Business::factory()->create();

        $promo1 = Promotion::factory()->create(['business_id' => $business1->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(30)]);
        $promo2 = Promotion::factory()->create(['business_id' => $business2->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(30)]);

        $crossPromo = CrossPromotion::create([
            'code' => 'CPIDEMP1',
            'name' => 'Cross Promo',
            'business_1_id' => $business1->id,
            'business_2_id' => $business2->id,
            'promotion_1_id' => $promo1->id,
            'promotion_2_id' => $promo2->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);

        $qr = QRCode::create([
            'business_id' => $business1->id,
            'code' => 'CPIDEMQR',
            'name' => 'Cross Promo QR',
            'type' => 'cross_promo',
            'cross_promotion_id' => $crossPromo->id,
            'is_active' => true,
        ]);

        $customer = User::factory()->create(['role' => 'customer']);

        $r1 = $this->actingAs($customer)->post(route('cross-promo.claim', [
            'crossPromotion' => $crossPromo->id,
            'promotion' => $promo1->id,
        ]));
        $r1->assertStatus(302);

        $token1 = UserPromoToken::query()->where('user_id', $customer->id)->where('promotion_id', $promo1->id)->whereNull('redeemed_at')->first();
        $this->assertNotNull($token1);
        $this->assertSame($qr->id, (int) $token1->qr_code_id);

        $r2 = $this->actingAs($customer)->post(route('cross-promo.claim', [
            'crossPromotion' => $crossPromo->id,
            'promotion' => $promo1->id,
        ]));
        $r2->assertStatus(302);

        $this->assertSame(1, UserPromoToken::query()->where('user_id', $customer->id)->where('promotion_id', $promo1->id)->whereNull('redeemed_at')->count());
        $token2 = UserPromoToken::query()->where('user_id', $customer->id)->where('promotion_id', $promo1->id)->whereNull('redeemed_at')->first();
        $this->assertSame($token1->id, $token2->id);
    }

    public function test_claim_is_blocked_when_promotion_is_locked_in_sequential_chain(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andReturn('qrcodes/test.png');
        });

        $business1 = Business::factory()->create();
        $business2 = Business::factory()->create();

        $promo1 = Promotion::factory()->create(['business_id' => $business1->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(30)]);
        $promo2 = Promotion::factory()->create(['business_id' => $business2->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(30)]);

        $crossPromo = CrossPromotion::create([
            'code' => 'CHAIN001',
            'name' => 'Sequential Chain',
            'business_1_id' => $business1->id,
            'business_2_id' => $business2->id,
            'promotion_1_id' => $promo1->id,
            'promotion_2_id' => $promo2->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_SEQUENTIAL,
            'primary_promotion_id' => $promo1->id,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);

        QRCode::create([
            'business_id' => $business1->id,
            'code' => 'XSEQ1234',
            'name' => 'Cross Promo QR',
            'type' => 'cross_promo',
            'cross_promotion_id' => $crossPromo->id,
            'is_active' => true,
        ]);

        $customer = User::factory()->create(['role' => 'customer']);

        // Secondary is locked until primary redeemed.
        $resp = $this->actingAs($customer)->post(route('cross-promo.claim', [
            'crossPromotion' => $crossPromo->id,
            'promotion' => $promo2->id,
        ]));

        $resp->assertStatus(302);
        $resp->assertSessionHas('error');

        $this->assertDatabaseMissing('user_promo_tokens', [
            'user_id' => $customer->id,
            'promotion_id' => $promo2->id,
        ]);
    }
}


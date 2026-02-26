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
use Tests\TestCase;

class PortalSavedQRCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_save_and_unsave_qr_code_and_token_is_ensured(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        // Avoid writing QR images to disk in tests.
        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andReturn('qrcodes/test.png');
        });

        $customer = User::factory()->create(['role' => 'customer']);
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

        $save = $this->actingAs($customer)->postJson("/portal/qr-codes/{$qr->id}/save");
        $save->assertStatus(200)->assertJson(['success' => true, 'saved' => true]);

        $this->assertSame(1, SavedQRCode::query()->where('user_id', $customer->id)->where('qr_code_id', $qr->id)->count());
        $this->assertSame(1, UserPromoToken::query()->where('user_id', $customer->id)->where('qr_code_id', $qr->id)->whereNull('redeemed_at')->count());

        // Saving again should be idempotent and not create duplicates.
        $save2 = $this->actingAs($customer)->postJson("/portal/qr-codes/{$qr->id}/save");
        $save2->assertStatus(200)->assertJson(['success' => true, 'saved' => true]);
        $this->assertSame(1, SavedQRCode::query()->where('user_id', $customer->id)->where('qr_code_id', $qr->id)->count());
        $this->assertSame(1, UserPromoToken::query()->where('user_id', $customer->id)->where('qr_code_id', $qr->id)->whereNull('redeemed_at')->count());

        $unsave = $this->actingAs($customer)->deleteJson("/portal/qr-codes/{$qr->id}/unsave");
        $unsave->assertStatus(200)->assertJson(['success' => true, 'saved' => false]);

        $this->assertSame(0, SavedQRCode::query()->where('user_id', $customer->id)->where('qr_code_id', $qr->id)->count());
    }

    public function test_inertia_save_returns_303_redirect_back(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andReturn('qrcodes/test.png');
        });

        $customer = User::factory()->create(['role' => 'customer']);
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

        $resp = $this->actingAs($customer)->withHeader('X-Inertia', 'true')->post("/portal/qr-codes/{$qr->id}/save");
        $resp->assertStatus(303);
    }
}


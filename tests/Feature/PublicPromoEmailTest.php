<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Promotion;
use App\Models\QRCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicPromoEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_promo_email_endpoint_sends_email_for_valid_promo_qr(): void
    {
        Mail::fake();

        $business = Business::factory()->create(['name' => 'My Biz']);
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'is_active' => true,
        ]);

        $resp = $this->postJson('/api/public/promo/email', [
            'code' => $qr->code,
            'email' => 'test@example.com',
        ]);

        $resp->assertStatus(200)->assertJson(['success' => true]);
        // PromoLinkEmail implements ShouldQueue, so Mail::fake() records it as queued
        Mail::assertQueued(\App\Mail\PromoLinkEmail::class, 1);
    }

    public function test_promo_email_endpoint_returns_404_for_missing_promo(): void
    {
        Mail::fake();

        $resp = $this->postJson('/api/public/promo/email', [
            'code' => 'NOTFOUND',
            'email' => 'test@example.com',
        ]);

        $resp->assertStatus(404)->assertJson([
            'success' => false,
            'message' => 'Promotion not found',
        ]);
        Mail::assertNothingSent();
    }

    public function test_promo_email_endpoint_is_rate_limited(): void
    {
        Mail::fake();
        $server = ['REMOTE_ADDR' => '203.0.113.10'];

        $business = Business::factory()->create();
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'is_active' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables($server)->postJson('/api/public/promo/email', [
                'code' => $qr->code,
                'email' => 'test@example.com',
            ])->assertStatus(200);
        }

        // 6th request in a minute should throttle
        $this->withServerVariables($server)->postJson('/api/public/promo/email', [
            'code' => $qr->code,
            'email' => 'test@example.com',
        ])->assertStatus(429);
    }
}


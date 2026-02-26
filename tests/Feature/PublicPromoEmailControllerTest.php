<?php

namespace Tests\Feature;

use App\Mail\PromoLinkEmail;
use App\Models\Business;
use App\Models\Promotion;
use App\Models\QRCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicPromoEmailControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_promo_email_returns_404_for_invalid_code(): void
    {
        Mail::fake();

        $response = $this->postJson(route('api.public.promo.email'), [
            'code' => 'INVALID1',
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Promotion not found',
            ]);

        Mail::assertNothingSent();
    }

    public function test_promo_email_sends_and_returns_success(): void
    {
        Mail::fake();

        $business = Business::factory()->create(['name' => 'Test Biz']);
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'name' => 'Summer Sale',
            'is_active' => true,
        ]);
        $qr = QRCode::factory()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'type' => 'promotion',
            'code' => 'ABCD1234',
        ]);

        $response = $this->postJson(route('api.public.promo.email'), [
            'code' => $qr->code,
            'email' => 'customer@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        Mail::assertQueued(PromoLinkEmail::class, fn ($mail) => $mail->hasTo('customer@example.com'));
    }
}

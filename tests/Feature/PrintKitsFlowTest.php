<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Order;
use App\Models\QRCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrintKitsFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_can_create_print_kits_order_and_open_avery_without_payment_gate(): void
    {
        Http::fake([
            'services.print.avery.com/*' => Http::response('', 303, [
                'Location' => 'https://services.print.avery.com/dpp/public/v3/dpo/somewhere',
            ]),
        ]);

        $user = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create([
            'user_id' => $user->id,
            'trial_ends_at' => now()->addDays(7),
            'subscription_tier' => 'growth',
            'is_testing_account' => true,
        ]);
        $qrA = QRCode::factory()->create([
            'business_id' => $business->id,
            'type' => 'promotion',
            'is_active' => true,
        ]);
        $qrB = QRCode::factory()->create([
            'business_id' => $business->id,
            'type' => 'promotion',
            'is_active' => true,
        ]);

        // Create an order: size + multiple QR codes + quantities (one row per label).
        $response = $this->actingAs($user)->post('/business/print-kits', [
            'size' => 'round_2in',
            'items' => [
                ['qr_code_id' => $qrA->id, 'quantity' => 3],
                ['qr_code_id' => $qrB->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(302);

        $order = Order::where('business_id', $business->id)->where('type', 'print_kits')->first();
        $this->assertNotNull($order);
        $order->load('items');
        $this->assertCount(2, $order->items, 'Order should contain one item per selected QR code.');
        $this->assertEquals('pending', $order->payment_status);

        // Verify Avery handoff redirects user even when payment is pending.
        $averyResponse = $this->actingAs($user)->get("/business/print-kits/{$order->id}/avery");
        $averyResponse->assertRedirect('https://services.print.avery.com/dpp/public/v3/dpo/somewhere');
    }

    public function test_avery_returns_400_when_no_labels(): void
    {
        $user = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create(['user_id' => $user->id, 'subscription_tier' => 'growth', 'is_testing_account' => true]);
        $qr = QRCode::factory()->create(['business_id' => $business->id, 'type' => 'promotion', 'is_active' => true]);

        // Create order, then delete QR before attempting Avery.
        $this->actingAs($user)->post('/business/print-kits', [
            'size' => 'round_2in',
            'items' => [
                ['qr_code_id' => $qr->id, 'quantity' => 2],
            ],
        ])->assertStatus(302);

        $order = Order::where('business_id', $business->id)->where('type', 'print_kits')->firstOrFail();
        $order->update(['payment_status' => 'paid']);

        $qr->delete(); // simulate QR removed after payment

        $this->actingAs($user)
            ->get("/business/print-kits/{$order->id}/avery")
            ->assertStatus(400)
            ->assertSee('No QR codes found', false);
    }

    public function test_avery_returns_400_when_bundle_missing(): void
    {
        config()->set('printkits.sizes.round_2in.bundle_filename', null); // force failure

        $user = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create(['user_id' => $user->id, 'subscription_tier' => 'growth', 'is_testing_account' => true]);
        $qr = QRCode::factory()->create(['business_id' => $business->id, 'type' => 'promotion', 'is_active' => true]);

        $this->actingAs($user)->post('/business/print-kits', [
            'size' => 'round_2in',
            'items' => [
                ['qr_code_id' => $qr->id, 'quantity' => 1],
            ],
        ])->assertStatus(302);

        $order = Order::where('business_id', $business->id)->where('type', 'print_kits')->firstOrFail();
        $order->update(['payment_status' => 'paid']);

        $this->actingAs($user)
            ->get("/business/print-kits/{$order->id}/avery")
            ->assertStatus(400)
            ->assertSee('Sticker template bundle is not available', false);
    }

    public function test_cloudprinter_webhook_route_is_removed(): void
    {
        $this->post('/webhooks/cloudprinter')->assertStatus(404);
    }
}



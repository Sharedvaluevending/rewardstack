<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeRefundDisputeTest extends TestCase
{
    use RefreshDatabase;

    public function test_charge_refunded_marks_order_refunded(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $order = Order::factory()->create([
            'type' => 'merch',
            'status' => 'processing',
            'payment_status' => 'paid',
            'stripe_payment_intent_id' => 'pi_refund_1',
            'refunded_at' => null,
        ]);

        $resp = $this->postJson('/webhooks/stripe', [
            'id' => 'evt_refund_1',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'payment_intent' => 'pi_refund_1',
                ],
            ],
        ]);

        $resp->assertStatus(200)->assertJson(['status' => 'success']);

        $order->refresh();
        $this->assertEquals('refunded', $order->payment_status);
        $this->assertEquals('cancelled', $order->status);
        $this->assertNotNull($order->refunded_at);
    }

    public function test_charge_dispute_marks_order_disputed(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $order = Order::factory()->create([
            'type' => 'merch',
            'status' => 'processing',
            'payment_status' => 'paid',
            'stripe_payment_intent_id' => 'pi_dispute_1',
        ]);

        $resp = $this->postJson('/webhooks/stripe', [
            'id' => 'evt_dispute_1',
            'type' => 'charge.dispute.created',
            'data' => [
                'object' => [
                    'payment_intent' => 'pi_dispute_1',
                ],
            ],
        ]);

        $resp->assertStatus(200)->assertJson(['status' => 'success']);

        $order->refresh();
        $this->assertEquals('disputed', $order->payment_status);
    }

    public function test_refund_event_is_idempotent_for_existing_order(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $order = Order::factory()->create([
            'type' => 'merch',
            'status' => 'processing',
            'payment_status' => 'paid',
            'stripe_payment_intent_id' => 'pi_refund_dup',
            'refunded_at' => null,
        ]);

        $payload = [
            'id' => 'evt_refund_dup',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'payment_intent' => 'pi_refund_dup',
                ],
            ],
        ];

        $first = $this->postJson('/webhooks/stripe', $payload);
        $first->assertStatus(200)->assertJson(['status' => 'success']);

        $second = $this->postJson('/webhooks/stripe', $payload);
        $second->assertStatus(200)->assertJson(['status' => 'already_processed']);

        $order->refresh();
        $this->assertEquals('refunded', $order->payment_status);
        $this->assertEquals('cancelled', $order->status);
        $this->assertNotNull($order->refunded_at);
    }

    public function test_dispute_event_is_idempotent_for_existing_order(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $order = Order::factory()->create([
            'type' => 'merch',
            'status' => 'processing',
            'payment_status' => 'paid',
            'stripe_payment_intent_id' => 'pi_dispute_dup',
        ]);

        $payload = [
            'id' => 'evt_dispute_dup',
            'type' => 'charge.dispute.created',
            'data' => [
                'object' => [
                    'payment_intent' => 'pi_dispute_dup',
                ],
            ],
        ];

        $first = $this->postJson('/webhooks/stripe', $payload);
        $first->assertStatus(200)->assertJson(['status' => 'success']);

        $second = $this->postJson('/webhooks/stripe', $payload);
        $second->assertStatus(200)->assertJson(['status' => 'already_processed']);

        $order->refresh();
        $this->assertEquals('disputed', $order->payment_status);
    }

    public function test_refund_for_missing_order_is_deferred_and_deduped(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $payload = [
            'id' => 'evt_refund_missing_1',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'payment_intent' => 'pi_missing_refund',
                ],
            ],
        ];

        $first = $this->postJson('/webhooks/stripe', $payload);
        $first->assertStatus(200)->assertJson(['status' => 'order_not_found_deferred']);

        $second = $this->postJson('/webhooks/stripe', $payload);
        $second->assertStatus(200)->assertJson(['status' => 'already_processed']);
    }

    public function test_dispute_for_missing_order_is_deferred_and_deduped(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $payload = [
            'id' => 'evt_dispute_missing_1',
            'type' => 'charge.dispute.created',
            'data' => [
                'object' => [
                    'payment_intent' => 'pi_missing_dispute',
                ],
            ],
        ];

        $first = $this->postJson('/webhooks/stripe', $payload);
        $first->assertStatus(200)->assertJson(['status' => 'order_not_found_deferred']);

        $second = $this->postJson('/webhooks/stripe', $payload);
        $second->assertStatus(200)->assertJson(['status' => 'already_processed']);
    }
}


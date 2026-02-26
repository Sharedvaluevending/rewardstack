<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StripeWebhookOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_intent_before_checkout_session_still_results_in_paid_processing_order(): void
    {
        // Disable signature verification for tests
        config(['services.stripe.webhook_secret' => null]);
        Queue::fake();

        $order = Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'pending',
            'status' => 'pending',
            'stripe_payment_intent_id' => null,
        ]);

        $paymentIntentId = 'pi_test_123';

        // Send payment_intent.succeeded first
        $piResponse = $this->postJson('/webhooks/stripe', [
            'id' => 'evt_pi_1',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $paymentIntentId,
                    'metadata' => [
                        'order_id' => $order->id,
                    ],
                ],
            ],
        ]);
        $piResponse->assertStatus(200);

        // Then send checkout.session.completed
        $checkoutResponse = $this->postJson('/webhooks/stripe', [
            'id' => 'evt_checkout_1',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'payment_intent' => $paymentIntentId,
                    'metadata' => [
                        'order_id' => $order->id,
                    ],
                ],
            ],
        ]);
        $checkoutResponse->assertStatus(200);

        $order->refresh();

        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('processing', $order->status);
        $this->assertEquals($paymentIntentId, $order->stripe_payment_intent_id);

        $this->assertEquals(2, WebhookEvent::where('provider', 'stripe')->count());
    }

    public function test_replay_of_payment_intent_is_deduped(): void
    {
        config(['services.stripe.webhook_secret' => null]);
        Queue::fake();

        $order = Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'pending',
            'status' => 'pending',
            'stripe_payment_intent_id' => null,
        ]);

        $eventId = 'evt_replay_123';
        $paymentIntentId = 'pi_replay_123';

        $payload = [
            'id' => $eventId,
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $paymentIntentId,
                    'metadata' => [
                        'order_id' => $order->id,
                    ],
                ],
            ],
        ];

        $first = $this->postJson('/webhooks/stripe', $payload);
        $first->assertStatus(200);

        $second = $this->postJson('/webhooks/stripe', $payload);
        $second->assertStatus(200)->assertJson(['status' => 'already_processed']);

        $order->refresh();

        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('processing', $order->status);
        $this->assertEquals($paymentIntentId, $order->stripe_payment_intent_id);
        $this->assertEquals(1, WebhookEvent::where('provider', 'stripe')->where('event_id', $eventId)->count());
    }

    public function test_duplicate_webhook_same_event_id_is_idempotent(): void
    {
        config(['services.stripe.webhook_secret' => null]);
        Queue::fake();

        $order = Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        $eventId = 'evt_duplicate_123';
        $payload = [
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'payment_intent' => 'pi_dup_123',
                    'metadata' => [
                        'order_id' => $order->id,
                    ],
                ],
            ],
        ];

        $first = $this->postJson('/webhooks/stripe', $payload);
        $first->assertStatus(200);

        $second = $this->postJson('/webhooks/stripe', $payload);
        $second->assertStatus(200)->assertJson(['status' => 'already_processed']);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('processing', $order->status);
        $this->assertEquals('pi_dup_123', $order->stripe_payment_intent_id);
        $this->assertEquals(1, WebhookEvent::where('provider', 'stripe')->where('event_id', $eventId)->count());
    }
}


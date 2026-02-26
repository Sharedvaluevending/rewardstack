<?php

namespace Tests\Feature;

use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeMissingOrderWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_completed_for_missing_order_is_deferred(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $resp = $this->postJson('/webhooks/stripe', [
            'id' => 'evt_missing_order_1',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'payment_intent' => 'pi_missing_1',
                    'metadata' => [
                        'order_id' => 9999,
                    ],
                ],
            ],
        ]);

        $resp->assertStatus(200)->assertJson(['status' => 'order_not_found_deferred']);
    }

    public function test_payment_intent_for_missing_order_is_deferred(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $resp = $this->postJson('/webhooks/stripe', [
            'id' => 'evt_missing_order_2',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_missing_2',
                    'metadata' => [
                        'order_id' => 8888,
                    ],
                ],
            ],
        ]);

        $resp->assertStatus(200)->assertJson(['status' => 'order_not_found_deferred']);
    }

    public function test_missing_order_webhook_is_recorded_and_replay_is_deduped(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $payload = [
            'id' => 'evt_missing_order_dedupe',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_missing_dedupe',
                    'metadata' => [
                        'order_id' => 7777,
                    ],
                ],
            ],
        ];

        $first = $this->postJson('/webhooks/stripe', $payload);
        $first->assertStatus(200)->assertJson(['status' => 'order_not_found_deferred']);

        $second = $this->postJson('/webhooks/stripe', $payload);
        $second->assertStatus(200)->assertJson(['status' => 'already_processed']);

        $this->assertEquals(1, WebhookEvent::where('provider', 'stripe')->where('event_id', 'evt_missing_order_dedupe')->count());
    }
}


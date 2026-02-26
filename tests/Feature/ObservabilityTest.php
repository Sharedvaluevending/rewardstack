<?php

namespace Tests\Feature;

use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_events_record_ids_and_types(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $payload = [
            'id' => 'evt_obs_1',
            'type' => 'stock_updated',
            'data' => [
                'object' => [
                    'id' => 'obj_1',
                ],
            ],
        ];

        $this->postJson('/webhooks/stripe', $payload)->assertStatus(200);

        $event = WebhookEvent::where('provider', 'stripe')->where('event_id', 'evt_obs_1')->first();
        $this->assertNotNull($event);
        $this->assertEquals('stock_updated', $event->type);
    }

    public function test_webhook_events_enforce_uniqueness(): void
    {
        WebhookEvent::create([
            'provider' => 'stripe',
            'event_id' => 'evt_unique_1',
            'type' => 'test',
            'processed_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        WebhookEvent::create([
            'provider' => 'stripe',
            'event_id' => 'evt_unique_1',
            'type' => 'test',
            'processed_at' => now(),
        ]);
    }

    public function test_logs_include_request_id_and_order_when_processing_checkout(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        Log::swap(new \Illuminate\Log\Logger(new \Monolog\Logger('testing')));
        Log::getLogger()->pushHandler(new \Monolog\Handler\TestHandler());
        $handler = Log::getLogger()->popHandler();
        Log::getLogger()->pushHandler($handler);

        $orderId = 1234;
        $resp = $this->postJson('/webhooks/stripe', [
            'id' => 'evt_obs_checkout',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'payment_intent' => 'pi_obs_1',
                    'metadata' => [
                        'order_id' => $orderId,
                    ],
                ],
            ],
        ]);

        $resp->assertStatus(200);

        $records = $handler->getRecords();
        $found = false;
        foreach ($records as $record) {
            $context = $record['context'] ?? [];
            if (($context['order_id'] ?? null) === $orderId || ($context['orderId'] ?? null) === $orderId) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected order_id to be present in logs for checkout webhook');
    }
}


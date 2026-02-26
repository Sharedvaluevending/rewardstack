<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PrintfulWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_rejects_webhook_with_invalid_token()
    {
        config(['services.printful.webhook_token' => 'test_token_123']);

        $response = $this->postJson('/webhooks/printful', [
            'type' => 'package_shipped',
            'data' => [],
        ], [
            'X-Webhook-Token' => 'invalid_token',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Unauthorized']);
    }

    /** @test */
    public function it_accepts_webhook_with_valid_token()
    {
        config(['services.printful.webhook_token' => 'test_token_123']);

        $response = $this->postJson('/webhooks/printful', [
            'type' => 'package_shipped',
            'data' => [],
        ], [
            'X-Webhook-Token' => 'test_token_123',
        ]);

        // Should not return 401 (even if payload is invalid)
        $this->assertNotEquals(401, $response->status());
    }

    /** @test */
    public function it_handles_package_shipped_webhook()
    {
        // Disable webhook token verification for tests
        config(['services.printful.webhook_token' => null]);

        $order = Order::factory()->create([
            'printful_order_id' => '12345',
            'status' => 'processing',
            'printful_status' => 'inprocess',
        ]);

        $payload = [
            'type' => 'package_shipped',
            'data' => [
                'order' => [
                    'id' => '12345',
                ],
                'shipment' => [
                    'tracking_number' => 'TRACK123456',
                    'tracking_url' => 'https://tracking.example.com/TRACK123456',
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/printful', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $order->refresh();
        $this->assertEquals('shipped', $order->status);
        $this->assertEquals('shipped', $order->printful_status);
        $this->assertEquals('TRACK123456', $order->tracking_number);
        $this->assertEquals('https://tracking.example.com/TRACK123456', $order->tracking_url);
        $this->assertNotNull($order->shipped_at);
    }

    /** @test */
    public function it_dedupes_printful_webhook_using_stable_hash_when_event_id_missing()
    {
        config(['services.printful.webhook_token' => null]);

        $order = Order::factory()->create([
            'order_number' => 'ORD-TEST-789',
            'printful_order_id' => '99999',
            'status' => 'processing',
            'printful_status' => 'inprocess',
        ]);

        $basePayload = [
            'type' => 'order_updated',
            'data' => [
                'order' => [
                    'id' => '99999',
                    'external_id' => 'ORD-TEST-789',
                    'status' => 'inprocess',
                ],
            ],
        ];

        $first = $this->postJson('/webhooks/printful', $basePayload);
        $first->assertStatus(200);

        // Second payload differs only by a volatile delivery timestamp
        $secondPayload = $basePayload;
        $secondPayload['data']['delivered_at'] = now()->timestamp;

        $second = $this->postJson('/webhooks/printful', $secondPayload);
        $second->assertStatus(200)->assertJson(['status' => 'already_processed']);
    }

    /** @test */
    public function it_handles_order_created_webhook()
    {
        config(['services.printful.webhook_token' => null]);

        $order = Order::factory()->create([
            'order_number' => 'ORD-TEST-123',
            'printful_order_id' => null,
        ]);

        $payload = [
            'type' => 'order_created',
            'data' => [
                'order' => [
                    'id' => '67890',
                    'external_id' => 'ORD-TEST-123',
                    'status' => 'pending',
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/printful', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $order->refresh();
        $this->assertEquals('67890', $order->printful_order_id);
        $this->assertEquals('pending', $order->printful_status);
    }

    /** @test */
    public function it_handles_order_updated_webhook()
    {
        config(['services.printful.webhook_token' => null]);

        $order = Order::factory()->create([
            'printful_order_id' => '11111',
            'status' => 'pending',
            'printful_status' => 'pending',
        ]);

        $payload = [
            'type' => 'order_updated',
            'data' => [
                'order' => [
                    'id' => '11111',
                    'status' => 'inprocess',
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/printful', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $order->refresh();
        $this->assertEquals('inprocess', $order->printful_status);
        $this->assertEquals('processing', $order->status);
    }

    /** @test */
    public function it_handles_order_failed_webhook()
    {
        config(['services.printful.webhook_token' => null]);

        $order = Order::factory()->create([
            'printful_order_id' => '22222',
            'status' => 'processing',
            'printful_status' => 'inprocess',
        ]);

        $payload = [
            'type' => 'order_failed',
            'data' => [
                'order' => [
                    'id' => '22222',
                ],
                'reason' => 'Payment failed',
            ],
        ];

        $response = $this->postJson('/webhooks/printful', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $order->refresh();
        $this->assertEquals('failed', $order->printful_status);
        $this->assertEquals('cancelled', $order->status);
    }

    /** @test */
    public function it_handles_order_canceled_webhook()
    {
        config(['services.printful.webhook_token' => null]);

        $order = Order::factory()->create([
            'printful_order_id' => '33333',
            'status' => 'processing',
            'printful_status' => 'inprocess',
        ]);

        $payload = [
            'type' => 'order_canceled',
            'data' => [
                'order' => [
                    'id' => '33333',
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/printful', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $order->refresh();
        $this->assertEquals('canceled', $order->printful_status);
        $this->assertEquals('cancelled', $order->status);
    }

    /** @test */
    public function it_handles_order_put_hold_webhook()
    {
        config(['services.printful.webhook_token' => null]);

        $order = Order::factory()->create([
            'printful_order_id' => '44444',
            'status' => 'processing',
            'printful_status' => 'inprocess',
        ]);

        $payload = [
            'type' => 'order_put_hold',
            'data' => [
                'order' => [
                    'id' => '44444',
                ],
                'reason' => 'Address verification needed',
            ],
        ];

        $response = $this->postJson('/webhooks/printful', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $order->refresh();
        $this->assertEquals('onhold', $order->printful_status);
    }

    /** @test */
    public function it_handles_product_synced_webhook()
    {
        config(['services.printful.webhook_token' => null]);

        $payload = [
            'type' => 'product_synced',
            'data' => [
                'product' => [
                    'id' => '123',
                    'name' => 'Test Product',
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/printful', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
    }

    /** @test */
    public function it_handles_stock_updated_webhook()
    {
        config(['services.printful.webhook_token' => null]);

        $payload = [
            'type' => 'stock_updated',
            'data' => [
                'variant' => [
                    'id' => '456',
                    'stock' => 100,
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/printful', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
    }

    /** @test */
    public function it_handles_unknown_webhook_type()
    {
        config(['services.printful.webhook_token' => null]);

        $payload = [
            'type' => 'unknown_event',
            'data' => [],
        ];

        $response = $this->postJson('/webhooks/printful', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ignored']);
    }

    /** @test */
    public function it_handles_package_shipped_with_missing_order()
    {
        config(['services.printful.webhook_token' => null]);

        $payload = [
            'type' => 'package_shipped',
            'data' => [
                'order' => [
                    'id' => '99999', // Non-existent order
                ],
                'shipment' => [
                    'tracking_number' => 'TRACK123456',
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/printful', $payload);

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Order not found']);
    }

    /** @test */
    public function it_handles_package_shipped_with_missing_order_id()
    {
        config(['services.printful.webhook_token' => null]);

        $payload = [
            'type' => 'package_shipped',
            'data' => [
                'shipment' => [
                    'tracking_number' => 'TRACK123456',
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/printful', $payload);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Missing order ID']);
    }
}


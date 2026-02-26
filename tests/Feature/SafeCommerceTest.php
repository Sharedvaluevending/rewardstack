<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Models\User;
use App\Services\AveryDpoService;
use App\Services\PrintfulService;
use App\Services\ReferralCommissionService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SafeCommerceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Safety First: Force mocked drivers/keys for this test suite
        Config::set('services.stripe.key', 'pk_test_MOCK');
        Config::set('services.stripe.secret', 'sk_test_MOCK');
        Config::set('services.printful.api_key', 'test_key');
        
        // Prevent any accidental external HTTP calls. Individual tests can still stub expected requests.
        Http::preventStrayRequests();
    }

    /** @test */
    public function referral_commission_calculates_correctly()
    {
        $service = app(ReferralCommissionService::class);
        
        $referrer = User::factory()->create(['role' => 'customer', 'name' => 'Referrer']);

        $businessOwner = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create(['user_id' => $businessOwner->id]);

        Referral::create([
            'referrer_id' => $referrer->id,
            'business_id' => $business->id,
            'business_user_id' => $businessOwner->id,
            'referral_code' => 'TEST-' . uniqid(),
            'commission_rate' => 10.00, // 10%
            'status' => Referral::STATUS_ACTIVE,
        ]);

        $commission = $service->createCommissionForPayment($business, 100.00, '2026-01');
        $this->assertNotNull($commission);
        $this->assertEquals(10.00, round((float) $commission->commission_amount, 2));
        $this->assertEquals(ReferralCommission::STATUS_PENDING, $commission->status);

        // Idempotency: same business+period should not create a second commission.
        $commission2 = $service->createCommissionForPayment($business, 100.00, '2026-01');
        $this->assertNotNull($commission2);
        $this->assertEquals($commission->id, $commission2->id);
    }

    /** @test */
    public function printful_order_payload_is_constructed_correctly()
    {
        $product = Product::create([
            'name' => 'Tee',
            'slug' => 'tee-' . uniqid(),
            'category' => 't-shirt',
            'base_price' => 10,
            'printful_product_id' => 1234,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'type' => 'merch',
            'order_number' => 'ORD-SAFE-1',
            'payment_status' => 'paid',
            'status' => 'processing',
            'printful_order_id' => null,
            'shipping_name' => 'Test User',
            'shipping_address_1' => '123 Test St',
            'shipping_city' => 'Testville',
            'shipping_state' => 'CA',
            'shipping_zip' => '90210',
            'shipping_country' => 'US',
            'subtotal' => 20,
            'shipping_cost' => 2,
            'tax' => 1,
            'total' => 23,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 10,
            'total_price' => 20,
            'product_name' => 'Tee',
            'variant' => 'M',
            'design_data' => [
                'printful_variant_id' => 999,
            ],
        ]);

        $captured = [
            'url' => null,
            'method' => null,
            'data' => null,
        ];

        // Fake with a callback so we always capture the actual URL used.
        Http::fake(function ($request) use (&$captured) {
            $captured['url'] = $request->url();
            $captured['method'] = $request->method();
            $captured['data'] = $request->data();

            return Http::response([
                'result' => [
                    'id' => 'pf_safe_1',
                    'status' => 'pending',
                ],
            ], 200);
        });

        $service = app(PrintfulService::class);
        $result = $service->createOrder($order);

        $this->assertEquals('https://api.printful.com/orders', $captured['url']);
        $this->assertEquals('POST', $captured['method']);
        $this->assertEquals($order->order_number, $captured['data']['external_id'] ?? null);
        $this->assertEquals('Test User', $captured['data']['recipient']['name'] ?? null);
        $this->assertEquals(2, $captured['data']['items'][0]['quantity'] ?? null);

        $this->assertEquals('pf_safe_1', $result['id']);
    }

    /** @test */
    public function stripe_webhook_is_handled_safely()
    {
        // Keep this test safe/offline: disable signature verification and send a fake webhook payload.
        config(['services.stripe.webhook_secret' => null]);

        $payload = [
            'id' => 'evt_test_123',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_abc',
                    'payment_intent' => 'pi_test_abc',
                    'metadata' => ['order_id' => 1],
                ]
            ]
        ];

        $order = Order::factory()->create([
            'id' => 1,
            'total' => 50.00,
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        $response = $this->postJson(route('webhooks.stripe'), $payload);

        $response->assertStatus(200);
        
        // Verify order updated (paid + processing is current behavior)
        $this->assertDatabaseHas('orders', [
            'id' => 1,
            'payment_status' => 'paid',
            'status' => 'processing',
        ]);
    }

    /** @test */
    public function avery_csv_generation_is_correct()
    {
        $service = app(AveryDpoService::class);
        
        $urls = [
            'https://example.com/qr/1',
            'https://example.com/qr/2',
        ];
        
        $csv = $service->buildSingleColumnCsv('qr_url', $urls);
        
        $expected = "qr_url\nhttps://example.com/qr/1\nhttps://example.com/qr/2";
        $this->assertEquals($expected, $csv);
    }
}

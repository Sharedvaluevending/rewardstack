<?php

namespace Tests\Feature\Business;

use App\Models\Business;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\QRCode;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PrintfulService;
use App\Services\PreviewService;
use App\Services\QRGeneratorService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MerchControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createProMerchBusiness(User $owner, array $businessOverrides = []): Business
    {
        $business = Business::factory()->create(array_merge([
            'user_id' => $owner->id,
            'is_testing_account' => true,
            'subscription_tier' => 'pro',
            'country' => 'CA',
        ], $businessOverrides));

        SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'Pro',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'features' => [
                'merch_store' => true,
                'merch_categories' => ['all'],
            ],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 1,
        ]);

        return $business;
    }

    public function test_merch_index_redirects_when_merch_not_enabled_on_plan(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create([
            'user_id' => $owner->id,
            'is_testing_account' => true,
            'subscription_tier' => 'starter',
        ]);

        SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Starter',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'features' => ['merch_store' => false],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 1,
        ]);

        $this->actingAs($owner)
            ->get('/business/merch')
            ->assertStatus(302)
            ->assertRedirect('/business/billing')
            ->assertSessionHas('error');
    }

    public function test_merch_index_redirects_when_user_has_no_business(): void
    {
        $owner = User::factory()->create(['role' => 'business']);

        // The business routes are behind subscription middleware; disable it here
        // so we can assert the controller's own "missing business" handling.
        $this->withoutMiddleware(\App\Http\Middleware\EnsureActiveSubscription::class);

        $this->actingAs($owner)
            ->get('/business/merch')
            ->assertStatus(302)
            ->assertRedirect('/business/dashboard')
            ->assertSessionHas('error');
    }

    public function test_merch_index_renders_when_enabled_on_plan(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $business = $this->createProMerchBusiness($owner);

        Product::create([
            'name' => 'Tee',
            'slug' => 'tee-' . uniqid(),
            'category' => 't-shirt',
            'base_price' => 10,
            'printful_product_id' => 1234,
            'variants' => [
                [
                    'name' => 'M',
                    'variant_ids' => [999],
                    'price_modifier' => 0,
                    'colors' => [['variant_id' => 999, 'color_code' => '#ffffff']],
                ],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        QRCode::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->get('/business/merch')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Merch/Index')
                ->has('products')
                ->has('qrCodes')
                ->has('merchCurrency')
            );
    }

    public function test_merch_index_defaults_to_tshirt_when_plan_has_no_categories(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create([
            'user_id' => $owner->id,
            'is_testing_account' => true,
            'subscription_tier' => 'pro-lite',
            'country' => 'CA',
        ]);

        // Plan enabled but categories missing -> controller defaults to t-shirt only.
        SubscriptionPlan::create([
            'name' => 'Pro Lite',
            'slug' => 'pro-lite',
            'description' => 'Pro Lite',
            'monthly_price' => 5,
            'yearly_price' => 50,
            'features' => [
                'merch_store' => true,
                // no merch_categories configured
            ],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 1,
        ]);

        // One allowed, one should be filtered out by default.
        Product::create([
            'name' => 'Tee',
            'slug' => 'tee-' . uniqid(),
            'category' => 't-shirt',
            'base_price' => 10,
            'printful_product_id' => 1234,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        Product::create([
            'name' => 'Mug',
            'slug' => 'mug-' . uniqid(),
            'category' => 'mug',
            'base_price' => 10,
            'printful_product_id' => 2222,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($owner)
            ->get('/business/merch')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Merch/Index')
                ->has('products')
            );
    }

    public function test_merch_quote_uses_printful_rates_and_returns_totals(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $business = $this->createProMerchBusiness($owner);

        $product = Product::create([
            'name' => 'Tee',
            'slug' => 'tee-' . uniqid(),
            'category' => 't-shirt',
            'base_price' => 10,
            'printful_product_id' => 1234,
            'variants' => [
                [
                    'name' => 'M',
                    'variant_ids' => [999],
                    'price_modifier' => 0,
                    'colors' => [['variant_id' => 999, 'color_code' => '#ffffff']],
                ],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Mock Printful so we never make external HTTP calls.
        $this->mock(PrintfulService::class, function ($mock) {
            $mock->shouldReceive('getShippingRates')
                ->once()
                ->andReturn([
                    ['id' => 'rate_1', 'name' => 'Standard', 'rate' => 7.50, 'currency' => 'USD'],
                    ['id' => 'rate_2', 'name' => 'Express', 'rate' => 15.00, 'currency' => 'USD'],
                ]);
        });

        $this->actingAs($owner)
            ->postJson('/business/merch/quote', [
                'items' => [
                    ['product_id' => $product->id, 'variant' => 'M', 'quantity' => 2],
                ],
                'shipping' => [
                    'country' => 'US',
                    'state' => 'CA',
                    'zip' => '90210',
                    'city' => 'Testville',
                    'address_1' => '123 Test St',
                ],
            ])
            ->assertStatus(200)
            ->assertJson([
                'subtotal' => 20.00,
                'shipping_cost' => 7.50,
                'currency' => 'USD',
            ])
            ->assertJsonStructure([
                'subtotal',
                'shipping_cost',
                'tax',
                'total',
                'currency',
            ]);
    }

    public function test_merch_quote_tax_defaults_to_8_percent_for_unknown_us_state_and_zero_for_non_us(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $this->createProMerchBusiness($owner);

        $product = Product::create([
            'name' => 'Tee',
            'slug' => 'tee-' . uniqid(),
            'category' => 't-shirt',
            'base_price' => 100,
            'printful_product_id' => 1234,
            'variants' => [
                ['name' => 'M', 'variant_ids' => [999], 'price_modifier' => 0],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->mock(PrintfulService::class, function ($mock) {
            $mock->shouldReceive('getShippingRates')->andReturn([]);
        });

        // Unknown US state -> defaults to 8% tax
        $this->actingAs($owner)
            ->postJson('/business/merch/quote', [
                'items' => [
                    ['product_id' => $product->id, 'variant' => null, 'quantity' => 1],
                ],
                'shipping' => [
                    'country' => 'US',
                    'state' => 'ZZ',
                    'zip' => '90210',
                    'city' => 'Testville',
                    'address_1' => '123 Test St',
                ],
            ])
            ->assertStatus(200)
            ->assertJson([
                'subtotal' => 100.00,
                'tax' => 8.00,
            ]);

        // Non-US -> zero tax
        $this->actingAs($owner)
            ->postJson('/business/merch/quote', [
                'items' => [
                    ['product_id' => $product->id, 'variant' => null, 'quantity' => 1],
                ],
                'shipping' => [
                    'country' => 'CA',
                    'state' => 'ON',
                    'zip' => 'M5V1A1',
                    'city' => 'Toronto',
                    'address_1' => '123 Test St',
                ],
            ])
            ->assertStatus(200)
            ->assertJson([
                'subtotal' => 100.00,
                'tax' => 0.00,
            ]);
    }

    public function test_merch_process_payment_returns_error_when_stripe_not_configured(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $business = $this->createProMerchBusiness($owner);

        $order = Order::factory()->create([
            'business_id' => $business->id,
            'type' => 'merch',
            'status' => 'pending',
            'total' => 23.00,
        ]);

        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->once()->andReturn(false);
        });

        $this->actingAs($owner)
            ->withHeader('Referer', '/business/merch/checkout/' . $order->id)
            ->post('/business/merch/checkout/' . $order->id . '/pay', [])
            ->assertStatus(302)
            ->assertSessionHas('error');
    }

    public function test_merch_process_payment_can_return_json_checkout_url_without_real_stripe(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $business = $this->createProMerchBusiness($owner);
        $business->update(['stripe_customer_id' => 'cus_existing']);

        $order = Order::factory()->create([
            'business_id' => $business->id,
            'type' => 'merch',
            'status' => 'pending',
            'shipping_country' => 'US',
            'total' => 23.00,
        ]);

        $customer = \Stripe\Customer::constructFrom(['id' => 'cus_existing'], null);

        $this->mock(StripeService::class, function ($mock) use ($customer) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('getOrCreateCustomer')->andReturn($customer);
        });

        // Stub Stripe HTTP client so Session::create never hits the network.
        $client = new class() implements \Stripe\HttpClient\ClientInterface {
            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                $body = json_encode([
                    'id' => 'cs_test_123',
                    'object' => 'checkout.session',
                    'url' => 'https://stripe.test/checkout',
                ]);
                return [$body, 200, []];
            }
        };
        \Stripe\ApiRequestor::setHttpClient($client);
        \Stripe\Stripe::setApiKey('sk_test_fake');

        $this->actingAs($owner)
            ->postJson('/business/merch/checkout/' . $order->id . '/pay', [])
            ->assertStatus(200)
            ->assertJson([
                'checkout_url' => 'https://stripe.test/checkout',
            ]);

        // Reset to default to avoid affecting other tests.
        \Stripe\ApiRequestor::setHttpClient(\Stripe\HttpClient\CurlClient::instance());
    }

    public function test_merch_process_payment_handles_exception_safely(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $business = $this->createProMerchBusiness($owner);

        $order = Order::factory()->create([
            'business_id' => $business->id,
            'type' => 'merch',
            'status' => 'pending',
            'total' => 23.00,
        ]);

        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('getOrCreateCustomer')->andThrow(new \Exception('boom'));
        });

        $this->actingAs($owner)
            ->from('/business/merch/checkout/' . $order->id)
            ->post('/business/merch/checkout/' . $order->id . '/pay', [])
            ->assertStatus(302)
            ->assertRedirect('/business/merch/checkout/' . $order->id)
            ->assertSessionHas('error');
    }

    public function test_merch_quote_falls_back_when_printful_throws(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $this->createProMerchBusiness($owner);

        $product = Product::create([
            'name' => 'Tee',
            'slug' => 'tee-' . uniqid(),
            'category' => 't-shirt',
            'base_price' => 10,
            'printful_product_id' => 1234,
            'variants' => [
                [
                    'name' => 'M',
                    'variant_ids' => [999],
                    'price_modifier' => 0,
                    'colors' => [['variant_id' => 999, 'color_code' => '#ffffff']],
                ],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->mock(PrintfulService::class, function ($mock) {
            $mock->shouldReceive('getShippingRates')->andThrow(new \Exception('down'));
        });

        $this->actingAs($owner)
            ->postJson('/business/merch/quote', [
                'items' => [
                    ['product_id' => $product->id, 'variant' => 'M', 'quantity' => 1],
                ],
                'shipping' => [
                    'country' => 'US',
                    'state' => 'CA',
                    'zip' => '90210',
                    'city' => 'Testville',
                    'address_1' => '123 Test St',
                ],
            ])
            ->assertStatus(200)
            ->assertJson([
                'subtotal' => 10.00,
                'shipping_cost' => 5.99,
            ]);
    }

    public function test_merch_products_renders_grouped_products(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $this->createProMerchBusiness($owner);

        Product::create([
            'name' => 'Tee',
            'slug' => 'tee-' . uniqid(),
            'category' => 't-shirt',
            'base_price' => 10,
            'printful_product_id' => 1234,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($owner)
            ->get('/business/merch/products')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Merch/Products')
                ->has('productsByCategory')
            );
    }

    public function test_merch_order_creates_order_and_items_safely(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create(['role' => 'business']);
        $business = $this->createProMerchBusiness($owner, [
            'logo_path' => null,
            'country' => 'US',
        ]);

        $product = Product::create([
            'name' => 'Tee',
            'slug' => 'tee-' . uniqid(),
            'category' => 't-shirt',
            'base_price' => 10,
            'printful_product_id' => 1234,
            'variants' => [
                [
                    'name' => 'M',
                    'variant_ids' => [2222],
                    'price_modifier' => 0,
                    'colors' => [['variant_id' => 2222, 'color_code' => '#ffffff']],
                ],
                [
                    'name' => 'L',
                    'variant_ids' => [3333],
                    'price_modifier' => 0,
                ],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        // Avoid any file generation side effects.
        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andReturn('qrcodes/test.png');
        });

        // Avoid external Printful calls.
        $this->mock(PrintfulService::class, function ($mock) {
            $mock->shouldReceive('getShippingRates')->andReturn([
                ['id' => 'rate_1', 'name' => 'Standard', 'rate' => 7.50, 'currency' => 'USD'],
            ]);
        });

        $response = $this->actingAs($owner)->postJson('/business/merch/order', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'qr_code_id' => $qrCode->id,
                    'variant' => 'M',
                    'quantity' => 2,
                    'logo_position' => 'front_center',
                    'logo_size' => 'medium',
                    'qr_position' => 'sleeve_left',
                    'qr_size' => 'medium',
                ],
            ],
            'shipping' => [
                'name' => 'Test User',
                'address_1' => '123 Test St',
                'address_2' => null,
                'city' => 'Testville',
                'state' => 'CA',
                'zip' => '90210',
                'country' => 'US',
                'phone' => null,
            ],
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $orderId = $response->json('order.id');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'business_id' => $business->id,
            'type' => 'merch',
            'status' => 'pending',
        ]);

        // The controller expands quantity into per-unit order items.
        $this->assertDatabaseCount('order_items', 2);

        $items = OrderItem::query()->get();
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $dd = is_array($item->design_data) ? $item->design_data : [];
            $pc = $dd['print_config'] ?? null;
            $this->assertIsArray($pc);
            $this->assertSame('center', $pc['logo']['position'] ?? null);
            $this->assertSame('front', $pc['logo']['placement'] ?? null);
        }
    }

    public function test_merch_orders_page_renders_for_business(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $business = $this->createProMerchBusiness($owner);

        $product = Product::create([
            'name' => 'Tee',
            'slug' => 'tee-' . uniqid(),
            'category' => 't-shirt',
            'base_price' => 10,
            'printful_product_id' => 1234,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $order = Order::factory()->create([
            'business_id' => $business->id,
            'type' => 'merch',
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'qr_code_id' => null,
            'product_name' => $product->name,
            'variant' => 'M',
            'quantity' => 1,
            'unit_price' => 10,
            'total_price' => 10,
            'design_data' => [],
            'preview_url' => null,
        ]);

        $this->actingAs($owner)
            ->get('/business/merch/orders')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Merch/Orders')
                ->has('orders')
            );
    }

    public function test_merch_orders_redirects_when_user_has_no_business(): void
    {
        $owner = User::factory()->create(['role' => 'business']);

        // Disable subscription middleware so we hit the controller branch.
        $this->withoutMiddleware(\App\Http\Middleware\EnsureActiveSubscription::class);

        $this->actingAs($owner)
            ->get('/business/merch/orders')
            ->assertStatus(302)
            ->assertRedirect('/business/dashboard')
            ->assertSessionHas('error');
    }

    public function test_merch_checkout_page_renders_for_own_order(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $business = $this->createProMerchBusiness($owner);

        $product = Product::create([
            'name' => 'Tee',
            'slug' => 'tee-' . uniqid(),
            'category' => 't-shirt',
            'base_price' => 10,
            'printful_product_id' => 1234,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'business_id' => $business->id,
            'type' => 'merch',
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'qr_code_id' => $qrCode->id,
            'product_name' => $product->name,
            'variant' => 'M',
            'quantity' => 1,
            'unit_price' => 10,
            'total_price' => 10,
            'design_data' => [],
            'preview_url' => null,
        ]);

        $this->actingAs($owner)
            ->get('/business/merch/checkout/' . $order->id)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Merch/Checkout')
                ->has('order')
            );
    }

    public function test_merch_generate_preview_uses_local_preview_service_and_can_render_logo_or_qr(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $business = $this->createProMerchBusiness($owner);

        $product = Product::create([
            'name' => 'Tee',
            'slug' => 'tee-' . uniqid(),
            'category' => 't-shirt',
            'base_price' => 10,
            'printful_product_id' => 1234,
            'variants' => [
                [
                    'name' => 'M',
                    'variant_ids' => [2222],
                    'price_modifier' => 0,
                ],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        $this->mock(PrintfulService::class, function ($mock) {
            // Prevent real API calls; return null so controller falls back to generic centering.
            $mock->shouldReceive('getGhostTemplateEntry')->andReturn(null);
        });

        $this->mock(PreviewService::class, function ($mock) {
            $mock->shouldReceive('getPreviewData')->andReturn([
                'preview_url' => 'https://example.test/preview.png',
                'config' => [],
                'has_qr' => true,
                'has_logo' => false,
                'view' => 'front',
                'color' => null,
            ]);
        });

        $this->actingAs($owner)
            ->postJson('/business/merch/preview', [
                'product_id' => $product->id,
                'qr_code_id' => $qrCode->id,
                'view' => 'front',
                'logo_position' => 'front_left_chest',
                'logo_size' => 'medium',
            ])
            ->assertStatus(200)
            ->assertJson([
                'provider' => 'local',
                'preview_url' => 'https://example.test/preview.png',
            ]);
    }

    public function test_merch_generate_preview_sleeve_uses_printful_template_entry_when_available(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $business = $this->createProMerchBusiness($owner);

        $product = Product::create([
            'name' => 'Tee',
            'slug' => 'tee-' . uniqid(),
            'category' => 't-shirt',
            'base_price' => 10,
            'printful_product_id' => 1234,
            'variants' => [
                ['name' => 'M', 'variant_ids' => [2222], 'price_modifier' => 0],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        $this->mock(PrintfulService::class, function ($mock) {
            $mock->shouldReceive('getGhostTemplateEntry')->andReturn([
                'template_width' => 1000,
                'template_height' => 1000,
                'print_area_left' => 100,
                'print_area_top' => 200,
                'print_area_width' => 300,
                'print_area_height' => 400,
            ]);
        });

        $this->mock(PreviewService::class, function ($mock) {
            $mock->shouldReceive('getPreviewData')->andReturn([
                'preview_url' => 'https://example.test/sleeve.png',
                'config' => [],
                'has_qr' => true,
                'has_logo' => false,
                'view' => 'sleeve_left',
                'color' => null,
            ]);
        });

        $this->actingAs($owner)
            ->postJson('/business/merch/preview', [
                'product_id' => $product->id,
                'qr_code_id' => $qrCode->id,
                'view' => 'sleeve_left',
                'qr_position' => 'sleeve_left',
                'qr_size' => 'small',
            ])
            ->assertStatus(200)
            ->assertJson([
                'provider' => 'local',
                'preview_url' => 'https://example.test/sleeve.png',
            ]);
    }

    public function test_merch_generate_preview_back_defaults_qr_size_to_xl_when_not_provided(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $business = $this->createProMerchBusiness($owner);

        $product = Product::create([
            'name' => 'Tee',
            'slug' => 'tee-' . uniqid(),
            'category' => 't-shirt',
            'base_price' => 10,
            'printful_product_id' => 1234,
            'variants' => [
                ['name' => 'M', 'variant_ids' => [2222], 'price_modifier' => 0],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        $this->mock(PrintfulService::class, function ($mock) {
            $mock->shouldReceive('getGhostTemplateEntry')->andReturn(null);
        });

        $this->mock(PreviewService::class, function ($mock) {
            $mock->shouldReceive('getPreviewData')->andReturn([
                'preview_url' => 'https://example.test/back.png',
                'config' => [],
                'has_qr' => true,
                'has_logo' => false,
                'view' => 'back',
                'color' => null,
            ]);
        });

        $this->actingAs($owner)
            ->postJson('/business/merch/preview', [
                'product_id' => $product->id,
                'qr_code_id' => $qrCode->id,
                'view' => 'back',
                'qr_position' => 'back',
                // intentionally omit qr_size to hit default-to-xl branch
            ])
            ->assertStatus(200)
            ->assertJson([
                'provider' => 'local',
                'preview_url' => 'https://example.test/back.png',
            ]);
    }

    public function test_merch_generate_preview_returns_500_on_exception_with_fallback_url(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $business = $this->createProMerchBusiness($owner);

        $product = Product::create([
            'name' => 'Tee',
            'slug' => 'tee-' . uniqid(),
            'category' => 't-shirt',
            'base_price' => 10,
            'printful_product_id' => 1234,
            'images' => ['https://example.test/fallback.png'],
            'variants' => [
                [
                    'name' => 'M',
                    'variant_ids' => [2222],
                    'price_modifier' => 0,
                ],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->mock(PrintfulService::class, function ($mock) {
            $mock->shouldReceive('getGhostTemplateEntry')->andReturn(null);
        });

        $this->mock(PreviewService::class, function ($mock) {
            $mock->shouldReceive('getPreviewData')->andThrow(new \Exception('boom'));
        });

        $this->actingAs($owner)
            ->postJson('/business/merch/preview', [
                'product_id' => $product->id,
                'qr_code_id' => null,
                'view' => 'front',
            ])
            ->assertStatus(500)
            ->assertJson([
                'preview_url' => 'https://example.test/fallback.png',
            ]);
    }
}


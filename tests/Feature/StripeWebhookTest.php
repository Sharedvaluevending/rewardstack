<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_rejects_webhook_with_invalid_signature()
    {
        config(['services.stripe.webhook_secret' => 'test_secret']);

        $response = $this->postJson('/webhooks/stripe', [
            'type' => 'customer.subscription.created',
            'data' => ['object' => []],
        ], [
            'Stripe-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(400);
    }

    /** @test */
    public function it_handles_subscription_created_webhook()
    {
        // Disable webhook signature verification for tests
        config(['services.stripe.webhook_secret' => null]);
        
        $business = Business::factory()->create([
            'stripe_subscription_id' => null,
            'subscription_tier' => 'starter',
        ]);
        $plan = SubscriptionPlan::factory()->create([
            'slug' => 'growth',
        ]);

        $payload = [
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'id' => 'sub_test123',
                    'metadata' => [
                        'business_id' => $business->id,
                        'plan_id' => $plan->id,
                    ],
                ],
            ],
        ];

        // Use postJson - controller now handles both formats
        $response = $this->postJson('/webhooks/stripe', $payload);

        $response->assertStatus(200);
        $business->refresh();
        $this->assertEquals('sub_test123', $business->stripe_subscription_id);
        $this->assertEquals('growth', $business->subscription_tier);
    }

    /** @test */
    public function it_handles_subscription_updated_webhook()
    {
        // Disable webhook signature verification for tests
        config(['services.stripe.webhook_secret' => null]);
        
        $plan = SubscriptionPlan::factory()->create([
            'slug' => 'growth',
            'stripe_monthly_price_id' => 'price_test123',
        ]);
        $business = Business::factory()->create([
            'stripe_subscription_id' => 'sub_test123',
            'subscription_tier' => 'starter',
        ]);

        $payload = [
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_test123',
                    'items' => [
                        'data' => [
                            [
                                'price' => [
                                    'id' => 'price_test123',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/stripe', $payload);

        $response->assertStatus(200);
        $business->refresh();
        $this->assertEquals('growth', $business->subscription_tier);
    }

    /** @test */
    public function it_handles_subscription_deleted_webhook()
    {
        // Disable webhook signature verification for tests
        config(['services.stripe.webhook_secret' => null]);
        
        $business = Business::factory()->create([
            'stripe_subscription_id' => 'sub_test123',
            'subscription_tier' => 'growth',
        ]);

        $payload = [
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id' => 'sub_test123',
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/stripe', $payload);

        $response->assertStatus(200);
        $business->refresh();
        $this->assertNull($business->stripe_subscription_id);
        $this->assertEquals('starter', $business->subscription_tier);
    }

    /** @test */
    public function it_handles_invoice_payment_failed_webhook()
    {
        // Disable webhook signature verification for tests
        config(['services.stripe.webhook_secret' => null]);
        
        $business = Business::factory()->create([
            'stripe_customer_id' => 'cus_test123',
        ]);

        $payload = [
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'customer' => 'cus_test123',
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/stripe', $payload);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_ignores_webhook_without_business_id()
    {
        // Disable webhook signature verification for tests
        config(['services.stripe.webhook_secret' => null]);
        
        $payload = [
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'id' => 'sub_test123',
                    'metadata' => [],
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/stripe', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'no_business_id']);
    }

    /** @test */
    public function it_rejects_webhook_when_signature_missing_and_secret_configured()
    {
        config(['services.stripe.webhook_secret' => 'test_secret']);

        $response = $this->postJson('/webhooks/stripe', [
            'type' => 'customer.subscription.created',
            'data' => ['object' => []],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid signature']);
    }

    /** @test */
    public function it_marks_payment_intent_failed(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $order = \App\Models\Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        $resp = $this->postJson('/webhooks/stripe', [
            'id' => 'evt_pi_failed_1',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_fail_1',
                    'metadata' => [
                        'order_id' => $order->id,
                    ],
                ],
            ],
        ]);

        $resp->assertStatus(200)->assertJson(['status' => 'success']);

        $order->refresh();
        $this->assertEquals('failed', $order->payment_status);
    }

    /** @test */
    public function it_handles_charge_refunded_without_payment_intent(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $resp = $this->postJson('/webhooks/stripe', [
            'id' => 'evt_no_pi_refund',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [],
            ],
        ]);

        $resp->assertStatus(200)->assertJson(['status' => 'no_payment_intent']);
    }

    /** @test */
    public function it_handles_charge_dispute_without_payment_intent(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $resp = $this->postJson('/webhooks/stripe', [
            'id' => 'evt_no_pi_dispute',
            'type' => 'charge.dispute.created',
            'data' => [
                'object' => [],
            ],
        ]);

        $resp->assertStatus(200)->assertJson(['status' => 'no_payment_intent']);
    }
}


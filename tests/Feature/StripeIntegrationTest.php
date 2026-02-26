<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected StripeService $stripeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkipped('Stripe tests excluded from automated tests (handled manually).');
        $this->stripeService = app(StripeService::class);
    }

    /** @test */
    public function it_creates_stripe_customer_for_business()
    {
        $business = Business::factory()->create([
            'stripe_customer_id' => null,
        ]);

        // Skip if Stripe not configured or using fake key
        if (!$this->stripeService->isConfigured() || config('services.stripe.secret') === 'sk_test_fake') {
            $this->markTestSkipped('Stripe not configured or using fake key');
        }

        try {
            $customer = $this->stripeService->getOrCreateCustomer($business);

            $this->assertNotNull($customer);
            $this->assertNotNull($business->fresh()->stripe_customer_id);
            $this->assertEquals($customer->id, $business->fresh()->stripe_customer_id);
        } catch (\Stripe\Exception\AuthenticationException $e) {
            $this->markTestSkipped('Stripe API key invalid: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_returns_existing_customer_if_already_exists()
    {
        $business = Business::factory()->create([
            'stripe_customer_id' => 'cus_test123',
        ]);

        if (!$this->stripeService->isConfigured() || config('services.stripe.secret') === 'sk_test_fake') {
            $this->markTestSkipped('Stripe not configured or using fake key');
        }

        try {
            // Should not create new customer
            $customer = $this->stripeService->getOrCreateCustomer($business);

            $this->assertNotNull($customer);
            $this->assertEquals('cus_test123', $business->fresh()->stripe_customer_id);
        } catch (\Stripe\Exception\AuthenticationException $e) {
            $this->markTestSkipped('Stripe API key invalid: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_validates_price_id_before_creating_checkout()
    {
        $business = Business::factory()->create();
        $plan = SubscriptionPlan::factory()->create([
            'stripe_monthly_price_id' => null,
            'stripe_yearly_price_id' => null,
        ]);

        if (!$this->stripeService->isConfigured() || config('services.stripe.secret') === 'sk_test_fake') {
            $this->markTestSkipped('Stripe not configured or using fake key');
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Stripe price ID not configured');

        try {
            $this->stripeService->createCheckoutSession($business, $plan, 'monthly');
        } catch (\Stripe\Exception\AuthenticationException $e) {
            $this->markTestSkipped('Stripe API key invalid: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_creates_billing_portal_session()
    {
        $business = Business::factory()->create([
            'stripe_customer_id' => null,
        ]);

        if (!$this->stripeService->isConfigured() || config('services.stripe.secret') === 'sk_test_fake') {
            $this->markTestSkipped('Stripe not configured or using fake key');
        }

        try {
            // Create customer first
            $this->stripeService->getOrCreateCustomer($business);

            $session = $this->stripeService->createPortalSession($business);

            $this->assertNotNull($session);
            $this->assertStringContainsString('billing.stripe.com', $session->url);
        } catch (\Stripe\Exception\AuthenticationException $e) {
            $this->markTestSkipped('Stripe API key invalid: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_subscription_status_retrieval()
    {
        $business = Business::factory()->create([
            'stripe_subscription_id' => null,
            'trial_ends_at' => now()->addDays(10),
        ]);

        $status = $this->stripeService->getSubscriptionStatus($business);

        $this->assertIsArray($status);
        $this->assertTrue($status['is_trial']);
        $this->assertTrue($status['is_active']);
    }

    /** @test */
    public function it_handles_missing_subscription_gracefully()
    {
        $business = Business::factory()->create([
            'stripe_subscription_id' => null,
            'trial_ends_at' => null,
        ]);

        $status = $this->stripeService->getSubscriptionStatus($business);

        $this->assertEquals('none', $status['status']);
        $this->assertFalse($status['is_active']);
    }

    /** @test */
    public function business_can_access_feature_based_on_tier()
    {
        $plan = SubscriptionPlan::factory()->create([
            'slug' => 'pro',
            'features' => [
                'custom_domain' => true,
                'white_label' => false,
                'api_access' => true,
            ],
        ]);

        $business = Business::factory()->create([
            'subscription_tier' => 'pro',
        ]);

        $this->assertTrue($business->canAccess('custom_domain'));
        $this->assertTrue($business->canAccess('api_access'));
        $this->assertFalse($business->canAccess('white_label'));
    }

    /** @test */
    public function business_gets_correct_limits_based_on_tier()
    {
        $plan = SubscriptionPlan::factory()->create([
            'slug' => 'growth',
            'features' => [
                'qr_codes' => 50,
                'promotions' => 25,
                'employees' => 10,
            ],
        ]);

        $business = Business::factory()->create([
            'subscription_tier' => 'growth',
        ]);

        $this->assertEquals(50, $business->getLimit('qr_codes'));
        $this->assertEquals(25, $business->getLimit('promotions'));
        $this->assertEquals(10, $business->getLimit('employees'));
    }

    /** @test */
    public function trial_expiration_middleware_blocks_access()
    {
        $user = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create([
            'user_id' => $user->id,
            'trial_ends_at' => now()->subDay(), // Trial expired
            'stripe_subscription_id' => null,
        ]);

        $response = $this->actingAs($user)->get('/business/dashboard');

        $response->assertRedirect('/business/billing');
        $response->assertSessionHas('error');
    }

    /** @test */
    public function trial_expiration_middleware_allows_billing_access()
    {
        $user = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create([
            'user_id' => $user->id,
            'trial_ends_at' => now()->subDay(),
            'stripe_subscription_id' => null,
        ]);

        $response = $this->actingAs($user)->get('/business/billing');

        $response->assertOk();
    }

    /** @test */
    public function active_subscription_bypasses_trial_check()
    {
        $user = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create([
            'user_id' => $user->id,
            'trial_ends_at' => now()->subDay(),
            'stripe_subscription_id' => 'sub_test123',
        ]);

        $response = $this->actingAs($user)->get('/business/dashboard');

        $response->assertOk();
    }
}


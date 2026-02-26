<?php

namespace Tests\Feature\Business;

use App\Models\Business;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_index_redirects_when_user_has_no_business(): void
    {
        $user = User::factory()->create(['role' => 'business']);

        $this->actingAs($user)
            ->get('/business/billing')
            ->assertStatus(302)
            ->assertRedirect('/business/dashboard')
            ->assertSessionHas('error');
    }

    public function test_billing_index_renders_and_uses_stripe_service(): void
    {
        $user = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create([
            'user_id' => $user->id,
            'subscription_tier' => 'starter',
        ]);

        SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Starter',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'features' => [],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 1,
        ]);

        $this->mock(StripeService::class, function ($mock) use ($business) {
            $mock->shouldReceive('getSubscriptionStatus')
                ->once()
                ->withArgs(function ($b) use ($business) {
                    return (int) $b->id === (int) $business->id;
                })
                ->andReturn(['status' => 'none', 'is_active' => false, 'is_trial' => false]);

            $mock->shouldReceive('isConfigured')->once()->andReturn(false);
        });

        $this->actingAs($user)
            ->get('/business/billing')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Billing/Index')
                ->has('plans')
                ->has('subscriptionStatus')
                ->has('stripeConfigured')
            );
    }

    public function test_subscribe_returns_500_when_stripe_not_configured(): void
    {
        $user = User::factory()->create(['role' => 'business']);
        Business::factory()->create(['user_id' => $user->id]);

        $plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Starter',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'features' => [],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 1,
        ]);

        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->once()->andReturn(false);
        });

        $this->actingAs($user)
            ->postJson('/business/billing/subscribe', [
                'plan_id' => $plan->id,
                'billing_period' => 'monthly',
            ])
            ->assertStatus(500)
            ->assertJson([
                'error' => 'Payment system is not configured. Please contact support.',
            ]);
    }

    public function test_subscribe_returns_checkout_url_when_configured(): void
    {
        $user = User::factory()->create(['role' => 'business']);
        Business::factory()->create(['user_id' => $user->id]);

        $plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth',
            'description' => 'Growth',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'features' => [],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 2,
        ]);

        $session = \Stripe\Checkout\Session::constructFrom([
            'url' => 'https://stripe.test/checkout',
        ], null);

        // Keep expectations loose: we only care that we get a checkout_url back,
        // and we must not hit the real Stripe API.
        $stripeMock = \Mockery::mock(StripeService::class);
        $stripeMock->shouldReceive('isConfigured')->andReturn(true);
        $stripeMock->shouldReceive('createCheckoutSession')->andReturn($session);
        $this->app->instance(StripeService::class, $stripeMock);
        $this->assertSame($stripeMock, $this->app->make(StripeService::class));

        $this->actingAs($user)
            ->postJson('/business/billing/subscribe', [
                'plan_id' => $plan->id,
                'billing_period' => 'monthly',
            ])
            ->assertStatus(200)
            ->assertJson([
                'checkout_url' => 'https://stripe.test/checkout',
            ]);
    }

    public function test_subscribe_returns_500_when_stripe_returns_null_session(): void
    {
        $user = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create(['user_id' => $user->id]);

        $plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth',
            'description' => 'Growth',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'features' => [],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 2,
        ]);

        $stripeMock = \Mockery::mock(StripeService::class);
        $stripeMock->shouldReceive('isConfigured')->andReturn(true);
        $stripeMock->shouldReceive('createCheckoutSession')->andReturn(null);
        $this->app->instance(StripeService::class, $stripeMock);

        $this->actingAs($user)
            ->postJson('/business/billing/subscribe', [
                'plan_id' => $plan->id,
                'billing_period' => 'monthly',
            ])
            ->assertStatus(500)
            ->assertJson([
                'error' => 'Failed to create checkout session. Please try again.',
            ]);
    }

    public function test_subscribe_returns_500_when_stripe_throws_exception(): void
    {
        $user = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create(['user_id' => $user->id]);

        $plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth',
            'description' => 'Growth',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'features' => [],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 2,
        ]);

        $stripeMock = \Mockery::mock(StripeService::class);
        $stripeMock->shouldReceive('isConfigured')->andReturn(true);
        $stripeMock->shouldReceive('createCheckoutSession')->andThrow(new \Exception('boom'));
        $this->app->instance(StripeService::class, $stripeMock);

        $this->actingAs($user)
            ->postJson('/business/billing/subscribe', [
                'plan_id' => $plan->id,
                'billing_period' => 'monthly',
            ])
            ->assertStatus(500)
            ->assertJson([
                'error' => 'Failed to create checkout session: boom',
            ]);
    }

    public function test_portal_redirects_back_when_stripe_not_configured(): void
    {
        $user = User::factory()->create(['role' => 'business']);
        Business::factory()->create(['user_id' => $user->id]);

        $stripeMock = \Mockery::mock(StripeService::class);
        $stripeMock->shouldReceive('isConfigured')->andReturn(false);
        $this->app->instance(StripeService::class, $stripeMock);

        $this->actingAs($user)
            ->from('/business/billing')
            ->get('/business/billing/portal')
            ->assertStatus(302)
            ->assertRedirect('/business/billing')
            ->assertSessionHas('error');
    }

    public function test_portal_redirects_back_when_customer_create_fails(): void
    {
        $user = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create([
            'user_id' => $user->id,
            'stripe_customer_id' => null,
        ]);

        $stripeMock = \Mockery::mock(StripeService::class);
        $stripeMock->shouldReceive('isConfigured')->andReturn(true);
        $stripeMock->shouldReceive('getOrCreateCustomer')->andThrow(new \Exception('nope'));
        $this->app->instance(StripeService::class, $stripeMock);

        $this->actingAs($user)
            ->from('/business/billing')
            ->get('/business/billing/portal')
            ->assertStatus(302)
            ->assertRedirect('/business/billing')
            ->assertSessionHas('error', 'Failed to create billing account.');
    }

    public function test_portal_redirects_back_when_portal_session_is_null(): void
    {
        $user = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create([
            'user_id' => $user->id,
            'stripe_customer_id' => 'cus_existing',
        ]);

        $stripeMock = \Mockery::mock(StripeService::class);
        $stripeMock->shouldReceive('isConfigured')->andReturn(true);
        $stripeMock->shouldReceive('createPortalSession')->andReturn(null);
        $this->app->instance(StripeService::class, $stripeMock);

        $this->actingAs($user)
            ->from('/business/billing')
            ->get('/business/billing/portal')
            ->assertStatus(302)
            ->assertRedirect('/business/billing')
            ->assertSessionHas('error', 'Failed to open billing portal.');
    }

    public function test_portal_redirects_back_when_portal_session_throws(): void
    {
        $user = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create([
            'user_id' => $user->id,
            'stripe_customer_id' => 'cus_existing',
        ]);

        $stripeMock = \Mockery::mock(StripeService::class);
        $stripeMock->shouldReceive('isConfigured')->andReturn(true);
        $stripeMock->shouldReceive('createPortalSession')->andThrow(new \Exception('boom'));
        $this->app->instance(StripeService::class, $stripeMock);

        $this->actingAs($user)
            ->from('/business/billing')
            ->get('/business/billing/portal')
            ->assertStatus(302)
            ->assertRedirect('/business/billing')
            ->assertSessionHas('error', 'Failed to open billing portal: boom');
    }

    public function test_portal_non_inertia_request_redirects_to_stripe_url(): void
    {
        $user = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create([
            'user_id' => $user->id,
            'stripe_customer_id' => 'cus_existing',
        ]);

        $portalSession = \Stripe\BillingPortal\Session::constructFrom([
            'url' => 'https://billing.stripe.test/portal',
        ], null);

        $stripeMock = \Mockery::mock(StripeService::class);
        $stripeMock->shouldReceive('isConfigured')->andReturn(true);
        $stripeMock->shouldReceive('createPortalSession')->andReturn($portalSession);
        $this->app->instance(StripeService::class, $stripeMock);

        $this->actingAs($user)
            ->get('/business/billing/portal')
            ->assertStatus(302)
            ->assertRedirect('https://billing.stripe.test/portal');
    }

    public function test_portal_inertia_request_returns_inertia_location(): void
    {
        $user = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create([
            'user_id' => $user->id,
            'stripe_customer_id' => null,
        ]);

        $customer = \Stripe\Customer::constructFrom([
            'id' => 'cus_test_123',
        ], null);

        $portalSession = \Stripe\BillingPortal\Session::constructFrom([
            'url' => 'https://billing.stripe.test/portal',
        ], null);

        $stripeMock = \Mockery::mock(StripeService::class);
        $stripeMock->shouldReceive('isConfigured')->once()->andReturn(true);
        $stripeMock->shouldReceive('getOrCreateCustomer')->once()->andReturn($customer);
        $stripeMock->shouldReceive('createPortalSession')->once()->andReturn($portalSession);
        $this->app->instance(StripeService::class, $stripeMock);
        $this->assertSame($stripeMock, $this->app->make(StripeService::class));

        $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->withHeader('Referer', 'https://example.test/business/billing')
            ->get('/business/billing/portal')
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location');
    }
}


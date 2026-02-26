<?php

namespace Tests\Feature\Console;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileStripeSubscriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exits_when_stripe_not_configured(): void
    {
        config(['services.stripe.secret' => '']);

        $this->artisan('stripe:reconcile-subscriptions')
            ->expectsOutputToContain('Stripe SDK or API key not configured')
            ->assertExitCode(0);
    }

    public function test_command_downgrades_orphan_active_businesses(): void
    {
        config(['services.stripe.secret' => 'sk_test_fake']);

        $business = Business::factory()->create([
            'stripe_subscription_id' => null,
            'stripe_customer_id' => null,
            'subscription_status' => 'active',
            'subscription_tier' => 'growth',
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->artisan('stripe:reconcile-subscriptions')
            ->expectsOutputToContain('Found 1 businesses marked active with no subscription')
            ->expectsOutputToContain('downgraded to starter')
            ->assertExitCode(0);

        $business->refresh();
        $this->assertSame('canceled', $business->subscription_status);
        $this->assertSame('starter', $business->subscription_tier);
    }

    public function test_command_dry_run_does_not_update_orphans(): void
    {
        config(['services.stripe.secret' => 'sk_test_fake']);

        $business = Business::factory()->create([
            'stripe_subscription_id' => null,
            'subscription_status' => 'active',
            'subscription_tier' => 'growth',
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->artisan('stripe:reconcile-subscriptions', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->assertExitCode(0);

        $business->refresh();
        $this->assertSame('active', $business->subscription_status);
        $this->assertSame('growth', $business->subscription_tier);
    }

    public function test_command_filters_by_business_option(): void
    {
        config(['services.stripe.secret' => 'sk_test_fake']);

        $b1 = Business::factory()->create([
            'stripe_subscription_id' => null,
            'subscription_status' => 'active',
            'trial_ends_at' => now()->subDay(),
        ]);
        $b2 = Business::factory()->create([
            'stripe_subscription_id' => null,
            'subscription_status' => 'active',
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->artisan('stripe:reconcile-subscriptions', ['--business' => (string) $b1->id])
            ->assertExitCode(0);

        $b1->refresh();
        $b2->refresh();
        $this->assertSame('canceled', $b1->subscription_status);
        $this->assertSame('active', $b2->subscription_status);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\Business;
use App\Models\ReferralPayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminControllersCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_dashboard_returns_200_with_stats(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('stats')
                ->has('recentBusinesses')
            );
    }

    public function test_businesses_index_returns_200(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.businesses.index'))
            ->assertStatus(200);
    }

    public function test_crm_index_returns_200(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.crm'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/CRM/Index'));
    }

    public function test_qrcade_index_returns_200(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.qrcade'))
            ->assertStatus(200);
    }

    public function test_analytics_index_returns_200(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.analytics'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Analytics/Index'));
    }

    public function test_settings_index_returns_200(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Settings/Index'));
    }

    public function test_merch_index_returns_200(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.merch'))
            ->assertStatus(200);
    }

    public function test_referrals_index_returns_200(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.referrals'))
            ->assertStatus(200);
    }

    public function test_users_index_returns_200(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertStatus(200);
    }

    public function test_products_index_returns_200(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.products.index'))
            ->assertStatus(200);
    }

    public function test_analytics_revenue_returns_200_and_inertia(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.analytics.revenue'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Analytics/Revenue'));
    }

    public function test_analytics_usage_returns_200_and_inertia(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.analytics.usage'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Analytics/Usage'));
    }

    public function test_settings_subscriptions_returns_200_and_plans(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.subscriptions'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/Subscriptions')
                ->has('plans'));
    }

    public function test_settings_update_redirects_with_status(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update'), [])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_onboarding_qr_returns_200(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.onboarding-qr'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/OnboardingQr'));
    }

    public function test_qrcade_games_returns_200(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.qrcade.games'))
            ->assertStatus(200);
    }

    public function test_qrcade_packs_returns_200(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.qrcade.packs'))
            ->assertStatus(200);
    }

    public function test_qrcade_seasonal_returns_200(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.qrcade.seasonal'))
            ->assertStatus(200);
    }

    public function test_qrcade_analytics_returns_200(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.qrcade.analytics'))
            ->assertStatus(200);
    }

    public function test_referrals_payouts_returns_200(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.referrals.payouts'))
            ->assertStatus(200);
    }

    public function test_businesses_show_returns_200(): void
    {
        $business = Business::factory()->create();
        $this->actingAs($this->admin)
            ->get(route('admin.businesses.show', $business))
            ->assertStatus(200);
    }

    public function test_users_show_returns_200(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($this->admin)
            ->get(route('admin.users.show', $user))
            ->assertStatus(200);
    }

    public function test_businesses_toggle_redirects_and_flips_is_active(): void
    {
        $business = Business::factory()->create(['is_active' => true]);
        $this->actingAs($this->admin)
            ->post(route('admin.businesses.toggle', $business))
            ->assertRedirect()
            ->assertSessionHas('success');
        $business->refresh();
        $this->assertFalse($business->is_active);

        $this->actingAs($this->admin)
            ->post(route('admin.businesses.toggle', $business))
            ->assertRedirect()
            ->assertSessionHas('success');
        $business->refresh();
        $this->assertTrue($business->is_active);
    }

    public function test_businesses_toggle_testing_redirects_and_flips_is_testing_account(): void
    {
        $business = Business::factory()->create(['is_testing_account' => false]);
        $this->actingAs($this->admin)
            ->post(route('admin.businesses.toggle-testing', $business))
            ->assertRedirect()
            ->assertSessionHas('success');
        $business->refresh();
        $this->assertTrue($business->is_testing_account);
    }

    public function test_users_toggle_redirects_with_success(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($this->admin)
            ->post(route('admin.users.toggle', $user))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->actingAs($this->admin)
            ->post(route('admin.users.toggle', $user))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_referrals_mark_paid_redirects_with_success(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $payout = ReferralPayout::create([
            'user_id' => $user->id,
            'amount' => 25.00,
            'method' => 'stripe',
            'destination' => 'acct_xxx',
            'status' => ReferralPayout::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.referrals.payouts.paid', $payout), [
                'transaction_id' => 'txn_123',
                'notes' => 'Paid via Stripe',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $payout->refresh();
        $this->assertSame(ReferralPayout::STATUS_COMPLETED, $payout->status);
        $this->assertSame('txn_123', $payout->transaction_id);
    }

    public function test_referrals_approve_commissions_redirects_with_success(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.referrals.approve'))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_referrals_reject_payout_redirects_with_success(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $payout = ReferralPayout::create([
            'user_id' => $user->id,
            'amount' => 50.00,
            'method' => 'stripe',
            'destination' => 'acct_xxx',
            'status' => ReferralPayout::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.referrals.payouts.reject', $payout), [
                'reason' => 'Customer requested cancellation',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $payout->refresh();
        $this->assertSame(ReferralPayout::STATUS_FAILED, $payout->status);
        $this->assertStringContainsString('Customer requested cancellation', $payout->notes ?? '');
    }
}

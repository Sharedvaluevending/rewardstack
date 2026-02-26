<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessCustomerSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalBusinessSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscriptions_page_loads_and_subscribe_unsubscribe_work(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $customer = User::factory()->create(['role' => 'customer']);
        $business = Business::factory()->create(['is_active' => true]);

        $this->actingAs($customer)
            ->get('/portal/subscriptions')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Portal/Subscriptions'));

        $this->actingAs($customer)
            ->post("/portal/subscriptions/{$business->id}/subscribe", ['source' => 'portal'])
            ->assertStatus(302);

        $sub = BusinessCustomerSubscription::query()
            ->where('business_id', $business->id)
            ->where('user_id', $customer->id)
            ->first();
        $this->assertNotNull($sub);
        $this->assertNotNull($sub->subscribed_at);
        $this->assertNull($sub->unsubscribed_at);

        $this->actingAs($customer)
            ->post("/portal/subscriptions/{$business->id}/unsubscribe", ['source' => 'portal'])
            ->assertStatus(302);

        $sub->refresh();
        $this->assertNotNull($sub->unsubscribed_at);
    }
}


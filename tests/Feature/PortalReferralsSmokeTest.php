<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalReferralsSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_referrals_page_loads_for_customer(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->get('/portal/referrals')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Portal/Referrals'));
    }
}


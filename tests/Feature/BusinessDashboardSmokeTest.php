<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessDashboardSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_dashboard_loads(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $this->actingAs($owner)
            ->get('/business/dashboard')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/Dashboard'));
    }
}


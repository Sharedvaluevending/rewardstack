<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessCrmPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_crm_pages_load(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $this->actingAs($owner)
            ->get('/business/crm')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/CRM/Index'));

        $this->actingAs($owner)
            ->get('/business/crm/customers')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/CRM/Customers'));

        $this->actingAs($owner)
            ->get('/business/crm/recommendations')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/CRM/Recommendations'));

        $this->actingAs($owner)
            ->get('/business/crm/segments')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/CRM/Segments'));

        $this->actingAs($owner)
            ->get('/business/crm/campaigns')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/CRM/Campaigns/Index'));

        $this->actingAs($owner)
            ->get('/business/crm/campaigns/create')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/CRM/Campaigns/Create'));

        $this->actingAs($owner)
            ->get('/business/crm/automations')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/CRM/Automations'));

        $this->actingAs($owner)
            ->get('/business/crm/settings')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/CRM/Settings'));
    }
}


<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessAIInsightsSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_insights_pages_load_without_generation(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $this->actingAs($owner)
            ->get('/business/ai-insights/basic?period=30')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/AIInsights/Basic'));

        $this->actingAs($owner)
            ->get('/business/ai-insights/advanced?period=90')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/AIInsights/Advanced'));
    }
}


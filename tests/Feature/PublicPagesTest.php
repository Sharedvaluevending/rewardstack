<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render(): void
    {
        $this->get('/')->assertStatus(200)->assertInertia(fn (Assert $page) => $page->component('Welcome'));
        $this->get('/features')->assertStatus(200)->assertInertia(fn (Assert $page) => $page->component('Public/Features'));
        $this->get('/demo')->assertStatus(200)->assertInertia(fn (Assert $page) => $page->component('Public/Demo'));
        $this->get('/privacy')->assertStatus(200)->assertInertia(fn (Assert $page) => $page->component('Public/Privacy'));
        $this->get('/terms')->assertStatus(200)->assertInertia(fn (Assert $page) => $page->component('Public/Terms'));
    }

    public function test_pricing_page_loads_plans_from_db(): void
    {
        Cache::flush();

        SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter-' . uniqid(),
            'description' => 'Test plan',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'features' => ['qr_codes' => 10],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 1,
        ]);

        $this->get('/pricing')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/Pricing')
                    ->has('plans')
                    ->has('plans.0.slug')
            );
    }
}


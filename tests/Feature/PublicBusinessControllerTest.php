<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicBusinessControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_business_show_returns_200_for_active_business(): void
    {
        $business = Business::factory()->create([
            'slug' => 'test-business-' . uniqid(),
            'is_active' => true,
        ]);

        $response = $this->get(route('public.business.show', $business));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->has('business')
                ->has('promotions')
            );
    }

    public function test_public_business_show_returns_404_for_inactive_business(): void
    {
        $business = Business::factory()->create([
            'slug' => 'inactive-' . uniqid(),
            'is_active' => false,
        ]);

        $response = $this->get(route('public.business.show', $business));

        $response->assertStatus(404);
    }
}

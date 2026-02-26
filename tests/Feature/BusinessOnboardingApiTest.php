<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessOnboardingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_endpoints_work(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $this->actingAs($owner)
            ->postJson('/business/onboarding/complete-step', ['step_id' => 'create_promotion'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->actingAs($owner)
            ->postJson('/business/onboarding/dismiss')
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->actingAs($owner)
            ->postJson('/business/onboarding/reopen')
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->actingAs($owner)
            ->getJson('/business/onboarding/progress')
            ->assertStatus(200)
            ->assertJsonStructure([
                'steps',
                'progress' => ['completed', 'total', 'percentage'],
                'show_onboarding',
                'completed',
            ]);
    }
}


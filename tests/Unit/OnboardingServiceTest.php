<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_all_steps_returns_array_with_expected_structure(): void
    {
        $service = new OnboardingService();
        $steps = $service->getAllSteps();

        $this->assertIsArray($steps);
        $this->assertNotEmpty($steps);
        $first = $steps[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('title', $first);
        $this->assertArrayHasKey('route', $first);
        $this->assertArrayHasKey('check', $first);
    }

    public function test_get_tier_display_name_returns_expected_names(): void
    {
        $service = new OnboardingService();
        $this->assertSame('Starter', $service->getTierDisplayName('starter'));
        $this->assertSame('Growth', $service->getTierDisplayName('growth'));
        $this->assertSame('Pro', $service->getTierDisplayName('pro'));
        $this->assertSame('Enterprise', $service->getTierDisplayName('enterprise'));
        $this->assertSame('Custom', $service->getTierDisplayName('custom'));
    }

    public function test_get_steps_for_business_returns_steps_with_completion_flags(): void
    {
        $business = Business::factory()->create();
        $service = new OnboardingService();
        $steps = $service->getStepsForBusiness($business);

        $this->assertIsArray($steps);
        $this->assertNotEmpty($steps);
        foreach ($steps as $step) {
            $this->assertArrayHasKey('is_completed', $step);
            $this->assertArrayHasKey('is_available', $step);
            $this->assertArrayHasKey('is_locked', $step);
            $this->assertArrayNotHasKey('check', $step);
        }
    }
}

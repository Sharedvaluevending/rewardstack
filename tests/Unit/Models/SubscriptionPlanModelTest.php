<?php

namespace Tests\Unit\Models;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_features_is_cast_to_array(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'features' => ['ai_insights' => true, 'leaderboards' => true],
        ]);
        $plan->refresh();
        $this->assertIsArray($plan->features);
        $this->assertTrue($plan->features['ai_insights'] ?? false);
    }

    public function test_factory_creates_valid_plan(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $this->assertNotNull($plan->name);
        $this->assertNotNull($plan->slug);
        $this->assertGreaterThanOrEqual(0, $plan->monthly_price);
    }
}

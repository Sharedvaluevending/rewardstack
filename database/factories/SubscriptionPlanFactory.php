<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->slug(),
            'description' => fake()->sentence(),
            'monthly_price' => fake()->randomFloat(2, 19, 99),
            'yearly_price' => fake()->randomFloat(2, 190, 990),
            'stripe_monthly_price_id' => 'price_' . fake()->bothify('########'),
            'stripe_yearly_price_id' => 'price_' . fake()->bothify('########'),
            'features' => [],
            'is_active' => true,
            'sort_order' => 1,
        ];
    }

    public function growth(): static
    {
        return $this->state(fn () => [
            'slug' => 'growth',
            'name' => 'Growth Plan',
        ]);
    }

    public function starter(): static
    {
        return $this->state(fn () => [
            'slug' => 'starter',
            'name' => 'Starter Plan',
        ]);
    }
}


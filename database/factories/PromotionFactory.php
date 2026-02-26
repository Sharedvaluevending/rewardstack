<?php

namespace Database\Factories;

use App\Models\Promotion;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => fake()->numberBetween(10, 50),
            'is_active' => true,
            'starts_at' => now()->subDays(7),
            'ends_at' => now()->addDays(30),
            'rules' => [],
        ];
    }

    public function percentage(int $value = 20): static
    {
        return $this->state(fn () => [
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => $value,
        ]);
    }

    public function fixedAmount(float $value = 10.00): static
    {
        return $this->state(fn () => [
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => $value,
        ]);
    }

    public function bogo(): static
    {
        return $this->state(fn () => [
            'discount_type' => Promotion::TYPE_BOGO,
        ]);
    }
}


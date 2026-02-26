<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\QRCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QRCode>
 */
class QRCodeFactory extends Factory
{
    protected $model = QRCode::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'code' => Str::random(8),
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(['promotion', 'static', 'dynamic']),
            'destination_url' => 'https://example.com',
            'is_active' => true,
            'total_scans' => 0,
            'unique_scans' => 0,
        ];
    }

    public function promotion(): static
    {
        return $this->state(fn () => [
            'type' => 'promotion',
            'destination_url' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

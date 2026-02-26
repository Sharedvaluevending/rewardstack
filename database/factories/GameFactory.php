<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        // Use unique names to avoid games.slug collisions in tests.
        $name = fake()->unique()->words(2, true);
        
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement([
                Game::TYPE_MEMORY_MATCH,
                Game::TYPE_WORD_SEARCH,
                Game::TYPE_SNAKE,
                Game::TYPE_TAP_COUNTER,
            ]),
            'tier' => Game::TIER_BASIC,
            'category' => fake()->randomElement(['arcade', 'puzzle', 'action']),
            'min_score' => 0,
            'max_score' => 1000,
            'time_limit' => 60,
            'is_active' => true,
            'sort_order' => 1,
        ];
    }

    public function wordSearch(): static
    {
        return $this->state(fn () => [
            'type' => Game::TYPE_WORD_SEARCH,
            'name' => 'Word Search',
            'slug' => 'word-search',
        ]);
    }

    public function withMaxScore(int $maxScore): static
    {
        return $this->state(fn () => [
            'max_score' => $maxScore,
        ]);
    }

    public function withTimeLimit(int $timeLimit): static
    {
        return $this->state(fn () => [
            'time_limit' => $timeLimit,
        ]);
    }
}


<?php

namespace Database\Factories;

use App\Models\Leaderboard;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Leaderboard>
 */
class LeaderboardFactory extends Factory
{
    protected $model = Leaderboard::class;

    public function definition(): array
    {
        $name = 'Leaderboard ' . fake()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'description' => fake()->sentence(),
            'type' => 'global',
            'business_id' => null,
            'game_id' => null,
            'business_ids' => null,
            'reset_frequency' => 'weekly',
            'reset_day' => null,
            'current_period_start' => now()->startOfWeek(),
            'current_period_end' => now()->endOfWeek(),
            'last_reset_at' => null,
            'max_entries' => 100,
            'score_type' => 'highest',
            'show_score' => true,
            'show_games_played' => true,
            'prize_config' => null,
            'skin' => null,
            'sponsor_info' => null,
            'is_premium' => false,
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GamePlay;
use App\Models\GameSession;
use App\Models\Business;
use App\Models\QRCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GamePlayFactory extends Factory
{
    protected $model = GamePlay::class;

    public function definition(): array
    {
        return [
            'game_session_id' => GameSession::factory(),
            'user_id' => User::factory(),
            'game_id' => Game::factory(),
            'business_id' => Business::factory(),
            'qr_code_id' => QRCode::factory(),
            'score' => fake()->numberBetween(0, 1000),
            'duration_seconds' => fake()->numberBetween(10, 60),
            'result' => fake()->randomElement([
                GamePlay::RESULT_WIN,
                GamePlay::RESULT_LOSE,
                GamePlay::RESULT_COMPLETE,
            ]),
            'started_at' => now()->subSeconds(30),
            'completed_at' => now(),
        ];
    }

    public function win(): static
    {
        return $this->state(fn () => [
            'result' => GamePlay::RESULT_WIN,
            'score' => fake()->numberBetween(500, 1000),
        ]);
    }

    public function lose(): static
    {
        return $this->state(fn () => [
            'result' => GamePlay::RESULT_LOSE,
            'score' => fake()->numberBetween(0, 499),
        ]);
    }
}


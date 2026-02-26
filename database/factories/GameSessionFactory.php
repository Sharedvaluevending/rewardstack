<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameSession;
use App\Models\Business;
use App\Models\QRCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GameSessionFactory extends Factory
{
    protected $model = GameSession::class;

    public function definition(): array
    {
        return [
            'session_token' => Str::uuid(),
            'user_id' => User::factory(),
            'qr_code_id' => QRCode::factory(),
            'game_id' => Game::factory(),
            'business_id' => Business::factory(),
            'status' => GameSession::STATUS_ACTIVE,
            'location_status' => GameSession::LOCATION_VERIFIED,
            'expires_at' => now()->addHour(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => GameSession::STATUS_ACTIVE,
        ]);
    }

    public function playing(): static
    {
        return $this->state(fn () => [
            'status' => GameSession::STATUS_PLAYING,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => GameSession::STATUS_COMPLETED,
        ]);
    }
}


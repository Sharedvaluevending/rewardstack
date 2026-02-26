<?php

namespace Database\Factories;

use App\Models\QRCodeGame;
use App\Models\QRCode;
use App\Models\Game;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

class QRCodeGameFactory extends Factory
{
    protected $model = QRCodeGame::class;

    public function definition(): array
    {
        return [
            'qr_code_id' => QRCode::factory(),
            'game_id' => Game::factory(),
            'business_id' => function (array $attributes) {
                return QRCode::find($attributes['qr_code_id'])->business_id;
            },
            'promotion_id' => null,
            'win_mode' => QRCodeGame::WIN_MODE_SCORE,
            'win_threshold_score' => 500,
            'win_probability' => 100,
            'is_active' => true,
        ];
    }

    public function alwaysWin(): static
    {
        return $this->state(fn () => [
            'win_mode' => QRCodeGame::WIN_MODE_ALWAYS,
        ]);
    }

    public function scoreBased(int $threshold = 500): static
    {
        return $this->state(fn () => [
            'win_mode' => QRCodeGame::WIN_MODE_SCORE,
            'win_threshold_score' => $threshold,
        ]);
    }

    public function random(int $probability = 50): static
    {
        return $this->state(fn () => [
            'win_mode' => QRCodeGame::WIN_MODE_RANDOM,
            'win_probability' => $probability,
        ]);
    }
}


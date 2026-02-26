<?php

namespace Database\Factories;

use App\Models\GameReward;
use App\Models\GamePlay;
use App\Models\Promotion;
use App\Services\RewardCodeService;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameRewardFactory extends Factory
{
    protected $model = GameReward::class;

    public function definition(): array
    {
        return [
            'reward_code' => app(RewardCodeService::class)->generateUniqueCode(),
            'game_play_id' => GamePlay::factory(),
            'user_id' => function (array $attributes) {
                return GamePlay::find($attributes['game_play_id'])->user_id;
            },
            'business_id' => function (array $attributes) {
                return GamePlay::find($attributes['game_play_id'])->business_id;
            },
            'promotion_id' => Promotion::factory(),
            'reward_type' => GameReward::TYPE_PERCENTAGE,
            'discount_value' => fake()->randomFloat(2, 5, 50),
            'status' => GameReward::STATUS_AVAILABLE,
            'expires_at' => now()->addDays(30),
            'valid_from' => now(),
        ];
    }

    public function redeemed(): static
    {
        return $this->state(fn () => [
            'status' => GameReward::STATUS_REDEEMED,
            'redeemed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => GameReward::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
    }
}


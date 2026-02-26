<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class NewGamesSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            [
                'name' => 'Vape Cloud Pop',
                'slug' => 'vape-cloud-pop',
                'type' => 'vape_cloud_pop',
                'description' => 'Pop floating vape clouds before they escape! Build combos by popping multiple clouds quickly. Watch out for fast ones!',
                'category' => 'action',
                'tier' => 'premium',
                'time_limit' => 45,
                'min_score' => 0,
                'max_score' => null,
                'thumbnail' => '/images/games/vape-cloud-pop.png',
                'is_active' => true,
                'sort_order' => 11,
                'config' => json_encode([
                    'spawn_rate' => 800,
                    'cloud_speed' => 1,
                    'min_score_to_win' => 300,
                ]),
            ],
            [
                'name' => 'Coffee Rush',
                'slug' => 'coffee-rush',
                'type' => 'coffee_rush',
                'description' => 'Catch falling coffee cups before they hit the ground! Move the tray to save the drinks. Don\'t drop too many!',
                'category' => 'action',
                'tier' => 'premium',
                'time_limit' => 60,
                'min_score' => 0,
                'max_score' => null,
                'thumbnail' => '/images/games/coffee-rush.png',
                'is_active' => true,
                'sort_order' => 13,
                'config' => json_encode([
                    'max_lives' => 5,
                    'spawn_rate' => 1000,
                    'min_score_to_win' => 400,
                ]),
            ],
        ];

        foreach ($games as $gameData) {
            Game::updateOrCreate(
                ['slug' => $gameData['slug']],
                $gameData
            );
        }

        $this->command->info('Added 2 premium games: Vape Cloud Pop, Coffee Rush');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tune early badges so users don't unlock 4-5 badges on their first game.
        // This updates existing installs (seeders use firstOrCreate and won't overwrite).

        $updates = [
            'first-play' => [
                'description' => 'Play 3 games',
                'requirements' => ['type' => 'games_played', 'value' => 3],
            ],
            'first-win' => [
                'description' => 'Win 3 games',
                'requirements' => ['type' => 'wins', 'value' => 3],
            ],
            'high-scorer' => [
                'description' => 'Score over 5000 in a single game',
                'requirements' => ['type' => 'score', 'value' => 5000],
            ],
            'speed-demon' => [
                'description' => 'Complete a game in under 20 seconds',
                'requirements' => ['type' => 'speed_run', 'value' => 20],
            ],
        ];

        foreach ($updates as $slug => $data) {
            DB::table('badges')
                ->where('slug', $slug)
                ->update([
                    'description' => $data['description'],
                    'requirements' => json_encode($data['requirements']),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Best-effort rollback to original seeder defaults.
        $rollbacks = [
            'first-play' => [
                'description' => 'Play your first game',
                'requirements' => ['type' => 'games_played', 'value' => 1],
            ],
            'first-win' => [
                'description' => 'Win your first game',
                'requirements' => ['type' => 'wins', 'value' => 1],
            ],
            'high-scorer' => [
                'description' => 'Score over 1000 in a single game',
                'requirements' => ['type' => 'score', 'value' => 1000],
            ],
            'speed-demon' => [
                'description' => 'Complete a game in under 30 seconds',
                'requirements' => ['type' => 'speed_run', 'value' => 30],
            ],
        ];

        foreach ($rollbacks as $slug => $data) {
            DB::table('badges')
                ->where('slug', $slug)
                ->update([
                    'description' => $data['description'],
                    'requirements' => json_encode($data['requirements']),
                    'updated_at' => now(),
                ]);
        }
    }
};



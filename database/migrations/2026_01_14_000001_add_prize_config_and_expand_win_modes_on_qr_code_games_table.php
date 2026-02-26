<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        // SQLite (tests) doesn't support MySQL-style ALTER TABLE ... AFTER ... or ENUM modifies.
        // Keep tests/migrations portable by using Schema builder where possible.
        if ($driver === 'sqlite') {
            if (!Schema::hasColumn('qr_code_games', 'prize_config')) {
                Schema::table('qr_code_games', function (Blueprint $table) {
                    $table->json('prize_config')->nullable();
                });
            }

            // `win_mode` is treated as TEXT in sqlite; no enum modification needed/possible.
            return;
        }

        // Add prize_config JSON if missing
        if (!Schema::hasColumn('qr_code_games', 'prize_config')) {
            DB::statement("ALTER TABLE `qr_code_games` ADD COLUMN `prize_config` JSON NULL AFTER `win_probability`");
        }

        // Expand win_mode enum to support new modes used by the app.
        // Existing values: always, skill, random, tiered
        // New values: score, time, leaderboard
        DB::statement(
            "ALTER TABLE `qr_code_games` MODIFY `win_mode` ENUM('always','skill','random','tiered','score','time','leaderboard') NOT NULL DEFAULT 'always'"
        );
    }

    public function down(): void
    {
        // Best-effort rollback: keep schema stable for existing data.
        // We do not drop prize_config to avoid data loss, and we do not shrink the enum to avoid invalid values.
    }
};


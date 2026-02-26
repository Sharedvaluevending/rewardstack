<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite does not support ALTER TABLE ... MODIFY or ENUM types.
        // In tests (often sqlite), we can safely no-op here because the column is treated as TEXT.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Align DB enum with application logic:
        // - QRCodeGame and QRcadeController expect score/time/leaderboard modes.
        // - Existing DB enum was limited and caused writes (and correct leaderboard behavior) to break.
        DB::statement(
            "ALTER TABLE qr_code_games MODIFY win_mode ENUM('always','skill','random','tiered','score','time','leaderboard') NOT NULL DEFAULT 'always'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Restore original enum values.
        DB::statement(
            "ALTER TABLE qr_code_games MODIFY win_mode ENUM('always','skill','random','tiered') NOT NULL DEFAULT 'always'"
        );
    }
};


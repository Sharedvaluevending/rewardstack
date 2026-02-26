<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_rewards', function (Blueprint $table) {
            $table->foreignId('leaderboard_entry_id')->nullable()->after('game_play_id')->constrained('leaderboard_entries')->onDelete('set null');
            $table->index('leaderboard_entry_id');
        });

        // Add composite index on leaderboard_entries for efficient per-period queries
        if (Schema::hasTable('leaderboard_entries')) {
            Schema::table('leaderboard_entries', function (Blueprint $table) {
                try {
                    if (
                        Schema::hasColumn('leaderboard_entries', 'leaderboard_id') &&
                        Schema::hasColumn('leaderboard_entries', 'period_key')
                    ) {
                        $table->index(['leaderboard_id', 'period_key'], 'lb_entries_lb_period_index');
                    }
                } catch (\Throwable $e) {
                    // Ignore if exists
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('leaderboard_entries')) {
            Schema::table('leaderboard_entries', function (Blueprint $table) {
                try {
                    $table->dropIndex('lb_entries_lb_period_index');
                } catch (\Throwable $e) {
                    // Ignore if doesn't exist
                }
            });
        }

        Schema::table('game_rewards', function (Blueprint $table) {
            $table->dropIndex(['leaderboard_entry_id']);
            $table->dropForeign(['leaderboard_entry_id']);
            $table->dropColumn('leaderboard_entry_id');
        });
    }
};

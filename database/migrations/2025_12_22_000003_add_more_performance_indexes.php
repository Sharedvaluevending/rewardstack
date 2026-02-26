<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additional performance indexes for common queries.
     *
     * Safe migration:
     * - Only runs if tables/columns exist
     * - Index creation is wrapped in try/catch (skip if already exists)
     */
    public function up(): void
    {
        // Orders: admin listings filter by type/status and sort by created_at
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                // (type, created_at) speeds up global merch list ordering
                try {
                    if (Schema::hasColumn('orders', 'type') && Schema::hasColumn('orders', 'created_at')) {
                        $table->index(['type', 'created_at'], 'orders_type_created_at_index');
                    }
                } catch (\Throwable $e) {
                    // Ignore if exists or unsupported
                }

                // (type, status, created_at) speeds up filtered admin views
                try {
                    if (Schema::hasColumn('orders', 'type') && Schema::hasColumn('orders', 'status') && Schema::hasColumn('orders', 'created_at')) {
                        $table->index(['type', 'status', 'created_at'], 'orders_type_status_created_at_index');
                    }
                } catch (\Throwable $e) {
                    // Ignore if exists or unsupported
                }
            });
        }

        // Leaderboard entries: period_key filters are common
        if (Schema::hasTable('leaderboard_entries')) {
            Schema::table('leaderboard_entries', function (Blueprint $table) {
                // (leaderboard_id, period_key, rank) speeds rank-window queries
                try {
                    if (
                        Schema::hasColumn('leaderboard_entries', 'leaderboard_id') &&
                        Schema::hasColumn('leaderboard_entries', 'period_key') &&
                        Schema::hasColumn('leaderboard_entries', 'rank')
                    ) {
                        $table->index(['leaderboard_id', 'period_key', 'rank'], 'lb_entries_lb_period_rank_index');
                    }
                } catch (\Throwable $e) {
                    // Ignore if exists or unsupported
                }

                // (leaderboard_id, period_key, score) speeds top-entry queries
                try {
                    if (
                        Schema::hasColumn('leaderboard_entries', 'leaderboard_id') &&
                        Schema::hasColumn('leaderboard_entries', 'period_key') &&
                        Schema::hasColumn('leaderboard_entries', 'score')
                    ) {
                        $table->index(['leaderboard_id', 'period_key', 'score'], 'lb_entries_lb_period_score_index');
                    }
                } catch (\Throwable $e) {
                    // Ignore if exists or unsupported
                }

                // (user_id, period_key) speeds "my rankings" queries if period_key used later
                try {
                    if (Schema::hasColumn('leaderboard_entries', 'user_id') && Schema::hasColumn('leaderboard_entries', 'period_key')) {
                        $table->index(['user_id', 'period_key'], 'lb_entries_user_period_index');
                    }
                } catch (\Throwable $e) {
                    // Ignore if exists or unsupported
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                try { $table->dropIndex('orders_type_created_at_index'); } catch (\Throwable $e) {}
                try { $table->dropIndex('orders_type_status_created_at_index'); } catch (\Throwable $e) {}
            });
        }

        if (Schema::hasTable('leaderboard_entries')) {
            Schema::table('leaderboard_entries', function (Blueprint $table) {
                try { $table->dropIndex('lb_entries_lb_period_rank_index'); } catch (\Throwable $e) {}
                try { $table->dropIndex('lb_entries_lb_period_score_index'); } catch (\Throwable $e) {}
                try { $table->dropIndex('lb_entries_user_period_index'); } catch (\Throwable $e) {}
            });
        }
    }
};

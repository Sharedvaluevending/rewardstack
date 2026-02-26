<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Performance indexes for common queries
     * Safe migration - only adds indexes that don't exist yet
     */
    public function up(): void
    {
        // Define indexes to add: table => [column => index_name]
        $indexes = [
            'scans' => [
                'business_id' => 'scans_business_id_index',
                'qr_code_id' => 'scans_qr_code_id_index',
                'created_at' => 'scans_created_at_index',
            ],
            'qr_codes' => [
                'code' => 'qr_codes_code_index',
                'business_id' => 'qr_codes_business_id_index',
                'is_active' => 'qr_codes_is_active_index',
            ],
            'promotions' => [
                'business_id' => 'promotions_business_id_index',
                'is_active' => 'promotions_is_active_index',
            ],
            'redemptions' => [
                'business_id' => 'redemptions_business_id_index',
                'promotion_id' => 'redemptions_promotion_id_index',
            ],
            'game_sessions' => [
                'user_id' => 'game_sessions_user_id_index',
                'game_id' => 'game_sessions_game_id_index',
            ],
            'game_plays' => [
                'user_id' => 'game_plays_user_id_index',
                'score' => 'game_plays_score_index',
            ],
            'users' => [
                'role' => 'users_role_index',
                'business_id' => 'users_business_id_index',
            ],
            'businesses' => [
                'slug' => 'businesses_slug_index',
            ],
            'orders' => [
                'business_id' => 'orders_business_id_index',
                'status' => 'orders_status_index',
            ],
        ];

        foreach ($indexes as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column => $indexName) {
                if (Schema::hasColumn($table, $column) && !$this->indexExists($table, $indexName)) {
                    try {
                        Schema::table($table, function (Blueprint $t) use ($column, $indexName) {
                            $t->index($column, $indexName);
                        });
                    } catch (\Exception $e) {
                        // Index might already exist under different name, skip
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Safe rollback - only drop indexes that exist
        $indexes = [
            'scans' => ['scans_business_id_index', 'scans_qr_code_id_index', 'scans_created_at_index'],
            'qr_codes' => ['qr_codes_code_index', 'qr_codes_business_id_index', 'qr_codes_is_active_index'],
            'promotions' => ['promotions_business_id_index', 'promotions_is_active_index'],
            'redemptions' => ['redemptions_business_id_index', 'redemptions_promotion_id_index'],
            'game_sessions' => ['game_sessions_user_id_index', 'game_sessions_game_id_index'],
            'game_plays' => ['game_plays_user_id_index', 'game_plays_score_index'],
            'users' => ['users_role_index', 'users_business_id_index'],
            'businesses' => ['businesses_slug_index'],
            'orders' => ['orders_business_id_index', 'orders_status_index'],
        ];

        foreach ($indexes as $table => $indexNames) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexNames as $indexName) {
                if ($this->indexExists($table, $indexName)) {
                    try {
                        Schema::table($table, function (Blueprint $t) use ($indexName) {
                            $t->dropIndex($indexName);
                        });
                    } catch (\Exception $e) {
                        // Skip if can't drop
                    }
                }
            }
        }
    }

    /**
     * Check if an index exists on a table
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        try {
            $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};

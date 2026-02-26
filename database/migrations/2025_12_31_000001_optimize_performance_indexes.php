<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add composite indexes for common query patterns to improve performance
     */
    public function up(): void
    {
        // Composite index for scans: business_id + scanned_at (for date range queries)
        if (Schema::hasTable('scans')) {
            try {
                Schema::table('scans', function (Blueprint $table) {
                    if (!$this->indexExists('scans', 'scans_business_scanned_at_index')) {
                        $table->index(['business_id', 'scanned_at'], 'scans_business_scanned_at_index');
                    }
                });
            } catch (\Exception $e) {
                // Index might already exist
            }
        }

        // Composite index for redemptions: business_id + redeemed_at (for date range queries)
        if (Schema::hasTable('redemptions')) {
            try {
                Schema::table('redemptions', function (Blueprint $table) {
                    if (!$this->indexExists('redemptions', 'redemptions_business_redeemed_at_index')) {
                        $table->index(['business_id', 'redeemed_at'], 'redemptions_business_redeemed_at_index');
                    }
                });
            } catch (\Exception $e) {
                // Index might already exist
            }
        }

        // Composite index for scans: qr_code_id + scanned_at (for QR code analytics)
        if (Schema::hasTable('scans')) {
            try {
                Schema::table('scans', function (Blueprint $table) {
                    if (!$this->indexExists('scans', 'scans_qr_scanned_at_index')) {
                        $table->index(['qr_code_id', 'scanned_at'], 'scans_qr_scanned_at_index');
                    }
                });
            } catch (\Exception $e) {
                // Index might already exist
            }
        }

        // Index for scans: session_id (for unique scan counting)
        if (Schema::hasTable('scans')) {
            try {
                Schema::table('scans', function (Blueprint $table) {
                    if (!$this->indexExists('scans', 'scans_session_id_index')) {
                        $table->index('session_id', 'scans_session_id_index');
                    }
                });
            } catch (\Exception $e) {
                // Index might already exist
            }
        }

        // Composite index for game_plays: business_id + game_id + created_at
        if (Schema::hasTable('game_plays')) {
            try {
                Schema::table('game_plays', function (Blueprint $table) {
                    if (!$this->indexExists('game_plays', 'game_plays_business_game_created_index')) {
                        $table->index(['business_id', 'game_id', 'created_at'], 'game_plays_business_game_created_index');
                    }
                });
            } catch (\Exception $e) {
                // Index might already exist
            }
        }

        // Composite index for game_plays: user_id + game_id + created_at (for user play limits)
        if (Schema::hasTable('game_plays')) {
            try {
                Schema::table('game_plays', function (Blueprint $table) {
                    if (!$this->indexExists('game_plays', 'game_plays_user_game_created_index')) {
                        $table->index(['user_id', 'game_id', 'created_at'], 'game_plays_user_game_created_index');
                    }
                });
            } catch (\Exception $e) {
                // Index might already exist
            }
        }
    }

    public function down(): void
    {
        // Drop indexes if they exist
        $indexes = [
            'scans' => ['scans_business_scanned_at_index', 'scans_qr_scanned_at_index', 'scans_session_id_index'],
            'redemptions' => ['redemptions_business_redeemed_at_index'],
            'game_plays' => ['game_plays_business_game_created_index', 'game_plays_user_game_created_index'],
        ];

        foreach ($indexes as $table => $indexNames) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexNames as $indexName) {
                try {
                    Schema::table($table, function (Blueprint $table) use ($indexName) {
                        $table->dropIndex($indexName);
                    });
                } catch (\Exception $e) {
                    // Index might not exist
                }
            }
        }
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        
        $result = DB::select(
            "SELECT COUNT(*) as count FROM information_schema.statistics 
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$database, $table, $indexName]
        );
        
        return $result[0]->count > 0;
    }
};


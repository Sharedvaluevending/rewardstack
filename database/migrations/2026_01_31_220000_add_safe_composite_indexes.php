<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add composite indexes for high-traffic queries (safe, idempotent).
     */
    public function up(): void
    {
        $indexes = [
            'scans' => [
                [
                    'name' => 'scans_user_qr_scanned_at_index',
                    'columns' => ['user_id', 'qr_code_id', 'scanned_at'],
                ],
                [
                    'name' => 'scans_user_scanned_at_index',
                    'columns' => ['user_id', 'scanned_at'],
                ],
                [
                    'name' => 'scans_type_scanned_at_index',
                    'columns' => ['scan_type', 'scanned_at'],
                ],
            ],
            'redemptions' => [
                [
                    'name' => 'redemptions_customer_qr_redeemed_at_index',
                    'columns' => ['customer_user_id', 'qr_code_id', 'redeemed_at'],
                ],
                [
                    'name' => 'redemptions_identifier_qr_index',
                    'columns' => ['customer_identifier', 'qr_code_id'],
                ],
                [
                    'name' => 'redemptions_promotion_card_completed_index',
                    'columns' => ['promotion_id', 'card_completed'],
                ],
            ],
            'user_promo_tokens' => [
                [
                    'name' => 'user_promo_tokens_user_promo_index',
                    'columns' => ['user_id', 'promotion_id'],
                ],
            ],
            'game_plays' => [
                [
                    'name' => 'game_plays_user_qr_created_index',
                    'columns' => ['user_id', 'qr_code_id', 'created_at'],
                ],
            ],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($tableIndexes as $index) {
                $columns = $index['columns'];
                $indexName = $index['name'];

                $allColumnsExist = collect($columns)->every(fn ($col) => Schema::hasColumn($table, $col));
                if (!$allColumnsExist || $this->indexExists($table, $indexName)) {
                    continue;
                }

                try {
                    Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
                        $t->index($columns, $indexName);
                    });
                } catch (\Exception $e) {
                    // Ignore if the index already exists under another name.
                }
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            'scans' => [
                'scans_user_qr_scanned_at_index',
                'scans_user_scanned_at_index',
                'scans_type_scanned_at_index',
            ],
            'redemptions' => [
                'redemptions_customer_qr_redeemed_at_index',
                'redemptions_identifier_qr_index',
                'redemptions_promotion_card_completed_index',
            ],
            'user_promo_tokens' => [
                'user_promo_tokens_user_promo_index',
            ],
            'game_plays' => [
                'game_plays_user_qr_created_index',
            ],
        ];

        foreach ($indexes as $table => $indexNames) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexNames as $indexName) {
                if (!$this->indexExists($table, $indexName)) {
                    continue;
                }
                try {
                    Schema::table($table, function (Blueprint $t) use ($indexName) {
                        $t->dropIndex($indexName);
                    });
                } catch (\Exception $e) {
                    // Ignore if drop fails.
                }
            }
        }
    }

    /**
     * Check if an index exists on a table (MySQL-compatible).
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};

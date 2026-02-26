<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('redemptions', 'user_promo_token_id')) {
            return;
        }

        Schema::table('redemptions', function (Blueprint $table) {
            // Prevent duplicate redemption ledger rows for a single-use token.
            // Multiple NULLs are allowed, so punch-card stamp redemptions can keep user_promo_token_id = NULL.
            try {
                $table->unique(['user_promo_token_id'], 'redemptions_user_promo_token_unique');
            } catch (\Throwable $e) {
                // Index may already exist in some environments; ignore.
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('redemptions', 'user_promo_token_id')) {
            return;
        }

        Schema::table('redemptions', function (Blueprint $table) {
            try {
                $table->dropUnique('redemptions_user_promo_token_unique');
            } catch (\Throwable $e) {
                // Ignore if missing.
            }
        });
    }
};




<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_referral_awards', function (Blueprint $table) {
            if (!Schema::hasColumn('merch_referral_awards', 'user_promo_token_id')) {
                $table->foreignId('user_promo_token_id')
                    ->nullable()
                    ->constrained('user_promo_tokens')
                    ->nullOnDelete()
                    ->after('reward_id');
                $table->index('user_promo_token_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merch_referral_awards', function (Blueprint $table) {
            if (Schema::hasColumn('merch_referral_awards', 'user_promo_token_id')) {
                $table->dropConstrainedForeignId('user_promo_token_id');
            }
        });
    }
};

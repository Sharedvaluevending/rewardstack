<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_referral_rewards', function (Blueprint $table) {
            if (!Schema::hasColumn('merch_referral_rewards', 'reward_item_value')) {
                $table->decimal('reward_item_value', 10, 2)->nullable()->after('reward_value');
            }
            if (!Schema::hasColumn('merch_referral_rewards', 'reward_promotion_id')) {
                $table->foreignId('reward_promotion_id')
                    ->nullable()
                    ->constrained('promotions')
                    ->nullOnDelete()
                    ->after('qr_code_id');
            }
            if (!Schema::hasColumn('merch_referral_rewards', 'reward_qr_code_id')) {
                $table->foreignId('reward_qr_code_id')
                    ->nullable()
                    ->constrained('qr_codes')
                    ->nullOnDelete()
                    ->after('reward_promotion_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merch_referral_rewards', function (Blueprint $table) {
            if (Schema::hasColumn('merch_referral_rewards', 'reward_qr_code_id')) {
                $table->dropConstrainedForeignId('reward_qr_code_id');
            }
            if (Schema::hasColumn('merch_referral_rewards', 'reward_promotion_id')) {
                $table->dropConstrainedForeignId('reward_promotion_id');
            }
            if (Schema::hasColumn('merch_referral_rewards', 'reward_item_value')) {
                $table->dropColumn('reward_item_value');
            }
        });
    }
};

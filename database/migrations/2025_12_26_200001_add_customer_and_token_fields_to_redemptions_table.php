<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redemptions', function (Blueprint $table) {
            // Who performed the redemption (employee/business owner user id)
            if (!Schema::hasColumn('redemptions', 'redeemed_by_user_id')) {
                $table->foreignId('redeemed_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete()
                    ->after('employee_id');
                $table->index('redeemed_by_user_id');
            }

            // Which customer account this redemption belongs to (reliable portal linkage)
            if (!Schema::hasColumn('redemptions', 'customer_user_id')) {
                $table->foreignId('customer_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete()
                    ->after('scan_id');
                $table->index('customer_user_id');
            }

            // Optional link to the per-user promo token that was used
            if (!Schema::hasColumn('redemptions', 'user_promo_token_id')) {
                $table->foreignId('user_promo_token_id')
                    ->nullable()
                    ->constrained('user_promo_tokens')
                    ->nullOnDelete()
                    ->after('customer_user_id');
                $table->index('user_promo_token_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('redemptions', function (Blueprint $table) {
            if (Schema::hasColumn('redemptions', 'user_promo_token_id')) {
                $table->dropConstrainedForeignId('user_promo_token_id');
            }
            if (Schema::hasColumn('redemptions', 'customer_user_id')) {
                $table->dropConstrainedForeignId('customer_user_id');
            }
            if (Schema::hasColumn('redemptions', 'redeemed_by_user_id')) {
                $table->dropConstrainedForeignId('redeemed_by_user_id');
            }
        });
    }
};



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
        Schema::table('referral_commissions', function (Blueprint $table) {
            // Prevent duplicate commissions for same referral/period
            // This is a belt-and-suspenders approach alongside webhook idempotency
            $table->unique(['referral_id', 'subscription_period'], 'unique_commission_per_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->dropUnique('unique_commission_per_period');
        });
    }
};


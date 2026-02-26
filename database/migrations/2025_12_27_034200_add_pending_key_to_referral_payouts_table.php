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
        Schema::table('referral_payouts', function (Blueprint $table) {
            // Add pending_key column for MySQL-compatible unique constraint
            // Pattern: "user:{user_id}:pending" when status is pending, NULL otherwise
            $table->string('pending_key')->nullable()->unique()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referral_payouts', function (Blueprint $table) {
            $table->dropUnique(['pending_key']);
            $table->dropColumn('pending_key');
        });
    }
};


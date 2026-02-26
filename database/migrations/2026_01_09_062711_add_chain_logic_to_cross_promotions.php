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
        Schema::table('cross_promotions', function (Blueprint $table) {
            // Chain Mode: 'open' (any order), 'sequential' (ordered)
            $table->string('chain_mode')->default('open')->after('display_mode');
            
            // If sequential, which promotion must be redeemed first?
            // If null in sequential mode, business_1 is assumed first.
            $table->unsignedBigInteger('primary_promotion_id')->nullable()->after('chain_mode');
            
            // Add foreign key constraint if you want, but lightweight is fine for now
            // $table->foreign('primary_promotion_id')->references('id')->on('promotions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cross_promotions', function (Blueprint $table) {
            $table->dropColumn(['chain_mode', 'primary_promotion_id']);
        });
    }
};

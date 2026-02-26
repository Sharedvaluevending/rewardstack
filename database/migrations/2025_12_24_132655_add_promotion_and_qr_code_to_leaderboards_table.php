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
        Schema::table('leaderboards', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('prize_config')->constrained()->onDelete('set null');
            $table->foreignId('qr_code_id')->nullable()->after('promotion_id')->constrained()->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leaderboards', function (Blueprint $table) {
            $table->dropForeign(['promotion_id']);
            $table->dropForeign(['qr_code_id']);
            $table->dropColumn(['promotion_id', 'qr_code_id']);
        });
    }
};

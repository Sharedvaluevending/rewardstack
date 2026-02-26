<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_code_games', function (Blueprint $table) {
            $table->foreignId('leaderboard_id')
                ->nullable()
                ->after('business_id')
                ->constrained('leaderboards')
                ->nullOnDelete();

            $table->index(['leaderboard_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('qr_code_games', function (Blueprint $table) {
            $table->dropForeign(['leaderboard_id']);
            $table->dropIndex(['leaderboard_id', 'is_active']);
            $table->dropColumn('leaderboard_id');
        });
    }
};

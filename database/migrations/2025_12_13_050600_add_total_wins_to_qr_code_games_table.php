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
        Schema::table('qr_code_games', function (Blueprint $table) {
            $table->integer('total_wins')->default(0)->after('active_end_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qr_code_games', function (Blueprint $table) {
            $table->dropColumn('total_wins');
        });
    }
};

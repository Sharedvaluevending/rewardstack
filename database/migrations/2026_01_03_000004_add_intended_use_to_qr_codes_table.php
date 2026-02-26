<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            // Purpose flag to prevent accidental scanning of internal/config QR codes (ex: leaderboard prizes)
            $table->string('intended_use')->default('public')->after('type');
            $table->index('intended_use');
        });
    }

    public function down(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropIndex(['intended_use']);
            $table->dropColumn('intended_use');
        });
    }
};



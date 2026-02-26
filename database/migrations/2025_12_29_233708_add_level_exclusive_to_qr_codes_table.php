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
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->integer('required_level')->nullable()->after('type');
            $table->boolean('is_level_exclusive')->default(false)->after('required_level');
            $table->index('required_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropIndex(['required_level']);
            $table->dropColumn(['required_level', 'is_level_exclusive']);
        });
    }
};

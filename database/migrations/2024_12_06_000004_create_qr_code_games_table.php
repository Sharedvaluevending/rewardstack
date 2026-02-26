<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_code_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_code_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            
            // Reward configuration
            $table->foreignId('promotion_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('win_mode', ['always', 'score', 'time', 'random', 'leaderboard', 'tiered', 'skill'])->default('always');
            $table->integer('win_threshold_score')->nullable(); // For skill-based
            $table->integer('win_probability')->nullable(); // For random (0-100)
            $table->json('tier_rewards')->nullable(); // {gold: promo_id, silver: promo_id, bronze: promo_id}
            $table->json('score_tiers')->nullable(); // {gold: 1000, silver: 500, bronze: 100}
            
            // Scheduling
            $table->json('active_days')->nullable(); // ["monday", "tuesday", ...]
            $table->time('active_start_time')->nullable();
            $table->time('active_end_time')->nullable();
            
            $table->timestamps();
            
            $table->unique(['qr_code_id', 'game_id']);
            $table->index(['business_id', 'is_active']);
        });

        // Extend qr_codes table for game support
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->boolean('game_enabled')->default(false)->after('is_active');
            $table->enum('location_lock_type', ['none', 'gps', 'wifi', 'nfc'])->default('none')->after('game_enabled');
            $table->integer('location_radius')->nullable()->after('location_lock_type'); // meters for GPS
            $table->string('wifi_ssid')->nullable()->after('location_radius');
            $table->string('nfc_tag_id')->nullable()->after('wifi_ssid');
        });
    }

    public function down(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropColumn(['game_enabled', 'location_lock_type', 'location_radius', 'wifi_ssid', 'nfc_tag_id']);
        });
        Schema::dropIfExists('qr_code_games');
    }
};


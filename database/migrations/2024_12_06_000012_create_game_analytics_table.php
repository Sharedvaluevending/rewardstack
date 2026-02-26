<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daily aggregated game analytics per business
        Schema::create('game_analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->nullable()->constrained()->onDelete('cascade');
            $table->date('date');
            
            // Play metrics
            $table->integer('total_sessions')->default(0);
            $table->integer('total_plays')->default(0);
            $table->integer('unique_players')->default(0);
            $table->integer('new_players')->default(0);
            $table->integer('returning_players')->default(0);
            
            // Engagement
            $table->integer('total_play_time_seconds')->default(0);
            $table->decimal('avg_session_duration', 10, 2)->default(0);
            $table->decimal('avg_plays_per_session', 10, 2)->default(0);
            $table->decimal('completion_rate', 5, 2)->default(0);
            
            // Scores
            $table->integer('total_score')->default(0);
            $table->decimal('avg_score', 10, 2)->default(0);
            $table->integer('high_score')->default(0);
            
            // Rewards
            $table->integer('rewards_given')->default(0);
            $table->integer('rewards_redeemed')->default(0);
            $table->decimal('reward_value', 10, 2)->default(0);
            
            // Location verification
            $table->integer('location_verified')->default(0);
            $table->integer('location_failed')->default(0);
            
            $table->timestamps();
            
            $table->unique(['business_id', 'game_id', 'date'], 'game_analytics_daily_unique');
            $table->index(['business_id', 'date']);
            $table->index(['game_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_analytics_daily');
    }
};


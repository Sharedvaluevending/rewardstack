<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            
            // Scope
            $table->enum('type', [
                'location',      // Single business
                'multi_location', // Franchise/chain
                'game_specific', // Per game
                'global',        // Platform-wide
                'seasonal',      // Time-limited event
                'age_based',     // Kids vs adults
                'family_team'    // Family groups
            ])->default('location');
            
            $table->foreignId('business_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->nullable()->constrained()->onDelete('cascade');
            $table->json('business_ids')->nullable(); // For multi-location
            
            // Reset schedule
            $table->enum('reset_frequency', ['never', 'daily', 'weekly', 'monthly', 'seasonal'])->default('weekly');
            $table->string('reset_day')->nullable(); // monday, 1st, etc.
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('last_reset_at')->nullable();
            
            // Configuration
            $table->integer('max_entries')->default(100);
            $table->enum('score_type', ['highest', 'cumulative', 'average'])->default('highest');
            $table->boolean('show_score')->default(true);
            $table->boolean('show_games_played')->default(true);
            $table->json('prize_config')->nullable(); // Weekly prizes
            
            // Customization (monetization)
            $table->string('skin')->nullable();
            $table->json('sponsor_info')->nullable();
            $table->boolean('is_premium')->default(false);
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['type', 'is_active']);
            $table->index(['business_id', 'is_active']);
            $table->index(['game_id', 'is_active']);
        });

        Schema::create('leaderboard_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leaderboard_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('business_id')->nullable()->constrained()->onDelete('cascade');
            
            // Scores
            $table->integer('score')->default(0);
            $table->integer('games_played')->default(0);
            $table->integer('wins')->default(0);
            $table->integer('best_score')->default(0);
            $table->decimal('win_rate', 5, 2)->default(0);
            $table->integer('current_streak')->default(0);
            $table->integer('best_streak')->default(0);
            
            // Ranking
            $table->integer('rank')->nullable();
            $table->integer('previous_rank')->nullable();
            $table->integer('rank_change')->default(0); // +/- movement
            
            // Period tracking
            $table->string('period_key')->nullable(); // "2024-W50" for weekly
            
            $table->timestamps();
            
            $table->unique(['leaderboard_id', 'user_id', 'period_key'], 'leaderboard_user_period');
            $table->index(['leaderboard_id', 'score']);
            $table->index(['leaderboard_id', 'rank']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_entries');
        Schema::dropIfExists('leaderboards');
    }
};


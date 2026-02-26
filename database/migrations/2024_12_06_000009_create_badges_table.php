<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('image')->nullable();
            $table->string('color')->nullable();
            
            // Category and rarity
            $table->enum('category', [
                'achievement',  // Score/skill based
                'milestone',    // Cumulative (100 games played)
                'streak',       // Consecutive days/weeks
                'explorer',     // Visit different locations
                'champion',     // Leaderboard top positions
                'seasonal',     // Time-limited
                'special'       // One-off events
            ])->default('achievement');
            
            $table->enum('rarity', ['common', 'uncommon', 'rare', 'epic', 'legendary'])->default('common');
            
            // Requirements
            $table->json('requirements')->nullable(); // {type: 'games_played', value: 100}
            $table->foreignId('game_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('business_id')->nullable()->constrained()->onDelete('cascade');
            
            // Points/XP value
            $table->integer('points')->default(0);
            
            // Availability
            $table->boolean('is_hidden')->default(false); // Secret badges
            $table->boolean('is_seasonal')->default(false);
            $table->date('available_from')->nullable();
            $table->date('available_until')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['category', 'is_active']);
            $table->index(['rarity']);
            $table->index(['game_id']);
            $table->index(['business_id']);
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('badge_id')->constrained()->onDelete('cascade');
            $table->foreignId('business_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('game_play_id')->nullable()->constrained()->onDelete('set null');
            
            $table->integer('progress')->default(0); // For progressive badges
            $table->integer('progress_max')->nullable();
            $table->boolean('is_complete')->default(true);
            $table->timestamp('earned_at');
            $table->boolean('is_featured')->default(false); // User's showcased badges
            $table->boolean('is_new')->default(true); // For notification dot
            
            $table->timestamps();
            
            $table->unique(['user_id', 'badge_id', 'business_id'], 'user_badge_business');
            $table->index(['user_id', 'is_complete']);
            $table->index(['earned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
    }
};


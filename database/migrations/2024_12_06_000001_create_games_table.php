<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type'); // memory_match, word_search, snake, tap_counter, brick_breaker, etc.
            $table->enum('tier', ['basic', 'pro', 'premium', 'seasonal'])->default('basic');
            $table->string('category')->nullable(); // family, fitness, food, seasonal
            $table->string('thumbnail')->nullable();
            $table->string('icon')->nullable();
            $table->json('config')->nullable(); // Game-specific configuration
            $table->json('assets')->nullable(); // URLs to game assets
            $table->json('difficulty_levels')->nullable(); // easy, medium, hard configs
            $table->integer('min_score')->default(0);
            $table->integer('max_score')->nullable();
            $table->integer('time_limit')->nullable(); // seconds
            $table->boolean('is_branded')->default(false); // Custom branded game
            $table->boolean('is_seasonal')->default(false);
            $table->date('season_start')->nullable();
            $table->date('season_end')->nullable();
            $table->decimal('setup_fee', 10, 2)->nullable(); // For custom branded games
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tier', 'is_active']);
            $table->index(['type', 'is_active']);
            $table->index(['is_seasonal', 'season_start', 'season_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};


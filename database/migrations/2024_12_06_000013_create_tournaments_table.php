<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('business_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            
            // Type
            $table->enum('type', [
                'high_score',      // Highest single score wins
                'cumulative',      // Total score over period
                'challenge',       // Beat the manager/target
                'bracket',         // Head-to-head elimination
                'time_attack'      // Best time/speed
            ])->default('high_score');
            
            // Timing
            $table->timestamp('registration_opens')->nullable();
            $table->timestamp('registration_closes')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            
            // Configuration
            $table->integer('max_participants')->nullable();
            $table->integer('max_attempts')->nullable(); // Attempts per participant
            $table->integer('target_score')->nullable(); // For challenge mode
            $table->foreignId('challenge_user_id')->nullable(); // "Beat the manager"
            $table->json('rules')->nullable();
            
            // Prizes
            $table->json('prizes')->nullable(); // {1: {promo_id: 1, description: "..."}, 2: {...}}
            $table->boolean('has_entry_fee')->default(false);
            $table->decimal('entry_fee', 10, 2)->nullable();
            
            // Sponsor
            $table->string('sponsor_name')->nullable();
            $table->string('sponsor_logo')->nullable();
            
            // Status
            $table->enum('status', ['draft', 'upcoming', 'registration', 'active', 'completed', 'cancelled'])->default('draft');
            $table->boolean('is_featured')->default(false);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['business_id', 'status']);
            $table->index(['game_id', 'status']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('tournament_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Stats
            $table->integer('best_score')->default(0);
            $table->integer('total_score')->default(0);
            $table->integer('attempts_used')->default(0);
            $table->integer('rank')->nullable();
            $table->boolean('is_qualified')->default(false);
            
            // Payment (if entry fee)
            $table->boolean('entry_paid')->default(false);
            $table->string('payment_id')->nullable();
            
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();
            
            $table->unique(['tournament_id', 'user_id']);
            $table->index(['tournament_id', 'rank']);
            $table->index(['tournament_id', 'best_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_participants');
        Schema::dropIfExists('tournaments');
    }
};


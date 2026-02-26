<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('qr_code_id')->constrained()->onDelete('cascade');
            
            // Game performance
            $table->integer('score')->default(0);
            $table->integer('duration_seconds')->nullable();
            $table->string('difficulty')->nullable(); // easy, medium, hard
            $table->integer('level_reached')->nullable();
            $table->json('game_data')->nullable(); // Game-specific data (moves, combos, etc.)
            
            // Result
            $table->enum('result', ['win', 'lose', 'complete'])->nullable();
            $table->enum('reward_tier', ['gold', 'silver', 'bronze', 'none'])->nullable();
            $table->boolean('is_high_score')->default(false);
            $table->boolean('is_personal_best')->default(false);
            
            // Fraud prevention
            $table->boolean('is_suspicious')->default(false);
            $table->string('suspicious_reason')->nullable();
            
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'game_id', 'created_at']);
            $table->index(['business_id', 'created_at']);
            $table->index(['game_id', 'score']);
            $table->index(['is_high_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_plays');
    }
};


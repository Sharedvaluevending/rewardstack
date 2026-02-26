<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->boolean('is_enabled')->default(true);
            $table->json('schedule')->nullable(); // Daypart rules: {"monday": {"start": "09:00", "end": "17:00"}}
            $table->json('reward_config')->nullable(); // Prize probability, tiers
            $table->integer('max_plays_per_day')->nullable();
            $table->integer('max_plays_per_week')->nullable();
            $table->integer('cooldown_minutes')->nullable(); // Minutes between plays
            $table->json('custom_branding')->nullable(); // Business logo overlay, colors
            $table->boolean('leaderboard_enabled')->default(true);
            $table->boolean('staff_can_play')->default(false);
            $table->timestamps();
            
            $table->unique(['business_id', 'game_id']);
            $table->index(['business_id', 'is_enabled']);
        });

        // Business game pack subscriptions
        Schema::create('business_game_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_pack_id')->constrained()->onDelete('cascade');
            $table->string('stripe_subscription_id')->nullable();
            $table->enum('status', ['active', 'cancelled', 'expired', 'trial'])->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_game_packs');
        Schema::dropIfExists('business_games');
    }
};


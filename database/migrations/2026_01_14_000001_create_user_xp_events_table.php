<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // XP event log:
        // - Enables daily caps for non-monetary XP (scan+game)
        // - Enables diminishing returns for repeated redemptions on the same promo/day
        // - Provides auditable history for support/debugging
        Schema::create('user_xp_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Source categories (kept flexible as string for forward compatibility)
            // examples: scan, game, redemption, reward_redemption, badge
            $table->string('source', 40);

            $table->integer('amount'); // awarded XP amount (post caps/diminishing)

            // Common context fields (denormalized for easy querying across DB engines)
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('qr_code_id')->nullable()->constrained('qr_codes')->nullOnDelete();
            $table->foreignId('game_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('badge_id')->nullable()->constrained('badges')->nullOnDelete();

            // Optional context for analytics/debugging (promotion_id, qr_code_id, game_id, badge_id, etc.)
            $table->json('context')->nullable();

            // Denormalized date for fast daily cap lookups
            $table->date('occurred_on');

            $table->timestamps();

            $table->index(['user_id', 'occurred_on']);
            $table->index(['user_id', 'occurred_on', 'source']);
            $table->index(['user_id', 'occurred_on', 'promotion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_xp_events');
    }
};


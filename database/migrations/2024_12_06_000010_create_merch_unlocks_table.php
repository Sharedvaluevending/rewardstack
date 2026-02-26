<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Define what merch items unlock what games/features
        Schema::create('merch_unlock_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('unlock_type'); // game, game_pack, double_rewards, exclusive_badge
            $table->foreignId('game_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('game_pack_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('badge_id')->nullable()->constrained()->onDelete('cascade');
            
            // Duration
            $table->enum('duration_type', ['permanent', 'days', 'until_date'])->default('permanent');
            $table->integer('duration_days')->nullable();
            $table->date('valid_until')->nullable();
            
            // Bonus effects
            $table->decimal('reward_multiplier', 3, 2)->nullable(); // 2.0 = double rewards
            $table->integer('extra_plays_per_day')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['product_id', 'is_active']);
        });

        // Track user's active unlocks from merch purchases
        Schema::create('user_merch_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('merch_unlock_rule_id')->constrained()->onDelete('cascade');
            
            // Status
            $table->enum('status', ['pending', 'active', 'expired'])->default('pending');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            // Tracking
            $table->string('printful_order_id')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('delivered_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_merch_unlocks');
        Schema::dropIfExists('merch_unlock_rules');
    }
};


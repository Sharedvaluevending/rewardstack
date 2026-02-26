<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_rewards', function (Blueprint $table) {
            $table->id();
            $table->uuid('reward_code')->unique();
            $table->foreignId('game_play_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('promotion_id')->nullable()->constrained()->onDelete('set null');
            
            // Reward details
            $table->string('reward_type'); // percentage, fixed, free_item, badge, mystery
            $table->enum('tier', ['gold', 'silver', 'bronze', 'participation'])->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->string('free_item')->nullable();
            $table->text('description')->nullable();
            
            // Status
            $table->enum('status', ['available', 'claimed', 'redeemed', 'expired'])->default('available');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('redeemed_by_employee_id')->nullable();
            
            // Restrictions
            $table->boolean('next_visit_only')->default(false);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            
            $table->timestamps();
            
            $table->index(['reward_code']);
            $table->index(['user_id', 'status']);
            $table->index(['business_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_rewards');
    }
};


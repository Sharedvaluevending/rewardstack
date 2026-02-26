<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('qr_code_id')->constrained('qr_codes')->onDelete('cascade');
            $table->string('reward_type', 32);
            $table->decimal('reward_value', 10, 2)->nullable();
            $table->string('reward_description', 255)->nullable();
            $table->unsignedInteger('redemptions_required');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('qr_code_id');
            $table->index(['business_id', 'is_active']);
        });

        Schema::create('merch_referral_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merch_tag_id')->constrained('merch_tags')->onDelete('cascade');
            $table->foreignId('owner_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reward_id')->constrained('merch_referral_rewards')->onDelete('cascade');
            $table->unsignedInteger('redemptions_count_snapshot');
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->index(['merch_tag_id', 'reward_id']);
            $table->index(['owner_user_id', 'awarded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_referral_awards');
        Schema::dropIfExists('merch_referral_rewards');
    }
};

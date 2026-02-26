<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_customers', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('user_id');

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_redeemed_at')->nullable();

            $table->unsignedInteger('scans_count')->default(0);
            $table->unsignedInteger('saved_count')->default(0);
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->unsignedInteger('game_plays_count')->default(0);
            $table->unsignedInteger('rewards_won_count')->default(0);
            $table->unsignedInteger('rewards_redeemed_count')->default(0);

            $table->decimal('lifetime_savings', 10, 2)->default(0);
            $table->unsignedInteger('level_at_last_seen')->nullable();
            $table->unsignedInteger('xp_at_last_seen')->nullable();

            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('country', 2)->nullable();

            $table->timestamps();

            $table->unique(['business_id', 'user_id'], 'bc_business_user_unique');
            $table->index(['business_id', 'last_seen_at'], 'bc_business_last_seen_at_index');

            $table
                ->foreign('business_id')
                ->references('id')
                ->on('businesses')
                ->onDelete('cascade');

            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_customers');
    }
};


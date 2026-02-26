<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_packs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['subscription', 'one_time'])->default('subscription');
            $table->string('category')->nullable(); // starter, pro, family, fitness, seasonal
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->decimal('price_yearly', 10, 2)->nullable();
            $table->decimal('price_one_time', 10, 2)->nullable();
            $table->string('stripe_price_id_monthly')->nullable();
            $table->string('stripe_price_id_yearly')->nullable();
            $table->string('stripe_product_id')->nullable();
            $table->string('thumbnail')->nullable();
            $table->json('features')->nullable(); // Feature list for display
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['type', 'is_active']);
        });

        // Pivot table for games in packs
        Schema::create('game_pack_game', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_pack_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['game_pack_id', 'game_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_pack_game');
        Schema::dropIfExists('game_packs');
    }
};


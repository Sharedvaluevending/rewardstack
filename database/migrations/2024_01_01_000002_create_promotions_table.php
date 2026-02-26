<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('terms')->nullable(); // Terms and conditions
            
            // Discount Type
            $table->string('discount_type'); 
            // Types: percentage, fixed_amount, bogo, buy_x_get_y, buy_x_for_y, 
            // punch_card, tiered, bundle, happy_hour, first_time, loyalty_milestone
            
            // Discount Values (flexible based on type)
            $table->decimal('discount_value', 10, 2)->nullable(); // For percentage or fixed amount
            $table->integer('buy_quantity')->nullable(); // For buy X get Y
            $table->integer('get_quantity')->nullable(); // For buy X get Y
            $table->decimal('for_price', 10, 2)->nullable(); // For buy X for $Y
            $table->integer('punches_required')->nullable(); // For punch cards
            $table->json('tiers')->nullable(); // For tiered discounts [{min_spend, discount}, ...]
            
            // Pricing
            $table->decimal('original_price', 10, 2)->nullable();
            $table->decimal('minimum_purchase', 10, 2)->nullable();
            $table->decimal('maximum_discount', 10, 2)->nullable();
            
            // Rules
            $table->json('rules')->nullable(); // Complex rules stored as JSON
            /*
             * Example rules structure:
             * {
             *   "max_redemptions_total": 100,
             *   "max_redemptions_per_user": 1,
             *   "max_per_day": 10,
             *   "max_per_week": 50,
             *   "max_per_month": 200,
             *   "valid_days": ["monday", "tuesday", "wednesday"],
             *   "valid_hours": {"start": "09:00", "end": "17:00"},
             *   "new_customers_only": false,
             *   "combinable": false,
             *   "requires_minimum_items": 2,
             *   "applies_to_categories": ["appetizers", "drinks"],
             *   "excludes_items": ["special_item_1"]
             * }
             */
            
            // Schedule
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            
            // Statistics
            $table->unsignedBigInteger('total_views')->default(0);
            $table->unsignedBigInteger('total_redemptions')->default(0);
            $table->decimal('total_savings', 12, 2)->default(0);
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['business_id', 'is_active']);
            $table->index(['starts_at', 'ends_at']);
            $table->index('discount_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};


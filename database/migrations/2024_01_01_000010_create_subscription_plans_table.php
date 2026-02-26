<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Starter, Growth, Pro, Enterprise
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            
            // Pricing
            $table->decimal('monthly_price', 10, 2);
            $table->decimal('yearly_price', 10, 2)->nullable();
            $table->string('stripe_monthly_price_id')->nullable();
            $table->string('stripe_yearly_price_id')->nullable();
            
            // Limits
            $table->json('features')->nullable();
            /*
             * Example features:
             * {
             *   "qr_codes": 5,
             *   "promotions": 10,
             *   "scans_per_month": 1000,
             *   "employees": 2,
             *   "analytics_days": 30,
             *   "custom_domain": false,
             *   "white_label": false,
             *   "api_access": false,
             *   "priority_support": false,
             *   "advanced_analytics": false,
             *   "ai_insights": false,
             *   "cross_promotions": false,
             *   "stackable_pools": false,
             *   "print_studio": true,
             *   "merch_store": false,
             *   "remove_branding": false
             * }
             */
            
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Daily analytics aggregation (for faster dashboard loading)
        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->date('date');
            
            // Scan metrics
            $table->unsignedInteger('total_scans')->default(0);
            $table->unsignedInteger('unique_scans')->default(0);
            
            // Redemption metrics
            $table->unsignedInteger('total_redemptions')->default(0);
            $table->decimal('total_discount_value', 12, 2)->default(0);
            $table->decimal('total_revenue_influenced', 12, 2)->default(0);
            
            // Top performers (stored as JSON for flexibility)
            $table->json('top_qr_codes')->nullable();
            $table->json('top_promotions')->nullable();
            $table->json('device_breakdown')->nullable();
            $table->json('location_breakdown')->nullable();
            $table->json('hourly_breakdown')->nullable();
            
            $table->timestamps();
            
            $table->unique(['business_id', 'date']);
            $table->index(['business_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily');
        Schema::dropIfExists('subscription_plans');
    }
};


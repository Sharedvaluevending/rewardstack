<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stackable pools - shared QR codes where multiple businesses can add offers
        Schema::create('stackable_pools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // Unique code for the shared QR
            $table->text('description')->nullable();
            
            // Geographic targeting
            $table->decimal('center_latitude', 10, 8)->nullable();
            $table->decimal('center_longitude', 11, 8)->nullable();
            $table->integer('radius_miles')->default(10); // Radius for geo-targeting
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            
            // Settings
            $table->integer('max_entries')->nullable(); // Max businesses that can join
            $table->decimal('entry_fee', 10, 2)->nullable(); // Optional fee to join pool
            $table->boolean('requires_approval')->default(false);
            
            // Admin
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['is_active', 'city']);
            $table->index('code');
        });

        // Entries in stackable pools (business promotions in the pool)
        Schema::create('stackable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stackable_pool_id')->constrained()->onDelete('cascade');
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('promotion_id')->constrained()->onDelete('cascade');
            
            // Position and priority
            $table->integer('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_approved')->default(false);
            $table->timestamp('approved_at')->nullable();
            
            $table->timestamps();
            
            $table->unique(['stackable_pool_id', 'business_id', 'promotion_id'], 'stackable_entries_unique');
            $table->index(['stackable_pool_id', 'is_active', 'is_approved']);
        });

        // Cross-promotions - two businesses sharing one QR
        Schema::create('cross_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            
            // Partner businesses
            $table->foreignId('business_1_id')->constrained('businesses')->onDelete('cascade');
            $table->foreignId('business_2_id')->constrained('businesses')->onDelete('cascade');
            $table->foreignId('promotion_1_id')->constrained('promotions')->onDelete('cascade');
            $table->foreignId('promotion_2_id')->constrained('promotions')->onDelete('cascade');
            
            // Display mode: alternating, split, random
            $table->string('display_mode')->default('split');
            
            // Revenue share
            $table->decimal('revenue_share_percent', 5, 2)->default(50.00);
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cross_promotions');
        Schema::dropIfExists('stackable_entries');
        Schema::dropIfExists('stackable_pools');
    }
};


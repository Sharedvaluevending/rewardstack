<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('code')->unique(); // Short unique code for URL
            $table->string('name');
            $table->string('type'); // static, dynamic, promotion, cross_promo, stackable
            
            // Destination
            $table->string('destination_url')->nullable();
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->unsignedBigInteger('stackable_pool_id')->nullable();
            
            // Foreign keys added after table creation
            $table->foreign('promotion_id')->references('id')->on('promotions')->onDelete('set null');
            
            // Design (stored as JSON for flexibility)
            $table->json('design')->nullable();
            
            // Placement tracking
            $table->string('placement_location')->nullable(); // front_desk, window, card, merch, etc.
            $table->string('placement_description')->nullable();
            
            // Statistics (denormalized for performance)
            $table->unsignedBigInteger('total_scans')->default(0);
            $table->unsignedBigInteger('unique_scans')->default(0);
            $table->timestamp('last_scanned_at')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['business_id', 'is_active']);
            $table->index('code');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};


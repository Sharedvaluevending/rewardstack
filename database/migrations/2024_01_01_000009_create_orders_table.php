<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Products catalog (synced from Printful or custom)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category'); // t-shirt, mug, sticker, poster, business_card, etc.
            $table->decimal('base_price', 10, 2);
            $table->string('printful_product_id')->nullable();
            $table->json('variants')->nullable(); // sizes, colors, etc.
            $table->json('print_areas')->nullable(); // Where QR codes can be placed
            $table->json('images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['category', 'is_active']);
        });

        // Orders
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('order_number')->unique();
            $table->string('type'); // print_studio, merch
            
            // Status
            $table->string('status')->default('pending'); // pending, processing, shipped, delivered, cancelled
            
            // Printful integration
            $table->string('printful_order_id')->nullable();
            $table->string('printful_status')->nullable();
            
            // Shipping
            $table->string('shipping_name')->nullable();
            $table->string('shipping_address_1')->nullable();
            $table->string('shipping_address_2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_zip')->nullable();
            $table->string('shipping_country')->default('US');
            $table->string('shipping_phone')->nullable();
            
            // Pricing
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            
            // Payment
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('payment_status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            
            // Tracking
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['business_id', 'status']);
            $table->index('order_number');
        });

        // Order items
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('qr_code_id')->nullable()->constrained()->onDelete('set null');
            
            $table->string('product_name');
            $table->string('variant')->nullable(); // e.g., "Large / Black"
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            
            // Custom design data
            $table->json('design_data')->nullable();
            $table->string('preview_url')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
    }
};


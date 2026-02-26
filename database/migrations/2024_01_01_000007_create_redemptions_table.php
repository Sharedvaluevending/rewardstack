<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->onDelete('cascade');
            $table->foreignId('qr_code_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('scan_id')->nullable()->constrained()->onDelete('set null');
            
            // Customer identification (optional, for tracking repeat customers)
            $table->string('customer_identifier')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            
            // Transaction details
            $table->decimal('original_amount', 10, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->decimal('final_amount', 10, 2)->nullable();
            
            // For punch cards
            $table->integer('punches_added')->nullable();
            $table->boolean('card_completed')->default(false);
            
            // Notes
            $table->text('notes')->nullable();
            
            // Location
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            $table->timestamp('redeemed_at');
            $table->timestamps();
            
            $table->index(['promotion_id', 'redeemed_at']);
            $table->index(['business_id', 'redeemed_at']);
            $table->index(['employee_id', 'redeemed_at']);
            $table->index('customer_identifier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redemptions');
    }
};


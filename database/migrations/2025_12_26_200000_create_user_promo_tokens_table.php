<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_promo_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('qr_code_id')->constrained('qr_codes')->onDelete('cascade');
            $table->foreignId('promotion_id')->constrained('promotions')->onDelete('cascade');
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');

            // Human-enterable token code shown under the QR (unique)
            $table->string('code', 32)->unique();

            // Stored path under storage/app/public (served via /storage/...)
            $table->string('qr_image_path')->nullable();

            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redemption_id')->nullable()->constrained('redemptions')->nullOnDelete();

            $table->timestamps();

            // One active token per user per promo QR (simple v1; can be extended to stacking later)
            $table->unique(['user_id', 'qr_code_id']);

            $table->index(['business_id', 'redeemed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_promo_tokens');
    }
};



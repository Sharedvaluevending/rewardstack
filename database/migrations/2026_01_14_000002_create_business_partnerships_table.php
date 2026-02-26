<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_partnerships')) {
            return;
        }

        Schema::create('business_partnerships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('requester_business_id')->constrained('businesses')->onDelete('cascade');
            $table->foreignId('partner_business_id')->constrained('businesses')->onDelete('cascade');

            // pending / accepted / declined / cancelled
            $table->string('status')->default('pending');
            $table->text('message')->nullable();
            $table->text('response_message')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->index(['requester_business_id', 'status']);
            $table->index(['partner_business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_partnerships');
    }
};


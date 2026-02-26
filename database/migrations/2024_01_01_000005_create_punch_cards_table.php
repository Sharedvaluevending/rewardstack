<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('punch_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->onDelete('cascade');
            $table->string('customer_identifier'); // phone, email, or unique ID
            $table->unsignedInteger('punches')->default(0);
            $table->unsignedInteger('completed_cards')->default(0);
            $table->timestamp('last_punch_at')->nullable();
            $table->timestamps();
            
            $table->unique(['promotion_id', 'customer_identifier']);
            $table->index('customer_identifier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('punch_cards');
    }
};


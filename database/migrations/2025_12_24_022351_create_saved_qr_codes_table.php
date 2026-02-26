<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('saved_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('qr_code_id')->constrained()->onDelete('cascade');
            $table->timestamp('saved_at')->useCurrent();
            $table->timestamps();
            
            // Unique constraint to prevent duplicate saves
            $table->unique(['user_id', 'qr_code_id']);
            
            // Indexes for performance
            $table->index('user_id');
            $table->index('qr_code_id');
            $table->index('saved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_qr_codes');
    }
};

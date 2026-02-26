<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_health_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->unsignedInteger('total_scans')->default(0);
            $table->unsignedInteger('scans_this_week')->default(0);
            $table->unsignedInteger('active_promotions')->default(0);
            $table->unsignedInteger('qr_codes_count')->default(0);
            $table->unsignedInteger('customers_count')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->enum('health_status', ['healthy', 'at_risk', 'inactive'])->default('inactive');
            $table->unsignedTinyInteger('health_score')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->unique(['business_id', 'date']);
            $table->index(['date', 'health_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_health_scores');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Weekly AI Insights (Basic & Advanced) are generated every Monday.
     * Stored in DB so they persist until the next Monday, even if cache is cleared.
     */
    public function up(): void
    {
        Schema::create('weekly_ai_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('type', 20); // basic | advanced
            $table->unsignedSmallInteger('period'); // 7, 30, 90, 180
            $table->json('payload'); // Full report data (summary, quick_insights, stats, etc.)
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['business_id', 'type', 'period']);
            $table->index(['business_id', 'generated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_ai_insights');
    }
};

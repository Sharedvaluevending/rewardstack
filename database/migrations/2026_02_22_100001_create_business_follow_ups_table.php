<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->enum('follow_up_type', ['day_3_checkin', 'week_1_review', 'week_4_testimonial', 'custom']);
            $table->date('due_date');
            $table->enum('status', ['pending', 'completed', 'snoozed'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('next_action')->nullable();
            $table->date('next_action_date')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index(['business_id', 'follow_up_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_follow_ups');
    }
};

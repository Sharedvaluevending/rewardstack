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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50); // 'stripe', 'nayax', etc.
            $table->string('event_id', 255); // Provider's event ID (e.g., Stripe's evt_xxx)
            $table->string('type', 100); // Event type
            $table->json('processed_data')->nullable(); // Optional processed payload snapshot
            $table->timestamp('processed_at');
            $table->timestamps();

            // Unique constraint prevents duplicate webhook processing
            $table->unique(['provider', 'event_id'], 'webhook_events_provider_event_unique');
            
            // Index for querying by provider and type
            $table->index(['provider', 'type'], 'webhook_events_provider_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};


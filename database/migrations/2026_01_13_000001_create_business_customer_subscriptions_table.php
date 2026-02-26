<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_customer_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('user_id');

            // Explicit opt-in state
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();

            // Provenance / audit
            $table->string('source', 50)->nullable(); // promotion, business_page, portal, etc.
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->unique(['business_id', 'user_id'], 'bcs_business_user_unique');
            $table->index(['business_id', 'subscribed_at'], 'bcs_business_subscribed_at_index');

            $table
                ->foreign('business_id')
                ->references('id')
                ->on('businesses')
                ->onDelete('cascade');

            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_customer_subscriptions');
    }
};


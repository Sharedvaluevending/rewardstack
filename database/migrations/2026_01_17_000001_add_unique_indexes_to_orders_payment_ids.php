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
        Schema::table('orders', function (Blueprint $table) {
            // Prevent duplicate payment intents per order
            if (!Schema::hasColumn('orders', 'stripe_payment_intent_id')) {
                return;
            }

            $table->unique('stripe_payment_intent_id', 'orders_stripe_payment_intent_unique');

            // Prevent duplicate Printful order linkage
            if (!Schema::hasColumn('orders', 'printful_order_id')) {
                return;
            }

            $table->unique('printful_order_id', 'orders_printful_order_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_stripe_payment_intent_unique');
            $table->dropUnique('orders_printful_order_id_unique');
        });
    }
};


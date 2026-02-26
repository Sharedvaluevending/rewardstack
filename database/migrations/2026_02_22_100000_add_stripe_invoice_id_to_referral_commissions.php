<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->string('stripe_invoice_id')->nullable()->after('subscription_period');
            $table->index('stripe_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->dropIndex(['stripe_invoice_id']);
            $table->dropColumn('stripe_invoice_id');
        });
    }
};

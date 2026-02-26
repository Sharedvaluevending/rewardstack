<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Stripe Connect account ID (acct_xxxx)
            if (!Schema::hasColumn('users', 'stripe_connect_id')) {
                $table->string('stripe_connect_id')->nullable()->after('referral_code');
            }
            
            // Whether their Stripe account is fully onboarded
            if (!Schema::hasColumn('users', 'stripe_connect_onboarded')) {
                $table->boolean('stripe_connect_onboarded')->default(false)->after('stripe_connect_id');
            }
            
            // Preferred payout method: 'paypal', 'venmo', 'stripe'
            if (!Schema::hasColumn('users', 'payout_method')) {
                $table->string('payout_method')->default('paypal')->after('stripe_connect_onboarded');
            }
            
            // PayPal/Venmo destination (email or username)
            if (!Schema::hasColumn('users', 'payout_destination')) {
                $table->string('payout_destination')->nullable()->after('payout_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_connect_id',
                'stripe_connect_onboarded', 
                'payout_method',
                'payout_destination',
            ]);
        });
    }
};

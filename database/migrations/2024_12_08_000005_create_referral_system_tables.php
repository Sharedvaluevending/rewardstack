<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Referral Army System
     * - Users get unique referral codes
     * - Track when businesses sign up via referral
     * - Calculate recurring commissions
     */
    public function up(): void
    {
        // Add referral code to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 20)->nullable()->unique()->after('email');
            $table->foreignId('referred_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('referral_code');
        });

        // Referrals tracking table
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->onDelete('cascade'); // The user who referred
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade'); // The business that signed up
            $table->foreignId('business_user_id')->constrained('users')->onDelete('cascade'); // The business owner user
            $table->string('referral_code', 20); // Code used at signup
            $table->decimal('commission_rate', 5, 2)->default(10.00); // Percentage (10 = 10%)
            $table->string('status')->default('active'); // active, paused, cancelled
            $table->timestamp('converted_at')->nullable(); // When business first paid
            $table->timestamps();

            $table->index(['referrer_id', 'status']);
            $table->index('business_id');
        });

        // Commission payouts tracking
        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals')->onDelete('cascade');
            $table->foreignId('referrer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
            $table->string('subscription_period'); // e.g., "2024-12" for December 2024
            $table->decimal('business_payment', 10, 2); // What business paid
            $table->decimal('commission_rate', 5, 2); // Rate at time of calculation
            $table->decimal('commission_amount', 10, 2); // Actual commission earned
            $table->string('status')->default('pending'); // pending, approved, paid, cancelled
            $table->timestamp('paid_at')->nullable();
            $table->string('payout_method')->nullable(); // paypal, stripe, bank
            $table->string('payout_reference')->nullable(); // Transaction ID
            $table->timestamps();

            $table->index(['referrer_id', 'status']);
            $table->index(['subscription_period']);
        });

        // Referral payouts (when we pay out to referrers)
        Schema::create('referral_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('method'); // paypal, stripe, bank
            $table->string('destination'); // Email or account number
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_payouts');
        Schema::dropIfExists('referral_commissions');
        Schema::dropIfExists('referrals');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by_user_id']);
            $table->dropColumn(['referral_code', 'referred_by_user_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update subscription plans with Stripe price IDs
        // These are placeholder IDs - you'll need to create actual prices in Stripe dashboard
        $priceUpdates = [
            'starter' => [
                'stripe_monthly_price_id' => 'price_starter_monthly',
                'stripe_yearly_price_id' => 'price_starter_yearly',
            ],
            'growth' => [
                'stripe_monthly_price_id' => 'price_growth_monthly',
                'stripe_yearly_price_id' => 'price_growth_yearly',
            ],
            'pro' => [
                'stripe_monthly_price_id' => 'price_pro_monthly',
                'stripe_yearly_price_id' => 'price_pro_yearly',
            ],
            'enterprise' => [
                'stripe_monthly_price_id' => 'price_enterprise_monthly',
                'stripe_yearly_price_id' => 'price_enterprise_yearly',
            ],
        ];

        foreach ($priceUpdates as $slug => $prices) {
            DB::table('subscription_plans')
                ->where('slug', $slug)
                ->update($prices);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reset Stripe price IDs to null
        DB::table('subscription_plans')->update([
            'stripe_monthly_price_id' => null,
            'stripe_yearly_price_id' => null,
        ]);
    }
};

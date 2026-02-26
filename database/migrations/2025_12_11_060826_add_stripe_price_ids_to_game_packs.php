<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update game packs with Stripe price IDs
        // These are placeholder IDs - you'll need to create actual prices in Stripe dashboard
        $priceUpdates = [
            'Starter Pack' => [
                'stripe_price_id_monthly' => 'price_starter_pack_monthly',
                'stripe_price_id_yearly' => 'price_starter_pack_yearly',
            ],
            'Pro Pack' => [
                'stripe_price_id_monthly' => 'price_pro_pack_monthly',
                'stripe_price_id_yearly' => 'price_pro_pack_yearly',
            ],
            'Family Dining Pack' => [
                'stripe_price_id_monthly' => 'price_family_dining_monthly',
            ],
        ];

        foreach ($priceUpdates as $packName => $prices) {
            DB::table('game_packs')
                ->where('name', $packName)
                ->update($prices);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reset Stripe price IDs to null
        DB::table('game_packs')->update([
            'stripe_price_id_monthly' => null,
            'stripe_price_id_yearly' => null,
        ]);
    }
};

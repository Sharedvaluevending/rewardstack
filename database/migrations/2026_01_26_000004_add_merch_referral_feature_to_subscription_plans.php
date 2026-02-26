<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $plans = DB::table('subscription_plans')->get(['id', 'slug', 'features']);
        foreach ($plans as $plan) {
            $features = is_array($plan->features) ? $plan->features : (json_decode($plan->features ?? '{}', true) ?: []);
            $slug = strtolower((string) $plan->slug);

            $features['merch_referral_qr'] = in_array($slug, ['growth', 'pro', 'enterprise'], true);

            DB::table('subscription_plans')
                ->where('id', $plan->id)
                ->update(['features' => json_encode($features)]);
        }
    }

    public function down(): void
    {
        $plans = DB::table('subscription_plans')->get(['id', 'features']);
        foreach ($plans as $plan) {
            $features = is_array($plan->features) ? $plan->features : (json_decode($plan->features ?? '{}', true) ?: []);
            unset($features['merch_referral_qr']);

            DB::table('subscription_plans')
                ->where('id', $plan->id)
                ->update(['features' => json_encode($features)]);
        }
    }
};

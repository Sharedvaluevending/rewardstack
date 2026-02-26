<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SubscriptionPlan;

return new class extends Migration
{
    public function up(): void
    {
        // Keep DB as the canonical source of plan features.
        // Starter: t-shirt only
        // Growth: t-shirt + hoodie

        $starter = SubscriptionPlan::where('slug', 'starter')->first();
        if ($starter) {
            $features = is_array($starter->features) ? $starter->features : [];
            $features['merch_store'] = (bool)($features['merch_store'] ?? true);
            $features['merch_categories'] = ['t-shirt'];
            $starter->forceFill(['features' => $features])->save();
        }

        $growth = SubscriptionPlan::where('slug', 'growth')->first();
        if ($growth) {
            $features = is_array($growth->features) ? $growth->features : [];
            $features['merch_store'] = (bool)($features['merch_store'] ?? true);
            $features['merch_categories'] = ['t-shirt', 'hoodie'];
            $growth->forceFill(['features' => $features])->save();
        }
    }

    public function down(): void
    {
        // Non-destructive rollback: keep merch_categories as-is if already changed later.
        // (We avoid trying to guess the previous categories.)
    }
};


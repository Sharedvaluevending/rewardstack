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
        // Add/normalize the featured_promo feature flag on plan features.
        // Growth/Pro/Enterprise: enabled. Starter: disabled.
        $desired = [
            'starter' => false,
            'growth' => true,
            'pro' => true,
            'enterprise' => true,
        ];

        foreach ($desired as $slug => $enabled) {
            $row = DB::table('subscription_plans')->where('slug', $slug)->first(['id', 'features']);
            if (!$row) {
                continue;
            }

            $features = [];
            if (!empty($row->features)) {
                $decoded = json_decode($row->features, true);
                if (is_array($decoded)) {
                    $features = $decoded;
                }
            }

            $features['featured_promo'] = (bool) $enabled;

            DB::table('subscription_plans')
                ->where('id', $row->id)
                ->update(['features' => json_encode($features)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Best-effort: remove the key (leave other features intact).
        $slugs = ['starter', 'growth', 'pro', 'enterprise'];

        foreach ($slugs as $slug) {
            $row = DB::table('subscription_plans')->where('slug', $slug)->first(['id', 'features']);
            if (!$row) {
                continue;
            }

            $features = [];
            if (!empty($row->features)) {
                $decoded = json_decode($row->features, true);
                if (is_array($decoded)) {
                    $features = $decoded;
                }
            }

            unset($features['featured_promo']);

            DB::table('subscription_plans')
                ->where('id', $row->id)
                ->update(['features' => json_encode($features)]);
        }
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $plans = DB::table('subscription_plans')->get(['id', 'features']);

        foreach ($plans as $p) {
            $features = [];
            if (is_string($p->features) && $p->features !== '') {
                $decoded = json_decode($p->features, true);
                if (is_array($decoded)) {
                    $features = $decoded;
                }
            } elseif (is_array($p->features)) {
                $features = $p->features;
            }

            // CRM is included in all tiers.
            $features['crm'] = true;
            $features['crm_automations'] = true;

            DB::table('subscription_plans')
                ->where('id', $p->id)
                ->update([
                    'features' => json_encode($features),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Keep CRM features enabled (non-destructive).
    }
};


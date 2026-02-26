<?php

namespace App\Console\Commands;

use App\Models\Promotion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcilePromotionStats extends Command
{
    protected $signature = 'promotions:reconcile-stats {--promotion= : Specific promotion ID to reconcile}';
    protected $description = 'Reconcile promotion statistics (total_redemptions, total_savings) from actual redemption data';

    public function handle()
    {
        $promotionId = $this->option('promotion');

        if ($promotionId) {
            $this->info("Reconciling stats for promotion ID: {$promotionId}");
            $this->reconcilePromotion($promotionId);
        } else {
            $this->info('Reconciling stats for all promotions...');
            $this->reconcileAllPromotions();
        }

        return 0;
    }

    protected function reconcileAllPromotions(): void
    {
        $updated = DB::statement("
            UPDATE promotions p
            SET 
                total_redemptions = COALESCE((
                    SELECT COUNT(*) 
                    FROM redemptions r 
                    WHERE r.promotion_id = p.id
                ), 0),
                total_savings = COALESCE((
                    SELECT SUM(discount_amount)
                    FROM redemptions r
                    WHERE r.promotion_id = p.id
                ), 0)
        ");

        $this->info('✅ Reconciled stats for all promotions');
    }

    protected function reconcilePromotion(int $promotionId): void
    {
        $promotion = Promotion::find($promotionId);

        if (!$promotion) {
            $this->error("Promotion {$promotionId} not found");
            return;
        }

        $oldRedemptions = $promotion->total_redemptions;
        $oldSavings = $promotion->total_savings;

        $actualRedemptions = DB::table('redemptions')
            ->where('promotion_id', $promotionId)
            ->count();

        $actualSavings = DB::table('redemptions')
            ->where('promotion_id', $promotionId)
            ->sum('discount_amount') ?? 0;

        $promotion->update([
            'total_redemptions' => $actualRedemptions,
            'total_savings' => $actualSavings,
        ]);

        $redemptionsDiff = $actualRedemptions - $oldRedemptions;
        $savingsDiff = $actualSavings - $oldSavings;

        $this->info("Promotion: {$promotion->name} (ID: {$promotionId})");
        $this->info("  Redemptions: {$oldRedemptions} → {$actualRedemptions} (diff: {$redemptionsDiff})");
        $this->info("  Savings: \${$oldSavings} → \${$actualSavings} (diff: \${$savingsDiff})");

        if ($redemptionsDiff !== 0 || abs($savingsDiff) > 0.01) {
            $this->warn("⚠️  Discrepancy found and corrected");
        } else {
            $this->info("✅ Stats are accurate");
        }
    }
}


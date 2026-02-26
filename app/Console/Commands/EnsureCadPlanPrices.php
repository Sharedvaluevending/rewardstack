<?php

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;

class EnsureCadPlanPrices extends Command
{
    protected $signature = 'stripe:ensure-cad-plan-prices {--dry-run : Show what would change without updating DB}';
    protected $description = 'Ensure SubscriptionPlan Stripe prices are CAD and match DB amounts';

    public function handle(): int
    {
        if (!class_exists('\Stripe\Stripe')) {
            $this->error('Stripe SDK not installed.');
            return 1;
        }

        $secret = config('services.stripe.secret');
        if (!$secret) {
            $this->error('STRIPE_SECRET is not configured.');
            return 1;
        }

        \Stripe\Stripe::setApiKey($secret);

        $dryRun = (bool) $this->option('dry-run');

        $plans = SubscriptionPlan::where('is_active', true)->orderBy('id')->get();
        if ($plans->isEmpty()) {
            $this->warn('No active subscription plans found.');
            return 0;
        }

        $this->info('Ensuring CAD prices for plans...');

        foreach ($plans as $plan) {
            $this->line('');
            $this->info("Plan: {$plan->slug}");

            $updates = [];

            // Monthly
            if ($plan->stripe_monthly_price_id) {
                $updates += $this->ensureCadPriceForPlan($plan, $plan->stripe_monthly_price_id, 'month', (float) $plan->monthly_price, $dryRun, 'stripe_monthly_price_id');
            } else {
                $this->warn(' - monthly price id missing; skipping');
            }

            // Yearly (optional)
            if ($plan->stripe_yearly_price_id && $plan->yearly_price !== null) {
                $updates += $this->ensureCadPriceForPlan($plan, $plan->stripe_yearly_price_id, 'year', (float) $plan->yearly_price, $dryRun, 'stripe_yearly_price_id');
            } else {
                $this->line(' - yearly price id missing or yearly_price null; skipping');
            }

            if (!$dryRun && !empty($updates)) {
                $plan->forceFill($updates)->save();
                $this->info(' - updated DB price ids');
            }
        }

        $this->line('');
        $this->info($dryRun ? 'Dry run complete (no DB changes).' : 'Done.');
        return 0;
    }

    /**
     * @return array<string,string> DB updates (field => new_price_id)
     */
    private function ensureCadPriceForPlan(
        SubscriptionPlan $plan,
        string $currentPriceId,
        string $interval,
        float $amount,
        bool $dryRun,
        string $dbField
    ): array {
        $updates = [];

        $price = \Stripe\Price::retrieve($currentPriceId);
        $productId = is_string($price->product) ? $price->product : ($price->product->id ?? null);

        if (!$productId) {
            $this->error(" - could not resolve Stripe product for {$currentPriceId}");
            return $updates;
        }

        $currency = strtolower((string) $price->currency);
        $unitAmountExpected = (int) round($amount * 100);
        $currentUnitAmount = (int) ($price->unit_amount ?? 0);

        $this->line(" - {$interval}: current_price={$currentPriceId} currency={$currency} unit_amount={$currentUnitAmount}");

        // Already CAD and amount matches: no-op.
        if ($currency === 'cad' && $currentUnitAmount === $unitAmountExpected) {
            $this->info("   -> OK (already CAD and matches {$amount})");
            return $updates;
        }

        // Search for an existing CAD price on the same product+interval+amount.
        $existing = \Stripe\Price::all([
            'product' => $productId,
            'active' => true,
            'limit' => 100,
        ]);

        foreach ($existing->data as $p) {
            $pCurrency = strtolower((string) $p->currency);
            $pAmount = (int) ($p->unit_amount ?? 0);
            $pInterval = $p->recurring->interval ?? null;
            if ($pCurrency === 'cad' && $pAmount === $unitAmountExpected && $pInterval === $interval) {
                $this->info("   -> Found existing CAD price: {$p->id}");
                $updates[$dbField] = $p->id;
                return $dryRun ? [] : $updates;
            }
        }

        // Create new CAD price
        $this->warn("   -> Creating new CAD price for {$interval} {$amount} CAD");

        if ($dryRun) {
            return [];
        }

        $new = \Stripe\Price::create([
            'product' => $productId,
            'currency' => 'cad',
            'unit_amount' => $unitAmountExpected,
            'recurring' => [
                'interval' => $interval,
            ],
            'metadata' => [
                'app' => 'rewardstack',
                'plan_slug' => $plan->slug,
                'billing_period' => $interval,
            ],
        ]);

        $this->info("   -> Created {$new->id}");
        $updates[$dbField] = $new->id;
        return $updates;
    }
}


<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileStripeSubscriptions extends Command
{
    protected $signature = 'stripe:reconcile-subscriptions
                            {--dry-run : Show what would change without making updates}
                            {--business= : Reconcile a specific business ID only}';

    protected $description = 'Reconcile local subscription status with Stripe to catch missed webhooks';

    public function handle(): int
    {
        if (!class_exists('\Stripe\Stripe') || !config('services.stripe.secret')) {
            $this->warn('Stripe SDK or API key not configured. Skipping reconciliation.');
            return 0;
        }

        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $dryRun = $this->option('dry-run');
        $specificId = $this->option('business');

        if ($dryRun) {
            $this->info('=== DRY RUN MODE ===');
        }

        // ---------------------------------------------------------------
        // 1. Businesses WITH a stripe_subscription_id — verify still valid
        // ---------------------------------------------------------------
        $withSubQuery = Business::whereNotNull('stripe_subscription_id')
            ->whereNotNull('stripe_customer_id');

        if ($specificId) {
            $withSubQuery->where('id', $specificId);
        }

        $withSub = $withSubQuery->get();
        $this->info("Checking {$withSub->count()} businesses with recorded Stripe subscriptions...");

        $fixed = 0;
        $errors = 0;

        foreach ($withSub as $business) {
            try {
                $subscription = \Stripe\Subscription::retrieve($business->stripe_subscription_id);
                $stripeStatus = $subscription->status; // active, past_due, canceled, incomplete, etc.

                $updates = [];

                // Sync status
                if ($business->subscription_status !== $stripeStatus) {
                    $updates['subscription_status'] = $stripeStatus;
                }

                // Sync cancel_at_period_end
                if ((bool) $business->subscription_cancel_at_period_end !== (bool) $subscription->cancel_at_period_end) {
                    $updates['subscription_cancel_at_period_end'] = $subscription->cancel_at_period_end;
                }

                // If Stripe says canceled, clear subscription locally
                if ($stripeStatus === 'canceled') {
                    $updates['stripe_subscription_id'] = null;
                    $updates['subscription_tier'] = 'starter';
                    $updates['subscription_cancel_at_period_end'] = false;
                }

                // Sync tier from Stripe price if needed
                if ($stripeStatus === 'active' || $stripeStatus === 'trialing') {
                    $priceId = $subscription->items->data[0]->price->id ?? null;
                    if ($priceId) {
                        $plan = SubscriptionPlan::where('stripe_monthly_price_id', $priceId)
                            ->orWhere('stripe_yearly_price_id', $priceId)
                            ->first();

                        if ($plan && $business->subscription_tier !== $plan->slug) {
                            $updates['subscription_tier'] = $plan->slug;
                        }
                    }
                }

                if (!empty($updates)) {
                    if ($dryRun) {
                        $this->line("  [DRY RUN] Business #{$business->id} ({$business->name}): would update " . json_encode($updates));
                    } else {
                        $business->update($updates);
                        Log::info("Stripe reconciliation updated business {$business->id}", $updates);
                        $this->line("  Fixed Business #{$business->id} ({$business->name}): " . json_encode($updates));
                    }
                    $fixed++;
                }
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Subscription doesn't exist in Stripe (deleted, test mode mismatch, etc.)
                if (str_contains($e->getMessage(), 'No such subscription')) {
                    $updates = [
                        'stripe_subscription_id' => null,
                        'subscription_status' => 'canceled',
                        'subscription_tier' => 'starter',
                        'subscription_cancel_at_period_end' => false,
                    ];

                    if ($dryRun) {
                        $this->warn("  [DRY RUN] Business #{$business->id}: subscription not found in Stripe, would reset");
                    } else {
                        $business->update($updates);
                        Log::warning("Stripe reconciliation: subscription not found for business {$business->id}, reset to starter", [
                            'old_subscription_id' => $business->stripe_subscription_id,
                        ]);
                        $this->warn("  Business #{$business->id}: subscription not found in Stripe, reset to starter");
                    }
                    $fixed++;
                } else {
                    $this->error("  Business #{$business->id}: Stripe error - {$e->getMessage()}");
                    Log::error("Stripe reconciliation error for business {$business->id}", ['error' => $e->getMessage()]);
                    $errors++;
                }
            } catch (\Throwable $e) {
                $this->error("  Business #{$business->id}: error - {$e->getMessage()}");
                Log::error("Stripe reconciliation error for business {$business->id}", ['error' => $e->getMessage()]);
                $errors++;
            }
        }

        // ---------------------------------------------------------------
        // 2. Businesses marked 'active' but WITHOUT stripe_subscription_id
        //    and whose trial has ended — they should not be active.
        // ---------------------------------------------------------------
        $orphanQuery = Business::whereNull('stripe_subscription_id')
            ->where('subscription_status', 'active')
            ->where(function ($q) {
                $q->whereNull('trial_ends_at')
                  ->orWhere('trial_ends_at', '<', now());
            });

        if ($specificId) {
            $orphanQuery->where('id', $specificId);
        }

        $orphans = $orphanQuery->get();

        if ($orphans->isNotEmpty()) {
            $this->info("Found {$orphans->count()} businesses marked active with no subscription and expired trial...");

            foreach ($orphans as $business) {
                if ($dryRun) {
                    $this->warn("  [DRY RUN] Business #{$business->id} ({$business->name}): would downgrade to starter");
                } else {
                    $business->update([
                        'subscription_status' => 'canceled',
                        'subscription_tier' => 'starter',
                    ]);
                    Log::warning("Stripe reconciliation: orphan active business {$business->id} downgraded to starter (no subscription, trial expired)");
                    $this->warn("  Business #{$business->id} ({$business->name}): downgraded to starter");
                }
                $fixed++;
            }
        }

        $this->newLine();
        $this->info("Reconciliation complete. Fixed: {$fixed}, Errors: {$errors}");

        return $errors > 0 ? 1 : 0;
    }
}

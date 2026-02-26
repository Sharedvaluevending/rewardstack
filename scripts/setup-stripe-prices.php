#!/usr/bin/env php
<?php

/**
 * Stripe Price Setup Script
 * 
 * This script creates Stripe products and prices for all subscription plans
 * and updates the database with the real Stripe Price IDs.
 * 
 * Usage: php scripts/setup-stripe-prices.php [--dry-run]
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SubscriptionPlan;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

$dryRun = in_array('--dry-run', $argv);

echo "🔧 Stripe Price Setup Script\n";
echo "============================\n\n";

if ($dryRun) {
    echo "⚠️  DRY RUN MODE - No changes will be made\n\n";
}

// Check Stripe configuration
$stripeSecret = config('services.stripe.secret');
if (!$stripeSecret) {
    echo "❌ Error: STRIPE_SECRET not configured in .env\n";
    exit(1);
}

$stripe = new StripeClient($stripeSecret);

// Verify we're in the right mode
try {
    $account = $stripe->accounts->retrieve();
    $mode = strpos($stripeSecret, 'sk_live_') === 0 ? 'LIVE' : 'TEST';
    echo "Stripe Mode: {$mode}\n";
    if ($mode === 'LIVE') {
        echo "⚠️  WARNING: You are using LIVE/Production Stripe keys!\n";
        echo "   This will create REAL products and prices.\n";
        if (!$dryRun) {
            echo "\nPress Enter to continue or Ctrl+C to cancel...";
            fgets(STDIN);
        }
    }
    echo "\n";
} catch (\Exception $e) {
    echo "❌ Error connecting to Stripe: " . $e->getMessage() . "\n";
    exit(1);
}

// Get all subscription plans
$plans = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();

if ($plans->isEmpty()) {
    echo "❌ No active subscription plans found in database.\n";
    echo "   Run: php artisan db:seed --class=SubscriptionPlanSeeder\n";
    exit(1);
}

echo "Found {$plans->count()} subscription plans:\n\n";

$results = [];

foreach ($plans as $plan) {
    echo "Processing: {$plan->name} ({$plan->slug})\n";
    echo "  Monthly: \${$plan->monthly_price}\n";
    echo "  Yearly: \${$plan->yearly_price}\n";
    
    $updates = [];
    
    // Check if monthly price needs to be created
    $needsMonthlyPrice = false;
    if (!$plan->stripe_monthly_price_id || strpos($plan->stripe_monthly_price_id, 'price_') === false || strlen($plan->stripe_monthly_price_id) < 20) {
        $needsMonthlyPrice = true;
    } else {
        // Verify existing price
        try {
            $existingPrice = $stripe->prices->retrieve($plan->stripe_monthly_price_id);
            echo "    ✓ Monthly price exists: {$plan->stripe_monthly_price_id}\n";
        } catch (ApiErrorException $e) {
            echo "    ⚠️  Monthly price ID invalid: {$plan->stripe_monthly_price_id}\n";
            $needsMonthlyPrice = true;
        }
    }
    
    if ($needsMonthlyPrice) {
        echo "  Creating monthly price...\n";
        
        if (!$dryRun) {
            try {
                // Check if product exists, create if not
                $productName = "{$plan->name} Plan";
                $products = $stripe->products->all(['limit' => 100]);
                $product = null;
                
                foreach ($products->data as $p) {
                    if ($p->name === $productName) {
                        $product = $p;
                        break;
                    }
                }
                
                if (!$product) {
                    $product = $stripe->products->create([
                        'name' => $productName,
                        'description' => $plan->description,
                        'metadata' => [
                            'plan_slug' => $plan->slug,
                            'plan_id' => $plan->id,
                        ],
                    ]);
                    echo "    ✓ Created product: {$product->id}\n";
                } else {
                    echo "    ✓ Using existing product: {$product->id}\n";
                }
                
                // Create monthly price
                $price = $stripe->prices->create([
                    'product' => $product->id,
                    'unit_amount' => (int)($plan->monthly_price * 100), // Convert to cents
                    'currency' => 'usd',
                    'recurring' => [
                        'interval' => 'month',
                    ],
                    'nickname' => "{$plan->name} Monthly",
                    'metadata' => [
                        'plan_slug' => $plan->slug,
                        'plan_id' => $plan->id,
                        'billing_period' => 'monthly',
                    ],
                ]);
                
                $updates['stripe_monthly_price_id'] = $price->id;
                echo "    ✓ Created monthly price: {$price->id}\n";
            } catch (ApiErrorException $e) {
                echo "    ❌ Error: " . $e->getMessage() . "\n";
                continue;
            }
        } else {
            echo "    [DRY RUN] Would create monthly price\n";
        }
    }
    
    // Check if yearly price needs to be created
    $needsYearlyPrice = false;
    if (!$plan->stripe_yearly_price_id || strpos($plan->stripe_yearly_price_id, 'price_') === false || strlen($plan->stripe_yearly_price_id) < 20) {
        $needsYearlyPrice = true;
    } else {
        // Verify existing price
        try {
            $existingPrice = $stripe->prices->retrieve($plan->stripe_yearly_price_id);
            echo "    ✓ Yearly price exists: {$plan->stripe_yearly_price_id}\n";
        } catch (ApiErrorException $e) {
            echo "    ⚠️  Yearly price ID invalid: {$plan->stripe_yearly_price_id}\n";
            $needsYearlyPrice = true;
        }
    }
    
    if ($needsYearlyPrice) {
        echo "  Creating yearly price...\n";
        
        if (!$dryRun) {
            try {
                // Use same product (ensure it exists)
                if (!isset($product)) {
                    $productName = "{$plan->name} Plan";
                    $products = $stripe->products->all(['limit' => 100]);
                    $product = null;
                    foreach ($products->data as $p) {
                        if ($p->name === $productName) {
                            $product = $p;
                            break;
                        }
                    }
                    if (!$product) {
                        $product = $stripe->products->create([
                            'name' => $productName,
                            'description' => $plan->description,
                            'metadata' => [
                                'plan_slug' => $plan->slug,
                                'plan_id' => $plan->id,
                            ],
                        ]);
                    }
                }
                
                // Create yearly price
                $price = $stripe->prices->create([
                    'product' => $product->id,
                    'unit_amount' => (int)($plan->yearly_price * 100), // Convert to cents
                    'currency' => 'usd',
                    'recurring' => [
                        'interval' => 'year',
                    ],
                    'nickname' => "{$plan->name} Yearly",
                    'metadata' => [
                        'plan_slug' => $plan->slug,
                        'plan_id' => $plan->id,
                        'billing_period' => 'yearly',
                    ],
                ]);
                
                $updates['stripe_yearly_price_id'] = $price->id;
                echo "    ✓ Created yearly price: {$price->id}\n";
            } catch (ApiErrorException $e) {
                echo "    ❌ Error: " . $e->getMessage() . "\n";
                continue;
            }
        } else {
            echo "    [DRY RUN] Would create yearly price\n";
        }
    }
    
    // Update database
    if (!empty($updates) && !$dryRun) {
        $plan->update($updates);
        echo "  ✓ Updated database\n";
    }
    
    echo "\n";
}

echo "============================\n";
echo "✅ Setup complete!\n\n";

if ($dryRun) {
    echo "Run without --dry-run to actually create prices and update database.\n";
} else {
    echo "All prices have been created and database updated.\n";
    echo "\nNext steps:\n";
    echo "1. Verify prices in Stripe Dashboard\n";
    echo "2. Test subscription creation\n";
    echo "3. Configure webhook endpoint\n";
}


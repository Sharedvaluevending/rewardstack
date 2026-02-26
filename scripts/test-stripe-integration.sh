#!/bin/bash

# Stripe Integration Quick Test Script
# This script helps verify Stripe integration setup

echo "🔍 Stripe Integration Test Script"
echo "=================================="
echo ""

# Check if .env file exists
if [ ! -f .env ]; then
    echo "❌ .env file not found"
    exit 1
fi

# Check Stripe configuration
echo "1. Checking Stripe Configuration..."
if grep -q "STRIPE_KEY=pk_test" .env; then
    echo "   ✅ Stripe test key found"
else
    echo "   ⚠️  Stripe test key not found (check STRIPE_KEY)"
fi

if grep -q "STRIPE_SECRET=sk_test" .env; then
    echo "   ✅ Stripe test secret found"
else
    echo "   ⚠️  Stripe test secret not found (check STRIPE_SECRET)"
fi

if grep -q "STRIPE_WEBHOOK_SECRET=whsec" .env; then
    echo "   ✅ Stripe webhook secret found"
else
    echo "   ⚠️  Stripe webhook secret not found (check STRIPE_WEBHOOK_SECRET)"
fi

echo ""

# Check database for Stripe price IDs
echo "2. Checking Database Configuration..."
php artisan tinker --execute="
\$plans = \App\Models\SubscriptionPlan::all();
foreach (\$plans as \$plan) {
    \$monthly = \$plan->stripe_monthly_price_id ? '✅' : '❌';
    \$yearly = \$plan->stripe_yearly_price_id ? '✅' : '❌';
    echo \"   {$plan->name}: Monthly {$monthly} Yearly {$yearly}\n\";
}
"

echo ""

# Check webhook route
echo "3. Checking Webhook Route..."
if php artisan route:list | grep -q "webhooks/stripe"; then
    echo "   ✅ Webhook route registered"
else
    echo "   ❌ Webhook route not found"
fi

echo ""

# Run automated tests
echo "4. Running Automated Tests..."
if php artisan test --filter StripeWebhookTest --quiet; then
    echo "   ✅ Webhook tests passed"
else
    echo "   ⚠️  Some webhook tests failed (check output above)"
fi

if php artisan test --filter StripeIntegrationTest --quiet; then
    echo "   ✅ Integration tests passed"
else
    echo "   ⚠️  Some integration tests failed (check output above)"
fi

echo ""
echo "=================================="
echo "📋 Next Steps:"
echo "1. Create Stripe products/prices in test mode"
echo "2. Update database with price IDs"
echo "3. Configure webhook endpoint in Stripe Dashboard"
echo "4. Test subscription flow manually"
echo ""
echo "See docs/STRIPE_TESTING_GUIDE.md for detailed instructions"


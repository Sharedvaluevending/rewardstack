# Stripe Connect Setup Guide

This guide covers setting up Stripe Connect for automatic referral commission payouts.

## Overview

Stripe Connect allows referrers to connect their bank accounts and receive automatic payouts when commissions are earned. This replaces manual PayPal/Venmo payouts.

## Prerequisites

1. **Stripe Account** - You need a Stripe account with Connect enabled
2. **Stripe API Keys** - Already configured ✅
3. **Stripe Connect Client ID** - **OPTIONAL** (only needed for OAuth flows, not required for Express accounts)

## Step 1: Enable Stripe Connect

Update `.env`:
```
STRIPE_CONNECT_ENABLED=true
STRIPE_CONNECT_AUTO_PAYOUT_MIN=25
STRIPE_CONNECT_PAYOUT_SCHEDULE=manual
```

**Note**: Client ID is **NOT required** for Express accounts created via API. The code creates Express accounts directly using your Stripe secret key, so you don't need to set up a Connect application in the Stripe Dashboard.

## Step 2: (Optional) Get Stripe Connect Client ID

**Only needed if you want to use OAuth flows instead of direct API account creation.**

1. Go to https://dashboard.stripe.com/settings/applications
2. Click "Create application" or use existing Connect application
3. Copy the **Client ID** (starts with `ca_`)
4. Add to `.env`:
   ```
   STRIPE_CONNECT_CLIENT_ID=ca_...
   ```

## Step 3: Configure Connect Settings (Optional)

### Auto Payout Minimum
- Minimum balance before automatic payout (default: $25)
- Set `STRIPE_CONNECT_AUTO_PAYOUT_MIN=25` in `.env`

### Payout Schedule
- `manual` - Payouts only when manually triggered
- `daily` - Daily automatic payouts
- `weekly` - Weekly automatic payouts  
- `monthly` - Monthly automatic payouts

## Step 4: Verify Setup

Run this to check if Connect is enabled:
```bash
php artisan tinker --execute="echo app(\App\Services\StripeConnectService::class)->isEnabled() ? 'Enabled' : 'Disabled';"
```

## How It Works

### Commission Creation Flow

1. **Business Subscribes** → Stripe webhook `invoice.paid` fires
2. **Commission Created** → `ReferralCommissionService` creates commission record
3. **Commission Status** → Starts as `pending`, can be `approved` → `paid`

### Payout Flow

1. **Referrer Connects Bank** → Via `/portal/stripe/connect`
2. **Commissions Accumulate** → Status changes to `approved`
3. **Auto Payout** → When balance >= minimum, payout is created
4. **Transfer Created** → Funds transferred to referrer's Stripe Connect account
5. **Commissions Marked Paid** → Status updated to `paid`

## Routes

- `/portal/stripe/connect` - Start Connect onboarding
- `/portal/stripe/return` - Return from Stripe onboarding
- `/portal/stripe/dashboard` - Open Stripe Express dashboard
- `/portal/stripe/disconnect` - Disconnect Stripe account

## Commission Calculation

- Default commission rate: **10%**
- Calculated on subscription payment amount
- Recurring commissions for each billing period
- Commission = (Payment Amount × Commission Rate) / 100

## Testing

### Test Connect Account Creation
```bash
php artisan tinker
$user = App\Models\User::first();
$service = app(\App\Services\StripeConnectService::class);
$accountId = $service->createConnectAccount($user);
```

### Test Commission Creation
```bash
php artisan tinker
$business = App\Models\Business::first();
$service = app(\App\Services\ReferralCommissionService::class);
$commission = $service->createCommissionForPayment($business, 39.99, '2024-12');
```

### Test Payout
```bash
php artisan referrals:process-payouts
```

## Important Notes

1. **Stripe Connect Fees**: Stripe charges fees on transfers (typically 0.25% + $0.25 per transfer)
2. **Minimum Payout**: Set reasonable minimum to avoid excessive fees
3. **Tax Reporting**: Stripe handles 1099 forms for US accounts
4. **Compliance**: Referrers must complete KYC (Know Your Customer) verification

## Troubleshooting

### Connect Not Enabled
- Check `STRIPE_CONNECT_ENABLED=true` in `.env`
- Verify `STRIPE_SECRET_KEY` is set in `.env`
- Restart application if needed

### Onboarding Link Fails
- Verify Stripe secret key is correct
- Check Stripe Dashboard → Connect → Settings
- Ensure Express accounts are enabled in your Stripe account

### Payouts Fail
- Check referrer has completed onboarding
- Verify `stripe_connect_onboarded` is true
- Check Stripe account has valid bank account
- Review logs: `storage/logs/laravel.log`

## Production Checklist

- [ ] Enable Connect: `STRIPE_CONNECT_ENABLED=true`
- [ ] Set minimum payout amount: `STRIPE_CONNECT_AUTO_PAYOUT_MIN=25`
- [ ] Configure payout schedule: `STRIPE_CONNECT_PAYOUT_SCHEDULE=manual`
- [ ] (Optional) Add Client ID if using OAuth flows
- [ ] Test Connect onboarding flow
- [ ] Test commission creation
- [ ] Test payout processing
- [ ] Monitor first few payouts
- [ ] Set up webhook for Connect events (optional)


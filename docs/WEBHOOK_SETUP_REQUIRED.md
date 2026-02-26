# ⚠️ WEBHOOK SETUP REQUIRED

## Yes, You Need Webhooks!

**Webhooks are CRITICAL** for your payment system to work properly. Without them:

❌ Subscriptions won't be linked to businesses  
❌ Plan changes won't be tracked  
❌ Cancellations won't be handled  
❌ **Referral commissions won't be created**  
❌ Payment failures won't be tracked  

## What Webhooks Do

Your system handles these Stripe webhook events:

### 1. `customer.subscription.created`
- **What it does**: Links Stripe subscription to your business record
- **Without it**: Business won't have `stripe_subscription_id` set

### 2. `customer.subscription.updated`
- **What it does**: Updates business plan when subscription changes
- **Without it**: Plan upgrades/downgrades won't be tracked

### 3. `customer.subscription.deleted`
- **What it does**: Handles subscription cancellations
- **Without it**: Cancelled subscriptions won't be cleaned up

### 4. `invoice.paid` ⭐ **MOST IMPORTANT**
- **What it does**: 
  - Creates referral commissions (10% recurring)
  - Tracks subscription payments
- **Without it**: **Referrers won't get paid!**

### 5. `invoice.payment_failed`
- **What it does**: Logs failed payments
- **Without it**: You won't know when payments fail

### 6. `checkout.session.completed`
- **What it does**: Handles checkout completion
- **Without it**: Checkout flow may not complete properly

## Setup Steps

### Step 1: Get Your Webhook URL

**Production URL:**
```
https://yourdomain.com/webhooks/stripe
```

**Local/Development (use Stripe CLI):**
```bash
stripe listen --forward-to localhost:8000/webhooks/stripe
```

### Step 2: Configure in Stripe Dashboard

1. Go to: https://dashboard.stripe.com/webhooks
2. Click **"Add endpoint"**
3. Enter your webhook URL: `https://yourdomain.com/webhooks/stripe`
4. Select these events:
   - ✅ `customer.subscription.created`
   - ✅ `customer.subscription.updated`
   - ✅ `customer.subscription.deleted`
   - ✅ `invoice.paid`
   - ✅ `invoice.payment_failed`
   - ✅ `checkout.session.completed`
   - ✅ `payment_intent.succeeded` (for merch orders)
   - ✅ `payment_intent.payment_failed` (for merch orders)

### Step 3: Get Webhook Secret

1. After creating the webhook endpoint, click on it
2. Click **"Reveal"** next to "Signing secret"
3. Copy the secret (starts with `whsec_`)
4. Add to your `.env`:
   ```bash
   STRIPE_WEBHOOK_SECRET=whsec_...
   ```

### Step 4: Verify Setup

```bash
# Check if webhook secret is set
cd /var/www/rewardstack
grep STRIPE_WEBHOOK_SECRET .env

# Test webhook endpoint (from Stripe Dashboard → Webhooks → Send test webhook)
```

## Security

Your webhook endpoint is protected by:
1. **Signature Verification**: Stripe signs all webhooks with your secret
2. **Token Middleware**: Additional `webhook.token` middleware (optional)

## Testing Webhooks

### Option 1: Stripe Dashboard
1. Go to Webhooks → Your endpoint
2. Click **"Send test webhook"**
3. Select event type (e.g., `invoice.paid`)
4. Check your logs: `storage/logs/laravel.log`

### Option 2: Stripe CLI (Local Development)
```bash
# Install Stripe CLI
brew install stripe/stripe-cli/stripe  # macOS
# or download from https://stripe.com/docs/stripe-cli

# Login
stripe login

# Forward webhooks to local server
stripe listen --forward-to localhost:8000/webhooks/stripe

# Trigger test event
stripe trigger invoice.paid
```

## Current Status Check

Run this to check your webhook configuration:

```bash
cd /var/www/rewardstack
php artisan tinker --execute="
echo 'Webhook Secret: ' . (config('services.stripe.webhook_secret') ? '✅ Set' : '❌ Missing') . PHP_EOL;
echo 'Webhook URL: ' . url('/webhooks/stripe') . PHP_EOL;
"
```

## Troubleshooting

### Webhooks Not Working?

1. **Check webhook secret**:
   ```bash
   grep STRIPE_WEBHOOK_SECRET .env
   ```

2. **Check webhook logs in Stripe Dashboard**:
   - Go to Webhooks → Your endpoint
   - Check "Recent deliveries"
   - Look for failed requests (red status)

3. **Check Laravel logs**:
   ```bash
   tail -f storage/logs/laravel.log | grep webhook
   ```

4. **Verify endpoint is accessible**:
   ```bash
   curl -X POST https://yourdomain.com/webhooks/stripe
   ```

### Common Issues

**Issue**: "Invalid signature" errors
- **Fix**: Make sure `STRIPE_WEBHOOK_SECRET` matches the secret from Stripe Dashboard

**Issue**: Webhooks not received
- **Fix**: 
  - Check firewall/security groups allow Stripe IPs
  - Verify URL is publicly accessible
  - Check Stripe Dashboard → Webhooks → Recent deliveries

**Issue**: Commissions not being created
- **Fix**: 
  - Verify `invoice.paid` event is enabled
  - Check webhook is reaching your server
  - Check Laravel logs for errors

## Production Checklist

- [ ] Webhook endpoint created in Stripe Dashboard
- [ ] All required events selected
- [ ] `STRIPE_WEBHOOK_SECRET` added to `.env`
- [ ] Webhook URL is publicly accessible
- [ ] Test webhook sent successfully
- [ ] Checked webhook logs in Stripe Dashboard
- [ ] Verified signature verification works

---

**⚠️ IMPORTANT**: Without webhooks configured, your referral commission system will NOT work. Commissions are created when `invoice.paid` webhooks are received!


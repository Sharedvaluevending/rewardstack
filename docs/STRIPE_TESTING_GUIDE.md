# Stripe Integration Testing Guide

This guide covers manual testing of the Stripe subscription integration, including subscription creation, updates, cancellation, and webhook handling.

## Prerequisites

1. **Stripe Test Mode Setup**
   - Create Stripe test account at https://dashboard.stripe.com/test
   - Get test API keys (Publishable Key and Secret Key)
   - Configure in `.env`:
     ```
     STRIPE_KEY=pk_test_...
     STRIPE_SECRET=sk_test_...
     STRIPE_WEBHOOK_SECRET=whsec_...
     ```

2. **Create Stripe Products & Prices**
   - Go to Stripe Dashboard → Products
   - Create products for each plan:
     - Starter Plan ($19.99/month, $199.99/year)
     - Growth Plan ($39.99/month, $399.99/year)
     - Pro Plan ($79.99/month, $799.99/year)
     - Enterprise Plan ($199.99/month, $1999.99/year)
   - Copy the Price IDs (e.g., `price_1234567890`)
   - Update database:
     ```sql
     UPDATE subscription_plans SET stripe_monthly_price_id = 'price_...' WHERE slug = 'starter';
     UPDATE subscription_plans SET stripe_yearly_price_id = 'price_...' WHERE slug = 'starter';
     -- Repeat for each plan
     ```

3. **Webhook Endpoint Setup**
   - Install Stripe CLI: https://stripe.com/docs/stripe-cli
   - Forward webhooks to local: `stripe listen --forward-to localhost:8000/webhooks/stripe`
   - Or configure webhook in Stripe Dashboard:
     - URL: `https://yourdomain.com/webhooks/stripe`
     - Events to listen:
       - `customer.subscription.created`
       - `customer.subscription.updated`
       - `customer.subscription.deleted`
       - `invoice.paid`
       - `invoice.payment_failed`
       - `checkout.session.completed`

## Test Scenarios

### 1. Subscription Creation

**Steps:**
1. Register a new business account (gets 14-day trial)
2. Navigate to `/business/billing`
3. Select a plan (e.g., Growth - Monthly)
4. Click "Start Free Trial" or "Switch Plan"
5. Complete Stripe Checkout with test card: `4242 4242 4242 4242`
   - Expiry: Any future date (e.g., 12/34)
   - CVC: Any 3 digits (e.g., 123)
   - ZIP: Any 5 digits (e.g., 12345)

**Expected Results:**
- ✅ Redirected to Stripe Checkout page
- ✅ After payment, redirected back to settings page
- ✅ Webhook `customer.subscription.created` received
- ✅ Database updated:
  - `businesses.stripe_subscription_id` = subscription ID
  - `businesses.subscription_tier` = plan slug (e.g., 'growth')
  - `businesses.trial_ends_at` = NULL
- ✅ Billing page shows "Active" status
- ✅ Features unlocked based on plan tier

**Verify in Stripe Dashboard:**
- Customer created
- Subscription created with correct price
- Subscription status = "active"

**Verify in Database:**
```sql
SELECT id, name, subscription_tier, stripe_subscription_id, trial_ends_at 
FROM businesses 
WHERE id = <business_id>;
```

### 2. Subscription Upgrade

**Steps:**
1. With an active Growth subscription
2. Navigate to `/business/billing`
3. Select Pro plan
4. Click "Switch Plan"
5. Complete checkout

**Expected Results:**
- ✅ Webhook `customer.subscription.updated` received
- ✅ Database updated: `subscription_tier` = 'pro'
- ✅ Features upgraded (custom domain, API access, etc.)
- ✅ Old subscription cancelled, new one active

**Verify:**
- Check `businesses.subscription_tier` changed
- Verify feature access (e.g., can set custom domain)

### 3. Subscription Downgrade

**Steps:**
1. With an active Pro subscription
2. Navigate to `/business/billing`
3. Select Growth plan
4. Click "Switch Plan"
5. Complete checkout

**Expected Results:**
- ✅ Subscription tier downgraded
- ✅ Features restricted (custom domain disabled)
- ✅ Access to Pro features removed

### 4. Subscription Cancellation

**Steps:**
1. With an active subscription
2. Navigate to `/business/billing`
3. Click "Manage Billing" button
4. In Stripe Billing Portal, cancel subscription
5. Return to application

**Expected Results:**
- ✅ Webhook `customer.subscription.deleted` received
- ✅ Database updated:
  - `stripe_subscription_id` = NULL
  - `subscription_tier` = 'starter'
- ✅ Access downgraded to Starter tier limits
- ✅ Trial ended (no access to paid features)

**Verify:**
```sql
SELECT subscription_tier, stripe_subscription_id 
FROM businesses 
WHERE id = <business_id>;
-- Should show: subscription_tier = 'starter', stripe_subscription_id = NULL
```

### 5. Trial Expiration

**Steps:**
1. Create business account (14-day trial)
2. Manually expire trial in database:
   ```sql
   UPDATE businesses 
   SET trial_ends_at = '2024-01-01 00:00:00' 
   WHERE id = <business_id>;
   ```
3. Try to access protected route (e.g., `/business/dashboard`)

**Expected Results:**
- ✅ Redirected to `/business/billing`
- ✅ Error message: "Your trial has expired. Please subscribe to continue using the platform."
- ✅ Can still access billing page to subscribe
- ✅ Cannot access other business features

### 6. Webhook Signature Verification

**Steps:**
1. Send test webhook from Stripe Dashboard
2. Check logs for signature verification

**Expected Results:**
- ✅ Webhook accepted if signature valid
- ✅ Webhook rejected if signature invalid (400 error)
- ✅ Logs show webhook processing

**Test Invalid Signature:**
```bash
curl -X POST http://localhost:8000/webhooks/stripe \
  -H "Stripe-Signature: invalid_signature" \
  -d '{"type":"customer.subscription.created","data":{}}'
```

### 7. Payment Failure Handling

**Steps:**
1. Use test card that fails: `4000 0000 0000 0002`
2. Attempt subscription

**Expected Results:**
- ✅ Payment fails at checkout
- ✅ No subscription created
- ✅ Webhook `invoice.payment_failed` received (if subscription created but payment failed)
- ✅ Business remains on trial/starter tier

### 8. Missing Price ID Handling

**Steps:**
1. Set plan price ID to NULL:
   ```sql
   UPDATE subscription_plans 
   SET stripe_monthly_price_id = NULL 
   WHERE slug = 'growth';
   ```
2. Try to subscribe to Growth plan

**Expected Results:**
- ✅ Error message: "Stripe price ID not configured for Growth plan (monthly billing). Please contact support."
- ✅ No checkout session created
- ✅ Error logged

### 9. Billing Portal Access

**Steps:**
1. With active subscription
2. Click "Manage Billing" button
3. Verify portal access

**Expected Results:**
- ✅ Redirected to Stripe Billing Portal
- ✅ Can view subscription details
- ✅ Can update payment method
- ✅ Can cancel subscription
- ✅ Can view invoices

### 10. Feature Gating Verification

**Test Custom Domain (Pro/Enterprise only):**
1. As Starter/Growth tier → Try to set custom domain
2. Expected: Error message, domain not saved
3. Upgrade to Pro → Set custom domain
4. Expected: Domain saved successfully

**Test White Label (Enterprise only):**
1. As Pro tier → Colors can be changed (basic customization)
2. As Enterprise → Full white label branding available

**Test API Access (Pro/Enterprise only):**
1. Verify API endpoints check `canAccess('api_access')`
2. Lower tiers should receive 403 errors

## Test Cards

Use these Stripe test cards:

- **Success**: `4242 4242 4242 4242`
- **Decline**: `4000 0000 0000 0002`
- **Requires Authentication**: `4000 0025 0000 3155`
- **Insufficient Funds**: `4000 0000 0000 9995`

## Troubleshooting

### Webhooks Not Received

1. **Check webhook endpoint URL**
   - Must be publicly accessible (use ngrok for local testing)
   - URL: `https://yourdomain.com/webhooks/stripe`

2. **Verify webhook secret**
   ```bash
   # In Stripe Dashboard → Developers → Webhooks
   # Copy the "Signing secret" (starts with whsec_)
   # Add to .env: STRIPE_WEBHOOK_SECRET=whsec_...
   ```

3. **Check webhook logs**
   ```bash
   tail -f storage/logs/laravel.log | grep -i stripe
   ```

4. **Test with Stripe CLI**
   ```bash
   stripe listen --forward-to localhost:8000/webhooks/stripe
   stripe trigger customer.subscription.created
   ```

### Subscription Not Updating

1. **Check webhook handler logs**
   ```sql
   SELECT * FROM businesses WHERE stripe_subscription_id IS NOT NULL;
   ```

2. **Verify metadata in Stripe**
   - Subscription → Metadata should have `business_id` and `plan_id`

3. **Manually trigger webhook**
   ```bash
   stripe events resend evt_...
   ```

### Database Not Updating

1. **Check webhook signature verification**
   - Invalid signatures will reject webhook
   - Check logs for "Invalid signature" errors

2. **Verify business exists**
   - Webhook handler checks `Business::find($businessId)`
   - If business deleted, webhook will fail silently

3. **Check for exceptions**
   - Review Laravel logs for exceptions
   - Webhook handler should return JSON response

## Automated Testing

See `tests/Feature/StripeWebhookTest.php` for automated webhook tests.

Run tests:
```bash
php artisan test --filter StripeWebhookTest
```

## Production Checklist

Before going live:

- [ ] Switch to Stripe Live mode API keys
- [ ] Update webhook endpoint to production URL
- [ ] Configure webhook in Stripe Dashboard (Live mode)
- [ ] Set all Stripe price IDs in database
- [ ] Test with real card (small amount)
- [ ] Verify webhook delivery
- [ ] Monitor webhook logs for first 24 hours
- [ ] Set up webhook failure alerts
- [ ] Test cancellation flow
- [ ] Verify trial expiration handling

## Support

If issues persist:
1. Check Stripe Dashboard → Logs
2. Review Laravel logs: `storage/logs/laravel.log`
3. Verify webhook events in Stripe Dashboard
4. Test with Stripe CLI for local debugging


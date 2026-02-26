<?php

namespace App\Services;

use App\Models\Business;
use App\Models\SubscriptionPlan;
use App\Models\GamePack;
use Illuminate\Support\Facades\Log;

class StripeService
{
    protected bool $stripeAvailable = false;

    public function __construct()
    {
        // Check if Stripe SDK is installed
        if (class_exists('\Stripe\Stripe')) {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            $this->stripeAvailable = true;
        }
    }

    /**
     * Check if Stripe SDK is available
     */
    public function isAvailable(): bool
    {
        return $this->stripeAvailable && !empty(config('services.stripe.secret'));
    }

    /**
     * Create or get Stripe customer for a business
     */
    public function getOrCreateCustomer(Business $business): ?\Stripe\Customer
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            if ($business->stripe_customer_id) {
                return \Stripe\Customer::retrieve($business->stripe_customer_id);
            }

            $customer = \Stripe\Customer::create([
                'email' => $business->email ?? $business->owner->email,
                'name' => $business->name,
                'metadata' => [
                    'business_id' => $business->id,
                    'owner_id' => $business->user_id,
                ],
            ]);

            $business->update(['stripe_customer_id' => $customer->id]);

            return $customer;
        } catch (\Throwable $e) {
            // Fail closed (no customer/session created) instead of fatals.
            Log::error('Stripe getOrCreateCustomer failed', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create a checkout session for game pack purchase
     */
    public function createGamePackCheckoutSession(Business $business, GamePack $gamePack, string $billingPeriod = 'monthly'): ?\Stripe\Checkout\Session
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $priceId = $billingPeriod === 'yearly'
            ? $gamePack->stripe_price_id_yearly
            : $gamePack->stripe_price_id_monthly;

        if (!$priceId) {
            return null; // Game pack doesn't support this billing period
        }

        $customer = $this->getOrCreateCustomer($business);
        if (!$customer) {
            return null;
        }

        return \Stripe\Checkout\Session::create([
            'customer' => $customer->id,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'mode' => 'subscription', // Game packs are recurring subscriptions
            'success_url' => route('business.qrcade') . '?pack=activated&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('business.qrcade'),
            'metadata' => [
                'business_id' => $business->id,
                'game_pack_id' => $gamePack->id,
                'billing_period' => $billingPeriod,
            ],
            'subscription_data' => [
                'metadata' => [
                    'business_id' => $business->id,
                    'game_pack_id' => $gamePack->id,
                ],
            ],
        ]);
    }

    /**
     * Create a checkout session for a subscription
     */
    public function createCheckoutSession(Business $business, SubscriptionPlan $plan, string $billingPeriod = 'monthly'): ?\Stripe\Checkout\Session
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $priceId = $billingPeriod === 'yearly' 
            ? $plan->stripe_yearly_price_id 
            : $plan->stripe_monthly_price_id;

        if (!$priceId) {
            throw new \Exception("Stripe price ID not configured for {$plan->name} plan ({$billingPeriod} billing). Please contact support.");
        }

        $customer = $this->getOrCreateCustomer($business);
        if (!$customer) {
            return null;
        }

        return \Stripe\Checkout\Session::create([
            'customer' => $customer->id,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => route('business.settings') . '?subscription=success',
            'cancel_url' => route('business.settings') . '?subscription=cancelled',
            'metadata' => [
                'business_id' => $business->id,
                'plan_id' => $plan->id,
            ],
            'subscription_data' => [
                'metadata' => [
                    'business_id' => $business->id,
                    'plan_id' => $plan->id,
                ],
            ],
        ]);
    }

    /**
     * Create a billing portal session
     */
    public function createPortalSession(Business $business): ?\Stripe\BillingPortal\Session
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $customer = $this->getOrCreateCustomer($business);
        if (!$customer) {
            return null;
        }

        return \Stripe\BillingPortal\Session::create([
            'customer' => $customer->id,
            'return_url' => route('business.billing'),
        ]);
    }

    /**
     * Get subscription status
     */
    public function getSubscriptionStatus(Business $business): array
    {
        // Check if Stripe is available
        if (!$this->isAvailable()) {
            return [
                'status' => $business->subscription_tier ? 'active' : 'none',
                'is_active' => true,
                'is_trial' => $business->isOnTrial(),
                'trial_ends_at' => $business->trial_ends_at,
                'stripe_configured' => false,
            ];
        }

        if (!$business->stripe_subscription_id) {
            return [
                'status' => 'none',
                'is_active' => $business->isOnTrial(),
                'is_trial' => $business->isOnTrial(),
                'trial_ends_at' => $business->trial_ends_at,
            ];
        }

        try {
            $subscription = \Stripe\Subscription::retrieve($business->stripe_subscription_id);

            return [
                'status' => $subscription->status,
                'is_active' => in_array($subscription->status, ['active', 'trialing']),
                'is_trial' => $subscription->status === 'trialing',
                'current_period_end' => date('Y-m-d', $subscription->current_period_end),
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
                'next_billing_date' => date('Y-m-d', $subscription->current_period_end),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'is_active' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Refund a payment by its PaymentIntent ID
     *
     * @param string $paymentIntentId The Stripe PaymentIntent ID
     * @param int|null $amountInCents Optional partial refund amount in cents. Null = full refund.
     * @param string $reason Refund reason (duplicate, fraudulent, requested_by_customer)
     * @return array{success: bool, refund_id?: string, error?: string}
     */
    public function refundPayment(string $paymentIntentId, ?int $amountInCents = null, string $reason = 'requested_by_customer'): array
    {
        if (!$this->isAvailable()) {
            return ['success' => false, 'error' => 'Stripe is not configured'];
        }

        try {
            $params = [
                'payment_intent' => $paymentIntentId,
                'reason' => $reason,
            ];

            if ($amountInCents !== null) {
                $params['amount'] = $amountInCents;
            }

            $refund = \Stripe\Refund::create($params);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'status' => $refund->status,
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe refund failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            Log::error('Stripe refund unexpected error', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'An unexpected error occurred while processing the refund.'];
        }
    }

    /**
     * Check if Stripe is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->isAvailable();
    }
}


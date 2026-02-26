<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Models\GamePack;
use App\Models\BusinessGamePack;
use App\Models\BusinessGame;
use App\Models\WebhookEvent;
use App\Services\ReferralCommissionService;
use App\Jobs\SubmitPrintfulOrder;
use App\Notifications\MerchOrderConfirmed;
use App\Notifications\BusinessInvoicePaid;
use App\Notifications\BusinessInvoicePaymentFailed;
use App\Notifications\BusinessSubscriptionActivated;
use App\Notifications\ReferralBusinessCancelled;
use App\Notifications\ReferralBusinessUpgraded;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhooks
     */
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        // Require webhook secret in all non-local environments to prevent forged payloads.
        // Only local dev and the test suite are allowed to skip signature verification.
        $isLocalOrTesting = app()->environment('local', 'testing') || app()->runningUnitTests();

        if (!$isLocalOrTesting && !$webhookSecret) {
            Log::critical('STRIPE_WEBHOOK_SECRET not configured – rejecting webhook in ' . app()->environment());
            return response()->json(['error' => 'Server misconfiguration'], 500);
        }

        // Verify webhook signature if secret is configured
        if ($webhookSecret && class_exists('\Stripe\Webhook')) {
            try {
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } catch (\Stripe\Exception\SignatureVerificationException $e) {
                Log::error('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);
                return response()->json(['error' => 'Invalid signature'], 400);
            } catch (\UnexpectedValueException $e) {
                Log::error('Stripe webhook invalid payload', ['error' => $e->getMessage()]);
                return response()->json(['error' => 'Invalid payload'], 400);
            }
        } elseif ($isLocalOrTesting) {
            // Allow unsigned payloads ONLY in local dev / test suite.
            // Handle both raw JSON (from Stripe) and array format (from tests using postJson)
            $event = null;
            
            if (!empty($payload) && trim($payload) !== '') {
                $event = json_decode($payload, false); // false = return object
            }
            
            // If decoding failed or payload was empty, try request data (for postJson in tests)
            if (!$event && $request->has('type')) {
                $all = $request->all();
                $event = (object) [
                    'type' => $all['type'] ?? null,
                    'data' => (object) [
                        'object' => $all['data']['object'] ?? [],
                    ],
                ];
            }
        } else {
            // Non-local environment without webhook secret – already rejected above,
            // but guard defensively in case control flow changes.
            Log::critical('Stripe webhook rejected: no secret configured');
            return response()->json(['error' => 'Server misconfiguration'], 500);
        }

        // Handle null event (empty or invalid payload)
        if (!$event || (!isset($event->type) && !isset($event['type']))) {
            Log::warning('Stripe webhook received empty or invalid payload', [
                'payload_empty' => empty($payload),
                'has_request_data' => $request->has('type'),
            ]);
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $type = is_object($event) ? ($event->type ?? null) : ($event['type'] ?? null);
        $data = is_object($event) && isset($event->data) 
            ? (is_object($event->data) ? ($event->data->object ?? []) : ($event->data['object'] ?? []))
            : ($event['data']['object'] ?? []);

        // Extract event ID for idempotency check
        $eventId = is_object($event) ? ($event->id ?? null) : ($event['id'] ?? null);

        // Check webhook idempotency FIRST before any processing
        if ($eventId) {
            try {
                WebhookEvent::create([
                    'provider' => 'stripe',
                    'event_id' => $eventId,
                    'type' => $type,
                    'processed_at' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Duplicate key error (23000) means webhook already processed
                if ($e->getCode() === '23000') {
                    Log::info('Stripe webhook already processed', [
                        'event_id' => $eventId,
                        'type' => $type,
                    ]);
                    return response()->json(['status' => 'already_processed'], 200);
                }
                throw $e;
            }
        }

        Log::info('Stripe webhook received', ['type' => $type, 'event_id' => $eventId]);

        switch ($type) {
            // Subscription events
            case 'customer.subscription.created':
                return $this->handleSubscriptionCreated($data);

            case 'customer.subscription.updated':
                return $this->handleSubscriptionUpdated($data);

            case 'customer.subscription.deleted':
                return $this->handleSubscriptionDeleted($data);

            // Payment events
            case 'invoice.paid':
                return $this->handleInvoicePaid($data);

            case 'invoice.payment_failed':
                return $this->handleInvoicePaymentFailed($data);

            // Checkout events
            case 'checkout.session.completed':
                return $this->handleCheckoutCompleted($data);

            // Payment intent events (for merch orders)
            case 'payment_intent.succeeded':
                return $this->handlePaymentIntentSucceeded($data);

            case 'payment_intent.payment_failed':
                return $this->handlePaymentIntentFailed($data);

            case 'charge.refunded':
                return $this->handleChargeRefunded($data);

            case 'charge.dispute.created':
                return $this->handleChargeDisputeCreated($data);

            case 'charge.dispute.closed':
                return $this->handleChargeDisputeClosed($data);

            default:
                Log::info('Unhandled Stripe webhook type: ' . $type);
                return response()->json(['status' => 'ignored']);
        }
    }

    /**
     * Handle subscription created
     */
    protected function handleSubscriptionCreated($data): \Illuminate\Http\JsonResponse
    {
        // Handle both object and array formats
        $metadata = is_object($data) 
            ? ($data->metadata ?? (object)[])
            : ($data['metadata'] ?? []);
        
        $businessId = is_object($metadata) 
            ? ($metadata->business_id ?? null)
            : ($metadata['business_id'] ?? null);
        $planId = is_object($metadata)
            ? ($metadata->plan_id ?? null)
            : ($metadata['plan_id'] ?? null);
        $gamePackId = is_object($metadata)
            ? ($metadata->game_pack_id ?? null)
            : ($metadata['game_pack_id'] ?? null);

        if (!$businessId) {
            return response()->json(['status' => 'no_business_id']);
        }

        $business = Business::find($businessId);
        if (!$business) {
            return response()->json(['error' => 'Business not found'], 404);
        }

        $subscriptionId = is_object($data) ? ($data->id ?? null) : ($data['id'] ?? null);

        // Handle game pack subscription
        if ($gamePackId) {
            $gamePack = GamePack::find($gamePackId);
            if ($gamePack) {
                // Create or update game pack subscription
                BusinessGamePack::updateOrCreate(
                    [
                        'business_id' => $business->id,
                        'game_pack_id' => $gamePack->id,
                    ],
                    [
                        'status' => 'active',
                        'starts_at' => now(),
                        'stripe_subscription_id' => $subscriptionId,
                        'trial_ends_at' => null,
                    ]
                );

                // Enable all games in the pack
                foreach ($gamePack->games as $game) {
                    BusinessGame::firstOrCreate(
                        ['business_id' => $business->id, 'game_id' => $game->id],
                        ['is_enabled' => true]
                    );
                }

                Log::info("Game pack subscription created for business {$business->id}, pack {$gamePack->name}");
            }
        }
        // Handle regular subscription plan
        elseif ($planId) {
            $plan = SubscriptionPlan::find($planId);

            $business->update([
                'stripe_subscription_id' => $subscriptionId,
                'subscription_tier' => $plan?->slug ?? 'growth',
                'subscription_status' => 'active',
                'subscription_cancel_at_period_end' => false,
                'trial_ends_at' => null,
            ]);

            Log::info("Subscription created for business {$business->id}");

            // Email the business owner (async)
            try {
                if ($plan) {
                    $business->owner?->notify(new BusinessSubscriptionActivated($business, $plan));
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to send subscription activated email', [
                    'business_id' => $business->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Mark referral as converted (first subscription)
            $referral = \App\Models\Referral::where('business_id', $business->id)
                ->where('status', 'active')
                ->whereNull('converted_at')
                ->first();
            
            if ($referral) {
                $referral->update(['converted_at' => now()]);
                Log::info("Marked referral as converted for business {$business->id}");
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle subscription updated
     */
    protected function handleSubscriptionUpdated($data): \Illuminate\Http\JsonResponse
    {
        $subscriptionId = is_object($data) ? ($data->id ?? null) : ($data['id'] ?? null);

        $business = Business::where('stripe_subscription_id', $subscriptionId)->first();
        if (!$business) {
            Log::warning('Stripe subscription.updated: no matching business – acknowledging to stop retries', ['subscription_id' => $subscriptionId]);
            return response()->json(['status' => 'no_matching_business'], 200);
        }

        // Sync subscription status and cancellation flag from Stripe.
        // Stripe may signal cancellation via `cancel_at_period_end` (boolean) OR
        // `cancel_at` (timestamp). When a specific cancel date is set, the boolean
        // may stay false while `cancel_at` contains the termination timestamp.
        $status = is_object($data) ? ($data->status ?? null) : ($data['status'] ?? null);
        $cancelAtPeriodEnd = is_object($data)
            ? ($data->cancel_at_period_end ?? false)
            : ($data['cancel_at_period_end'] ?? false);
        $cancelAt = is_object($data)
            ? ($data->cancel_at ?? null)
            : ($data['cancel_at'] ?? null);

        // Treat subscription as cancelling if either flag is set
        $isCancelling = (bool) $cancelAtPeriodEnd || !empty($cancelAt);

        $updates = [];

        if ($status) {
            $updates['subscription_status'] = $status;
        }

        $updates['subscription_cancel_at_period_end'] = $isCancelling;

        // Check for plan changes
        $items = $data->items->data ?? $data['items']['data'] ?? [];
        if (!empty($items)) {
            $priceId = $items[0]->price->id ?? $items[0]['price']['id'] ?? null;
            
            if ($priceId) {
                $plan = SubscriptionPlan::where(function ($q) use ($priceId) {
                    $q->where('stripe_monthly_price_id', $priceId)
                      ->orWhere('stripe_yearly_price_id', $priceId);
                })->first();

                if ($plan && $business->subscription_tier !== $plan->slug) {
                    $oldTier = $business->subscription_tier;
                    $updates['subscription_tier'] = $plan->slug;
                    Log::info("Business {$business->id} plan updated from {$oldTier} to {$plan->slug}");

                    // Detect downgrade and revoke features the new tier doesn't support.
                    // Tier hierarchy: starter < growth < pro < enterprise
                    $tierRank = ['starter' => 0, 'growth' => 1, 'pro' => 2, 'enterprise' => 3];
                    $oldRank = $tierRank[$oldTier] ?? 0;
                    $newRank = $tierRank[$plan->slug] ?? 0;

                    if ($newRank < $oldRank) {
                        // Apply the update first so canAccess() reflects the new tier
                        $business->update($updates);
                        $updates = []; // Already saved

                        $this->revokeDowngradedFeatures($business, $oldTier, $plan->slug);
                    }

                    // Notify referrer on upgrade
                    if ($newRank > $oldRank) {
                        $this->notifyReferrerOfUpgrade($business, $oldTier, $plan->slug);
                    }
                }
            }
        }

        if (!empty($updates)) {
            $business->update($updates);
        }

        if ($isCancelling) {
            Log::info("Business {$business->id} subscription set to cancel", [
                'cancel_at_period_end' => $cancelAtPeriodEnd,
                'cancel_at' => $cancelAt ? date('Y-m-d', is_numeric($cancelAt) ? $cancelAt : strtotime($cancelAt)) : null,
            ]);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle subscription deleted
     */
    protected function handleSubscriptionDeleted($data): \Illuminate\Http\JsonResponse
    {
        $subscriptionId = is_object($data) ? ($data->id ?? null) : ($data['id'] ?? null);

        $business = Business::where('stripe_subscription_id', $subscriptionId)->first();
        if (!$business) {
            Log::warning('Stripe subscription.deleted: no matching business – acknowledging to stop retries', ['subscription_id' => $subscriptionId]);
            return response()->json(['status' => 'no_matching_business'], 200);
        }

        $previousTier = $business->subscription_tier;

        $business->update([
            'stripe_subscription_id' => null,
            'subscription_tier' => 'starter',
            'subscription_status' => 'canceled',
            'subscription_cancel_at_period_end' => false,
        ]);

        Log::info("Subscription cancelled for business {$business->id}", [
            'previous_tier' => $previousTier,
        ]);

        // Mark referral as cancelled and notify the referrer
        $referral = \App\Models\Referral::where('business_id', $business->id)
            ->where('status', \App\Models\Referral::STATUS_ACTIVE)
            ->first();

        if ($referral) {
            $referral->update(['status' => \App\Models\Referral::STATUS_CANCELLED]);
            Log::info("Referral {$referral->id} marked as cancelled for business {$business->id}");

            try {
                $referral->referrer?->notify(new ReferralBusinessCancelled($referral, $business));
            } catch (\Throwable $e) {
                Log::warning('Failed to send referral cancellation notification', [
                    'referrer_id' => $referral->referrer_id,
                    'business_id' => $business->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle invoice paid
     */
    protected function handleInvoicePaid($data): \Illuminate\Http\JsonResponse
    {
        // Normalize to plain array so data_get() dot-notation works reliably
        // (Stripe objects implement ArrayAccess but nested traversal can be unreliable)
        $data = is_object($data) && method_exists($data, 'toArray') ? $data->toArray() : (array) $data;

        $customerId = data_get($data, 'customer');
        $amountPaid = (data_get($data, 'amount_paid', 0)) / 100; // Convert from cents

        // Subscription id is normally present on subscription invoices, but some Stripe payloads/APIs
        // may omit it. We treat `billing_reason` as the source of truth for subscription invoices.
        $subscriptionId = data_get($data, 'subscription')
            ?? data_get($data, 'lines.data.0.subscription');

        $billingReason = data_get($data, 'billing_reason');
        
        // Extract the billing period from the invoice.
        // IMPORTANT: Prefer the line-item period over the invoice-level period.
        // For subscription renewals, the invoice's `period_start` can repeat across
        // billing cycles (e.g. both the Jan and Feb invoice show period_start = Jan 11),
        // but the line-item `period.start` correctly advances each cycle (Jan 11, Feb 11, …).
        $periodStart = null;
        $lineItemPeriodStart = data_get($data, 'lines.data.0.period.start');
        $invoicePeriodStart = data_get($data, 'period_start');
        $periodStartTs = $lineItemPeriodStart ?? $invoicePeriodStart;
        if (!is_null($periodStartTs)) {
            $periodStart = date('Y-m', $periodStartTs);
        }

        $business = Business::where('stripe_customer_id', $customerId)->first();
        if (!$business) {
            Log::warning('Stripe invoice.paid: no matching business – acknowledging to stop retries', ['customer_id' => $customerId]);
            return response()->json(['status' => 'no_matching_business'], 200);
        }

        Log::info("Invoice paid for business {$business->id}", [
            'amount' => $amountPaid,
            'subscription_id' => $subscriptionId,
            'billing_reason' => $billingReason,
            'period' => $periodStart,
        ]);

        // Only create commission for subscription invoices (not one-time payments).
        // Stripe uses billing_reason to indicate why an invoice exists.
        $isSubscriptionInvoice = is_string($billingReason) && str_starts_with($billingReason, 'subscription');
        $stripeInvoiceId = data_get($data, 'id'); // Stripe invoice ID (in_xxx)
        if ($amountPaid > 0 && ($isSubscriptionInvoice || $subscriptionId)) {
            try {
                $commissionService = app(ReferralCommissionService::class);
                $commission = $commissionService->createCommissionForPayment(
                    $business,
                    $amountPaid,
                    $periodStart,
                    $stripeInvoiceId
                );

                if ($commission) {
                    Log::info("Created referral commission for invoice payment", [
                        'commission_id' => $commission->id,
                        'referrer_id' => $commission->referrer_id,
                        'amount' => $commission->commission_amount,
                        'period' => $periodStart,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Failed to create referral commission", [
                    'business_id' => $business->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Email the business owner with a receipt-style notification (async).
        try {
            $invoiceUrl = data_get($data, 'hosted_invoice_url');
            $currency = data_get($data, 'currency');
            $business->owner?->notify(new BusinessInvoicePaid($business, [
                'amount' => $amountPaid,
                'currency' => $currency,
                'period' => $periodStart,
                'hosted_invoice_url' => $invoiceUrl,
            ]));
        } catch (\Throwable $e) {
            Log::warning('Failed to send invoice paid email', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle invoice payment failed
     */
    protected function handleInvoicePaymentFailed($data): \Illuminate\Http\JsonResponse
    {
        // Normalize to plain array for consistent data access
        $data = is_object($data) && method_exists($data, 'toArray') ? $data->toArray() : (array) $data;

        $customerId = data_get($data, 'customer');
        $subscriptionId = data_get($data, 'subscription')
            ?? data_get($data, 'lines.data.0.subscription');
        $attemptCount = data_get($data, 'attempt_count', 1);
        $nextAttemptTs = data_get($data, 'next_payment_attempt');

        $business = Business::where('stripe_customer_id', $customerId)->first();
        if (!$business) {
            Log::warning('Stripe invoice.payment_failed: no matching business – acknowledging to stop retries', ['customer_id' => $customerId]);
            return response()->json(['status' => 'no_matching_business'], 200);
        }

        Log::warning("Invoice payment failed for business {$business->id}", [
            'attempt_count' => $attemptCount,
            'subscription_id' => $subscriptionId,
        ]);

        // Mark subscription as past_due so middleware can restrict access
        if ($subscriptionId && $business->stripe_subscription_id === $subscriptionId) {
            $business->update([
                'subscription_status' => 'past_due',
            ]);

            Log::info("Business {$business->id} subscription marked past_due due to payment failure");
        }

        // Email business owner (async)
        try {
            $invoiceUrl = data_get($data, 'hosted_invoice_url');
            $business->owner?->notify(new BusinessInvoicePaymentFailed($business, [
                'hosted_invoice_url' => $invoiceUrl,
                'attempt_count' => $attemptCount,
                'next_payment_attempt' => $nextAttemptTs ? \Carbon\Carbon::createFromTimestamp($nextAttemptTs)->format('M j, Y \a\t g:i A') : null,
            ]));
        } catch (\Throwable $e) {
            Log::warning('Failed to send invoice payment failed email', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle checkout session completed
     */
    protected function handleCheckoutCompleted($data): \Illuminate\Http\JsonResponse
    {
        $metadata = $data->metadata ?? $data['metadata'] ?? [];
        $orderId = $metadata->order_id ?? $metadata['order_id'] ?? null;
        $mode = $data->mode ?? $data['mode'] ?? null;

        if ($orderId) {
            // This is a merch order checkout
            $order = Order::find($orderId);
            if (!$order) {
                Log::warning('Stripe checkout webhook could not find order', ['order_id' => $orderId]);
                return response()->json(['status' => 'order_not_found_deferred']);
            }

            // Idempotency: if the order was already marked paid (e.g. by payment_intent.succeeded),
            // skip duplicate processing to avoid double emails and double Printful submissions.
            if ($order->payment_status === 'paid') {
                Log::info("Order {$order->order_number} already paid, skipping checkout.session.completed duplicate");
                return response()->json(['status' => 'already_processed']);
            }

            $order->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
                'status' => 'processing',
                'stripe_payment_intent_id' => $data->payment_intent ?? $data['payment_intent'] ?? null,
            ]);

            Log::info("Order {$order->order_number} paid via checkout", ['type' => $order->type]);

            // Merch orders should auto-submit to Printful after payment.
            if ($order->type === 'merch') {
                // Email the business owner (async)
                try {
                    $order->business?->owner?->notify(new MerchOrderConfirmed($order));
                } catch (\Throwable $e) {
                    Log::warning('Failed to send merch order confirmed email', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                SubmitPrintfulOrder::dispatch($order->id);
            }
        }

        // -----------------------------------------------------------------
        // Safety net: If this is a subscription checkout, activate the
        // subscription on the business in case customer.subscription.created
        // webhook never arrives (network issues, Stripe delays, etc.).
        // Uses updateOrIgnore logic so the subscription.created handler can
        // still run idempotently without conflicting.
        // -----------------------------------------------------------------
        if ($mode === 'subscription') {
            $businessId = is_object($metadata)
                ? ($metadata->business_id ?? null)
                : ($metadata['business_id'] ?? null);
            $planId = is_object($metadata)
                ? ($metadata->plan_id ?? null)
                : ($metadata['plan_id'] ?? null);
            $subscriptionId = $data->subscription ?? $data['subscription'] ?? null;

            if ($businessId && $subscriptionId) {
                $business = Business::find($businessId);
                if ($business && !$business->stripe_subscription_id) {
                    $plan = $planId ? SubscriptionPlan::find($planId) : null;
                    $business->update([
                        'stripe_subscription_id' => $subscriptionId,
                        'subscription_tier' => $plan?->slug ?? $business->subscription_tier ?? 'growth',
                        'subscription_status' => 'active',
                        'subscription_cancel_at_period_end' => false,
                        'trial_ends_at' => null,
                    ]);

                    Log::info("Subscription activated via checkout.session.completed safety net", [
                        'business_id' => $business->id,
                        'subscription_id' => $subscriptionId,
                    ]);

                    // Send activation email if not already sent
                    try {
                        if ($plan) {
                            $business->owner?->notify(new BusinessSubscriptionActivated($business, $plan));
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Failed to send subscription activated email (checkout safety net)', [
                            'business_id' => $business->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    // Mark referral as converted (first subscription)
                    $referral = \App\Models\Referral::where('business_id', $business->id)
                        ->where('status', 'active')
                        ->whereNull('converted_at')
                        ->first();

                    if ($referral) {
                        $referral->update(['converted_at' => now()]);
                        Log::info("Marked referral as converted via checkout safety net for business {$business->id}");
                    }
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle payment intent succeeded (for merch orders)
     */
    protected function handlePaymentIntentSucceeded($data): \Illuminate\Http\JsonResponse
    {
        $paymentIntentId = $data->id ?? $data['id'] ?? null;
        $metadata = $data->metadata ?? $data['metadata'] ?? [];
        $orderId = $metadata->order_id ?? $metadata['order_id'] ?? null;

        if ($orderId) {
            $order = Order::find($orderId);
            if (!$order) {
                Log::warning('Stripe payment_intent webhook could not find order', ['order_id' => $orderId]);
                return response()->json(['status' => 'order_not_found_deferred']);
            }

            // Idempotency: if the order was already marked paid (e.g. by checkout.session.completed),
            // skip duplicate processing to avoid double emails and double Printful submissions.
            if ($order->payment_status === 'paid') {
                Log::info("Order {$order->order_number} already paid, skipping payment_intent.succeeded duplicate");
                return response()->json(['status' => 'already_processed']);
            }

            $order->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
                'status' => 'processing',
                'stripe_payment_intent_id' => $paymentIntentId,
            ]);

            Log::info("Order {$order->order_number} payment succeeded");

            if ($order->type === 'merch') {
                try {
                    $order->business?->owner?->notify(new MerchOrderConfirmed($order));
                } catch (\Throwable $e) {
                    Log::warning('Failed to send merch order confirmed email', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                SubmitPrintfulOrder::dispatch($order->id);
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle payment intent failed
     */
    protected function handlePaymentIntentFailed($data): \Illuminate\Http\JsonResponse
    {
        $metadata = $data->metadata ?? $data['metadata'] ?? [];
        $orderId = $metadata->order_id ?? $metadata['order_id'] ?? null;

        if ($orderId) {
            $order = Order::find($orderId);
            if ($order) {
                $order->update([
                    'payment_status' => 'failed',
                ]);

                Log::warning("Order {$order->order_number} payment failed");
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle charge refunded
     */
    protected function handleChargeRefunded($data): \Illuminate\Http\JsonResponse
    {
        $data = is_object($data) && method_exists($data, 'toArray') ? $data->toArray() : (array) $data;

        $paymentIntentId = data_get($data, 'payment_intent');
        $stripeInvoiceId = data_get($data, 'invoice');

        // Handle merch order refunds
        if ($paymentIntentId) {
            $order = Order::where('stripe_payment_intent_id', $paymentIntentId)->first();
            if ($order) {
                $order->update([
                    'payment_status' => 'refunded',
                    'status' => 'cancelled',
                    'refunded_at' => now(),
                ]);
                Log::info("Order {$order->order_number} marked refunded from Stripe charge.refunded");
            }
        }

        // Handle subscription invoice refunds — reverse the referral commission
        if ($stripeInvoiceId) {
            $commission = ReferralCommission::where('stripe_invoice_id', $stripeInvoiceId)
                ->whereNotIn('status', [ReferralCommission::STATUS_CANCELLED])
                ->first();

            if ($commission) {
                $commission->update(['status' => ReferralCommission::STATUS_CANCELLED]);
                Log::info("Referral commission {$commission->id} cancelled due to invoice refund", [
                    'stripe_invoice_id' => $stripeInvoiceId,
                    'referrer_id' => $commission->referrer_id,
                ]);

                try {
                    $commission->loadMissing(['referrer', 'business']);
                    $commission->referrer?->notify(new \App\Notifications\ReferralCommissionReversed($commission, 'refund'));
                } catch (\Throwable $e) {
                    Log::warning('Failed to send commission reversal notification', ['error' => $e->getMessage()]);
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle charge dispute created
     */
    protected function handleChargeDisputeCreated($data): \Illuminate\Http\JsonResponse
    {
        $data = is_object($data) && method_exists($data, 'toArray') ? $data->toArray() : (array) $data;

        $paymentIntentId = data_get($data, 'payment_intent');
        $stripeInvoiceId = data_get($data, 'invoice');

        // Handle merch order disputes
        if ($paymentIntentId) {
            $order = Order::where('stripe_payment_intent_id', $paymentIntentId)->first();
            if ($order) {
                $order->update(['payment_status' => 'disputed']);
                Log::warning("Order {$order->order_number} marked disputed from Stripe charge.dispute.created");
            }
        }

        // Handle subscription invoice disputes — hold the referral commission
        if ($stripeInvoiceId) {
            $commission = ReferralCommission::where('stripe_invoice_id', $stripeInvoiceId)
                ->whereNotIn('status', [ReferralCommission::STATUS_CANCELLED, ReferralCommission::STATUS_ON_HOLD])
                ->first();

            if ($commission) {
                $commission->update(['status' => ReferralCommission::STATUS_ON_HOLD]);
                Log::warning("Referral commission {$commission->id} put on hold due to dispute", [
                    'stripe_invoice_id' => $stripeInvoiceId,
                    'referrer_id' => $commission->referrer_id,
                ]);

                try {
                    $commission->loadMissing(['referrer', 'business']);
                    $commission->referrer?->notify(new \App\Notifications\ReferralCommissionReversed($commission, 'dispute'));
                } catch (\Throwable $e) {
                    Log::warning('Failed to send commission dispute notification', ['error' => $e->getMessage()]);
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle charge dispute closed (won or lost).
     */
    protected function handleChargeDisputeClosed($data): \Illuminate\Http\JsonResponse
    {
        $data = is_object($data) && method_exists($data, 'toArray') ? $data->toArray() : (array) $data;

        $stripeInvoiceId = data_get($data, 'invoice');
        $status = data_get($data, 'status'); // 'won', 'lost', 'warning_closed'

        if (!$stripeInvoiceId) {
            return response()->json(['status' => 'no_invoice']);
        }

        $commission = ReferralCommission::where('stripe_invoice_id', $stripeInvoiceId)
            ->where('status', ReferralCommission::STATUS_ON_HOLD)
            ->first();

        if (!$commission) {
            return response()->json(['status' => 'no_held_commission']);
        }

        if ($status === 'won') {
            // Business won the dispute — release the commission back to pending
            $commission->update(['status' => ReferralCommission::STATUS_PENDING]);
            Log::info("Referral commission {$commission->id} released after dispute won", [
                'stripe_invoice_id' => $stripeInvoiceId,
            ]);

            try {
                $commission->loadMissing(['referrer', 'business']);
                $commission->referrer?->notify(new \App\Notifications\ReferralCommissionReversed($commission, 'dispute_won'));
            } catch (\Throwable $e) {
                Log::warning('Failed to send dispute won notification', ['error' => $e->getMessage()]);
            }
        } else {
            // Business lost the dispute — cancel the commission
            $commission->update(['status' => ReferralCommission::STATUS_CANCELLED]);
            Log::info("Referral commission {$commission->id} cancelled after dispute lost", [
                'stripe_invoice_id' => $stripeInvoiceId,
                'dispute_status' => $status,
            ]);

            try {
                $commission->loadMissing(['referrer', 'business']);
                $commission->referrer?->notify(new \App\Notifications\ReferralCommissionReversed($commission, 'dispute_lost'));
            } catch (\Throwable $e) {
                Log::warning('Failed to send dispute lost notification', ['error' => $e->getMessage()]);
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Notify the referrer when a referred business upgrades their plan.
     */
    protected function notifyReferrerOfUpgrade(Business $business, string $oldTier, string $newTier): void
    {
        $referral = \App\Models\Referral::where('business_id', $business->id)
            ->where('status', \App\Models\Referral::STATUS_ACTIVE)
            ->first();

        if (!$referral) {
            return;
        }

        try {
            $referral->referrer?->notify(new ReferralBusinessUpgraded($referral, $business, $oldTier, $newTier));
            Log::info("Notified referrer {$referral->referrer_id} about business {$business->id} upgrade from {$oldTier} to {$newTier}");
        } catch (\Throwable $e) {
            Log::warning('Failed to send referral upgrade notification', [
                'referrer_id' => $referral->referrer_id,
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Revoke features that the new (lower) tier doesn't support.
     * Deactivates QR codes and features that require a higher plan.
     */
    protected function revokeDowngradedFeatures(Business $business, string $oldTier, string $newTier): void
    {
        $deactivated = [];

        // Deactivate cross-promotion QR codes if new tier doesn't support them
        if (!$business->canAccess('cross_promotions')) {
            $count = \App\Models\QRCode::where('business_id', $business->id)
                ->where('type', 'cross_promo')
                ->where('is_active', true)
                ->update(['is_active' => false]);
            if ($count > 0) {
                $deactivated[] = "{$count} cross-promotion QR code(s)";
            }
        }

        // Deactivate stackable QR codes if new tier doesn't support them
        if (!$business->canAccess('stackable_pools')) {
            $count = \App\Models\QRCode::where('business_id', $business->id)
                ->where('type', 'stackable')
                ->where('is_active', true)
                ->update(['is_active' => false]);
            if ($count > 0) {
                $deactivated[] = "{$count} stackable QR code(s)";
            }
        }

        // Deactivate premium leaderboards if new tier doesn't support them
        if (!$business->canAccess('leaderboards')) {
            $count = \App\Models\Leaderboard::where('business_id', $business->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
            if ($count > 0) {
                $deactivated[] = "{$count} leaderboard(s)";
            }
        }

        if (!empty($deactivated)) {
            $summary = implode(', ', $deactivated);
            Log::info("Subscription downgrade: deactivated features for business {$business->id} ({$oldTier} -> {$newTier}): {$summary}");

            // Notify the business owner
            try {
                $owner = $business->owner;
                if ($owner) {
                    $owner->notify(new \App\Notifications\SubscriptionDowngradeNotice($business, $oldTier, $newTier, $deactivated));
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to send downgrade notification', [
                    'business_id' => $business->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

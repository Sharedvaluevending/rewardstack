<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\SubscriptionPlan;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BillingController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    public function index(Request $request)
    {
        $business = $request->user()->business;

        if (!$business) {
            return redirect()->route('business.dashboard')
                ->with('error', 'Please complete your business profile first.');
        }

        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $subscriptionStatus = $this->stripeService->getSubscriptionStatus($business);

        return Inertia::render('Business/Billing/Index', [
            'business' => $business->only(['id', 'name', 'subscription_tier', 'trial_ends_at', 'stripe_customer_id']),
            'plans' => $plans,
            'subscriptionStatus' => $subscriptionStatus,
            'currentPlan' => $plans->firstWhere('slug', $business->subscription_tier),
            'stripeConfigured' => $this->stripeService->isConfigured(),
        ]);
    }

    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'billing_period' => 'required|in:monthly,yearly',
        ]);

        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);

        // Check if Stripe is configured
        if (!$this->stripeService->isConfigured()) {
            return response()->json([
                'error' => 'Payment system is not configured. Please contact support.',
            ], 500);
        }

        // Use a DB transaction with lockForUpdate to prevent double-click race conditions.
        // Two concurrent requests will serialize on the row lock, and the second will see
        // the stripe_subscription_id already set by the first.
        try {
            return DB::transaction(function () use ($request, $plan, $validated) {
                $business = Business::where('user_id', $request->user()->id)
                    ->lockForUpdate()
                    ->first();

                if (!$business) {
                    return response()->json([
                        'error' => 'Business not found. Please complete your business profile first.',
                    ], 422);
                }

                // Prevent creating a second subscription alongside an existing one
                if ($business->stripe_subscription_id) {
                    return response()->json([
                        'error' => 'You already have an active subscription. Use the billing portal to manage your plan.',
                    ], 422);
                }

                $session = $this->stripeService->createCheckoutSession(
                    $business,
                    $plan,
                    $validated['billing_period']
                );

                if (!$session) {
                    return response()->json([
                        'error' => 'Failed to create checkout session. Please try again.',
                    ], 500);
                }

                return response()->json([
                    'checkout_url' => $session->url,
                ]);
            });
        } catch (\Exception $e) {
            \Log::error('Stripe checkout error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to create checkout session: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function portal(Request $request)
    {
        $business = $request->user()->business;

        // Check if Stripe is configured
        if (!$this->stripeService->isConfigured()) {
            return back()->with('error', 'Payment system is not configured.');
        }

        // Create customer if not exists
        if (!$business->stripe_customer_id) {
            try {
                $this->stripeService->getOrCreateCustomer($business);
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to create billing account.');
            }
        }

        try {
            $session = $this->stripeService->createPortalSession($business);
            
            if (!$session) {
                return back()->with('error', 'Failed to open billing portal.');
            }

            // If this request came from Inertia, use Inertia::location so the client performs a full redirect.
            // Otherwise, use a normal external redirect.
            if ($request->header('X-Inertia')) {
                return Inertia::location($session->url);
            }

            return redirect()->away($session->url);
        } catch (\Exception $e) {
            \Log::error('Stripe portal error: ' . $e->getMessage());
            return back()->with('error', 'Failed to open billing portal: ' . $e->getMessage());
        }
    }
}


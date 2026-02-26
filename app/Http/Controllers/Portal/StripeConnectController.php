<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\StripeConnectService;
use Illuminate\Http\Request;

class StripeConnectController extends Controller
{
    protected StripeConnectService $stripeConnect;

    public function __construct(StripeConnectService $stripeConnect)
    {
        $this->stripeConnect = $stripeConnect;
    }

    /**
     * Start Stripe Connect onboarding
     */
    public function connect(Request $request)
    {
        $user = $request->user();

        if (!$this->stripeConnect->isEnabled()) {
            return back()->withErrors(['stripe' => 'Bank payouts are not yet available.']);
        }

        // Create connected account if not exists
        if (!$user->stripe_connect_id) {
            $accountId = $this->stripeConnect->createConnectAccount($user);
            
            if (!$accountId) {
                return back()->withErrors(['stripe' => 'Failed to create Stripe account. Please try again.']);
            }
        }

        // Generate onboarding link
        $onboardingUrl = $this->stripeConnect->createOnboardingLink(
            $user,
            // Route back through our handler so we can verify onboarding status and mark connected.
            route('portal.stripe.return'),
            route('portal.stripe.refresh')
        );

        if (!$onboardingUrl) {
            return back()->withErrors(['stripe' => 'Failed to generate setup link. Please try again.']);
        }

        return redirect($onboardingUrl);
    }

    /**
     * Handle return from Stripe onboarding
     */
    public function return(Request $request)
    {
        $user = $request->user();

        // Check onboarding status
        $isOnboarded = $this->stripeConnect->checkOnboardingStatus($user);

        if ($isOnboarded) {
            // Update user's preferred payout method
            $user->forceFill(['payout_method' => 'stripe'])->save();
            
            return redirect()->route('portal.referrals')
                ->with('success', '🎉 Bank account connected! You\'ll receive automatic payouts.');
        }

        return redirect()->route('portal.referrals')
            ->with('warning', 'Please complete your bank account setup to receive automatic payouts.');
    }

    /**
     * Refresh onboarding link (if expired or incomplete)
     */
    public function refresh(Request $request)
    {
        return $this->connect($request);
    }

    /**
     * Open Stripe Express dashboard
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        if (!$user->stripe_connect_id || !$user->stripe_connect_onboarded) {
            return back()->withErrors(['stripe' => 'Please complete your bank account setup first.']);
        }

        $dashboardUrl = $this->stripeConnect->createDashboardLink($user);

        if (!$dashboardUrl) {
            return back()->withErrors(['stripe' => 'Failed to open dashboard. Please try again.']);
        }

        return redirect($dashboardUrl);
    }

    /**
     * Disconnect Stripe account (switch back to PayPal/Venmo)
     */
    public function disconnect(Request $request)
    {
        $user = $request->user();

        $user->forceFill([
            'payout_method' => 'paypal',
            // Keep stripe_connect_id in case they reconnect
        ])->save();

        return back()->with('success', 'Switched to PayPal/Venmo payouts.');
    }

    /**
     * Start over: clear saved connect account so the user can restart onboarding.
     * (Useful if they picked the wrong business type or got stuck mid-flow.)
     */
    public function reset(Request $request)
    {
        $user = $request->user();

        $user->forceFill([
            'stripe_connect_id' => null,
            'stripe_connect_onboarded' => false,
            'payout_method' => 'paypal',
        ])->save();

        return redirect()->route('portal.referrals')
            ->with('success', 'Stripe Connect setup reset. Please click “Connect Bank Account” to start again.');
    }
}

<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Models\ReferralPayout;
use App\Services\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Helpers\DatabaseHelper;

class ReferralController extends Controller
{
    /**
     * Referral Dashboard - The Money Page 💰
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Ensure user has a referral code
        if (!$user->referral_code) {
            $user->referral_code = $this->generateUniqueCode($user);
            $user->save();
        }

        // Get referrals (paginated)
        $referralsPaginated = Referral::where('referrer_id', $user->id)
            ->with(['business:id,name,logo_path,subscription_tier', 'commissions'])
            ->latest()
            ->paginate(10, ['*'], 'referrals_page');

        // We also need the full collection for stats calculations
        $referrals = Referral::where('referrer_id', $user->id)->get();

        // Calculate stats
        $totalEarned = ReferralCommission::where('referrer_id', $user->id)
            ->where('status', 'paid')
            ->sum('commission_amount');

        $pendingEarnings = ReferralCommission::where('referrer_id', $user->id)
            ->where('status', 'pending')
            ->sum('commission_amount');

        $monthlyRecurring = ReferralCommission::where('referrer_id', $user->id)
            ->where('subscription_period', now()->format('Y-m'))
            ->where('status', '!=', 'cancelled')
            ->sum('commission_amount');

        // Recent commissions (paginated)
        $recentCommissions = ReferralCommission::where('referrer_id', $user->id)
            ->with('business:id,name')
            ->latest()
            ->paginate(10, ['*'], 'commissions_page');

        // Available for payout (pending commissions that are approved)
        $availableForPayout = ReferralCommission::where('referrer_id', $user->id)
            ->where('status', 'approved')
            ->sum('commission_amount');

        // Stripe Connect status
        $stripeConnect = app(StripeConnectService::class);
        $stripeEnabled = $stripeConnect->isEnabled();

        return Inertia::render('Portal/Referrals', [
            'referralCode' => $user->referral_code,
            'referralLink' => url("/join/{$user->referral_code}"),
            'stats' => [
                'total_referrals' => $referrals->count(),
                'active_referrals' => $referrals->where('status', 'active')->count(),
                'total_earned' => round($totalEarned, 2),
                'pending_earnings' => round($pendingEarnings, 2),
                'monthly_recurring' => round($monthlyRecurring, 2),
                'available_payout' => round($availableForPayout, 2),
            ],
            'referrals' => [
                'data' => $referralsPaginated->getCollection()->map(fn($r) => [
                    'id' => $r->id,
                    'business_name' => $r->business->name,
                    'business_logo' => $r->business->logo_url,
                    'subscription_tier' => $r->business->subscription_tier,
                    'commission_rate' => $r->commission_rate,
                    'status' => $r->status,
                    'total_earned' => round($r->total_earned, 2),
                    'created_at' => $r->created_at->format('M d, Y'),
                ]),
                'links' => $referralsPaginated->linkCollection()->toArray(),
                'current_page' => $referralsPaginated->currentPage(),
                'last_page' => $referralsPaginated->lastPage(),
                'total' => $referralsPaginated->total(),
            ],
            'recentCommissions' => [
                'data' => $recentCommissions->getCollection()->map(fn($c) => [
                    'id' => $c->id,
                    'business_name' => $c->business->name,
                    'period' => $c->subscription_period,
                    'amount' => round($c->commission_amount, 2),
                    'status' => $c->status,
                    'created_at' => $c->created_at->format('M d'),
                ]),
                'links' => $recentCommissions->linkCollection()->toArray(),
                'current_page' => $recentCommissions->currentPage(),
                'last_page' => $recentCommissions->lastPage(),
                'total' => $recentCommissions->total(),
            ],
            'minimumPayout' => 25.00, // Minimum $25 to request payout
            'stripeConnect' => [
                'enabled' => $stripeEnabled,
                'connected' => $stripeEnabled && $user->stripe_connect_onboarded,
                'accountId' => $user->stripe_connect_id,
                'payoutMethod' => $user->payout_method ?? 'paypal',
            ],
        ]);
    }

    /**
     * Request a payout
     */
    public function requestPayout(Request $request)
    {
        $validated = $request->validate([
            'method' => 'required|in:paypal,venmo',
            'destination' => 'required|string|max:255',
        ]);

        $user = $request->user();

        try {
            $result = DatabaseHelper::transactionWithRetry(function () use ($user, $validated) {
                // Get available amount (inside transaction to prevent race condition)
                $availableAmount = ReferralCommission::where('referrer_id', $user->id)
                    ->where('status', 'approved')
                    ->lockForUpdate()
                    ->sum('commission_amount');

                if ($availableAmount < 25) {
                    throw new \Exception('Minimum payout is $25.00');
                }

                // Create pending_key for unique constraint
                $pendingKey = "user:{$user->id}:pending";

                // Create payout request with pending_key to prevent duplicates
                try {
                    $payout = ReferralPayout::create([
                        'user_id' => $user->id,
                        'amount' => $availableAmount,
                        'method' => $validated['method'],
                        'destination' => $validated['destination'],
                        'status' => 'pending',
                        'pending_key' => $pendingKey,
                        'requested_at' => now(),
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Duplicate key error (23000) means pending payout already exists
                    if ($e->getCode() === '23000') {
                        throw new \Exception('You already have a pending payout request');
                    }
                    throw $e;
                }

                // Mark commissions as processing and tie them to this payout request
                ReferralCommission::where('referrer_id', $user->id)
                    ->where('status', 'approved')
                    ->update([
                        'status' => 'processing',
                        'payout_reference' => "payout:{$payout->id}",
                        'payout_method' => $validated['method'],
                    ]);

                return ['success' => true, 'amount' => $availableAmount];
            });

            return back()->with('success', "Payout of \${$result['amount']} requested! We'll process it within 3-5 business days.");
        } catch (\Exception $e) {
            return back()->withErrors(['payout' => $e->getMessage()]);
        }
    }

    /**
     * Generate a unique referral code
     */
    protected function generateUniqueCode($user): string
    {
        // Try to create a memorable code from the user's name
        $baseName = Str::upper(Str::substr(Str::slug($user->name, ''), 0, 4));
        
        if (strlen($baseName) < 3) {
            $baseName = 'QR';
        }

        $code = $baseName . '-' . Str::upper(Str::random(4));

        // Ensure uniqueness
        while (\App\Models\User::where('referral_code', $code)->exists()) {
            $code = $baseName . '-' . Str::upper(Str::random(4));
        }

        return $code;
    }

    /**
     * Regenerate referral code
     */
    public function regenerateCode(Request $request)
    {
        $user = $request->user();
        $user->referral_code = $this->generateUniqueCode($user);
        $user->save();

        return back()->with('success', 'Your referral code has been regenerated!');
    }
}

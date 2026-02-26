<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Models\ReferralPayout;
use App\Models\User;
use App\Services\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReferralManagementController extends Controller
{
    /**
     * Referral Army Dashboard
     */
    public function index(Request $request)
    {
        // Overall stats
        $stats = [
            'total_referrers' => User::whereNotNull('referral_code')->count(),
            'active_referrers' => (int) Referral::count(DB::raw('DISTINCT referrer_id')),
            'total_referrals' => Referral::count(),
            'total_commissions_paid' => ReferralCommission::where('status', 'paid')->sum('commission_amount'),
            'pending_commissions' => ReferralCommission::where('status', 'pending')->sum('commission_amount'),
            'pending_payouts' => ReferralPayout::where('status', 'pending')->count(),
            'pending_payout_amount' => ReferralPayout::where('status', 'pending')->sum('amount'),
        ];

        $stripeConnect = app(StripeConnectService::class);
        $stripeEnabled = $stripeConnect->isEnabled();

        // Top referrers
        $topReferrers = User::select('users.id', 'users.name', 'users.email', 'users.avatar_path', 'users.referral_code')
            ->selectRaw('COUNT(referrals.id) as referral_count')
            ->selectRaw('COALESCE(SUM(rc.commission_amount), 0) as total_earned')
            ->leftJoin('referrals', 'users.id', '=', 'referrals.referrer_id')
            ->leftJoin('referral_commissions as rc', function ($join) {
                $join->on('users.id', '=', 'rc.referrer_id')
                    ->where('rc.status', '=', 'paid');
            })
            ->whereNotNull('users.referral_code')
            ->groupBy('users.id', 'users.name', 'users.email', 'users.avatar_path', 'users.referral_code')
            ->having('referral_count', '>', 0)
            ->orderByDesc('referral_count')
            ->limit(10)
            ->get();

        // Pending payouts
        $pendingPayouts = ReferralPayout::with('user')
            ->where('status', 'pending')
            ->orderBy('requested_at')
            ->get();

        // Recent referrals (include subscription status for visibility)
        $recentReferrals = Referral::with(['referrer', 'business:id,name,subscription_tier,subscription_status,subscription_cancel_at_period_end'])
            ->latest()
            ->limit(10)
            ->get();

        $milestones = $this->getMilestones($stats);

        return Inertia::render('Admin/Referrals/Index', [
            'stats' => $stats,
            'milestones' => $milestones,
            'topReferrers' => $topReferrers,
            'pendingPayouts' => $pendingPayouts,
            'recentReferrals' => $recentReferrals,
            'stripeConnect' => [
                'enabled' => $stripeEnabled,
                'auto_payout_minimum' => (float) config('stripe.connect.auto_payout_minimum', 25),
                'payout_schedule' => (string) config('stripe.connect.payout_schedule', 'manual'),
            ],
            'referralBufferDays' => 30,
        ]);
    }

    /**
     * Mark payout as paid
     */
    public function markPaid(Request $request, ReferralPayout $payout)
    {
        $validated = $request->validate([
            'transaction_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $payout->update([
            'status' => 'completed',
            'transaction_id' => $validated['transaction_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'processed_at' => now(),
            'pending_key' => null,
        ]);

        // Mark associated commissions as paid (only those tied to this payout request)
        $commissionUpdate = [
            'status' => 'paid',
            'paid_at' => now(),
            'payout_method' => $payout->method,
        ];
        if (!empty($validated['transaction_id'])) {
            $commissionUpdate['payout_reference'] = $validated['transaction_id'];
        }
        ReferralCommission::where('referrer_id', $payout->user_id)
            ->where('status', 'processing')
            ->where('payout_reference', "payout:{$payout->id}")
            ->update($commissionUpdate);

        return back()->with('success', "Payout of \${$payout->amount} marked as paid!");
    }

    /**
     * Reject payout
     */
    public function rejectPayout(Request $request, ReferralPayout $payout)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $payout->update([
            'status' => 'failed',
            'notes' => $validated['reason'],
            'processed_at' => now(),
            'pending_key' => null,
        ]);

        // Return commissions to approved status
        ReferralCommission::where('referrer_id', $payout->user_id)
            ->where('status', 'processing')
            ->where('payout_reference', "payout:{$payout->id}")
            ->update([
                'status' => 'approved',
                'payout_reference' => null,
            ]);

        return back()->with('success', 'Payout rejected and commissions returned to pending.');
    }

    /**
     * View all payouts
     */
    public function payouts(Request $request)
    {
        $payouts = ReferralPayout::with('user')
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->orderByDesc('requested_at')
            ->paginate(20);

        return Inertia::render('Admin/Referrals/Payouts', [
            'payouts' => $payouts,
            'filters' => $request->only(['status']),
        ]);
    }

    /**
     * View referrer details
     */
    public function showReferrer(User $user)
    {
        $referrals = Referral::where('referrer_id', $user->id)
            ->with('business')
            ->get();

        $commissions = ReferralCommission::where('referrer_id', $user->id)
            ->with('business')
            ->orderByDesc('created_at')
            ->paginate(20);

        $payouts = ReferralPayout::where('user_id', $user->id)
            ->orderByDesc('requested_at')
            ->get();

        $stats = [
            'total_referrals' => $referrals->count(),
            'active_referrals' => $referrals->where('status', 'active')->count(),
            'total_earned' => ReferralCommission::where('referrer_id', $user->id)->where('status', 'paid')->sum('commission_amount'),
            'pending_earnings' => ReferralCommission::where('referrer_id', $user->id)->whereIn('status', ['pending', 'approved'])->sum('commission_amount'),
            'total_paid_out' => ReferralPayout::where('user_id', $user->id)->where('status', 'completed')->sum('amount'),
        ];

        return Inertia::render('Admin/Referrals/ReferrerShow', [
            'referrer' => $user,
            'referrals' => $referrals,
            'commissions' => $commissions,
            'payouts' => $payouts,
            'stats' => $stats,
        ]);
    }

    /**
     * Calculate milestones and reminders
     */
    protected function getMilestones(array $stats): array
    {
        $milestones = [];

        // Milestone 1: First referral
        $milestones[] = [
            'name' => 'First Referral',
            'target' => 1,
            'current' => $stats['total_referrals'],
            'completed' => $stats['total_referrals'] >= 1,
            'action' => null,
        ];

        // Milestone 2: 10 referrals - Army is forming
        $milestones[] = [
            'name' => '10 Referrals - Army Forming',
            'target' => 10,
            'current' => $stats['total_referrals'],
            'completed' => $stats['total_referrals'] >= 10,
            'action' => null,
        ];

        // Milestone 3: 25 active referrers - Consider PayPal Mass Pay
        $milestones[] = [
            'name' => '25 Active Referrers',
            'target' => 25,
            'current' => $stats['active_referrers'],
            'completed' => $stats['active_referrers'] >= 25,
            'action' => $stats['active_referrers'] >= 25 ? '💡 Consider PayPal Mass Pay for batch payouts' : null,
        ];

        // Milestone 4: 50 referrals - Need fraud monitoring
        $milestones[] = [
            'name' => '50 Referrals - Add Fraud Monitoring',
            'target' => 50,
            'current' => $stats['total_referrals'],
            'completed' => $stats['total_referrals'] >= 50,
            'action' => $stats['total_referrals'] >= 50 ? '⚠️ Review referrals for fraud patterns' : null,
        ];

        // Milestone 5: $500 paid out - Tax tracking
        $milestones[] = [
            'name' => '$500 Paid - Track for 1099s',
            'target' => 500,
            'current' => $stats['total_commissions_paid'],
            'completed' => $stats['total_commissions_paid'] >= 500,
            'action' => $stats['total_commissions_paid'] >= 500 ? '📋 Start tracking for 1099 requirements ($600+/person/year)' : null,
        ];

        // Milestone 6: 100 referrers - Stripe Connect time
        $milestones[] = [
            'name' => '100 Referrers - Automate Payouts',
            'target' => 100,
            'current' => $stats['active_referrers'],
            'completed' => $stats['active_referrers'] >= 100,
            'action' => $stats['active_referrers'] >= 100 ? '🚀 Time to implement Stripe Connect for automated payouts' : null,
        ];

        // Milestone 7: $5k/month in commissions - Need legal review
        $milestones[] = [
            'name' => '$5k/mo Commissions - Legal Review',
            'target' => 5000,
            'current' => $stats['pending_commissions'] + $stats['total_commissions_paid'],
            'completed' => ($stats['pending_commissions'] + $stats['total_commissions_paid']) >= 5000,
            'action' => ($stats['pending_commissions'] + $stats['total_commissions_paid']) >= 5000 ? '⚖️ Get affiliate program terms reviewed by lawyer' : null,
        ];

        return $milestones;
    }

    /**
     * Approve pending commissions (run monthly)
     */
    public function approveCommissions(Request $request)
    {
        // Move pending commissions to approved (ready for payout)
        $count = ReferralCommission::where('status', 'pending')
            ->where('created_at', '<=', now()->subDays(30)) // Only 30+ day old commissions
            ->update(['status' => 'approved']);

        return back()->with('success', "{$count} commissions approved for payout.");
    }

    /**
     * Trigger Stripe Connect auto payouts now (admin manual run).
     */
    public function runAutoPayouts(Request $request, StripeConnectService $stripeConnect)
    {
        if (!$stripeConnect->isEnabled()) {
            return back()->withErrors(['payouts' => 'Stripe Connect is not enabled.']);
        }

        $result = $stripeConnect->processAutomaticPayouts();

        $message = $result['message'] ?? 'Payouts processed.';
        if (!empty($result['processed'])) {
            $message .= " Processed {$result['processed']} payout(s).";
        }
        if (!empty($result['failed'])) {
            $message .= " {$result['failed']} payout(s) failed.";
        }

        return back()->with('success', $message);
    }
}

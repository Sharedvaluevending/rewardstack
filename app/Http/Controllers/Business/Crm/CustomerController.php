<?php

namespace App\Http\Controllers\Business\Crm;

use App\Http\Controllers\Controller;
use App\Models\BusinessCustomer;
use App\Models\BusinessCustomerSubscription;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->user()->business;

        $rows = BusinessCustomerSubscription::query()
            ->where('business_id', $business->id)
            ->whereNotNull('subscribed_at')
            ->whereNull('unsubscribed_at')
            ->with('user:id,name,email,level,xp,avatar_path,total_savings')
            ->orderByDesc('subscribed_at')
            ->paginate(25);

        // Load cached engagement stats (optional rows)
        $statsByUser = BusinessCustomer::where('business_id', $business->id)
            ->whereIn('user_id', $rows->pluck('user_id')->all())
            ->get()
            ->keyBy('user_id');

        return Inertia::render('Business/CRM/Customers', [
            'customers' => $rows->through(function ($s) use ($statsByUser) {
                $u = $s->user;
                $bc = $statsByUser->get($s->user_id);
                return [
                    'user' => [
                        'id' => $u?->id,
                        'name' => $u?->name,
                        'email' => $u?->email,
                        'avatar_url' => $u?->avatar_url,
                        'level' => $u?->level,
                        'xp' => $u?->xp,
                        'total_savings' => (string) ($u?->total_savings ?? '0.00'),
                    ],
                    'subscribed_at' => $s->subscribed_at?->format('M d, Y'),
                    'engagement' => $bc ? [
                        'last_seen_at' => $bc->last_seen_at?->format('M d, Y'),
                        'scans' => (int) $bc->scans_count,
                        'saves' => (int) $bc->saved_count,
                        'redemptions' => (int) $bc->redemptions_count,
                        'lifetime_savings' => (string) ($bc->lifetime_savings ?? '0.00'),
                    ] : [
                        'last_seen_at' => null,
                        'scans' => 0,
                        'saves' => 0,
                        'redemptions' => 0,
                        'lifetime_savings' => '0.00',
                    ],
                ];
            }),
        ]);
    }
}


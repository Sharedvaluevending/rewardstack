<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\GamePlay;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Redemption;
use App\Models\Scan;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index()
    {
        $today = now()->startOfDay();
        $start30 = now()->subDays(29)->startOfDay();
        $start14 = now()->subDays(13)->startOfDay();

        $activeBusinesses = Business::where('is_active', true)->count();
        $totalBusinesses = Business::count();
        $totalUsers = User::count();
        $newUsers30 = User::where('created_at', '>=', $start30)->count();

        $scanTotal = Scan::count();
        $scan30 = Scan::where('scanned_at', '>=', $start30)->count();
        $redemptions30 = Redemption::where('redeemed_at', '>=', $start30)->count();

        $planMix = Business::select('subscription_tier', DB::raw('COUNT(*) as total'))
            ->groupBy('subscription_tier')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'tier' => $row->subscription_tier ?? 'unknown',
                'count' => (int) $row->total,
            ])
            ->values();

        $scanSeries = $this->dailySeries(Scan::query(), 'scanned_at', $start14, 14);
        $redemptionSeries = $this->dailySeries(Redemption::query(), 'redeemed_at', $start14, 14);

        return Inertia::render('Admin/Analytics/Index', [
            'stats' => [
                'total_businesses' => $totalBusinesses,
                'active_businesses' => $activeBusinesses,
                'total_users' => $totalUsers,
                'new_users_30' => $newUsers30,
                'total_scans' => $scanTotal,
                'scans_30' => $scan30,
                'redemptions_30' => $redemptions30,
            ],
            'planMix' => $planMix,
            'series' => [
                'scans' => $scanSeries,
                'redemptions' => $redemptionSeries,
            ],
        ]);
    }

    public function revenue()
    {
        $start30 = now()->subDays(29)->startOfDay();

        $plans = SubscriptionPlan::query()
            ->get(['slug', 'name', 'monthly_price'])
            ->keyBy('slug');

        $activeSubs = Business::query()
            ->whereNotNull('stripe_subscription_id')
            ->where('is_active', true)
            ->get(['id', 'subscription_tier']);

        $mrr = $activeSubs->sum(function ($business) use ($plans) {
            $tier = $business->subscription_tier ?? 'starter';
            $price = $plans->get($tier)?->monthly_price ?? 0;
            return (float) $price;
        });

        $trialCount = Business::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->count();

        $merchRevenue30 = Order::where('type', 'merch')
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $start30)
            ->sum('total');

        $merchOrders30 = Order::where('type', 'merch')
            ->where('created_at', '>=', $start30)
            ->count();

        $planRevenue = $activeSubs->groupBy('subscription_tier')->map(function ($group, $tier) use ($plans) {
            $price = $plans->get($tier)?->monthly_price ?? 0;
            return [
                'tier' => $tier ?? 'unknown',
                'count' => $group->count(),
                'mrr' => round($group->count() * (float) $price, 2),
            ];
        })->values();

        $mrrSeries = $this->dailyMrrSeries($start30, 30);

        return Inertia::render('Admin/Analytics/Revenue', [
            'stats' => [
                'mrr' => round($mrr, 2),
                'active_subscriptions' => $activeSubs->count(),
                'trialing' => $trialCount,
                'merch_revenue_30' => round($merchRevenue30, 2),
                'merch_orders_30' => $merchOrders30,
            ],
            'planRevenue' => $planRevenue,
            'series' => [
                'mrr' => $mrrSeries,
            ],
        ]);
    }

    public function usage()
    {
        $start30 = now()->subDays(29)->startOfDay();
        $start14 = now()->subDays(13)->startOfDay();

        $scans30 = Scan::where('scanned_at', '>=', $start30)->count();
        $redemptions30 = Redemption::where('redeemed_at', '>=', $start30)->count();
        $promotionsActive = Promotion::where('is_active', true)->count();
        $qrCodesActive = QRCode::where('is_active', true)->count();
        $gamePlays30 = GamePlay::where('created_at', '>=', $start30)->count();

        $scanTypeBreakdown = Scan::select('scan_type', DB::raw('COUNT(*) as total'))
            ->whereNotNull('scan_type')
            ->groupBy('scan_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'type' => $row->scan_type,
                'count' => (int) $row->total,
            ])
            ->values();

        $scanSeries = $this->dailySeries(Scan::query(), 'scanned_at', $start14, 14);
        $redemptionSeries = $this->dailySeries(Redemption::query(), 'redeemed_at', $start14, 14);
        $gamePlaySeries = $this->dailySeries(GamePlay::query(), 'created_at', $start14, 14);

        return Inertia::render('Admin/Analytics/Usage', [
            'stats' => [
                'scans_30' => $scans30,
                'redemptions_30' => $redemptions30,
                'active_promotions' => $promotionsActive,
                'active_qr_codes' => $qrCodesActive,
                'game_plays_30' => $gamePlays30,
            ],
            'scanTypes' => $scanTypeBreakdown,
            'series' => [
                'scans' => $scanSeries,
                'redemptions' => $redemptionSeries,
                'gamePlays' => $gamePlaySeries,
            ],
        ]);
    }

    private function dailySeries($query, string $column, Carbon $start, int $days): array
    {
        $allowed = ['scanned_at', 'redeemed_at', 'created_at'];
        if (!in_array($column, $allowed)) {
            throw new \InvalidArgumentException("Invalid column for dailySeries: {$column}");
        }
        $wrapped = DB::connection()->getQueryGrammar()->wrap($column);
        $dateExpr = "DATE({$wrapped})";
        $rows = $query->where($column, '>=', $start)
            ->selectRaw("{$dateExpr} as day")
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw($dateExpr)
            ->orderBy('day')
            ->pluck('total', 'day');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $series[] = [
                'date' => Carbon::parse($date)->format('M j'),
                'value' => (int) ($rows[$date] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Daily MRR series (aggregated in DB to avoid loading all businesses into memory)
     */
    private function dailyMrrSeries(Carbon $start, int $days): array
    {
        $table = (new Business)->getTable();
        $planTable = (new SubscriptionPlan)->getTable();
        $rows = DB::table($table)
            ->selectRaw("DATE({$table}.created_at) as day")
            ->selectRaw('COALESCE(SUM(COALESCE(sp.monthly_price, 0)), 0) as total')
            ->leftJoin("{$planTable} as sp", function ($join) use ($table) {
                $join->on('sp.slug', '=', DB::raw("COALESCE({$table}.subscription_tier, 'starter')"));
            })
            ->whereNotNull("{$table}.stripe_subscription_id")
            ->where("{$table}.created_at", '>=', $start)
            ->groupByRaw("DATE({$table}.created_at)")
            ->orderBy('day')
            ->pluck('total', 'day');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $series[] = [
                'date' => Carbon::parse($date)->format('M j'),
                'value' => round((float) ($rows[$date] ?? 0), 2),
            ];
        }

        return $series;
    }
}

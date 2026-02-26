<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessFollowUp;
use App\Models\BusinessHealthScore;
use App\Models\Scan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BusinessHealthController extends Controller
{
    public function index(Request $request)
    {
        $today = today();

        $latestScores = BusinessHealthScore::select('business_health_scores.*')
            ->joinSub(
                BusinessHealthScore::select('business_id', DB::raw('MAX(date) as max_date'))
                    ->groupBy('business_id'),
                'latest',
                fn ($join) => $join
                    ->on('business_health_scores.business_id', '=', 'latest.business_id')
                    ->on('business_health_scores.date', '=', 'latest.max_date')
            )
            ->get()
            ->keyBy('business_id');

        $statusCounts = $latestScores->groupBy('health_status')->map->count();

        $followUpsDue = BusinessFollowUp::with('business.owner')
            ->where('status', 'pending')
            ->where('due_date', '<=', $today)
            ->orderBy('due_date')
            ->get()
            ->map(fn ($fu) => [
                'id' => $fu->id,
                'business_id' => $fu->business_id,
                'business_name' => $fu->business?->name,
                'business_created' => $fu->business?->created_at?->toDateString(),
                'owner_email' => $fu->business?->owner?->email,
                'owner_phone' => $fu->business?->phone,
                'type' => $fu->follow_up_type,
                'type_label' => $fu->getTypeLabel(),
                'due_date' => $fu->due_date->toDateString(),
                'days_overdue' => max(0, $fu->due_date->diffInDays($today)),
                'notes' => $fu->notes,
                'health_status' => $latestScores[$fu->business_id]?->health_status ?? 'unknown',
                'health_score' => $latestScores[$fu->business_id]?->health_score ?? 0,
            ]);

        $filter = $request->get('filter', 'all');
        $search = $request->get('search', '');

        $businessQuery = Business::where('is_active', true)
            ->with('owner')
            ->withCount(['scans', 'promotions', 'qrCodes']);

        if ($search) {
            $businessQuery->where('name', 'like', "%{$search}%");
        }

        $allBusinesses = $businessQuery->orderBy('name')->get()->map(function ($biz) use ($latestScores) {
            $score = $latestScores[$biz->id] ?? null;
            return [
                'id' => $biz->id,
                'name' => $biz->name,
                'owner_name' => $biz->owner?->name,
                'owner_email' => $biz->owner?->email,
                'phone' => $biz->phone,
                'created_at' => $biz->created_at->toDateString(),
                'days_since_signup' => $biz->created_at->diffInDays(now()),
                'subscription_tier' => $biz->subscription_tier,
                'health_status' => $score?->health_status ?? 'unknown',
                'health_score' => $score?->health_score ?? 0,
                'scans_count' => $biz->scans_count,
                'promotions_count' => $biz->promotions_count,
                'qr_codes_count' => $biz->qr_codes_count,
                'last_login' => $biz->owner?->last_login_at?->toDateString(),
            ];
        });

        if ($filter !== 'all') {
            $allBusinesses = $allBusinesses->filter(fn ($b) => $b['health_status'] === $filter)->values();
        }

        return Inertia::render('Admin/BusinessHealth/Index', [
            'statusCounts' => [
                'healthy' => $statusCounts['healthy'] ?? 0,
                'at_risk' => $statusCounts['at_risk'] ?? 0,
                'inactive' => $statusCounts['inactive'] ?? 0,
                'total' => $latestScores->count(),
            ],
            'followUpsDue' => $followUpsDue,
            'businesses' => $allBusinesses,
            'filters' => [
                'filter' => $filter,
                'search' => $search,
            ],
        ]);
    }

    public function show(Business $business)
    {
        $business->load('owner');

        $healthHistory = BusinessHealthScore::where('business_id', $business->id)
            ->where('date', '>=', today()->subDays(30))
            ->orderBy('date')
            ->get()
            ->map(fn ($s) => [
                'date' => $s->date->toDateString(),
                'score' => $s->health_score,
                'status' => $s->health_status,
            ]);

        $latestScore = BusinessHealthScore::where('business_id', $business->id)
            ->orderByDesc('date')
            ->first();

        $followUps = BusinessFollowUp::where('business_id', $business->id)
            ->orderByDesc('due_date')
            ->get()
            ->map(fn ($fu) => [
                'id' => $fu->id,
                'type' => $fu->follow_up_type,
                'type_label' => $fu->getTypeLabel(),
                'due_date' => $fu->due_date->toDateString(),
                'status' => $fu->status,
                'completed_at' => $fu->completed_at?->toDateString(),
                'notes' => $fu->notes,
                'next_action' => $fu->next_action,
                'next_action_date' => $fu->next_action_date?->toDateString(),
            ]);

        $recentScans = Scan::where('business_id', $business->id)
            ->orderByDesc('scanned_at')
            ->limit(20)
            ->get(['id', 'scanned_at', 'scan_type']);

        $stats = [
            'total_scans' => $latestScore?->total_scans ?? $business->scans()->count(),
            'scans_this_week' => $latestScore?->scans_this_week ?? 0,
            'active_promotions' => $latestScore?->active_promotions ?? $business->promotions()->where('is_active', true)->count(),
            'qr_codes' => $latestScore?->qr_codes_count ?? $business->qrCodes()->count(),
            'customers' => $latestScore?->customers_count ?? DB::table('business_customers')->where('business_id', $business->id)->count(),
            'last_login' => $business->owner?->last_login_at?->toDateString(),
            'health_score' => $latestScore?->health_score ?? 0,
            'health_status' => $latestScore?->health_status ?? 'unknown',
        ];

        return Inertia::render('Admin/BusinessHealth/Show', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'owner_name' => $business->owner?->name,
                'owner_email' => $business->owner?->email,
                'phone' => $business->phone,
                'created_at' => $business->created_at->toDateString(),
                'days_since_signup' => $business->created_at->diffInDays(now()),
                'subscription_tier' => $business->subscription_tier,
            ],
            'stats' => $stats,
            'healthHistory' => $healthHistory,
            'followUps' => $followUps,
            'recentScans' => $recentScans,
        ]);
    }

    public function logFollowUp(Request $request)
    {
        $validated = $request->validate([
            'follow_up_id' => 'nullable|exists:business_follow_ups,id',
            'business_id' => 'required|exists:businesses,id',
            'notes' => 'nullable|string|max:2000',
            'next_action' => 'nullable|string|max:255',
            'next_action_date' => 'nullable|date',
            'create_custom' => 'nullable|boolean',
        ]);

        if ($validated['follow_up_id']) {
            $followUp = BusinessFollowUp::findOrFail($validated['follow_up_id']);
            $followUp->update([
                'status' => 'completed',
                'completed_at' => now(),
                'notes' => $validated['notes'] ?? $followUp->notes,
                'next_action' => $validated['next_action'],
                'next_action_date' => $validated['next_action_date'] ?? null,
            ]);
        }

        if (!empty($validated['next_action']) && !empty($validated['next_action_date'])) {
            BusinessFollowUp::create([
                'business_id' => $validated['business_id'],
                'follow_up_type' => 'custom',
                'due_date' => $validated['next_action_date'],
                'status' => 'pending',
                'notes' => $validated['next_action'],
            ]);
        }

        if (!empty($validated['create_custom']) && !$validated['follow_up_id'] && empty($validated['next_action_date'])) {
            BusinessFollowUp::create([
                'business_id' => $validated['business_id'],
                'follow_up_type' => 'custom',
                'due_date' => today(),
                'status' => 'completed',
                'completed_at' => now(),
                'notes' => $validated['notes'],
            ]);
        }

        return back()->with('success', 'Follow-up logged successfully.');
    }

    public function snoozeFollowUp(BusinessFollowUp $followUp, Request $request)
    {
        $days = (int) $request->get('days', 3);
        $days = max(1, min($days, 30));

        $followUp->update([
            'due_date' => today()->addDays($days),
            'status' => 'pending',
        ]);

        return back()->with('success', "Follow-up snoozed for {$days} days.");
    }
}

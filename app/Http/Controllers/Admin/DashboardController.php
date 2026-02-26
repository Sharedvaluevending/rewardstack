<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use App\Models\Scan;
use App\Models\GamePlay;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_businesses' => Business::count(),
            'active_businesses' => Business::where('is_active', true)->count(),
            'total_users' => User::count(),
            'new_users_today' => User::whereDate('created_at', today())->count(),
            'total_scans' => Scan::count(),
            'scans_today' => Scan::whereDate('scanned_at', today())->count(),
            'monthly_revenue' => 0, // Would integrate with Stripe
            'storage_used' => 0,
        ];

        $recentBusinesses = Business::with('owner:id,name,email')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentBusinesses' => $recentBusinesses,
            'recentScans' => [],
        ]);
    }
}


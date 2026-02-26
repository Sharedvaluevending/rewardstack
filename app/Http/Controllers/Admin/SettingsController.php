<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'system' => [
                'app_env' => config('app.env'),
                'app_url' => config('app.url'),
                'app_version' => config('app.version'),
                'cache' => config('cache.default'),
                'queue' => config('queue.default'),
                'session' => config('session.driver'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Placeholder: we don't persist system settings yet.
        // Keep route stable so the Admin nav doesn't break.
        return back()->with('status', 'Admin settings are not configurable yet.');
    }

    public function subscriptions(Request $request): Response
    {
        $plans = SubscriptionPlan::query()
            ->orderBy('sort_order')
            ->orderBy('monthly_price')
            ->get([
                'id',
                'name',
                'slug',
                'monthly_price',
                'yearly_price',
                'is_active',
                'is_featured',
                'sort_order',
                'stripe_monthly_price_id',
                'stripe_yearly_price_id',
            ]);

        return Inertia::render('Admin/Settings/Subscriptions', [
            'plans' => $plans,
        ]);
    }
}

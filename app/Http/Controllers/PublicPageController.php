<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class PublicPageController
{
    public function home(): Response
    {
        return Inertia::render('Welcome');
    }

    public function features(): Response
    {
        return Inertia::render('Public/Features');
    }

    public function pricing(): Response
    {
        // Public pricing should reflect the canonical DB plans (not hardcoded frontend arrays).
        $plans = Cache::remember('public:pricing:plans:v1', now()->addMinutes(10), function () {
            return SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get([
                    'id',
                    'name',
                    'slug',
                    'description',
                    'monthly_price',
                    'yearly_price',
                    'features',
                    'is_featured',
                ]);
        });

        return Inertia::render('Public/Pricing', [
            'plans' => $plans,
        ]);
    }

    public function demo(): Response
    {
        return Inertia::render('Public/Demo');
    }

    public function privacy(): Response
    {
        return Inertia::render('Public/Privacy');
    }

    public function terms(): Response
    {
        return Inertia::render('Public/Terms');
    }
}

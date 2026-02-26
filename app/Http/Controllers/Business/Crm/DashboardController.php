<?php

namespace App\Http\Controllers\Business\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmCampaign;
use App\Models\CrmSegment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->user()->business;

        $subscriberCount = \App\Models\BusinessCustomerSubscription::where('business_id', $business->id)
            ->whereNotNull('subscribed_at')
            ->whereNull('unsubscribed_at')
            ->count();

        $recentCampaigns = CrmCampaign::where('business_id', $business->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $segmentsCount = CrmSegment::where('business_id', $business->id)->count();

        return Inertia::render('Business/CRM/Index', [
            'stats' => [
                'subscribers' => $subscriberCount,
                'segments' => $segmentsCount,
                'campaigns' => (int) CrmCampaign::where('business_id', $business->id)->count(),
            ],
            'recentCampaigns' => $recentCampaigns->map(fn (CrmCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'subject' => $c->subject,
                'status' => $c->status,
                'created_at' => $c->created_at?->format('M d, Y'),
                'scheduled_at' => $c->scheduled_at?->format('M d, Y H:i'),
                'sent_total' => (int) $c->sent_total,
                'delivered_total' => (int) $c->delivered_total,
                'open_total' => (int) $c->open_total,
                'click_total' => (int) $c->click_total,
            ]),
        ]);
    }
}


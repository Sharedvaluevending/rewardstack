<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessCustomerSubscription;
use App\Services\BusinessCustomerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BusinessSubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $subs = BusinessCustomerSubscription::query()
            ->where('user_id', $user->id)
            ->with('business:id,name,slug,logo_path,primary_color,secondary_color,type,city,state')
            ->orderByRaw('CASE WHEN unsubscribed_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('subscribed_at')
            ->get();

        return Inertia::render('Portal/Subscriptions', [
            'subscriptions' => $subs->map(function ($s) {
                $b = $s->business;
                return [
                    'id' => $s->id,
                    'business' => $b ? [
                        'id' => $b->id,
                        'name' => $b->name,
                        'slug' => $b->slug,
                        'logo_url' => $b->logo_url,
                        'type' => $b->type,
                        'city' => $b->city,
                        'state' => $b->state,
                        'primary_color' => $b->primary_color,
                        'secondary_color' => $b->secondary_color,
                        'public_href' => url('/b/' . $b->slug),
                    ] : null,
                    'subscribed_at' => $s->subscribed_at?->format('M d, Y'),
                    'unsubscribed_at' => $s->unsubscribed_at?->format('M d, Y'),
                    'is_subscribed' => $s->subscribed_at && !$s->unsubscribed_at,
                ];
            }),
        ]);
    }

    public function subscribe(Request $request, Business $business, BusinessCustomerService $service)
    {
        $user = $request->user();

        $service->subscribe(
            businessId: (int) $business->id,
            userId: (int) $user->id,
            source: (string) $request->input('source', 'portal'),
            ip: $request->ip(),
            ua: $request->userAgent()
        );

        return back()->with('success', 'Subscribed! You’ll now receive emails from this business.');
    }

    public function unsubscribe(Request $request, Business $business, BusinessCustomerService $service)
    {
        $user = $request->user();

        $service->unsubscribe(
            businessId: (int) $business->id,
            userId: (int) $user->id,
            source: (string) $request->input('source', 'portal')
        );

        return back()->with('success', 'Unsubscribed.');
    }
}


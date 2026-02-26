<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmMessageEvent;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CrmController extends Controller
{
    public function index(Request $request)
    {
        $since = now()->subDay();
        $baseQuery = fn () => CrmMessageEvent::query()->whereRaw('COALESCE(event_at, created_at) >= ?', [$since]);

        $events24h = $baseQuery()->count();
        $lastEvent = CrmMessageEvent::query()->orderByRaw('COALESCE(event_at, created_at) DESC')->first();

        $bounces24h = $baseQuery()->whereIn('event', ['bounce', 'blocked', 'dropped'])->count();
        $spam24h = $baseQuery()->where('event', 'spamreport')->count();
        $unsubs24h = $baseQuery()->whereIn('event', ['unsubscribe', 'group_unsubscribe'])->count();

        $webhookLast = WebhookEvent::where('provider', WebhookEvent::PROVIDER_SENDGRID)
            ->orderByDesc('processed_at')
            ->first();

        $lastEventAt = $lastEvent ? ($lastEvent->event_at ?? $lastEvent->created_at)?->toIso8601String() : null;

        return Inertia::render('Admin/CRM/Index', [
            'stats' => [
                'events_24h' => $events24h,
                'bounces_24h' => $bounces24h,
                'spam_24h' => $spam24h,
                'unsubs_24h' => $unsubs24h,
                'last_event_at' => $lastEventAt,
                'last_webhook_at' => $webhookLast?->processed_at?->toIso8601String(),
            ],
        ]);
    }
}


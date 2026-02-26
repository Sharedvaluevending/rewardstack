<?php

namespace App\Http\Controllers\Business\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmAutomation;
use App\Services\CrmAutomationRunner;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AutomationController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->user()->business;

        // Create sensible defaults on first visit (safe, can be disabled).
        // Uses firstOrCreate per trigger to prevent race-condition duplicates.
        if (!CrmAutomation::where('business_id', $business->id)->exists()) {
            CrmAutomation::firstOrCreate(
                ['business_id' => $business->id, 'trigger' => 'winback'],
                [
                    'name' => 'Winback (30 days)',
                    'config' => [
                        'days' => 30,
                        'cooldown_days' => 14,
                        'exclude_redeemed_days' => 7,
                    ],
                    'is_active' => true,
                ]
            );
            CrmAutomation::firstOrCreate(
                ['business_id' => $business->id, 'trigger' => 'punch_card_nudge'],
                [
                    'name' => 'Punch card nudge',
                    'config' => [
                        'inactive_days' => 7,
                        'cooldown_days' => 7,
                    ],
                    'is_active' => true,
                ]
            );
            CrmAutomation::firstOrCreate(
                ['business_id' => $business->id, 'trigger' => 'promo_expiring'],
                [
                    'name' => 'Saved offer expiring',
                    'config' => [
                        'days' => 3,
                        'cooldown_days' => 3,
                    ],
                    'is_active' => true,
                ]
            );
        }

        $automations = CrmAutomation::where('business_id', $business->id)
            ->orderBy('created_at')
            ->get();

        return Inertia::render('Business/CRM/Automations', [
            'automations' => $automations->map(fn (CrmAutomation $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'trigger' => $a->trigger,
                'is_active' => (bool) $a->is_active,
                'config' => $a->config,
            ]),
            'sendGridConfigured' => (string) config('services.sendgrid.api_key', '') !== '',
        ]);
    }

    public function toggle(Request $request, CrmAutomation $automation)
    {
        $business = $request->user()->business;
        abort_unless($automation->business_id === $business->id, 403);

        $automation->is_active = !$automation->is_active;
        $automation->save();

        return back()->with('success', $automation->is_active ? 'Automation enabled.' : 'Automation disabled.');
    }

    public function runNow(Request $request, CrmAutomationRunner $runner)
    {
        $business = $request->user()->business;

        $result = $runner->runForBusiness($business);

        $sent = 0;
        foreach (($result['automations'] ?? []) as $r) {
            $sent += (int) ($r['sent'] ?? 0);
        }

        return back()->with('success', "Automations ran. Queued {$sent} email(s).");
    }
}


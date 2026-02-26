<?php

namespace App\Http\Controllers\Business\Crm;

use App\Http\Controllers\Controller;
use App\Jobs\SendCrmMessage;
use App\Models\CrmCampaign;
use App\Models\CrmMessage;
use App\Models\CrmSegment;
use App\Models\Promotion;
use App\Services\CrmAudienceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->user()->business;

        $campaigns = CrmCampaign::where('business_id', $business->id)
            ->with('promotion:id,name')
            ->orderByDesc('created_at')
            ->paginate(15);

        return Inertia::render('Business/CRM/Campaigns/Index', [
            'campaigns' => $campaigns->through(fn (CrmCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'subject' => $c->subject,
                'status' => $c->status,
                'is_automation' => (bool) $c->is_automation,
                'promotion' => $c->promotion ? [
                    'id' => $c->promotion->id,
                    'name' => $c->promotion->name,
                ] : null,
                'created_at' => $c->created_at?->format('M d, Y'),
                'recipients_total' => (int) $c->recipients_total,
                'sent_total' => (int) $c->sent_total,
                'delivered_total' => (int) $c->delivered_total,
                'open_total' => (int) $c->open_total,
                'click_total' => (int) $c->click_total,
            ]),
        ]);
    }

    public function create(Request $request)
    {
        $business = $request->user()->business;

        $promotions = Promotion::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'ends_at', 'discount_type']);

        $segments = CrmSegment::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'definition']);

        $aiTitle = $request->query('ai_title');
        $aiSubject = $request->query('ai_subject');
        $aiBody = $request->query('ai_body');
        $aiPromoId = $request->query('ai_promo_id');

        return Inertia::render('Business/CRM/Campaigns/Create', [
            'segments' => $segments,
            'defaultFromName' => config('app.name', 'Revenue QR'),
            'defaultFromEmail' => config('mail.from.address'),
            'promotions' => $promotions,
            'aiPrefill' => [
                'title' => $aiTitle,
                'subject' => $aiSubject,
                'body' => $aiBody,
                'promo_id' => $aiPromoId ? (int) $aiPromoId : null,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $business = $request->user()->business;

        $validated = $request->validate([
            'name' => 'required|string|max:140',
            'subject' => 'required|string|max:200',
            'preheader' => 'nullable|string|max:200',
            'segment_id' => 'nullable|integer|exists:crm_segments,id',
            'content_html' => 'required|string|max:200000',
            'content_text' => 'nullable|string|max:200000',
            'promotion_id' => 'nullable|integer|exists:promotions,id',
        ]);

        if (!empty($validated['segment_id'])) {
            $segment = CrmSegment::where('id', $validated['segment_id'])
                ->where('business_id', $business->id)
                ->firstOrFail();
        }

        if (!empty($validated['promotion_id'])) {
            Promotion::where('id', $validated['promotion_id'])
                ->where('business_id', $business->id)
                ->firstOrFail();
        }

        // Sanitize HTML content: strip script tags, event handlers, and dangerous elements
        $sanitizedHtml = $validated['content_html'];
        // Remove <script> tags and their contents
        $sanitizedHtml = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $sanitizedHtml);
        // Remove on* event handlers (onclick, onerror, onload, etc.)
        $sanitizedHtml = preg_replace('#\s+on\w+\s*=\s*["\'][^"\']*["\']#is', '', $sanitizedHtml);
        $sanitizedHtml = preg_replace('#\s+on\w+\s*=\s*\S+#is', '', $sanitizedHtml);
        // Remove javascript: protocol in href/src attributes
        $sanitizedHtml = preg_replace('#(href|src)\s*=\s*["\']?\s*javascript\s*:#is', '$1="removed:', $sanitizedHtml);
        // Remove <iframe>, <object>, <embed>, <form> tags
        $sanitizedHtml = preg_replace('#<(iframe|object|embed|form)(.*?)>(.*?)</\1>#is', '', $sanitizedHtml);
        $sanitizedHtml = preg_replace('#<(iframe|object|embed|form)(.*?)\s*/?\s*>#is', '', $sanitizedHtml);

        $campaign = CrmCampaign::create([
            'business_id' => $business->id,
            'segment_id' => $validated['segment_id'] ?? null,
            'promotion_id' => $validated['promotion_id'] ?? null,
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'preheader' => $validated['preheader'] ?? null,
            'content_html' => $sanitizedHtml,
            'content_text' => $validated['content_text'] ?? null,
            'status' => CrmCampaign::STATUS_DRAFT,
            // platform-from by default
            'from_name' => config('app.name', 'Revenue QR'),
            'from_email' => config('mail.from.address'),
        ]);

        return redirect()->route('business.crm.campaigns.show', $campaign)->with('success', 'Campaign created.');
    }

    public function edit(Request $request, CrmCampaign $campaign)
    {
        $business = $request->user()->business;
        abort_unless($campaign->business_id === $business->id, 403);

        if ($campaign->status !== CrmCampaign::STATUS_DRAFT) {
            return redirect()->route('business.crm.campaigns.show', $campaign)
                ->withErrors(['status' => 'Only draft campaigns can be edited.']);
        }

        $promotions = Promotion::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'ends_at', 'discount_type']);

        $segments = CrmSegment::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'definition']);

        return Inertia::render('Business/CRM/Campaigns/Edit', [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'subject' => $campaign->subject,
                'preheader' => $campaign->preheader,
                'segment_id' => $campaign->segment_id,
                'promotion_id' => $campaign->promotion_id,
                'content_html' => $campaign->content_html,
                'content_text' => $campaign->content_text,
            ],
            'segments' => $segments,
            'promotions' => $promotions,
        ]);
    }

    public function update(Request $request, CrmCampaign $campaign)
    {
        $business = $request->user()->business;
        abort_unless($campaign->business_id === $business->id, 403);

        if ($campaign->status !== CrmCampaign::STATUS_DRAFT) {
            return back()->withErrors(['status' => 'Only draft campaigns can be edited.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:140',
            'subject' => 'required|string|max:200',
            'preheader' => 'nullable|string|max:200',
            'segment_id' => 'nullable|integer|exists:crm_segments,id',
            'content_html' => 'required|string|max:200000',
            'content_text' => 'nullable|string|max:200000',
            'promotion_id' => 'nullable|integer|exists:promotions,id',
        ]);

        if (!empty($validated['segment_id'])) {
            CrmSegment::where('id', $validated['segment_id'])
                ->where('business_id', $business->id)
                ->firstOrFail();
        }

        if (!empty($validated['promotion_id'])) {
            Promotion::where('id', $validated['promotion_id'])
                ->where('business_id', $business->id)
                ->firstOrFail();
        }

        $sanitizedHtml = $validated['content_html'];
        $sanitizedHtml = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $sanitizedHtml);
        $sanitizedHtml = preg_replace('#\s+on\w+\s*=\s*["\'][^"\']*["\']#is', '', $sanitizedHtml);
        $sanitizedHtml = preg_replace('#\s+on\w+\s*=\s*\S+#is', '', $sanitizedHtml);
        $sanitizedHtml = preg_replace('#(href|src)\s*=\s*["\']?\s*javascript\s*:#is', '$1="removed:', $sanitizedHtml);
        $sanitizedHtml = preg_replace('#<(iframe|object|embed|form)(.*?)>(.*?)</\1>#is', '', $sanitizedHtml);
        $sanitizedHtml = preg_replace('#<(iframe|object|embed|form)(.*?)\s*/?\s*>#is', '', $sanitizedHtml);

        $campaign->update([
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'preheader' => $validated['preheader'] ?? null,
            'segment_id' => $validated['segment_id'] ?? null,
            'promotion_id' => $validated['promotion_id'] ?? null,
            'content_html' => $sanitizedHtml,
            'content_text' => $validated['content_text'] ?? null,
        ]);

        return redirect()->route('business.crm.campaigns.show', $campaign)->with('success', 'Campaign updated.');
    }

    public function show(Request $request, CrmCampaign $campaign)
    {
        $business = $request->user()->business;
        abort_unless($campaign->business_id === $business->id, 403);

        $campaign->load('segment', 'promotion');

        $recentMessages = CrmMessage::where('campaign_id', $campaign->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return Inertia::render('Business/CRM/Campaigns/Show', [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'subject' => $campaign->subject,
                'preheader' => $campaign->preheader,
                'status' => $campaign->status,
                'is_automation' => (bool) $campaign->is_automation,
                'promotion' => $campaign->promotion ? [
                    'id' => $campaign->promotion->id,
                    'name' => $campaign->promotion->name,
                ] : null,
                'segment' => $campaign->segment ? [
                    'id' => $campaign->segment->id,
                    'name' => $campaign->segment->name,
                ] : null,
                'created_at' => $campaign->created_at?->format('M d, Y'),
                'recipients_total' => (int) $campaign->recipients_total,
                'sent_total' => (int) $campaign->sent_total,
                'delivered_total' => (int) $campaign->delivered_total,
                'open_total' => (int) $campaign->open_total,
                'click_total' => (int) $campaign->click_total,
                'bounce_total' => (int) $campaign->bounce_total,
                'unsubscribe_total' => (int) $campaign->unsubscribe_total,
            ],
            'recentMessages' => $recentMessages->map(fn (CrmMessage $m) => [
                'id' => $m->id,
                'email' => $m->email,
                'status' => $m->status,
                'sent_at' => $m->sent_at?->format('M d H:i'),
                'last_event_at' => $m->last_event_at?->format('M d H:i'),
            ]),
        ]);
    }

    public function queue(Request $request, CrmCampaign $campaign, CrmAudienceService $audience)
    {
        $business = $request->user()->business;
        abort_unless($campaign->business_id === $business->id, 403);

        if ((string) config('services.sendgrid.api_key', '') === '') {
            return back()->withErrors(['status' => 'SendGrid is not configured. Set SENDGRID_API_KEY to send campaigns.']);
        }

        if (!in_array($campaign->status, [CrmCampaign::STATUS_DRAFT, CrmCampaign::STATUS_SCHEDULED], true)) {
            return back()->withErrors(['status' => 'Campaign cannot be sent in its current state.']);
        }

        // Atomic status transition to prevent double-sends from concurrent requests.
        // Only the first request to update from draft/scheduled -> sending will succeed.
        $updated = CrmCampaign::where('id', $campaign->id)
            ->whereIn('status', [CrmCampaign::STATUS_DRAFT, CrmCampaign::STATUS_SCHEDULED])
            ->update([
                'status' => CrmCampaign::STATUS_SENDING,
                'started_at' => $campaign->started_at ?: now(),
            ]);

        if ($updated === 0) {
            return back()->withErrors(['status' => 'Campaign is already being sent.']);
        }

        $campaign->refresh();
        $campaign->load('segment', 'business', 'promotion');
        $q = $audience->subscribedUsersQuery($business);
        $q = $audience->applySegment($business, $q, $campaign->segment);
        $q = $audience->excludeSuppressed($business, $q);

        $total = 0;

        $q->orderBy('users.id')->chunkById(500, function ($users) use ($campaign, $business, &$total) {
            DB::transaction(function () use ($users, $campaign, $business, &$total) {
                foreach ($users as $u) {
                    $total++;
                    $customArgs = [];
                    if ($campaign->promotion) {
                        $customArgs['promotion_id'] = (string) $campaign->promotion->id;
                        $customArgs['promotion_name'] = (string) $campaign->promotion->name;
                    }
                    $msg = CrmMessage::firstOrCreate(
                        [
                            'campaign_id' => $campaign->id,
                            'user_id' => $u->id,
                        ],
                        [
                            'business_id' => $business->id,
                            'email' => $u->email,
                            'status' => 'queued',
                            'custom_args' => $customArgs ?: null,
                            'promo_link_token' => $campaign->promotion_id ? Str::random(48) : null,
                        ]
                    );

                    // If it already existed but wasn't sent yet, reset to queued.
                    if (in_array($msg->status, ['failed', 'queued'], true)) {
                        $msg->update([
                            'status' => 'queued',
                            'last_error' => null,
                            'custom_args' => $customArgs ?: null,
                        ]);
                    }

                    dispatch(new SendCrmMessage($msg->id));
                }
            });
        }, 'users.id', 'id');

        $campaign->update([
            'recipients_total' => $total,
            'status' => CrmCampaign::STATUS_SENT,
            'completed_at' => now(),
        ]);

        return back()->with('success', "Queued {$total} emails for sending.");
    }

    public function destroy(Request $request, CrmCampaign $campaign)
    {
        $business = $request->user()->business;
        abort_unless($campaign->business_id === $business->id, 403);

        if ($campaign->is_automation) {
            return back()->withErrors(['status' => 'Automated campaigns cannot be deleted.']);
        }

        $campaign->delete();

        return redirect()->route('business.crm.campaigns')->with('success', 'Campaign deleted.');
    }
}


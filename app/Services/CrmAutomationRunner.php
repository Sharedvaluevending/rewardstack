<?php

namespace App\Services;

use App\Jobs\SendCrmMessage;
use App\Models\Business;
use App\Models\CrmAutomation;
use App\Models\CrmAutomationSend;
use App\Models\CrmCampaign;
use App\Models\CrmMessage;
use App\Models\PunchCard;
use App\Models\SavedQRCode;
use Illuminate\Support\Facades\DB;

class CrmAutomationRunner
{
    public function runForBusiness(Business $business): array
    {
        if ((string) config('services.sendgrid.api_key', '') === '') {
            return [
                'business_id' => $business->id,
                'automations' => [],
                'note' => 'SendGrid not configured',
            ];
        }

        $automations = CrmAutomation::where('business_id', $business->id)
            ->where('is_active', true)
            ->get();

        $summary = [
            'business_id' => $business->id,
            'automations' => [],
        ];

        foreach ($automations as $a) {
            $summary['automations'][] = $this->runAutomation($business, $a);
        }

        return $summary;
    }

    public function runAutomation(Business $business, CrmAutomation $automation): array
    {
        $trigger = (string) $automation->trigger;
        $cfg = is_array($automation->config) ? $automation->config : [];

        return match ($trigger) {
            'winback' => $this->runWinback($business, $automation, $cfg),
            'punch_card_nudge' => $this->runPunchCardNudge($business, $automation, $cfg),
            'promo_expiring' => $this->runPromoExpiring($business, $automation, $cfg),
            default => ['id' => $automation->id, 'trigger' => $trigger, 'sent' => 0, 'skipped' => 0, 'note' => 'unknown trigger'],
        };
    }

    protected function runWinback(Business $business, CrmAutomation $automation, array $cfg): array
    {
        $days = max(7, (int) ($cfg['days'] ?? 30));
        $cooldownDays = max(1, (int) ($cfg['cooldown_days'] ?? 14));
        $cutoff = now()->subDays($days);

        $aud = app(CrmAudienceService::class);
        $q = $aud->subscribedUsersQuery($business);
        $q = $aud->excludeSuppressed($business, $q);

        // Join business_customers for last_seen
        $q->leftJoin('business_customers as bc', function ($join) use ($business) {
            $join->on('bc.user_id', '=', 'users.id')->where('bc.business_id', '=', $business->id);
        })->select('users.*', 'bc.last_seen_at', 'bc.last_redeemed_at');

        $q->whereNotNull('bc.last_seen_at')->where('bc.last_seen_at', '<=', $cutoff);

        // Optional: exclude those who redeemed recently
        if (!empty($cfg['exclude_redeemed_days']) && is_numeric($cfg['exclude_redeemed_days'])) {
            $exclude = now()->subDays((int) $cfg['exclude_redeemed_days']);
            $q->where(function ($qq) use ($exclude) {
                $qq->whereNull('bc.last_redeemed_at')->orWhere('bc.last_redeemed_at', '<=', $exclude);
            });
        }

        $campaign = $this->createAutomationCampaign($business, $automation, [
            'name' => 'Winback (Auto)',
            'subject' => $cfg['subject'] ?? "We miss you at {$business->name}",
            'html' => $cfg['content_html'] ?? $this->defaultWinbackHtml(),
            'text' => $cfg['content_text'] ?? null,
        ]);

        $sent = 0;
        $skipped = 0;

        $q->orderBy('users.id')->chunkById(500, function ($users) use ($business, $automation, $campaign, $cooldownDays, &$sent, &$skipped) {
            foreach ($users as $u) {
                $dedupeKey = 'default';
                if ($this->shouldSkip($business->id, $automation->id, $u->id, $dedupeKey, $cooldownDays)) {
                    $skipped++;
                    continue;
                }
                $msg = $this->queueMessage($business, $campaign, $u->id, $u->email, [
                    'last_seen_at' => optional($u->last_seen_at)->toDateString(),
                ]);
                $this->markSent($business->id, $automation->id, $u->id, $dedupeKey);
                dispatch(new SendCrmMessage($msg->id));
                $sent++;
            }
        }, 'users.id', 'id');

        $this->finalizeAutomationCampaign($campaign, $sent);

        return ['id' => $automation->id, 'trigger' => 'winback', 'sent' => $sent, 'skipped' => $skipped];
    }

    protected function runPunchCardNudge(Business $business, CrmAutomation $automation, array $cfg): array
    {
        $inactiveDays = max(3, (int) ($cfg['inactive_days'] ?? 7));
        $cooldownDays = max(1, (int) ($cfg['cooldown_days'] ?? 7));
        $cutoff = now()->subDays($inactiveDays);

        $campaign = $this->createAutomationCampaign($business, $automation, [
            'name' => 'Punch Card Nudge (Auto)',
            'subject' => $cfg['subject'] ?? "You’re close to a reward at {$business->name}",
            'html' => $cfg['content_html'] ?? $this->defaultPunchNudgeHtml(),
            'text' => $cfg['content_text'] ?? null,
        ]);

        // Find subscribed users with in-progress punch cards for this business.
        $subUserIds = DB::table('business_customer_subscriptions')
            ->where('business_id', $business->id)
            ->whereNotNull('subscribed_at')
            ->whereNull('unsubscribed_at')
            ->pluck('user_id');

        $sent = 0;
        $skipped = 0;

        // Join punch_cards -> promotions to get punches_required, business_id.
        PunchCard::query()
            ->whereIn('punch_cards.user_id', $subUserIds)
            ->whereNotNull('punch_cards.user_id')
            ->where('punch_cards.punches', '>', 0)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('punch_cards.last_punch_at')->orWhere('punch_cards.last_punch_at', '<=', $cutoff);
            })
            ->join('promotions as p', 'p.id', '=', 'punch_cards.promotion_id')
            ->where('p.business_id', $business->id)
            ->where('p.discount_type', 'punch_card')
            ->select('punch_cards.*', 'p.punches_required', 'p.name as promo_name')
            ->chunkById(500, function ($cards) use ($business, $automation, $campaign, $cooldownDays, &$sent, &$skipped) {
                foreach ($cards as $card) {
                    $required = (int) ($card->punches_required ?? 0);
                    if ($required <= 0) {
                        continue;
                    }
                    $current = (int) ($card->punches ?? 0);
                    if ($current >= $required) {
                        continue;
                    }

                    $userId = (int) $card->user_id;
                    $dedupeKey = 'promo:' . (int) $card->promotion_id;
                    if ($this->shouldSkip($business->id, $automation->id, $userId, $dedupeKey, $cooldownDays)) {
                        $skipped++;
                        continue;
                    }

                    $punchesLeft = max(0, $required - $current);

                    $u = DB::table('users')->where('id', $userId)->first(['id', 'email']);
                    if (!$u || empty($u->email)) {
                        continue;
                    }
                    $isSuppressed = DB::table('email_unsubscribes')
                        ->where('email', (string) $u->email)
                        ->where(function ($qq) use ($business) {
                            $qq->whereNull('business_id')->orWhere('business_id', $business->id);
                        })
                        ->exists();
                    if ($isSuppressed) {
                        $skipped++;
                        continue;
                    }

                    $msg = $this->queueMessage($business, $campaign, $userId, (string) $u->email, [
                        'promo_name' => (string) ($card->promo_name ?? 'Punch Card'),
                        'punches_left' => (string) $punchesLeft,
                    ]);

                    $this->markSent($business->id, $automation->id, $userId, $dedupeKey);
                    dispatch(new SendCrmMessage($msg->id));
                    $sent++;
                }
            }, 'punch_cards.id', 'id');

        $this->finalizeAutomationCampaign($campaign, $sent);

        return ['id' => $automation->id, 'trigger' => 'punch_card_nudge', 'sent' => $sent, 'skipped' => $skipped];
    }

    protected function runPromoExpiring(Business $business, CrmAutomation $automation, array $cfg): array
    {
        $days = max(1, (int) ($cfg['days'] ?? 3));
        $cooldownDays = max(1, (int) ($cfg['cooldown_days'] ?? 3));
        $start = now();
        $end = now()->addDays($days);

        $campaign = $this->createAutomationCampaign($business, $automation, [
            'name' => 'Offer Expiring (Auto)',
            'subject' => $cfg['subject'] ?? "An offer you saved is expiring soon",
            'html' => $cfg['content_html'] ?? $this->defaultExpiringHtml(),
            'text' => $cfg['content_text'] ?? null,
        ]);

        // Users who saved a QR code tied to a promo ending soon.
        $q = SavedQRCode::query()
            ->join('qr_codes as q', 'q.id', '=', 'saved_qr_codes.qr_code_id')
            ->join('promotions as p', 'p.id', '=', 'q.promotion_id')
            ->where('q.business_id', $business->id)
            ->whereNotNull('p.ends_at')
            ->whereBetween('p.ends_at', [$start, $end])
            ->join('users as u', 'u.id', '=', 'saved_qr_codes.user_id')
            ->select('saved_qr_codes.user_id', 'u.email', 'p.id as promo_id', 'p.name as promo_name', 'p.ends_at');

        // Only to subscribed users
        $q->join('business_customer_subscriptions as bcs', function ($join) use ($business) {
            $join->on('bcs.user_id', '=', 'saved_qr_codes.user_id')
                ->where('bcs.business_id', '=', $business->id)
                ->whereNotNull('bcs.subscribed_at')
                ->whereNull('bcs.unsubscribed_at');
        });

        // Exclude suppressed emails (business scoped or global)
        $q->whereNotExists(function ($sub) use ($business) {
            $sub->select(DB::raw(1))
                ->from('email_unsubscribes as eu')
                ->whereColumn('eu.email', 'u.email')
                ->where(function ($qq) use ($business) {
                    $qq->whereNull('eu.business_id')->orWhere('eu.business_id', $business->id);
                });
        });

        $sent = 0;
        $skipped = 0;

        $q->orderBy('saved_qr_codes.user_id')->chunk(500, function ($rows) use ($business, $automation, $campaign, $cooldownDays, &$sent, &$skipped) {
            foreach ($rows as $r) {
                $userId = (int) $r->user_id;
                $promoId = (int) $r->promo_id;
                $dedupeKey = 'promo:' . $promoId;

                if ($this->shouldSkip($business->id, $automation->id, $userId, $dedupeKey, $cooldownDays)) {
                    $skipped++;
                    continue;
                }

                $email = (string) ($r->email ?? '');
                if ($email === '') {
                    continue;
                }

                $expiresAt = $r->ends_at ? \Illuminate\Support\Carbon::parse($r->ends_at) : null;
                $expiresInDays = $expiresAt ? max(0, now()->diffInDays($expiresAt, false)) : 0;

                $msg = $this->queueMessage($business, $campaign, $userId, $email, [
                    'promo_name' => (string) ($r->promo_name ?? 'Offer'),
                    'expires_in_days' => (string) $expiresInDays,
                ]);

                $this->markSent($business->id, $automation->id, $userId, $dedupeKey);
                dispatch(new SendCrmMessage($msg->id));
                $sent++;
            }
        });

        $this->finalizeAutomationCampaign($campaign, $sent);

        return ['id' => $automation->id, 'trigger' => 'promo_expiring', 'sent' => $sent, 'skipped' => $skipped];
    }

    protected function createAutomationCampaign(Business $business, CrmAutomation $automation, array $spec): CrmCampaign
    {
        // Reuse existing automation campaign of same type to avoid clutter
        $name = (string) ($spec['name'] ?? 'Automation');
        
        $campaign = CrmCampaign::where('business_id', $business->id)
            ->where('is_automation', true)
            ->where('name', $name)
            ->first();

        if ($campaign) {
            // Update subject/content in case config changed; set to sending for this run.
            $campaign->update([
                'subject' => (string) ($spec['subject'] ?? $campaign->subject),
                'content_html' => (string) ($spec['html'] ?? $campaign->content_html),
                'content_text' => $spec['text'] ?? $campaign->content_text,
                'status' => CrmCampaign::STATUS_SENDING,
                'started_at' => $campaign->started_at ?: now(),
                'updated_at' => now(),
            ]);
            
            return $campaign;
        }

        return CrmCampaign::create([
            'business_id' => $business->id,
            'segment_id' => null,
            'is_automation' => true,
            'name' => $name,
            'subject' => (string) ($spec['subject'] ?? 'Update'),
            'preheader' => null,
            'content_html' => (string) ($spec['html'] ?? ''),
            'content_text' => $spec['text'] ?? null,
            'status' => CrmCampaign::STATUS_SENDING,
            'started_at' => now(),
            'from_name' => config('app.name', 'Revenue QR'),
            'from_email' => config('mail.from.address'),
            'reply_to' => $business->email,
        ]);
    }

    /**
     * Mark an automation campaign as sent after processing, with the recipient count.
     */
    protected function finalizeAutomationCampaign(CrmCampaign $campaign, int $sent): void
    {
        $campaign->update([
            'recipients_total' => $campaign->recipients_total + $sent,
            'status' => CrmCampaign::STATUS_SENT,
            'completed_at' => now(),
        ]);
    }

    protected function queueMessage(Business $business, CrmCampaign $campaign, int $userId, string $email, array $customArgs = []): CrmMessage
    {
        // Use firstOrCreate to prevent duplicate messages if the automation runner
        // is retried after a crash (message created but markSent not yet called).
        $msg = CrmMessage::firstOrCreate(
            [
                'campaign_id' => $campaign->id,
                'user_id' => $userId,
            ],
            [
                'business_id' => $business->id,
                'email' => $email,
                'status' => 'queued',
                'custom_args' => $customArgs,
            ]
        );

        // If it already existed but failed, reset to queued for retry.
        if ($msg->wasRecentlyCreated === false && in_array($msg->status, ['failed', 'queued'], true)) {
            $msg->update([
                'status' => 'queued',
                'last_error' => null,
                'custom_args' => $customArgs,
            ]);
        }

        return $msg;
    }

    protected function shouldSkip(int $businessId, int $automationId, int $userId, string $dedupeKey, int $cooldownDays): bool
    {
        $row = CrmAutomationSend::where('business_id', $businessId)
            ->where('automation_id', $automationId)
            ->where('user_id', $userId)
            ->where('dedupe_key', $dedupeKey)
            ->first();

        if (!$row || !$row->last_sent_at) {
            return false;
        }

        return $row->last_sent_at->isAfter(now()->subDays($cooldownDays));
    }

    protected function markSent(int $businessId, int $automationId, int $userId, string $dedupeKey): void
    {
        $row = CrmAutomationSend::firstOrNew([
            'business_id' => $businessId,
            'automation_id' => $automationId,
            'user_id' => $userId,
            'dedupe_key' => $dedupeKey,
        ]);

        $row->last_sent_at = now();
        $row->send_count = (int) ($row->send_count ?? 0) + 1;
        $row->save();
    }

    protected function defaultWinbackHtml(): string
    {
        return <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;color:#ffffff;background:#0b1220;padding:24px;">
  <h2 style="margin:0 0 10px 0;">Hey {{first_name}}, we miss you 👋</h2>
  <p style="margin:0;color:rgba(255,255,255,0.75);">
    It’s been a little while since your last visit to {{business_name}}.
    Come back in and check out what’s new.
  </p>
</div>
HTML;
    }

    protected function defaultPunchNudgeHtml(): string
    {
        return <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;color:#ffffff;background:#0b1220;padding:24px;">
  <h2 style="margin:0 0 10px 0;">You’re close to a reward 🥳</h2>
  <p style="margin:0;color:rgba(255,255,255,0.75);">
    You have <strong>{{punches_left}}</strong> punches left on <strong>{{promo_name}}</strong> at {{business_name}}.
  </p>
  <p style="margin:12px 0 0 0;color:rgba(255,255,255,0.75);">
    Swing by and finish your card!
  </p>
</div>
HTML;
    }

    protected function defaultExpiringHtml(): string
    {
        return <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;color:#ffffff;background:#0b1220;padding:24px;">
  <h2 style="margin:0 0 10px 0;">An offer you saved is expiring ⏳</h2>
  <p style="margin:0;color:rgba(255,255,255,0.75);">
    <strong>{{promo_name}}</strong> at {{business_name}} expires in <strong>{{expires_in_days}}</strong> day(s).
  </p>
  <p style="margin:12px 0 0 0;color:rgba(255,255,255,0.75);">
    Come use it before it’s gone.
  </p>
</div>
HTML;
    }
}


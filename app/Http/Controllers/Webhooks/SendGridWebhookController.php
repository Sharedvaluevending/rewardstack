<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\CrmCampaign;
use App\Models\CrmMessage;
use App\Models\CrmMessageEvent;
use App\Models\EmailUnsubscribe;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SendGridWebhookController extends Controller
{
    /**
     * Signed Event Webhook handler for SendGrid.
     *
     * Receives an array of event objects.
     */
    public function handle(Request $request)
    {
        $events = $request->json()->all();
        if (!is_array($events)) {
            return response()->json(['ok' => false], 400);
        }

        // Insert individual events; dedupe by sg_event_id using crm_message_events unique constraint.
        foreach ($events as $evt) {
            if (!is_array($evt)) {
                continue;
            }

            $sgEventId = (string) ($evt['sg_event_id'] ?? '');
            $eventType = (string) ($evt['event'] ?? '');
            $email = (string) ($evt['email'] ?? '');

            if ($eventType === '' || $sgEventId === '') {
                continue;
            }

            // SendGrid custom args can show up either as:
            // - top-level fields in the event payload, OR
            // - nested under custom_args / unique_args (older patterns)
            $customArgs = [];
            if (isset($evt['custom_args']) && is_array($evt['custom_args'])) {
                $customArgs = $evt['custom_args'];
            } elseif (isset($evt['unique_args']) && is_array($evt['unique_args'])) {
                $customArgs = $evt['unique_args'];
            }

            $crmMessageId = (int) ($customArgs['crm_message_id'] ?? $evt['crm_message_id'] ?? 0);
            $campaignId = (int) ($customArgs['crm_campaign_id'] ?? $evt['crm_campaign_id'] ?? 0);
            $businessId = (int) ($customArgs['business_id'] ?? $evt['business_id'] ?? 0);
            $userId = (int) ($customArgs['user_id'] ?? $evt['user_id'] ?? 0);

            $eventAt = null;
            if (isset($evt['timestamp']) && is_numeric($evt['timestamp'])) {
                $eventAt = now()->setTimestamp((int) $evt['timestamp']);
            }

            DB::beginTransaction();
            try {
                // Generic webhook audit (dedupe by provider+event_id).
                // If this insert fails due to unique constraint, it's a duplicate: exit early.
                try {
                    WebhookEvent::create([
                        'provider' => WebhookEvent::PROVIDER_SENDGRID,
                        'event_id' => $sgEventId,
                        'type' => $eventType,
                        'processed_data' => [
                            'email' => $email,
                            'sg_message_id' => $evt['sg_message_id'] ?? null,
                            'custom_args' => $customArgs ?: [
                                'crm_message_id' => $evt['crm_message_id'] ?? null,
                                'crm_campaign_id' => $evt['crm_campaign_id'] ?? null,
                                'business_id' => $evt['business_id'] ?? null,
                                'user_id' => $evt['user_id'] ?? null,
                            ],
                        ],
                        'processed_at' => now(),
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Duplicate provider/event_id
                    DB::rollBack();
                    continue;
                }

                // Record CRM event (dedupe by sg_event_id).
                try {
                    CrmMessageEvent::create([
                        'business_id' => $businessId ?: null,
                        'campaign_id' => $campaignId ?: null,
                        'crm_message_id' => $crmMessageId ?: null,
                        'user_id' => $userId ?: null,
                        'email' => $email ?: null,
                        'event' => $eventType,
                        'event_at' => $eventAt,
                        'sg_event_id' => $sgEventId,
                        'sg_message_id' => (string) ($evt['sg_message_id'] ?? ''),
                        'url' => isset($evt['url']) ? (string) $evt['url'] : null,
                        'ip' => isset($evt['ip']) ? (string) $evt['ip'] : null,
                        'user_agent' => isset($evt['useragent']) ? (string) $evt['useragent'] : null,
                        'payload' => $evt,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Duplicate sg_event_id
                }

                // Update message + campaign rollups (best-effort).
                if ($crmMessageId > 0) {
                    $msg = CrmMessage::find($crmMessageId);
                    if ($msg) {
                        $msg->sendgrid_message_id = (string) ($evt['sg_message_id'] ?? $msg->sendgrid_message_id);
                        $msg->last_event_at = $eventAt ?? now();

                        // Normalize statuses
                        if (in_array($eventType, ['processed', 'deferred'], true)) {
                            // Do not downgrade status if we already recorded a later lifecycle event.
                            if (!in_array($msg->status, ['delivered', 'opened', 'clicked', 'bounced', 'spam', 'unsubscribed'], true)) {
                                $msg->status = 'sent';
                            }
                            $msg->sent_at = $msg->sent_at ?: now();
                        } elseif ($eventType === 'delivered') {
                            $msg->status = 'delivered';
                            $msg->delivered_at = $msg->delivered_at ?: ($eventAt ?? now());
                        } elseif ($eventType === 'open') {
                            if (!in_array($msg->status, ['clicked', 'bounced', 'spam', 'unsubscribed'], true)) {
                                $msg->status = 'opened';
                            }
                            $msg->opened_at = $msg->opened_at ?: ($eventAt ?? now());
                        } elseif ($eventType === 'click') {
                            $msg->status = 'clicked';
                            $msg->clicked_at = $msg->clicked_at ?: ($eventAt ?? now());
                        } elseif (in_array($eventType, ['bounce', 'blocked', 'dropped'], true)) {
                            $msg->status = 'bounced';
                            $msg->bounced_at = $msg->bounced_at ?: ($eventAt ?? now());
                        } elseif ($eventType === 'spamreport') {
                            $msg->status = 'spam';
                        } elseif (in_array($eventType, ['unsubscribe', 'group_unsubscribe'], true)) {
                            $msg->status = 'unsubscribed';
                            $msg->unsubscribed_at = $msg->unsubscribed_at ?: ($eventAt ?? now());
                        }

                        $msg->save();

                        // Suppression table (global + per-business)
                        if ($email !== '') {
                            if (in_array($eventType, ['unsubscribe', 'group_unsubscribe'], true)) {
                                EmailUnsubscribe::firstOrCreate(
                                    ['email' => $email, 'business_id' => $businessId ?: null, 'reason' => 'user_unsubscribe'],
                                    ['user_id' => $userId ?: null, 'source' => 'sendgrid', 'unsubscribed_at' => $eventAt ?? now()]
                                );
                            } elseif (in_array($eventType, ['bounce', 'blocked', 'dropped'], true)) {
                                EmailUnsubscribe::firstOrCreate(
                                    ['email' => $email, 'business_id' => $businessId ?: null, 'reason' => 'bounce'],
                                    ['user_id' => $userId ?: null, 'source' => 'sendgrid', 'unsubscribed_at' => $eventAt ?? now()]
                                );
                            } elseif ($eventType === 'spamreport') {
                                EmailUnsubscribe::firstOrCreate(
                                    ['email' => $email, 'business_id' => null, 'reason' => 'spamreport'],
                                    ['user_id' => $userId ?: null, 'source' => 'sendgrid', 'unsubscribed_at' => $eventAt ?? now()]
                                );
                            }
                        }
                    }
                }

                // Campaign rollups: only increment once per message per event type
                // to avoid inflated metrics from repeat opens/clicks or multiple
                // processed+deferred events for the same message.
                if ($campaignId > 0 && $crmMessageId > 0) {
                    $campaign = CrmCampaign::find($campaignId);
                    if ($campaign) {
                        // Check if this is the first event of its category for this message.
                        // Map event types to the metric column they affect.
                        $metricMap = [
                            'processed' => 'sent_total',
                            'deferred' => 'sent_total',
                            'delivered' => 'delivered_total',
                            'open' => 'open_total',
                            'click' => 'click_total',
                            'bounce' => 'bounce_total',
                            'blocked' => 'bounce_total',
                            'dropped' => 'bounce_total',
                            'spamreport' => 'spam_total',
                            'unsubscribe' => 'unsubscribe_total',
                            'group_unsubscribe' => 'unsubscribe_total',
                        ];

                        $metricColumn = $metricMap[$eventType] ?? null;
                        if ($metricColumn) {
                            // Group events that map to the same metric column.
                            $sameMetricEvents = array_keys(array_filter($metricMap, fn ($col) => $col === $metricColumn));
                            $alreadyCounted = CrmMessageEvent::where('crm_message_id', $crmMessageId)
                                ->whereIn('event', $sameMetricEvents)
                                ->where('sg_event_id', '!=', $sgEventId)
                                ->exists();

                            if (!$alreadyCounted) {
                                $campaign->increment($metricColumn);
                            }
                        }
                    }
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                // Don't retry webhook endlessly; acknowledge to prevent provider backoff storms.
                // Errors are still visible via logs.
                \Log::error('SendGrid webhook handling failed', [
                    'error' => $e->getMessage(),
                    'sg_event_id' => $sgEventId,
                    'event' => $eventType,
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }
}


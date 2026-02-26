<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CrmCampaign;
use App\Models\CrmMessage;
use App\Models\CrmMessageEvent;
use App\Models\EmailUnsubscribe;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendGridWebhookEventIngestionTest extends TestCase
{
    use RefreshDatabase;

    private function postSignedSendGridEvents(array $events)
    {
        $raw = json_encode($events, JSON_UNESCAPED_SLASHES);
        $ts = time();
        $headers = $this->configureValidSendGridSignature($raw, $ts);

        return $this->call(
            'POST',
            '/webhooks/sendgrid/events',
            [],
            [],
            [],
            array_merge(['CONTENT_TYPE' => 'application/json'], $headers),
            $raw
        );
    }

    private function configureValidSendGridSignature(string $rawBody, int $timestamp): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        $this->assertNotFalse($privateKey);

        $details = openssl_pkey_get_details($privateKey);
        $this->assertIsArray($details);
        $publicPem = $details['key'];

        $publicDerB64 = preg_replace('/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/', '', $publicPem);
        $publicDer = base64_decode($publicDerB64, true);
        $this->assertNotFalse($publicDer);

        config()->set('services.sendgrid.event_webhook_public_key', base64_encode($publicDer));

        $payload = (string) $timestamp . $rawBody;
        $signature = '';
        $ok = openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $this->assertTrue($ok);

        return [
            'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_SIGNATURE' => base64_encode($signature),
        ];
    }

    public function test_sendgrid_events_webhook_updates_message_and_is_idempotent(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create(['role' => 'customer']);

        $campaign = CrmCampaign::create([
            'business_id' => $business->id,
            'name' => 'Campaign',
            'subject' => 'Subj',
            'content_html' => '<p>Hi</p>',
            'status' => CrmCampaign::STATUS_SENDING,
            'from_name' => 'RewardStack',
            'from_email' => 'noreply@example.com',
        ]);

        $message = CrmMessage::create([
            'business_id' => $business->id,
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'queued',
        ]);

        $events = [[
            'email' => $user->email,
            'event' => 'processed',
            'sg_event_id' => 'evt-1',
            'sg_message_id' => 'sg-mid-1',
            'timestamp' => time(),
            'custom_args' => [
                'crm_message_id' => (string) $message->id,
                'crm_campaign_id' => (string) $campaign->id,
                'business_id' => (string) $business->id,
                'user_id' => (string) $user->id,
            ],
        ]];

        $raw = json_encode($events, JSON_UNESCAPED_SLASHES);
        $ts = time();
        $headers = $this->configureValidSendGridSignature($raw, $ts);

        $resp = $this->call(
            'POST',
            '/webhooks/sendgrid/events',
            [],
            [],
            [],
            array_merge(['CONTENT_TYPE' => 'application/json'], $headers),
            $raw
        );

        $resp->assertStatus(200)->assertJson(['ok' => true]);

        $message->refresh();
        $campaign->refresh();

        $this->assertSame('sent', $message->status);
        $this->assertSame('sg-mid-1', $message->sendgrid_message_id);
        $this->assertNotNull($message->sent_at);
        $this->assertNotNull($message->last_event_at);
        $this->assertSame(1, (int) $campaign->sent_total);

        $this->assertSame(1, WebhookEvent::query()->where('provider', 'sendgrid')->where('event_id', 'evt-1')->count());
        $this->assertSame(1, CrmMessageEvent::query()->where('sg_event_id', 'evt-1')->count());

        // Re-send the exact same event: should dedupe and not double-count.
        $resp2 = $this->call(
            'POST',
            '/webhooks/sendgrid/events',
            [],
            [],
            [],
            array_merge(['CONTENT_TYPE' => 'application/json'], $headers),
            $raw
        );

        $resp2->assertStatus(200)->assertJson(['ok' => true]);

        $campaign->refresh();
        $this->assertSame(1, WebhookEvent::query()->where('provider', 'sendgrid')->where('event_id', 'evt-1')->count());
        $this->assertSame(1, CrmMessageEvent::query()->where('sg_event_id', 'evt-1')->count());
        $this->assertSame(1, (int) $campaign->sent_total);
    }

    public function test_invalid_signature_rejected(): void
    {
        $resp = $this->call(
            'POST',
            '/webhooks/sendgrid/events',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_TIMESTAMP' => (string) time(),
                'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_SIGNATURE' => 'bad',
            ],
            '[]'
        );

        $this->assertTrue(in_array($resp->status(), [403, 400]));
    }

    public function test_timestamp_out_of_range_rejected(): void
    {
        $resp = $this->call(
            'POST',
            '/webhooks/sendgrid/events',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_TIMESTAMP' => (string) (time() - 999999),
                'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_SIGNATURE' => 'invalid',
            ],
            '[]'
        );

        $this->assertTrue(in_array($resp->status(), [403, 400]));
    }

    public function test_unsubscribe_event_creates_suppression_record_and_rolls_up_campaign(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create(['role' => 'customer']);

        $campaign = CrmCampaign::create([
            'business_id' => $business->id,
            'name' => 'Campaign',
            'subject' => 'Subj',
            'content_html' => '<p>Hi</p>',
            'status' => CrmCampaign::STATUS_SENDING,
            'from_name' => 'RewardStack',
            'from_email' => 'noreply@example.com',
        ]);

        $message = CrmMessage::create([
            'business_id' => $business->id,
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $events = [[
            'email' => $user->email,
            'event' => 'unsubscribe',
            'sg_event_id' => 'evt-unsub-1',
            'sg_message_id' => 'sg-mid-2',
            'timestamp' => time(),
            'custom_args' => [
                'crm_message_id' => (string) $message->id,
                'crm_campaign_id' => (string) $campaign->id,
                'business_id' => (string) $business->id,
                'user_id' => (string) $user->id,
            ],
        ]];

        $raw = json_encode($events, JSON_UNESCAPED_SLASHES);
        $ts = time();
        $headers = $this->configureValidSendGridSignature($raw, $ts);

        $resp = $this->call(
            'POST',
            '/webhooks/sendgrid/events',
            [],
            [],
            [],
            array_merge(['CONTENT_TYPE' => 'application/json'], $headers),
            $raw
        );
        $resp->assertStatus(200)->assertJson(['ok' => true]);

        $message->refresh();
        $campaign->refresh();

        $this->assertSame('unsubscribed', $message->status);
        $this->assertNotNull($message->unsubscribed_at);
        $this->assertSame(1, (int) $campaign->unsubscribe_total);

        $this->assertSame(1, EmailUnsubscribe::query()->where('email', $user->email)->where('business_id', $business->id)->count());
    }

    public function test_event_payload_with_non_array_element_is_accepted_and_skipped(): void
    {
        $resp = $this->postSignedSendGridEvents([
            'not-an-event',
        ]);
        $resp->assertStatus(200)->assertJson(['ok' => true]);
    }

    public function test_event_missing_required_fields_is_skipped(): void
    {
        $resp = $this->postSignedSendGridEvents([[
            'email' => 'x@example.com',
            'event' => '',
            'sg_event_id' => '',
        ]]);
        $resp->assertStatus(200)->assertJson(['ok' => true]);
    }

    public function test_unique_args_are_supported_and_update_rollups_for_delivered_open_click(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create(['role' => 'customer']);

        $campaign = CrmCampaign::create([
            'business_id' => $business->id,
            'name' => 'Campaign',
            'subject' => 'Subj',
            'content_html' => '<p>Hi</p>',
            'status' => CrmCampaign::STATUS_SENDING,
            'from_name' => 'RewardStack',
            'from_email' => 'noreply@example.com',
        ]);

        $message = CrmMessage::create([
            'business_id' => $business->id,
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'queued',
        ]);

        // delivered
        $this->postSignedSendGridEvents([[
            'email' => $user->email,
            'event' => 'delivered',
            'sg_event_id' => 'evt-delivered-1',
            'sg_message_id' => 'sg-mid-1',
            'timestamp' => time(),
            'unique_args' => [
                'crm_message_id' => (string) $message->id,
                'crm_campaign_id' => (string) $campaign->id,
                'business_id' => (string) $business->id,
                'user_id' => (string) $user->id,
            ],
        ]])->assertStatus(200);

        // open
        $this->postSignedSendGridEvents([[
            'email' => $user->email,
            'event' => 'open',
            'sg_event_id' => 'evt-open-1',
            'sg_message_id' => 'sg-mid-1',
            'timestamp' => time(),
            'unique_args' => [
                'crm_message_id' => (string) $message->id,
                'crm_campaign_id' => (string) $campaign->id,
                'business_id' => (string) $business->id,
                'user_id' => (string) $user->id,
            ],
        ]])->assertStatus(200);

        // click
        $this->postSignedSendGridEvents([[
            'email' => $user->email,
            'event' => 'click',
            'sg_event_id' => 'evt-click-1',
            'sg_message_id' => 'sg-mid-1',
            'timestamp' => time(),
            'url' => 'https://example.com',
            'unique_args' => [
                'crm_message_id' => (string) $message->id,
                'crm_campaign_id' => (string) $campaign->id,
                'business_id' => (string) $business->id,
                'user_id' => (string) $user->id,
            ],
        ]])->assertStatus(200);

        $message->refresh();
        $campaign->refresh();

        $this->assertSame('clicked', $message->status);
        $this->assertNotNull($message->delivered_at);
        $this->assertNotNull($message->opened_at);
        $this->assertNotNull($message->clicked_at);
        $this->assertSame(1, (int) $campaign->delivered_total);
        $this->assertSame(1, (int) $campaign->open_total);
        $this->assertSame(1, (int) $campaign->click_total);
    }

    public function test_bounce_and_spamreport_create_suppressions(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create(['role' => 'customer']);

        $campaign = CrmCampaign::create([
            'business_id' => $business->id,
            'name' => 'Campaign',
            'subject' => 'Subj',
            'content_html' => '<p>Hi</p>',
            'status' => CrmCampaign::STATUS_SENDING,
            'from_name' => 'RewardStack',
            'from_email' => 'noreply@example.com',
        ]);

        $message = CrmMessage::create([
            'business_id' => $business->id,
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->postSignedSendGridEvents([[
            'email' => $user->email,
            'event' => 'bounce',
            'sg_event_id' => 'evt-bounce-1',
            'sg_message_id' => 'sg-mid-b',
            'timestamp' => time(),
            'custom_args' => [
                'crm_message_id' => (string) $message->id,
                'crm_campaign_id' => (string) $campaign->id,
                'business_id' => (string) $business->id,
                'user_id' => (string) $user->id,
            ],
        ]])->assertStatus(200)->assertJson(['ok' => true]);

        $this->assertSame(1, EmailUnsubscribe::query()
            ->where('email', $user->email)
            ->where('business_id', $business->id)
            ->where('reason', 'bounce')
            ->count());

        $this->postSignedSendGridEvents([[
            'email' => $user->email,
            'event' => 'spamreport',
            'sg_event_id' => 'evt-spam-1',
            'sg_message_id' => 'sg-mid-s',
            'timestamp' => time(),
            'custom_args' => [
                'crm_message_id' => (string) $message->id,
                'crm_campaign_id' => (string) $campaign->id,
                'business_id' => (string) $business->id,
                'user_id' => (string) $user->id,
            ],
        ]])->assertStatus(200);

        // Spamreport is global (business_id null)
        $this->assertSame(1, EmailUnsubscribe::query()
            ->where('email', $user->email)
            ->whereNull('business_id')
            ->where('reason', 'spamreport')
            ->count());
    }

    public function test_crm_message_event_duplicate_is_swallowed_and_webhook_still_ok(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create(['role' => 'customer']);

        $campaign = CrmCampaign::create([
            'business_id' => $business->id,
            'name' => 'Campaign',
            'subject' => 'Subj',
            'content_html' => '<p>Hi</p>',
            'status' => CrmCampaign::STATUS_SENDING,
            'from_name' => 'RewardStack',
            'from_email' => 'noreply@example.com',
        ]);

        $message = CrmMessage::create([
            'business_id' => $business->id,
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'queued',
        ]);

        // Create a CrmMessageEvent with the sg_event_id ahead of time (but no WebhookEvent),
        // so the controller hits its CrmMessageEvent duplicate catch path.
        CrmMessageEvent::create([
            'business_id' => $business->id,
            'campaign_id' => $campaign->id,
            'crm_message_id' => $message->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'event' => 'processed',
            'event_at' => now(),
            'sg_event_id' => 'evt-dup-1',
            'sg_message_id' => 'sg-mid-dup',
            'payload' => [],
        ]);

        $resp = $this->postSignedSendGridEvents([[
            'email' => $user->email,
            'event' => 'processed',
            'sg_event_id' => 'evt-dup-1',
            'sg_message_id' => 'sg-mid-dup',
            'timestamp' => time(),
            'custom_args' => [
                'crm_message_id' => (string) $message->id,
                'crm_campaign_id' => (string) $campaign->id,
                'business_id' => (string) $business->id,
                'user_id' => (string) $user->id,
            ],
        ]]);

        $resp->assertStatus(200)->assertJson(['ok' => true]);

        // WebhookEvent should still be created.
        $this->assertSame(1, WebhookEvent::query()->where('provider', 'sendgrid')->where('event_id', 'evt-dup-1')->count());
    }
}


<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CrmCampaign;
use App\Models\CrmMessage;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendGridWebhookHappyPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_records_events_and_dedupes(): void
    {
        $business = Business::factory()->create();
        $campaign = CrmCampaign::create([
            'business_id' => $business->id,
            'name' => 'Test Campaign',
            'subject' => 'Hi',
            'content_html' => '<p>Hi</p>',
            'content_text' => 'Hi',
            'status' => CrmCampaign::STATUS_SENDING,
        ]);
        $message = CrmMessage::create([
            'business_id' => $business->id,
            'campaign_id' => $campaign->id,
            'user_id' => $business->owner_id ?? 1,
            'email' => 'user@example.com',
            'status' => 'queued',
        ]);

        $payload = [
            [
                'sg_event_id' => 'evt-1',
                'event' => 'delivered',
                'email' => 'user@example.com',
                'timestamp' => now()->timestamp,
                'sg_message_id' => 'msg-1',
                'crm_message_id' => $message->id,
                'crm_campaign_id' => $campaign->id,
                'business_id' => $campaign->business_id,
                'user_id' => $message->user_id,
            ],
        ];

        $resp1 = $this->withoutMiddleware()->postJson('/webhooks/sendgrid/events', $payload);
        $resp2 = $this->withoutMiddleware()->postJson('/webhooks/sendgrid/events', $payload); // replay

        $resp1->assertStatus(200);
        $resp2->assertStatus(200);

        $this->assertEquals(1, WebhookEvent::where('provider', 'sendgrid')->where('event_id', 'evt-1')->count());
        $this->assertDatabaseHas('crm_message_events', ['sg_event_id' => 'evt-1']);

        $message->refresh();
        $this->assertEquals('delivered', $message->status);
        $this->assertNotNull($message->delivered_at);

        $campaign->refresh();
        $this->assertEquals(1, (int) $campaign->delivered_total);
    }
}


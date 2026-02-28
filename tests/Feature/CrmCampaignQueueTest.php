<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessCustomerSubscription;
use App\Models\CrmCampaign;
use App\Models\CrmMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;
use App\Jobs\SendCrmMessage;

class CrmCampaignQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_can_queue_campaign_and_messages_send_via_sendgrid_service(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        config()->set('services.sendgrid.api_key', 'SG_TEST_KEY');
        config()->set('mail.from.address', 'noreply@example.com');

        Bus::fake();

        $owner = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business = Business::factory()->create([
            'user_id' => $owner->id,
            'is_testing_account' => true,
        ]);

        $customer = User::factory()->create(['role' => 'customer']);
        BusinessCustomerSubscription::create([
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'subscribed_at' => now(),
            'source' => 'test',
        ]);

        $campaign = CrmCampaign::create([
            'business_id' => $business->id,
            'name' => 'Test campaign',
            'subject' => 'Hello',
            'content_html' => '<p>Hi {{first_name}}</p>',
            'content_text' => 'Hi {{first_name}}',
            'status' => CrmCampaign::STATUS_DRAFT,
            'from_name' => 'RewardStack',
            'from_email' => 'noreply@example.com',
        ]);

        $resp = $this->actingAs($owner)->post("/business/crm/campaigns/{$campaign->id}/queue");
        $resp->assertStatus(302);

        $campaign->refresh();
        // Controller sets status to SENT after dispatching (sync runs jobs immediately)
        $this->assertSame(CrmCampaign::STATUS_SENT, $campaign->status);
        $this->assertSame(1, (int) $campaign->recipients_total);

        $message = CrmMessage::query()->where('campaign_id', $campaign->id)->where('user_id', $customer->id)->first();
        $this->assertNotNull($message);
        $this->assertSame('queued', $message->status);

        Bus::assertDispatched(SendCrmMessage::class, 1);
    }
}


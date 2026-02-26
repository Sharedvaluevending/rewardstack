<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendCrmMessage;
use App\Models\CrmCampaign;
use App\Models\CrmMessage;
use App\Services\CrmSendGridService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendCrmMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_returns_early_when_message_not_found(): void
    {
        $job = new SendCrmMessage(99999);
        $sendGrid = Mockery::mock(CrmSendGridService::class);
        $sendGrid->shouldNotReceive('sendMessage');

        $job->handle($sendGrid);

        $this->assertSame(0, CrmMessage::where('id', 99999)->count());
    }

    public function test_handle_returns_early_when_campaign_cancelled(): void
    {
        $business = \App\Models\Business::factory()->create();
        $campaign = CrmCampaign::create([
            'business_id' => $business->id,
            'name' => 'Test',
            'subject' => 'Test Subject',
            'content_html' => '<p>Test</p>',
            'status' => CrmCampaign::STATUS_CANCELLED,
        ]);
        $user = \App\Models\User::factory()->create();
        $message = CrmMessage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'business_id' => $business->id,
            'email' => $user->email,
            'status' => 'queued',
        ]);

        $job = new SendCrmMessage($message->id);
        $sendGrid = Mockery::mock(CrmSendGridService::class);
        $sendGrid->shouldNotReceive('sendMessage');

        $job->handle($sendGrid);

        $message->refresh();
        $this->assertSame('queued', $message->status);
    }
}

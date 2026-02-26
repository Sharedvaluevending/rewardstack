<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Models\SubscriptionPlan;
use App\Notifications\BusinessSubscriptionActivated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessSubscriptionActivatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_mail_channel(): void
    {
        $business = Business::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $notification = new BusinessSubscriptionActivated($business, $plan);
        $this->assertSame(['mail'], $notification->via(new \stdClass()));
    }

    public function test_to_mail_returns_mail_message(): void
    {
        $business = Business::factory()->create(['name' => 'Biz']);
        $plan = SubscriptionPlan::factory()->create(['name' => 'Pro']);
        $notification = new BusinessSubscriptionActivated($business, $plan, 'monthly');
        $msg = $notification->toMail($business);
        $this->assertInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class, $msg);
        $this->assertStringContainsString('activated', $msg->subject);
    }
}

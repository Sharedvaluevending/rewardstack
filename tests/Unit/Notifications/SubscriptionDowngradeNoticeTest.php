<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Notifications\SubscriptionDowngradeNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionDowngradeNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_mail_returns_mail_message_with_tier_change(): void
    {
        $user = \App\Models\User::factory()->create();
        $business = Business::factory()->create(['name' => 'Test Shop', 'user_id' => $user->id]);
        $notification = new SubscriptionDowngradeNotice($business, 'Pro', 'Starter', []);

        $mail = $notification->toMail($user);

        $this->assertStringContainsString('Pro', $mail->subject);
        $this->assertStringContainsString('Starter', $mail->subject);
        $this->assertStringContainsString('/business/billing', $mail->actionUrl);
    }

    public function test_to_mail_includes_deactivated_features_when_provided(): void
    {
        $user = \App\Models\User::factory()->create();
        $business = Business::factory()->create(['user_id' => $user->id]);
        $notification = new SubscriptionDowngradeNotice($business, 'Pro', 'Starter', ['Advanced analytics', 'Custom branding']);

        $mail = $notification->toMail($user);

        $this->assertStringContainsString('Advanced analytics', implode(' ', $mail->introLines));
        $this->assertStringContainsString('Custom branding', implode(' ', $mail->introLines));
    }
}

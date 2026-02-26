<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessCustomerSubscription;
use App\Models\EmailUnsubscribe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailUnsubscribeSignedUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_unsubscribe_link_marks_subscription_unsubscribed_and_creates_suppression_record(): void
    {
        $business = Business::factory()->create(['name' => 'My Biz']);
        $user = User::factory()->create(['role' => 'customer']);

        BusinessCustomerSubscription::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'subscribed_at' => now(),
            'source' => 'test',
        ]);

        $url = URL::temporarySignedRoute('email.unsubscribe', now()->addMinutes(30), [
            'u' => $user->id,
            'b' => $business->id,
        ]);

        $resp = $this->get($url);
        $resp->assertStatus(200);
        $resp->assertSee('My Biz');
        $resp->assertSee($user->email);

        $sub = BusinessCustomerSubscription::query()
            ->where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($sub);
        $this->assertNotNull($sub->unsubscribed_at);

        $this->assertSame(1, EmailUnsubscribe::query()
            ->where('email', $user->email)
            ->where('business_id', $business->id)
            ->where('reason', 'user_unsubscribe')
            ->count());
    }
}


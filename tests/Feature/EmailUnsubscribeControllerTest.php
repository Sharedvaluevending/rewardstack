<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\EmailUnsubscribe;
use App\Models\User;
use App\Services\BusinessCustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailUnsubscribeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsubscribe_fails_for_invalid_params(): void
    {
        $url = URL::signedRoute('email.unsubscribe', ['u' => 0, 'b' => 1]);
        $response = $this->get($url);

        $response->assertStatus(404);
    }

    public function test_unsubscribe_renders_view_with_signed_url(): void
    {
        $user = User::factory()->create(['email' => 'unsub@example.com']);
        $business = Business::factory()->create(['name' => 'Test Biz']);

        $url = URL::signedRoute('email.unsubscribe', [
            'u' => $user->id,
            'b' => $business->id,
        ]);

        $this->mock(BusinessCustomerService::class, function ($mock) use ($business, $user) {
            $mock->shouldReceive('unsubscribe')
                ->once()
                ->with($business->id, $user->id, 'email_link');
        });

        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertViewIs('unsubscribe');
        $response->assertSee('Test Biz');
        $response->assertSee('unsub@example.com');

        $this->assertDatabaseHas('email_unsubscribes', [
            'email' => 'unsub@example.com',
            'business_id' => $business->id,
        ]);
    }
}

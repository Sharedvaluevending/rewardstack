<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_as_read_requires_auth(): void
    {
        $this->post(route('notifications.read', ['id' => 'fake-id']))
            ->assertRedirect(route('login'));
    }

    public function test_mark_as_read_returns_back_when_authenticated(): void
    {
        $user = User::factory()->create();
        $user->notify(new class extends Notification {
            public function via($notifiable): array
            {
                return ['database'];
            }
            public function toArray($notifiable): array
            {
                return [];
            }
        });
        $notification = $user->unreadNotifications->first();
        $this->assertNotNull($notification);

        $response = $this->actingAs($user)
            ->post(route('notifications.read', ['id' => $notification->id]));

        $response->assertRedirect();
    }

    public function test_mark_all_as_read_returns_back(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('notifications.read-all'));

        $response->assertRedirect();
    }
}

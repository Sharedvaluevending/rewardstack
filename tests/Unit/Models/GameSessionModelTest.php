<?php

namespace Tests\Unit\Models;

use App\Models\GameSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameSessionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_key_name_is_session_token(): void
    {
        $session = new GameSession();
        $this->assertSame('session_token', $session->getRouteKeyName());
    }

    public function test_location_status_constants_are_defined(): void
    {
        $this->assertSame('pending', GameSession::LOCATION_PENDING);
        $this->assertSame('verified', GameSession::LOCATION_VERIFIED);
        $this->assertSame('failed', GameSession::LOCATION_FAILED);
        $this->assertSame('expired', GameSession::LOCATION_EXPIRED);
    }

    public function test_status_constants_are_defined(): void
    {
        $this->assertSame('active', GameSession::STATUS_ACTIVE);
        $this->assertSame('playing', GameSession::STATUS_PLAYING);
        $this->assertSame('completed', GameSession::STATUS_COMPLETED);
        $this->assertSame('expired', GameSession::STATUS_EXPIRED);
        $this->assertSame('abandoned', GameSession::STATUS_ABANDONED);
    }

    public function test_scope_active_filters_active_and_not_expired(): void
    {
        $active = GameSession::factory()->create([
            'status' => GameSession::STATUS_ACTIVE,
            'expires_at' => now()->addHour(),
        ]);
        $expired = GameSession::factory()->create([
            'status' => GameSession::STATUS_ACTIVE,
            'expires_at' => now()->subHour(),
        ]);

        $ids = GameSession::active()->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($expired->id, $ids);
    }

    public function test_scope_verified_filters_location_verified(): void
    {
        $verified = GameSession::factory()->create(['location_status' => GameSession::LOCATION_VERIFIED]);
        $pending = GameSession::factory()->create(['location_status' => GameSession::LOCATION_PENDING]);

        $ids = GameSession::verified()->pluck('id')->all();
        $this->assertContains($verified->id, $ids);
        $this->assertNotContains($pending->id, $ids);
    }

    public function test_is_expired_returns_true_when_expires_at_past(): void
    {
        $session = GameSession::factory()->create(['expires_at' => now()->subMinute()]);
        $this->assertTrue($session->isExpired());
    }

    public function test_is_expired_returns_false_when_expires_at_future(): void
    {
        $session = GameSession::factory()->create(['expires_at' => now()->addHour()]);
        $this->assertFalse($session->isExpired());
    }

    public function test_is_valid_returns_true_when_active_verified_and_not_expired(): void
    {
        $session = GameSession::factory()->create([
            'status' => GameSession::STATUS_ACTIVE,
            'location_status' => GameSession::LOCATION_VERIFIED,
            'expires_at' => now()->addHour(),
        ]);
        $this->assertTrue($session->isValid());
    }

    public function test_is_valid_returns_false_when_expired(): void
    {
        $session = GameSession::factory()->create([
            'status' => GameSession::STATUS_ACTIVE,
            'location_status' => GameSession::LOCATION_VERIFIED,
            'expires_at' => now()->subMinute(),
        ]);
        $this->assertFalse($session->isValid());
    }

    public function test_mark_as_playing_updates_status(): void
    {
        $session = GameSession::factory()->create(['status' => GameSession::STATUS_ACTIVE]);
        $session->markAsPlaying();
        $session->refresh();
        $this->assertSame(GameSession::STATUS_PLAYING, $session->status);
    }

    public function test_mark_as_completed_updates_status(): void
    {
        $session = GameSession::factory()->create(['status' => GameSession::STATUS_PLAYING]);
        $session->markAsCompleted();
        $session->refresh();
        $this->assertSame(GameSession::STATUS_COMPLETED, $session->status);
    }

    public function test_mark_as_expired_updates_status(): void
    {
        $session = GameSession::factory()->create(['status' => GameSession::STATUS_ACTIVE]);
        $session->markAsExpired();
        $session->refresh();
        $this->assertSame(GameSession::STATUS_EXPIRED, $session->status);
    }

    public function test_verify_location_updates_location_status(): void
    {
        $session = GameSession::factory()->create(['location_status' => GameSession::LOCATION_PENDING]);
        $session->verifyLocation();
        $session->refresh();
        $this->assertSame(GameSession::LOCATION_VERIFIED, $session->location_status);
        $this->assertNotNull($session->location_verified_at);
    }

    public function test_fail_location_updates_location_status(): void
    {
        $session = GameSession::factory()->create(['location_status' => GameSession::LOCATION_PENDING]);
        $session->failLocation();
        $session->refresh();
        $this->assertSame(GameSession::LOCATION_FAILED, $session->location_status);
    }
}

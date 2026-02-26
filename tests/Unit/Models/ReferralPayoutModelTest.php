<?php

namespace Tests\Unit\Models;

use App\Models\ReferralPayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralPayoutModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_constants_are_defined(): void
    {
        $this->assertSame('pending', ReferralPayout::STATUS_PENDING);
        $this->assertSame('processing', ReferralPayout::STATUS_PROCESSING);
        $this->assertSame('completed', ReferralPayout::STATUS_COMPLETED);
        $this->assertSame('failed', ReferralPayout::STATUS_FAILED);
    }

    public function test_scope_pending_filters_pending_only(): void
    {
        $user = User::factory()->create();
        $pending = ReferralPayout::create([
            'user_id' => $user->id,
            'amount' => 10,
            'method' => 'stripe',
            'destination' => 'acct_123',
            'status' => ReferralPayout::STATUS_PENDING,
            'requested_at' => now(),
        ]);
        $completed = ReferralPayout::create([
            'user_id' => $user->id,
            'amount' => 20,
            'method' => 'stripe',
            'destination' => 'acct_456',
            'status' => ReferralPayout::STATUS_COMPLETED,
            'requested_at' => now(),
        ]);

        $ids = ReferralPayout::pending()->pluck('id')->all();
        $this->assertContains($pending->id, $ids);
        $this->assertNotContains($completed->id, $ids);
    }
}

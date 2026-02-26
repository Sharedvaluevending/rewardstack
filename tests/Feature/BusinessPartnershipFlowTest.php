<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessPartnership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BusinessPartnershipFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_can_send_and_partner_can_accept_partnership_request(): void
    {
        Notification::fake();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $owner1 = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business1 = Business::factory()->create([
            'user_id' => $owner1->id,
            'is_testing_account' => true, // bypass subscription.active middleware in tests
        ]);

        $owner2 = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business2 = Business::factory()->create([
            'user_id' => $owner2->id,
            'is_testing_account' => true, // bypass subscription.active middleware in tests
        ]);

        $resp = $this->actingAs($owner1)->post('/business/partnerships/request', [
            'partner_business_id' => $business2->id,
            'message' => 'Let’s partner!',
        ]);
        $resp->assertStatus(302);

        $partnership = BusinessPartnership::query()->first();
        $this->assertNotNull($partnership);
        $this->assertSame($business1->id, (int) $partnership->requester_business_id);
        $this->assertSame($business2->id, (int) $partnership->partner_business_id);
        $this->assertSame(BusinessPartnership::STATUS_PENDING, $partnership->status);

        $accept = $this->actingAs($owner2)->post("/business/partnerships/{$partnership->id}/accept", [
            'message' => 'Sounds good',
        ]);
        $accept->assertStatus(302);

        $partnership->refresh();
        $this->assertSame(BusinessPartnership::STATUS_ACCEPTED, $partnership->status);
        $this->assertNotNull($partnership->responded_at);
    }

    public function test_duplicate_pending_partnership_request_is_rejected(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $owner1 = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business1 = Business::factory()->create(['user_id' => $owner1->id, 'is_testing_account' => true]);
        $owner2 = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business2 = Business::factory()->create(['user_id' => $owner2->id, 'is_testing_account' => true]);

        BusinessPartnership::create([
            'requester_business_id' => $business1->id,
            'partner_business_id' => $business2->id,
            'status' => BusinessPartnership::STATUS_PENDING,
        ]);

        $resp = $this->actingAs($owner1)->post('/business/partnerships/request', [
            'partner_business_id' => $business2->id,
        ]);

        $resp->assertStatus(302);
        $resp->assertSessionHasErrors(['partner_business_id']);

        $this->assertSame(1, BusinessPartnership::query()->count());
    }
}


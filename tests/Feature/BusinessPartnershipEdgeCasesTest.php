<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessPartnership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessPartnershipEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_can_decline_partnership_request(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $owner1 = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business1 = Business::factory()->create(['user_id' => $owner1->id, 'is_testing_account' => true]);

        $owner2 = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business2 = Business::factory()->create(['user_id' => $owner2->id, 'is_testing_account' => true]);

        $partnership = BusinessPartnership::create([
            'requester_business_id' => $business1->id,
            'partner_business_id' => $business2->id,
            'status' => BusinessPartnership::STATUS_PENDING,
        ]);

        $resp = $this->actingAs($owner2)->post("/business/partnerships/{$partnership->id}/decline", [
            'message' => 'No thanks',
        ]);
        $resp->assertStatus(302);

        $partnership->refresh();
        $this->assertSame(BusinessPartnership::STATUS_DECLINED, $partnership->status);
        $this->assertNotNull($partnership->responded_at);
        $this->assertSame('No thanks', $partnership->response_message);
    }

    public function test_requester_can_cancel_partnership_request(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $owner1 = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business1 = Business::factory()->create(['user_id' => $owner1->id, 'is_testing_account' => true]);

        $owner2 = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business2 = Business::factory()->create(['user_id' => $owner2->id, 'is_testing_account' => true]);

        $partnership = BusinessPartnership::create([
            'requester_business_id' => $business1->id,
            'partner_business_id' => $business2->id,
            'status' => BusinessPartnership::STATUS_PENDING,
        ]);

        $resp = $this->actingAs($owner1)->post("/business/partnerships/{$partnership->id}/cancel");
        $resp->assertStatus(302);

        $partnership->refresh();
        $this->assertSame(BusinessPartnership::STATUS_CANCELLED, $partnership->status);
        $this->assertNotNull($partnership->responded_at);
    }

    public function test_third_party_business_cannot_modify_someone_elses_partnership(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $owner1 = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business1 = Business::factory()->create(['user_id' => $owner1->id, 'is_testing_account' => true]);
        $owner2 = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business2 = Business::factory()->create(['user_id' => $owner2->id, 'is_testing_account' => true]);

        $intruder = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        Business::factory()->create(['user_id' => $intruder->id, 'is_testing_account' => true]);

        $partnership = BusinessPartnership::create([
            'requester_business_id' => $business1->id,
            'partner_business_id' => $business2->id,
            'status' => BusinessPartnership::STATUS_PENDING,
        ]);

        $this->actingAs($intruder)->post("/business/partnerships/{$partnership->id}/accept")->assertStatus(403);
        $this->actingAs($intruder)->post("/business/partnerships/{$partnership->id}/decline")->assertStatus(403);
        $this->actingAs($intruder)->post("/business/partnerships/{$partnership->id}/cancel")->assertStatus(403);
        $this->actingAs($intruder)->delete("/business/partnerships/{$partnership->id}")->assertStatus(403);
    }
}


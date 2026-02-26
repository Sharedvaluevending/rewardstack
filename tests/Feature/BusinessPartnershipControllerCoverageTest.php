<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessPartnership;
use App\Models\CrossPromotion;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Scan;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessPartnershipControllerCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::preventStrayRequests();
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-01-25 12:00:00'));
    }

    protected function makeBusinessOwner(array $businessOverrides = []): array
    {
        $business = Business::factory()->create(array_merge([
            'is_testing_account' => true,
            'subscription_tier' => 'pro',
        ], $businessOverrides));

        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        return [$business, $owner];
    }

    protected function makeActivePromoForBusiness(Business $business, array $overrides = []): Promotion
    {
        return Promotion::factory()->create(array_merge([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->addDays(10),
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ], $overrides));
    }

    public function test_index_renders_with_partnerships_cross_promos_and_pending_counts(): void
    {
        [$bizA, $ownerA] = $this->makeBusinessOwner();
        [$bizB, $ownerB] = $this->makeBusinessOwner();
        [$bizC, $ownerC] = $this->makeBusinessOwner();

        // Pending request TO A (badge count)
        BusinessPartnership::create([
            'requester_business_id' => $bizB->id,
            'partner_business_id' => $bizA->id,
            'status' => BusinessPartnership::STATUS_PENDING,
            'message' => 'Let’s partner',
        ]);

        // Accepted partnership (for dropdown status mapping)
        BusinessPartnership::create([
            'requester_business_id' => $bizA->id,
            'partner_business_id' => $bizC->id,
            'status' => BusinessPartnership::STATUS_ACCEPTED,
        ]);

        $promoA = $this->makeActivePromoForBusiness($bizA);
        $promoB = $this->makeActivePromoForBusiness($bizB);
        $promoC = $this->makeActivePromoForBusiness($bizC);

        // Pending incoming cross-promo (B -> A)
        CrossPromotion::create([
            'code' => 'CPIN0001',
            'name' => 'Incoming Deal',
            'business_1_id' => $bizB->id,
            'business_2_id' => $bizA->id,
            'requested_by_business_id' => $bizB->id,
            'promotion_1_id' => $promoB->id,
            'promotion_2_id' => null,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'revenue_share_percent' => 0,
            'rules_status' => CrossPromotion::RULES_PENDING_AGREEMENT,
            'cross_promo_rules' => ['valid_days' => ['monday']],
            'usage_limit' => 100,
            'status' => CrossPromotion::STATUS_PENDING,
            'is_active' => false,
        ]);

        // Pending outgoing cross-promo (A -> C)
        CrossPromotion::create([
            'code' => 'CPOUT001',
            'name' => 'Outgoing Deal',
            'business_1_id' => $bizA->id,
            'business_2_id' => $bizC->id,
            'requested_by_business_id' => $bizA->id,
            'promotion_1_id' => $promoA->id,
            'promotion_2_id' => null,
            'display_mode' => CrossPromotion::DISPLAY_ALTERNATING,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'revenue_share_percent' => 0,
            'rules_status' => CrossPromotion::RULES_USE_PROMOTION_RULES,
            'status' => CrossPromotion::STATUS_PENDING,
            'is_active' => false,
        ]);

        // Active cross-promo between A and B, with a partner-created QR code
        $cpAB = CrossPromotion::create([
            'code' => 'CPAB0001',
            'name' => 'A+B Deal',
            'business_1_id' => $bizA->id,
            'business_2_id' => $bizB->id,
            'requested_by_business_id' => $bizA->id,
            'promotion_1_id' => $promoA->id,
            'promotion_2_id' => $promoB->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'revenue_share_percent' => 0,
            'rules_status' => CrossPromotion::RULES_USE_PROMOTION_RULES,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);

        $partnerQr = QRCode::factory()->create([
            'business_id' => $bizB->id,
            'type' => 'cross_promo',
            'is_active' => true,
            'cross_promotion_id' => $cpAB->id,
        ]);

        // Another active cross-promo A<->C so list isn’t empty if ordering changes
        CrossPromotion::create([
            'code' => 'CPAC0001',
            'name' => 'A+C Deal',
            'business_1_id' => $bizA->id,
            'business_2_id' => $bizC->id,
            'requested_by_business_id' => $bizA->id,
            'promotion_1_id' => $promoA->id,
            'promotion_2_id' => $promoC->id,
            'display_mode' => CrossPromotion::DISPLAY_ALTERNATING,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'revenue_share_percent' => 0,
            'rules_status' => CrossPromotion::RULES_USE_PROMOTION_RULES,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);

        $resp = $this->actingAs($ownerA)->get('/business/partnerships');
        $resp->assertStatus(200);
        $resp->assertInertia(fn (Assert $page) => $page
            ->component('Business/Partnerships/Index')
            ->where('pendingRequests', 1)
        );

        // ensure partner QR code made it into props (serialized in the page payload)
        $resp->assertSee($partnerQr->code);
        $resp->assertSee('Incoming Deal');
        $resp->assertSee('Outgoing Deal');
    }

    public function test_accept_cross_promo_accept_rules_and_covers_non_json_success_and_error_paths(): void
    {
        [$bizA, $ownerA] = $this->makeBusinessOwner();
        [$bizB, $ownerB] = $this->makeBusinessOwner();

        $promoA = $this->makeActivePromoForBusiness($bizA, ['ends_at' => now()->addDays(2)]);
        $promoB = $this->makeActivePromoForBusiness($bizB, ['ends_at' => null]);

        $cp = CrossPromotion::create([
            'code' => 'CPACPT01',
            'name' => 'Accept Rules',
            'business_1_id' => $bizA->id,
            'business_2_id' => $bizB->id,
            'requested_by_business_id' => $bizA->id,
            'promotion_1_id' => $promoA->id,
            'promotion_2_id' => null,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'cross_promo_rules' => ['valid_days' => ['monday']],
            'rules_status' => CrossPromotion::RULES_PENDING_AGREEMENT,
            'status' => CrossPromotion::STATUS_PENDING,
            'is_active' => false,
        ]);

        // Partner promotion invalid branch
        $promoA->update(['is_active' => false]);
        $this->actingAs($ownerB)
            ->post("/business/partnerships/cross-promo/{$cp->id}/accept", [
                'my_promotion_id' => $promoB->id,
                'agree' => '1',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['error']);
        $promoA->update(['is_active' => true]);

        // Accept rules (default) + non-JSON success branch + expires_at set from promo1 only
        $this->actingAs($ownerB)
            ->post("/business/partnerships/cross-promo/{$cp->id}/accept", [
                'my_promotion_id' => $promoB->id,
                'agree' => '1',
                'rules_decision' => 'accept_rules',
            ])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $cp->refresh();
        $this->assertSame(CrossPromotion::STATUS_ACCEPTED, $cp->status);
        $this->assertSame(CrossPromotion::RULES_USE_PROMOTION_RULES, $cp->rules_status);
        $this->assertNotNull($cp->expires_at);
        $this->assertSame($promoA->ends_at->toDateString(), $cp->expires_at->toDateString());

        // Status not pending branch
        $this->actingAs($ownerB)
            ->post("/business/partnerships/cross-promo/{$cp->id}/accept", [
                'my_promotion_id' => $promoB->id,
                'agree' => '1',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['error']);

        // Unauthorized declineRequest branch (partner-only decline)
        $p = BusinessPartnership::create([
            'requester_business_id' => $bizB->id,
            'partner_business_id' => $bizA->id,
            'status' => BusinessPartnership::STATUS_PENDING,
        ]);
        $this->actingAs($ownerB)->post("/business/partnerships/{$p->id}/decline")->assertStatus(403);
    }

    public function test_search_businesses_returns_paginated_with_partnership_status_and_caps_per_page(): void
    {
        [$bizA, $ownerA] = $this->makeBusinessOwner();
        [$bizB] = $this->makeBusinessOwner(['name' => 'Alpha Coffee', 'city' => 'Toronto', 'type' => 'cafe']);
        [$bizC] = $this->makeBusinessOwner(['name' => 'Alpha Salon', 'city' => 'Ottawa', 'type' => 'salon']);
        [$bizD] = $this->makeBusinessOwner(['name' => 'Beta Gym', 'city' => 'Montreal', 'type' => 'gym']);

        $accepted = BusinessPartnership::create([
            'requester_business_id' => $bizA->id,
            'partner_business_id' => $bizB->id,
            'status' => BusinessPartnership::STATUS_ACCEPTED,
        ]);

        BusinessPartnership::create([
            'requester_business_id' => $bizC->id,
            'partner_business_id' => $bizA->id,
            'status' => BusinessPartnership::STATUS_PENDING,
        ]);

        $resp = $this->actingAs($ownerA)->getJson('/business/partnerships/search?search=Alpha&per_page=999');
        $resp->assertStatus(200);
        $resp->assertJsonPath('pagination.per_page', 50);

        $payload = $resp->json();
        $ids = array_map(fn ($row) => $row['id'], $payload['data']);
        $this->assertContains($bizB->id, $ids);
        $this->assertContains($bizC->id, $ids);
        $this->assertNotContains($bizD->id, $ids);

        $alphaCoffee = collect($payload['data'])->firstWhere('id', $bizB->id);
        $this->assertSame('accepted', $alphaCoffee['partnership_status']);
        $this->assertSame($accepted->id, $alphaCoffee['partnership_id']);
    }

    public function test_send_request_handles_existing_pending_and_accepted_and_recreates_after_declined(): void
    {
        [$bizA, $ownerA] = $this->makeBusinessOwner();
        [$bizB] = $this->makeBusinessOwner();

        // Create new request
        $this->actingAs($ownerA)
            ->post('/business/partnerships/request', ['partner_business_id' => $bizB->id, 'message' => 'Hi'])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertDatabaseHas('business_partnerships', [
            'requester_business_id' => $bizA->id,
            'partner_business_id' => $bizB->id,
            'status' => BusinessPartnership::STATUS_PENDING,
        ]);

        // Duplicate pending
        $this->actingAs($ownerA)
            ->post('/business/partnerships/request', ['partner_business_id' => $bizB->id])
            ->assertStatus(302)
            ->assertSessionHasErrors(['partner_business_id']);

        // Already accepted
        BusinessPartnership::where('requester_business_id', $bizA->id)
            ->where('partner_business_id', $bizB->id)
            ->update(['status' => BusinessPartnership::STATUS_ACCEPTED]);

        $this->actingAs($ownerA)
            ->post('/business/partnerships/request', ['partner_business_id' => $bizB->id])
            ->assertStatus(302)
            ->assertSessionHasErrors(['partner_business_id']);

        // Declined/cancelled case: recreate
        BusinessPartnership::where('requester_business_id', $bizA->id)
            ->where('partner_business_id', $bizB->id)
            ->update(['status' => BusinessPartnership::STATUS_DECLINED]);

        $this->actingAs($ownerA)
            ->post('/business/partnerships/request', ['partner_business_id' => $bizB->id, 'message' => 'Try again'])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertDatabaseHas('business_partnerships', [
            'requester_business_id' => $bizA->id,
            'partner_business_id' => $bizB->id,
            'status' => BusinessPartnership::STATUS_PENDING,
            'message' => 'Try again',
        ]);
    }

    public function test_accept_decline_and_cancel_partnership_requests_with_authorization_and_status_checks(): void
    {
        [$bizA, $ownerA] = $this->makeBusinessOwner();
        [$bizB, $ownerB] = $this->makeBusinessOwner();

        // Accept (only partner can accept)
        $p1 = BusinessPartnership::create([
            'requester_business_id' => $bizB->id,
            'partner_business_id' => $bizA->id,
            'status' => BusinessPartnership::STATUS_PENDING,
        ]);
        $this->actingAs($ownerA)
            ->post("/business/partnerships/{$p1->id}/accept", ['message' => 'Welcome'])
            ->assertStatus(302)
            ->assertSessionHas('success');
        $this->assertDatabaseHas('business_partnerships', ['id' => $p1->id, 'status' => BusinessPartnership::STATUS_ACCEPTED]);

        // Not pending anymore
        $this->actingAs($ownerA)
            ->post("/business/partnerships/{$p1->id}/accept")
            ->assertStatus(302)
            ->assertSessionHasErrors(['error']);

        // Decline
        $p2 = BusinessPartnership::create([
            'requester_business_id' => $bizB->id,
            'partner_business_id' => $bizA->id,
            'status' => BusinessPartnership::STATUS_PENDING,
        ]);
        $this->actingAs($ownerA)
            ->post("/business/partnerships/{$p2->id}/decline", ['message' => 'No thanks'])
            ->assertStatus(302)
            ->assertSessionHas('success');
        $this->assertDatabaseHas('business_partnerships', ['id' => $p2->id, 'status' => BusinessPartnership::STATUS_DECLINED]);

        // Cancel (only requester can cancel)
        $p3 = BusinessPartnership::create([
            'requester_business_id' => $bizA->id,
            'partner_business_id' => $bizB->id,
            'status' => BusinessPartnership::STATUS_PENDING,
        ]);
        $this->actingAs($ownerA)
            ->post("/business/partnerships/{$p3->id}/cancel")
            ->assertStatus(302)
            ->assertSessionHas('success');
        $this->assertDatabaseHas('business_partnerships', ['id' => $p3->id, 'status' => BusinessPartnership::STATUS_CANCELLED]);

        // Authorization failures
        $this->actingAs($ownerB)->post("/business/partnerships/{$p1->id}/accept")->assertStatus(403);
        $this->actingAs($ownerB)->post("/business/partnerships/{$p3->id}/cancel")->assertStatus(403);
    }

    public function test_destroy_partnership_deactivates_cross_promos(): void
    {
        [$bizA, $ownerA] = $this->makeBusinessOwner();
        [$bizB, $ownerB] = $this->makeBusinessOwner();

        $partnership = BusinessPartnership::create([
            'requester_business_id' => $bizA->id,
            'partner_business_id' => $bizB->id,
            'status' => BusinessPartnership::STATUS_ACCEPTED,
        ]);

        $promoA = $this->makeActivePromoForBusiness($bizA);
        $promoB = $this->makeActivePromoForBusiness($bizB);

        $cp1 = CrossPromotion::create([
            'code' => 'CPEnd1',
            'name' => 'End Me 1',
            'business_1_id' => $bizA->id,
            'business_2_id' => $bizB->id,
            'requested_by_business_id' => $bizA->id,
            'promotion_1_id' => $promoA->id,
            'promotion_2_id' => $promoB->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);
        $cp2 = CrossPromotion::create([
            'code' => 'CPEnd2',
            'name' => 'End Me 2',
            'business_1_id' => $bizB->id,
            'business_2_id' => $bizA->id,
            'requested_by_business_id' => $bizB->id,
            'promotion_1_id' => $promoB->id,
            'promotion_2_id' => $promoA->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);

        $this->actingAs($ownerA)
            ->delete("/business/partnerships/{$partnership->id}")
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('business_partnerships', ['id' => $partnership->id]);
        $this->assertDatabaseHas('cross_promotions', ['id' => $cp1->id, 'is_active' => 0, 'status' => CrossPromotion::STATUS_DECLINED]);
        $this->assertDatabaseHas('cross_promotions', ['id' => $cp2->id, 'is_active' => 0, 'status' => CrossPromotion::STATUS_DECLINED]);
    }

    public function test_create_cross_promo_requires_accepted_partnership_and_valid_rules_and_can_return_json(): void
    {
        [$bizA, $ownerA] = $this->makeBusinessOwner();
        [$bizB, $ownerB] = $this->makeBusinessOwner();

        $promoA = $this->makeActivePromoForBusiness($bizA);
        $promoB = $this->makeActivePromoForBusiness($bizB);

        // Promotion ownership check => 404
        $this->actingAs($ownerA)
            ->post('/business/partnerships/cross-promo', [
                'partner_business_id' => $bizB->id,
                'name' => 'Bad',
                'my_promotion_id' => $promoB->id, // belongs to B, not A
                'display_mode' => 'split',
            ])->assertStatus(404);

        // No accepted partnership => error
        $this->actingAs($ownerA)
            ->post('/business/partnerships/cross-promo', [
                'partner_business_id' => $bizB->id,
                'name' => 'Need partnership',
                'my_promotion_id' => $promoA->id,
                'display_mode' => 'split',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['partner_business_id']);

        BusinessPartnership::create([
            'requester_business_id' => $bizA->id,
            'partner_business_id' => $bizB->id,
            'status' => BusinessPartnership::STATUS_ACCEPTED,
        ]);

        // Create cross-promo (controller does not validate cross_promo_rules; uses RULES_USE_PROMOTION_RULES)
        $this->actingAs($ownerA)
            ->post('/business/partnerships/cross-promo', [
                'partner_business_id' => $bizB->id,
                'name' => 'Rules bad',
                'my_promotion_id' => $promoA->id,
                'display_mode' => 'split',
            ])
            ->assertStatus(302)
            ->assertSessionHas('success');
        $this->assertDatabaseHas('cross_promotions', [
            'name' => 'Rules bad',
            'business_1_id' => $bizA->id,
            'business_2_id' => $bizB->id,
            'rules_status' => CrossPromotion::RULES_USE_PROMOTION_RULES,
        ]);

        // Success, JSON response branch
        $resp = $this->actingAs($ownerA)->postJson('/business/partnerships/cross-promo', [
            'partner_business_id' => $bizB->id,
            'name' => 'Good Rules',
            'my_promotion_id' => $promoA->id,
            'display_mode' => 'split',
            'chain_mode' => 'sequential',
            'primary_promotion_id' => $promoA->id,
            'rules_mode' => 'set_cross_promo_rules',
            'cross_promo_rules' => [
                'valid_days' => ['monday', 'tuesday'],
                'valid_hours' => ['start' => '09:00', 'end' => '17:00'],
                'max_redemptions_per_user' => 0,
                'max_per_day' => 0,
            ],
        ]);
        $resp->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('cross_promotions', [
            'name' => 'Good Rules',
            'business_1_id' => $bizA->id,
            'business_2_id' => $bizB->id,
            'promotion_1_id' => $promoA->id,
            'promotion_2_id' => null,
            'status' => CrossPromotion::STATUS_PENDING,
            'is_active' => 0,
            'rules_status' => CrossPromotion::RULES_USE_PROMOTION_RULES,
            'chain_mode' => CrossPromotion::CHAIN_SEQUENTIAL,
            'primary_promotion_id' => $promoA->id,
        ]);
    }

    public function test_accept_and_decline_cross_promo_cover_rules_decisions_expiration_and_json(): void
    {
        [$bizA, $ownerA] = $this->makeBusinessOwner();
        [$bizB, $ownerB] = $this->makeBusinessOwner();

        BusinessPartnership::create([
            'requester_business_id' => $bizA->id,
            'partner_business_id' => $bizB->id,
            'status' => BusinessPartnership::STATUS_ACCEPTED,
        ]);

        $promoA = $this->makeActivePromoForBusiness($bizA, ['ends_at' => now()->addDays(2)]);
        $promoB = $this->makeActivePromoForBusiness($bizB, ['ends_at' => now()->addDays(10)]);

        // Pending cross promo with proposed rules
        $cp = CrossPromotion::create([
            'code' => 'CPREQ001',
            'name' => 'Request',
            'business_1_id' => $bizA->id,
            'business_2_id' => $bizB->id,
            'requested_by_business_id' => $bizA->id,
            'promotion_1_id' => $promoA->id,
            'promotion_2_id' => null,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_SEQUENTIAL,
            'primary_promotion_id' => null,
            'cross_promo_rules' => [
                'valid_days' => ['monday'],
                'valid_hours' => ['start' => '09:00', 'end' => '17:00'],
            ],
            'rules_status' => CrossPromotion::RULES_PENDING_AGREEMENT,
            'status' => CrossPromotion::STATUS_PENDING,
            'is_active' => false,
        ]);

        // Forbidden for non-invited partner
        $this->actingAs($ownerA)->post("/business/partnerships/cross-promo/{$cp->id}/accept", [
            'my_promotion_id' => $promoA->id,
            'agree' => '1',
        ])->assertStatus(403);

        // rules_decision is not validated; controller accepts and sets RULES_USE_PROMOTION_RULES
        $this->actingAs($ownerB)->post("/business/partnerships/cross-promo/{$cp->id}/accept", [
            'my_promotion_id' => $promoB->id,
            'agree' => '1',
            'rules_decision' => 'request_changes',
        ])->assertStatus(302)->assertSessionHas('success');
        $cp->refresh();
        $this->assertSame(CrossPromotion::STATUS_ACCEPTED, $cp->status);

        // Second pending cross-promo for override_rules flow (first cp was already accepted above)
        $cpOverride = CrossPromotion::create([
            'code' => 'CPOVERRIDE',
            'name' => 'Override Request',
            'business_1_id' => $bizA->id,
            'business_2_id' => $bizB->id,
            'requested_by_business_id' => $bizA->id,
            'promotion_1_id' => $promoA->id,
            'promotion_2_id' => null,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_SEQUENTIAL,
            'primary_promotion_id' => null,
            'cross_promo_rules' => ['valid_days' => ['monday'], 'valid_hours' => ['start' => '09:00', 'end' => '17:00']],
            'rules_status' => CrossPromotion::RULES_PENDING_AGREEMENT,
            'status' => CrossPromotion::STATUS_PENDING,
            'is_active' => false,
        ]);

        // Accept with override_rules (controller ignores override_rules and sets RULES_USE_PROMOTION_RULES)
        $resp = $this->actingAs($ownerB)->postJson("/business/partnerships/cross-promo/{$cpOverride->id}/accept", [
            'my_promotion_id' => $promoB->id,
            'agree' => '1',
            'rules_decision' => 'override_rules',
            'override_rules' => [
                'valid_days' => ['monday', 'tuesday'],
                'valid_hours' => ['start' => '10:00', 'end' => '18:00'],
                'max_redemptions_per_user' => 0,
            ],
        ]);
        $resp->assertStatus(200)->assertJsonPath('success', true);

        $cpOverride->refresh();
        $this->assertSame(CrossPromotion::STATUS_ACCEPTED, $cpOverride->status);
        $this->assertTrue((bool) $cpOverride->is_active);
        $this->assertSame(CrossPromotion::RULES_USE_PROMOTION_RULES, $cpOverride->rules_status);
        $this->assertSame($promoB->id, $cpOverride->promotion_2_id);
        $this->assertSame($promoB->id, $cpOverride->primary_promotion_id); // sequential + was null => partner becomes primary
        $this->assertNotNull($cpOverride->expires_at);
        $this->assertSame($promoA->ends_at->toDateString(), $cpOverride->expires_at->toDateString());

        // Decline flow (new pending CP)
        $cp2 = CrossPromotion::create([
            'code' => 'CPREQ002',
            'name' => 'Request 2',
            'business_1_id' => $bizA->id,
            'business_2_id' => $bizB->id,
            'requested_by_business_id' => $bizA->id,
            'promotion_1_id' => $promoA->id,
            'promotion_2_id' => null,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'status' => CrossPromotion::STATUS_PENDING,
            'is_active' => false,
        ]);
        $this->actingAs($ownerA)->post("/business/partnerships/cross-promo/{$cp2->id}/decline")->assertStatus(403);
        $this->actingAs($ownerB)->postJson("/business/partnerships/cross-promo/{$cp2->id}/decline")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertDatabaseHas('cross_promotions', ['id' => $cp2->id, 'status' => CrossPromotion::STATUS_DECLINED, 'is_active' => 0]);
    }

    public function test_edit_update_pause_resume_rules_and_partner_analytics_cover_pages_and_error_paths(): void
    {
        [$bizA, $ownerA] = $this->makeBusinessOwner();
        [$bizB, $ownerB] = $this->makeBusinessOwner();

        $partnership = BusinessPartnership::create([
            'requester_business_id' => $bizA->id,
            'partner_business_id' => $bizB->id,
            'status' => BusinessPartnership::STATUS_ACCEPTED,
        ]);

        $promoA = $this->makeActivePromoForBusiness($bizA, [
            'rules' => ['valid_days' => ['monday'], 'valid_hours' => ['start' => '09:00', 'end' => '10:00']],
        ]);
        $promoB = $this->makeActivePromoForBusiness($bizB, [
            'rules' => ['valid_days' => ['tuesday'], 'valid_hours' => ['start' => '11:00', 'end' => '12:00']],
        ]);

        $cp = CrossPromotion::create([
            'code' => 'CPEdit01',
            'name' => 'Editable',
            'business_1_id' => $bizA->id,
            'business_2_id' => $bizB->id,
            'requested_by_business_id' => $bizA->id,
            'promotion_1_id' => $promoA->id,
            'promotion_2_id' => $promoB->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'cross_promo_rules' => null,
            'rules_status' => CrossPromotion::RULES_USE_PROMOTION_RULES,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);

        // Edit page + authorization
        $this->actingAs($ownerA)
            ->get("/business/partnerships/cross-promo/{$cp->id}/edit")
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/Partnerships/EditCrossPromo'));

        [, $intruderOwner] = $this->makeBusinessOwner();
        $this->actingAs($intruderOwner)->get("/business/partnerships/cross-promo/{$cp->id}/edit")->assertStatus(403);

        // Update
        $this->actingAs($ownerA)
            ->put("/business/partnerships/cross-promo/{$cp->id}", [
                'name' => 'Updated Name',
                'display_mode' => 'random',
                'chain_mode' => 'open',
            ])
            ->assertStatus(302)
            ->assertSessionHas('success');
        $this->assertDatabaseHas('cross_promotions', ['id' => $cp->id, 'name' => 'Updated Name', 'display_mode' => 'random']);

        // Pause + resume success
        $this->actingAs($ownerA)->post("/business/partnerships/cross-promo/{$cp->id}/pause")->assertStatus(302)->assertSessionHas('success');
        $this->assertDatabaseHas('cross_promotions', ['id' => $cp->id, 'is_active' => 0]);
        $this->actingAs($ownerA)->post("/business/partnerships/cross-promo/{$cp->id}/resume")->assertStatus(302)->assertSessionHas('success');
        $this->assertDatabaseHas('cross_promotions', ['id' => $cp->id, 'is_active' => 1]);

        // Resume expired error
        $cp->update(['expires_at' => now()->subDay(), 'is_active' => false]);
        $this->actingAs($ownerA)->post("/business/partnerships/cross-promo/{$cp->id}/resume")->assertStatus(302)->assertSessionHasErrors(['error']);

        // Rules negotiation page (conflicts + merge)
        $this->actingAs($ownerA)
            ->get("/business/partnerships/cross-promo/{$cp->id}/rules")
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Partnerships/RulesNegotiation')
                ->has('conflicts')
            );

        // Agree rules validation error
        $this->actingAs($ownerA)
            ->post("/business/partnerships/cross-promo/{$cp->id}/rules/agree", [
                'agreed_rules' => ['max_per_day' => -1],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['agreed_rules']);

        // Agree rules success
        $this->actingAs($ownerA)
            ->post("/business/partnerships/cross-promo/{$cp->id}/rules/agree", [
                'agreed_rules' => [
                    'valid_days' => ['monday'],
                    'valid_hours' => ['start' => '09:00', 'end' => '17:00'],
                    'max_per_day' => 0,
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHas('success');
        $this->assertDatabaseHas('cross_promotions', ['id' => $cp->id, 'rules_status' => CrossPromotion::RULES_AGREED]);

        // Partner analytics (with some scan + token counts)
        $qr = QRCode::factory()->create([
            'business_id' => $bizA->id,
            'type' => 'cross_promo',
            'is_active' => true,
            'cross_promotion_id' => $cp->id,
        ]);
        Scan::create([
            'qr_code_id' => $qr->id,
            'business_id' => $bizA->id,
            'session_id' => 'sess-pa-1',
            'scan_type' => Scan::TYPE_CROSS_PROMO,
            'scanned_at' => now()->subHour(),
        ]);
        $customer = User::factory()->create(['role' => 'customer']);
        UserPromoToken::create([
            'user_id' => $customer->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promoA->id,
            'business_id' => $bizA->id,
            'code' => 'UP-PA-0001',
        ]);

        $this->actingAs($ownerA)
            ->get("/business/partnerships/{$partnership->id}/analytics")
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Partnerships/PartnerAnalytics')
                ->where('metrics.total_cross_promos', 1)
            );
    }

    public function test_get_accepted_partners_returns_json(): void
    {
        [$bizA, $ownerA] = $this->makeBusinessOwner();
        [$bizB] = $this->makeBusinessOwner();
        [$bizC] = $this->makeBusinessOwner();

        BusinessPartnership::create([
            'requester_business_id' => $bizA->id,
            'partner_business_id' => $bizB->id,
            'status' => BusinessPartnership::STATUS_ACCEPTED,
        ]);
        BusinessPartnership::create([
            'requester_business_id' => $bizC->id,
            'partner_business_id' => $bizA->id,
            'status' => BusinessPartnership::STATUS_ACCEPTED,
        ]);

        $resp = $this->actingAs($ownerA)->getJson('/business/partnerships/partners');
        $resp->assertStatus(200)->assertJsonStructure(['partners' => [['id', 'name', 'logo', 'category']]]);

        $ids = array_map(fn ($p) => $p['id'], $resp->json('partners'));
        $this->assertContains($bizB->id, $ids);
        $this->assertContains($bizC->id, $ids);
    }
}


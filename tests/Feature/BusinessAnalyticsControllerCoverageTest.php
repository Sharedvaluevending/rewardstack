<?php

namespace Tests\Feature;

use App\Models\AIInsight;
use App\Models\Business;
use App\Models\BusinessPartnership;
use App\Models\CrossPromotion;
use App\Models\Game;
use App\Models\GamePlay;
use App\Models\GameSession;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Redemption;
use App\Models\Scan;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessAnalyticsControllerCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::preventStrayRequests();

        // Clear Business plan cache so SubscriptionPlan lookups are fresh (avoids stale cache from other tests)
        $ref = new \ReflectionClass(Business::class);
        $prop = $ref->getProperty('planCache');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    protected function makeBusinessOwner(array $businessOverrides = []): array
    {
        $business = Business::factory()->create(array_merge([
            'is_testing_account' => true,
            'subscription_tier' => 'pro', // enables ai_insights via config fallback
        ], $businessOverrides));

        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        return [$business, $owner];
    }

    protected function seedAnalyticsData(Business $business): array
    {
        Carbon::setTestNow(Carbon::parse('2026-01-25 12:00:00'));

        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(10),
            'discount_type' => 'percentage',
            'discount_value' => 20,
        ]);

        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'is_active' => true,
            'name' => 'QR A',
        ]);

        $scan = Scan::create([
            'qr_code_id' => $qr->id,
            'business_id' => $business->id,
            'session_id' => 'sess-ana-1',
            'scan_type' => Scan::TYPE_PROMOTION,
            'device_type' => 'mobile',
            'browser' => 'Chrome',
            'city' => 'Testville',
            'scanned_at' => now()->subDay(),
        ]);

        $redemption = Redemption::create([
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'scan_id' => $scan->id,
            'customer_identifier' => 'CUST-ANA-1',
            'discount_amount' => 5.00,
            'original_amount' => 25.00,
            'final_amount' => 20.00,
            'redeemed_at' => now()->subDay(),
        ]);

        return [$promo, $qr, $scan, $redemption];
    }

    public function test_analytics_index_finance_and_report_pages_render(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();
        $this->seedAnalyticsData($business);

        $this->actingAs($owner)
            ->get('/business/analytics?period=30')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/Analytics/Index'));

        $this->actingAs($owner)
            ->get('/business/analytics/finance?period=30')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/Analytics/Finance'));

        $this->actingAs($owner)
            ->get('/business/analytics/report?period=30')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/Analytics/Report'));
    }

    public function test_chart_data_endpoint_returns_expected_shapes(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();
        $this->seedAnalyticsData($business);

        $this->actingAs($owner)
            ->getJson('/api/analytics/chart-data?type=scans&period=30')
            ->assertStatus(200)
            ->assertJsonStructure([['date', 'value']]);

        $this->actingAs($owner)
            ->getJson('/api/analytics/chart-data?type=redemptions&period=30')
            ->assertStatus(200)
            ->assertJsonStructure([['date', 'count', 'savings']]);

        $this->actingAs($owner)
            ->getJson('/api/analytics/chart-data?type=devices&period=30')
            ->assertStatus(200)
            ->assertJsonStructure([['name', 'value']]);

        $this->actingAs($owner)
            ->getJson('/api/analytics/chart-data?type=hourly&period=30')
            ->assertStatus(200)
            ->assertJsonStructure([['hour', 'label', 'value']]);

        // Unknown type => []
        $this->actingAs($owner)
            ->getJson('/api/analytics/chart-data?type=unknown&period=30')
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_export_scans_and_redemptions_stream_csv(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();
        [$promo, $qr, $scan, $redemption] = $this->seedAnalyticsData($business);

        // scans export
        $resp = $this->actingAs($owner)->get('/business/analytics/export?type=scans&period=30');
        $resp->assertStatus(200);
        $content = $resp->streamedContent();
        $this->assertStringContainsString('Date,"QR Code",Device,City,Browser', $content);

        // redemptions export (any non-scans type hits redemptions branch)
        $resp2 = $this->actingAs($owner)->get('/business/analytics/export?type=redemptions&period=30');
        $resp2->assertStatus(200);
        $content2 = $resp2->streamedContent();
        $this->assertStringContainsString('Date,Promotion,Discount,"Final Amount",Employee', $content2);
    }

    public function test_ai_insights_in_analytics_index_respects_tier_filtering(): void
    {
        // Growth tier has ai_insights false => should return []
        [$businessGrowth, $ownerGrowth] = $this->makeBusinessOwner(['subscription_tier' => 'growth']);
        $this->seedAnalyticsData($businessGrowth);

        $this->actingAs($ownerGrowth)
            ->get('/business/analytics?period=30')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Analytics/Index')
                ->where('insights', [])
            );

        // Pro tier canAccess(ai_insights)=true; with stored insights, growth-only filter is not applied.
        [$businessPro, $ownerPro] = $this->makeBusinessOwner(['subscription_tier' => 'pro']);
        $this->seedAnalyticsData($businessPro);

        AIInsight::create([
            'business_id' => $businessPro->id,
            'type' => 'recommendation',
            'category' => 'basic',
            'title' => 'Basic Insight',
            'description' => 'B',
            'priority' => 'low',
            'is_read' => false,
            'is_dismissed' => false,
        ]);
        AIInsight::create([
            'business_id' => $businessPro->id,
            'type' => 'recommendation',
            'category' => 'advanced',
            'title' => 'Advanced Insight',
            'description' => 'A',
            'priority' => 'high',
            'is_read' => false,
            'is_dismissed' => false,
        ]);

        $this->actingAs($ownerPro)
            ->get('/business/analytics?period=30')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Analytics/Index')
                ->where('insights.0.title', 'Advanced Insight')
            );
    }

    public function test_ai_insights_growth_tier_filters_to_basic_when_plan_enables_ai_insights(): void
    {
        SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth',
            'monthly_price' => 39.99,
            'yearly_price' => null,
            'features' => [
                'ai_insights' => true,
                'analytics_days' => 30,
            ],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 1,
        ]);

        [$business, $owner] = $this->makeBusinessOwner(['subscription_tier' => 'growth']);
        $this->seedAnalyticsData($business);

        AIInsight::create([
            'business_id' => $business->id,
            'type' => 'recommendation',
            'category' => 'basic',
            'title' => 'Basic Growth Insight',
            'description' => 'B',
            'priority' => 'low',
            'is_read' => false,
            'is_dismissed' => false,
        ]);
        AIInsight::create([
            'business_id' => $business->id,
            'type' => 'recommendation',
            'category' => 'advanced',
            'title' => 'Advanced Growth Insight',
            'description' => 'A',
            'priority' => 'high',
            'is_read' => false,
            'is_dismissed' => false,
        ]);

        $resp = $this->actingAs($owner)->get('/business/analytics?period=30');
        $resp->assertStatus(200);
        // Extract insights from Inertia data-page JSON
        preg_match('/data-page="([^"]+)"/', $resp->getContent(), $m);
        $page = $m ? json_decode(html_entity_decode($m[1]), true) : [];
        $insights = $page['props']['insights'] ?? [];
        $titles = array_column($insights, 'title');
        $this->assertContains('Basic Growth Insight', $titles, 'Growth tier should show basic insights');
        $this->assertNotContains('Advanced Growth Insight', $titles, 'Growth tier should filter out advanced insights');
    }

    public function test_index_generates_basic_insights_from_data_when_no_ai_insights_exist(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-25 12:00:00'));

        [$business, $owner] = $this->makeBusinessOwner(['subscription_tier' => 'pro']);
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(10),
            'discount_type' => 'percentage',
            'discount_value' => 20,
        ]);
        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'is_active' => true,
            'name' => 'QR A',
            // ensure we don't generate "Inactive QR Codes" insight
            'last_scanned_at' => now()->subHours(2),
        ]);

        // Drive redemption-rate insights (issued > 0, low redemption rate)
        $customer = User::factory()->create(['role' => 'customer']);
        for ($i = 1; $i <= 10; $i++) {
            $t = UserPromoToken::create([
                'user_id' => $customer->id,
                'qr_code_id' => $qr->id,
                'promotion_id' => $promo->id,
                'business_id' => $business->id,
                'code' => sprintf('UP-ANA-%04d', $i),
            ]);
            $t->forceFill(['created_at' => now()->subDays(2)])->save();
        }
        // redeem 1 of 10 => 10%
        UserPromoToken::where('business_id', $business->id)->first()->forceFill(['redeemed_at' => now()->subDay()])->save();

        // Create enough mobile scans to trigger peak hour and mobile-first branches
        $peakTime = now()->subDays(2)->setTime(18, 5, 0);
        $cities = ['Toronto', 'Ottawa', 'Montreal', 'Vancouver'];
        for ($i = 0; $i < 10; $i++) {
            Scan::create([
                'qr_code_id' => $qr->id,
                'business_id' => $business->id,
                'session_id' => 'sess-ana-peak-' . $i,
                'scan_type' => Scan::TYPE_PROMOTION,
                'device_type' => 'mobile',
                'browser' => 'Chrome',
                'city' => $cities[$i % count($cities)],
                'scanned_at' => $peakTime,
            ]);
        }

        // Add last-week scans so growth trend has a baseline
        for ($i = 0; $i < 5; $i++) {
            Scan::create([
                'qr_code_id' => $qr->id,
                'business_id' => $business->id,
                'session_id' => 'sess-ana-lastweek-' . $i,
                'scan_type' => Scan::TYPE_PROMOTION,
                'device_type' => 'mobile',
                'city' => 'Toronto',
                'scanned_at' => now()->subDays(10)->setTime(10, 0, 0),
            ]);
        }

        // Placement performance insight needs two placements with very different totals
        QRCode::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'placement_location' => 'front_desk',
            'total_scans' => 120,
            'name' => 'Top Placement QR',
            'last_scanned_at' => now()->subHours(2),
        ]);
        QRCode::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'placement_location' => 'table',
            'total_scans' => 10,
            'name' => 'Worst Placement QR',
            'last_scanned_at' => now()->subHours(2),
        ]);

        // Add QRcade game plays to exercise game insights
        $game = Game::create([
            'name' => 'Test Game',
            'slug' => 'test-game-' . Str::uuid(),
            'type' => Game::TYPE_TAP_COUNTER,
            'tier' => Game::TIER_BASIC,
            'is_active' => true,
        ]);
        $session = GameSession::create([
            'qr_code_id' => $qr->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'location_status' => GameSession::LOCATION_VERIFIED,
            'status' => GameSession::STATUS_COMPLETED,
            'started_at' => now()->subDay(),
        ]);
        for ($i = 0; $i < 9; $i++) {
            GamePlay::create([
                'game_session_id' => $session->id,
                'user_id' => $customer->id,
                'game_id' => $game->id,
                'business_id' => $business->id,
                'qr_code_id' => $qr->id,
                'result' => GamePlay::RESULT_WIN,
                'started_at' => now()->subDay(),
                'completed_at' => now()->subDay(),
            ]);
        }
        GamePlay::create([
            'game_session_id' => $session->id,
            'user_id' => $customer->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qr->id,
            'result' => GamePlay::RESULT_LOSE,
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ]);

        $resp = $this->actingAs($owner)->get('/business/analytics?period=30');
        $resp->assertStatus(200);
        $resp->assertInertia(fn (Assert $page) => $page->component('Business/Analytics/Index'));

        // Lightweight sanity checks that the generated insights are actually present in the response payload
        $resp->assertSee('Low Prize Redemption Rate');
        $resp->assertSee('Peak Activity Time');
        $resp->assertSee('Games May Be Too Easy');
        $resp->assertSee('Most Popular Game');
        $resp->assertSee('Mobile-First Customers');
    }

    public function test_partnerships_analytics_page_renders_and_counts_scans_and_claims(): void
    {
        [$businessA, $ownerA] = $this->makeBusinessOwner(['subscription_tier' => 'pro']);
        [$businessB, $ownerB] = $this->makeBusinessOwner(['subscription_tier' => 'pro']);

        // accepted partnership
        BusinessPartnership::create([
            'requester_business_id' => $businessA->id,
            'partner_business_id' => $businessB->id,
            'status' => BusinessPartnership::STATUS_ACCEPTED,
        ]);

        $promoA = Promotion::factory()->create(['business_id' => $businessA->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $promoB = Promotion::factory()->create(['business_id' => $businessB->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

        $cross = CrossPromotion::create([
            'code' => 'CPAN1234',
            'name' => 'Partner Deal',
            'business_1_id' => $businessA->id,
            'business_2_id' => $businessB->id,
            'requested_by_business_id' => $businessA->id,
            'promotion_1_id' => $promoA->id,
            'promotion_2_id' => $promoB->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);

        // Create a second cross promo where business A is business_2 (covers alternate partner name branch)
        $cross2 = CrossPromotion::create([
            'code' => 'CPAN5678',
            'name' => 'Partner Deal 2',
            'business_1_id' => $businessB->id,
            'business_2_id' => $businessA->id,
            'requested_by_business_id' => $businessB->id,
            'promotion_1_id' => $promoB->id,
            'promotion_2_id' => $promoA->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);

        $qr = QRCode::factory()->create([
            'business_id' => $businessA->id,
            'type' => 'cross_promo',
            'destination_url' => null,
            'is_active' => true,
            'cross_promotion_id' => $cross->id,
        ]);

        QRCode::factory()->create([
            'business_id' => $businessA->id,
            'type' => 'cross_promo',
            'destination_url' => null,
            'is_active' => true,
            'cross_promotion_id' => $cross2->id,
        ]);

        Scan::create([
            'qr_code_id' => $qr->id,
            'business_id' => $businessA->id,
            'session_id' => 'sess-cp-1',
            'scan_type' => Scan::TYPE_CROSS_PROMO,
            'scanned_at' => now()->subDay(),
        ]);

        $customer = User::factory()->create(['role' => 'customer']);
        $token = UserPromoToken::create([
            'user_id' => $customer->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promoA->id,
            'business_id' => $businessA->id,
            'code' => 'UP-CP-0001',
        ]);
        $token->forceFill(['created_at' => now()->subDay()])->save();

        $this->actingAs($ownerA)
            ->get('/business/analytics/partnerships')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Analytics/Partnerships')
                ->where('metrics.total_partners', 1)
                ->where('metrics.active_cross_promos', 2)
            );
    }
}


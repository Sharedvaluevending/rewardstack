<?php

namespace Tests\Feature;

use App\Jobs\GenerateAIInsightsForBusiness;
use App\Models\Business;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Redemption;
use App\Models\Scan;
use App\Models\User;
use App\Models\UserPromoToken;
use App\Services\DeepSeekAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessAIInsightsControllerCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Cache::flush();
        Http::preventStrayRequests();
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

    protected function seedSomeEngagement(Business $business): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-25 12:00:00'));

        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(10),
        ]);

        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'is_active' => true,
            'last_scanned_at' => now()->subHours(2),
            'placement_location' => 'front_desk',
        ]);

        $scan = Scan::create([
            'qr_code_id' => $qr->id,
            'business_id' => $business->id,
            'user_id' => User::factory()->create(['role' => 'customer'])->id,
            'session_id' => 'sess-ai-1',
            'scan_type' => Scan::TYPE_PROMOTION,
            'device_type' => 'mobile',
            'browser' => 'Chrome',
            'city' => 'Testville',
            'scanned_at' => now()->subDay(),
        ]);

        Redemption::create([
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'scan_id' => $scan->id,
            'customer_user_id' => $scan->user_id,
            'customer_identifier' => 'CUST-AI-1',
            'discount_amount' => 5.00,
            'original_amount' => 25.00,
            'final_amount' => 20.00,
            'redeemed_at' => now()->subDay(),
        ]);

        // Prize/token funnel (used by stats + fallbacks)
        $customer = User::findOrFail($scan->user_id);
        $token = UserPromoToken::create([
            'user_id' => $customer->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'code' => 'UP-AIIN-0001',
        ]);
        $token->forceFill(['created_at' => now()->subDays(2), 'redeemed_at' => now()->subDay()])->save();
    }

    protected function seedLowPerformanceScenario(Business $business): void
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
            'last_scanned_at' => now()->subHours(2),
        ]);

        $customer = User::factory()->create(['role' => 'customer']);

        // 6 redemptions with bad ROI (< 1.5) + enough volume to trigger warning path.
        for ($i = 0; $i < 6; $i++) {
            $scan = Scan::create([
                'qr_code_id' => $qr->id,
                'business_id' => $business->id,
                'user_id' => $customer->id,
                'session_id' => 'sess-ai-low-' . $i,
                'scan_type' => Scan::TYPE_PROMOTION,
                'device_type' => 'mobile',
                'browser' => 'Chrome',
                'city' => 'Testville',
                'scanned_at' => now()->subDays(2),
            ]);

            Redemption::create([
                'qr_code_id' => $qr->id,
                'promotion_id' => $promo->id,
                'business_id' => $business->id,
                'scan_id' => $scan->id,
                'customer_user_id' => $customer->id,
                'customer_identifier' => 'CUST-LOW',
                'discount_amount' => 10.00,
                'original_amount' => 25.00,
                'final_amount' => 5.00,
                'redeemed_at' => now()->subDays(2),
            ]);
        }

        // Tokens: 10 issued, 1 redeemed => <25% rate
        for ($i = 0; $i < 10; $i++) {
            $token = UserPromoToken::create([
                'user_id' => $customer->id,
                'qr_code_id' => $qr->id,
                'promotion_id' => $promo->id,
                'business_id' => $business->id,
                'code' => 'UP-LOW-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            ]);
            $token->forceFill([
                'created_at' => now()->subDays(3),
                'redeemed_at' => $i === 0 ? now()->subDays(2) : null,
            ])->save();
        }
    }

    public function test_basic_page_returns_needs_generation_when_cache_missing(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();

        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        $this->actingAs($owner)
            ->get('/business/ai-insights/basic?period=30')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/AIInsights/Basic')
                ->where('needs_generation', true)
                ->where('period', '30')
                ->where('ai_enabled', false)
            );
    }

    public function test_basic_page_uses_cached_data_when_present(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();

        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        $cacheKey = 'ai-insights:basic:' . $business->id . ':30';
        Cache::put($cacheKey, [
            'summary' => 'Cached summary',
            'quick_insights' => [['title' => 'A', 'description' => 'B', 'type' => 'success']],
            'action_items' => [['title' => 'Do', 'description' => 'Now', 'priority' => 'high']],
            'stats' => ['total_scans' => 1, 'total_redemptions' => 1, 'prize_redemption_rate' => 100, 'prizes_redeemed' => 1, 'prizes_issued' => 1, 'active_qr_codes' => 1, 'active_promotions' => 1, 'roi' => 2],
            'previous_stats' => [],
            'top_qr_codes' => [],
            'top_promotions' => [],
            'device_breakdown' => [],
            'hourly_distribution' => [],
            'ai_enabled' => true,
        ], now()->addMinutes(5));

        $this->actingAs($owner)
            ->get('/business/ai-insights/basic?period=30')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/AIInsights/Basic')
                ->where('needs_generation', false)
                ->where('summary', 'Cached summary')
                ->where('period', '30')
            );
    }

    public function test_advanced_page_shows_weekly_message_when_no_cache_and_not_regenerating(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();
        $this->seedSomeEngagement($business);

        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        $errorKey = 'ai-insights:advanced:' . $business->id . ':90:error';
        Cache::put($errorKey, 'Prior generation error', now()->addMinutes(5));

        $this->actingAs($owner)
            ->get('/business/ai-insights/advanced?period=90')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/AIInsights/Advanced')
                ->where('needs_generation', true)
                ->where('period', '90')
                ->where('error', 'Prior generation error')
            );
    }

    public function test_advanced_page_uses_cached_data_when_present(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();

        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        $cacheKey = 'ai-insights:advanced:' . $business->id . ':90';
        Cache::put($cacheKey, [
            'summary' => 'Cached advanced',
            'predictions' => ['success' => true],
            'recommendations' => [],
            'customer_segments' => [],
            'promotion_optimizations' => [],
            'stats' => ['total_scans' => 1, 'total_redemptions' => 1, 'prize_redemption_rate' => 100, 'prizes_redeemed' => 1, 'prizes_issued' => 1, 'active_qr_codes' => 1, 'active_promotions' => 1, 'roi' => 2],
            'scans_over_time' => [],
            'redemptions_over_time' => [],
            'promotions' => [],
            'customer_data' => [],
            'game_data' => [],
            'ai_enabled' => true,
        ], now()->addMinutes(10));

        $this->actingAs($owner)
            ->get('/business/ai-insights/advanced?period=90')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/AIInsights/Advanced')
                ->where('from_cache', true)
                ->where('summary', 'Cached advanced')
            );
    }

    public function test_generate_basic_returns_json_and_populates_cache_with_ai_results(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();
        $this->seedSomeEngagement($business);

        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generateSummary')->andReturn('AI summary');
            $mock->shouldReceive('generateInsight')->andReturnUsing(function (string $prompt, array $data) {
                if (str_contains($prompt, 'top 3-5 key insights')) {
                    return ['success' => true, 'content' => '[{"title":"Insight","description":"Desc","type":"success"}]'];
                }
                if (str_contains($prompt, 'actionable recommendations')) {
                    return ['success' => true, 'content' => '[{"title":"Action","description":"Do it","priority":"high"}]'];
                }
                return ['success' => true, 'content' => '[]'];
            });
        });

        $resp = $this->actingAs($owner)->postJson('/business/ai-insights/generate', [
            'type' => 'basic',
            'period' => '30',
        ]);

        $resp->assertStatus(200)->assertJson(['success' => true]);

        $cacheKey = 'ai-insights:basic:' . $business->id . ':30';
        $cached = Cache::get($cacheKey);
        $this->assertIsArray($cached);
        $this->assertSame('AI summary', $cached['summary'] ?? null);
        $this->assertNotEmpty($cached['quick_insights'] ?? []);
        $this->assertNotEmpty($cached['action_items'] ?? []);
    }

    public function test_generate_basic_inertia_request_redirects_back_to_basic_page(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();
        $this->seedSomeEngagement($business);

        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        $this->actingAs($owner)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/business/ai-insights/generate', [
                'type' => 'basic',
                'period' => '30',
            ])
            ->assertStatus(303)
            ->assertRedirect('/business/ai-insights/basic?period=30');
    }

    public function test_generate_advanced_queues_job_and_redirects(): void
    {
        Queue::fake();
        [$business, $owner] = $this->makeBusinessOwner();

        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        $this->actingAs($owner)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/business/ai-insights/generate', [
                'type' => 'advanced',
                'period' => '90',
            ])
            ->assertStatus(303)
            ->assertRedirect('/business/ai-insights/advanced?period=90');

        Queue::assertPushed(GenerateAIInsightsForBusiness::class);

        $statusKey = 'ai-insights:advanced:' . $business->id . ':90:status';
        $status = Cache::get($statusKey);
        $this->assertSame('queued', $status['state'] ?? null);
    }

    public function test_advanced_regenerate_generates_synchronously_and_caches(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();
        $this->seedSomeEngagement($business);

        // Make AI configured but keep methods cheap/deterministic.
        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generateSummary')->andReturn('Advanced AI summary');
            $mock->shouldReceive('generatePrediction')->andReturn(['success' => true, 'predictions' => []]);
            $mock->shouldReceive('generateRecommendations')->andReturn(['success' => true, 'recommendations' => []]);
            $mock->shouldReceive('analyzeCustomerSegments')->andReturn(['success' => true, 'segments' => []]);
            $mock->shouldReceive('optimizePromotions')->andReturn(['success' => true, 'optimizations' => []]);
        });

        $this->actingAs($owner)
            ->get('/business/ai-insights/advanced?period=90&regenerate=1')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/AIInsights/Advanced')
                ->where('from_cache', false)
                ->where('summary', 'Advanced AI summary')
            );

        $cacheKey = 'ai-insights:advanced:' . $business->id . ':90';
        $this->assertNotNull(Cache::get($cacheKey));
    }

    public function test_generate_basic_falls_back_when_ai_not_configured(): void
    {
        [$business, $owner] = $this->makeBusinessOwner(['subscription_tier' => 'starter']);
        $this->seedLowPerformanceScenario($business);

        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        $resp = $this->actingAs($owner)->postJson('/business/ai-insights/generate', [
            'type' => 'basic',
            'period' => '30',
        ]);

        $resp->assertStatus(200)->assertJson(['success' => true]);
        $data = $resp->json('data');

        $this->assertIsArray($data);
        $this->assertStringContainsString('Your business has generated', (string) ($data['summary'] ?? ''));
        $this->assertNotEmpty($data['quick_insights'] ?? []);
        $this->assertNotEmpty($data['action_items'] ?? []);
    }

    public function test_generate_basic_uses_fallback_arrays_when_ai_returns_non_json(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();
        $this->seedLowPerformanceScenario($business);

        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generateSummary')->andReturn(''); // force fallback summary
            $mock->shouldReceive('generateInsight')->andReturn(['success' => true, 'content' => 'not json']);
        });

        $resp = $this->actingAs($owner)->postJson('/business/ai-insights/generate', [
            'type' => 'basic',
            'period' => '30',
        ]);

        $resp->assertStatus(200)->assertJson(['success' => true]);
        $data = $resp->json('data');

        $this->assertStringContainsString('Your business has generated', (string) ($data['summary'] ?? ''));
        $this->assertNotEmpty($data['quick_insights'] ?? []);
        $this->assertNotEmpty($data['action_items'] ?? []);
    }

    public function test_generate_basic_falls_back_when_ai_returns_invalid_json_array(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();
        $this->seedLowPerformanceScenario($business);

        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generateSummary')->andReturn('AI summary (but lists broken)');
            $mock->shouldReceive('generateInsight')->andReturnUsing(function (string $prompt, array $data) {
                // Regex will match [ ... ], but JSON decoding will fail.
                if (str_contains($prompt, 'top 3-5 key insights')) {
                    return ['success' => true, 'content' => '[{bad json]'];
                }
                if (str_contains($prompt, 'actionable recommendations')) {
                    return ['success' => true, 'content' => '[{bad json]'];
                }
                return ['success' => true, 'content' => null];
            });
        });

        $resp = $this->actingAs($owner)->postJson('/business/ai-insights/generate', [
            'type' => 'basic',
            'period' => '30',
        ]);

        $resp->assertStatus(200)->assertJson(['success' => true]);
        $data = $resp->json('data');

        $this->assertSame('AI summary (but lists broken)', $data['summary'] ?? null);
        $this->assertNotEmpty($data['quick_insights'] ?? [], 'Should fall back when insights JSON is invalid');
        $this->assertNotEmpty($data['action_items'] ?? [], 'Should fall back when actions JSON is invalid');
    }

    public function test_advanced_regenerate_handles_ai_exceptions_and_returns_fallback(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();
        $this->seedSomeEngagement($business);

        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generateSummary')->andThrow(new \RuntimeException('boom'));
            $mock->shouldReceive('generatePrediction')->andThrow(new \RuntimeException('boom'));
            $mock->shouldReceive('generateRecommendations')->andThrow(new \RuntimeException('boom'));
            $mock->shouldReceive('analyzeCustomerSegments')->andThrow(new \RuntimeException('boom'));
            $mock->shouldReceive('optimizePromotions')->andThrow(new \RuntimeException('boom'));
        });

        $this->actingAs($owner)
            ->get('/business/ai-insights/advanced?period=90&regenerate=1')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/AIInsights/Advanced')
                ->where('from_cache', false)
                ->where('ai_enabled', true)
                ->where('summary', fn ($s) => is_string($s) && str_contains($s, 'Your business has generated'))
            );
    }

    public function test_advanced_regenerate_with_no_data_still_returns(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();

        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        $this->actingAs($owner)
            ->get('/business/ai-insights/advanced?period=90&regenerate=1')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/AIInsights/Advanced')
                ->where('from_cache', false)
                ->where('ai_enabled', false)
            );
    }

    public function test_advanced_regenerate_returns_fallback_when_generation_throws(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();

        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        // Force the inner "generation failed" fallback block (around generateAdvancedInsights()).
        $call = 0;
        DB::partialMock()
            ->shouldReceive('selectOne')
            ->andReturnUsing(function () use (&$call) {
                $call++;
                if ($call === 1) {
                    throw new \RuntimeException('db boom');
                }
                // Second call is used while rendering the fallback response (stats).
                return (object) [
                    'scans' => 0,
                    'unique_scans' => 0,
                    'redemptions' => 0,
                    'savings' => 0,
                    'revenue' => 0,
                    'prizes_issued' => 0,
                    'prizes_redeemed' => 0,
                    'punch_stamps' => 0,
                    'punch_completions' => 0,
                ];
            });

        $this->actingAs($owner)
            ->get('/business/ai-insights/advanced?period=90&regenerate=1')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/AIInsights/Advanced')
                ->where('summary', 'Generation timed out or failed. Please try again later or wait for weekly automatic generation.')
                ->where('error', 'Generation failed. Please try again or wait for weekly generation.')
            );
    }

    public function test_generate_returns_json_error_when_cache_throws(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();

        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        // Force the controller's outer try/catch to be exercised.
        Cache::partialMock()
            ->shouldReceive('forget')
            ->andThrow(new \RuntimeException('cache boom'));

        $this->actingAs($owner)
            ->postJson('/business/ai-insights/generate', [
                'type' => 'basic',
                'period' => '30',
            ])
            ->assertStatus(500)
            ->assertJson([
                'success' => false,
                'error' => 'Generation failed. Please try again later.',
            ]);
    }

    public function test_generate_returns_inertia_error_when_cache_throws(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();

        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        Cache::partialMock()
            ->shouldReceive('forget')
            ->andThrow(new \RuntimeException('cache boom'));

        $this->actingAs($owner)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/business/ai-insights/generate', [
                'type' => 'basic',
                'period' => '30',
            ])
            ->assertStatus(303)
            ->assertSessionHasErrors(['ai_insights']);
    }

    public function test_advanced_page_outer_catch_returns_fallback_when_cache_get_throws(): void
    {
        [$business, $owner] = $this->makeBusinessOwner();

        $this->mock(DeepSeekAIService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        Cache::partialMock()
            ->shouldReceive('get')
            ->andThrow(new \RuntimeException('cache boom'));

        $this->actingAs($owner)
            ->get('/business/ai-insights/advanced?period=90')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/AIInsights/Advanced')
                ->where('ai_enabled', false)
                ->where('error', 'Service temporarily unavailable')
            );
    }
}


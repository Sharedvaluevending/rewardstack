<?php

namespace Tests\Unit\Models;

use App\Models\GameReward;
use App\Models\Promotion;
use App\Models\Redemption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PromotionLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_currently_valid_respects_active_and_date_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-14 12:00:00'));

        $promo = Promotion::factory()->create([
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $this->assertTrue($promo->isCurrentlyValid());

        $promo->update(['is_active' => false]);
        $this->assertFalse($promo->fresh()->isCurrentlyValid());

        $promo->update(['is_active' => true, 'starts_at' => now()->addDay()]);
        $this->assertFalse($promo->fresh()->isCurrentlyValid());

        $promo->update(['starts_at' => now()->subDays(2), 'ends_at' => now()->subMinute()]);
        $this->assertFalse($promo->fresh()->isCurrentlyValid());
    }

    public function test_can_redeem_enforces_daily_limit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-14 12:00:00'));

        $promo = Promotion::factory()->create([
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'rules' => ['max_per_day' => 2],
            'total_redemptions' => 0,
        ]);

        Redemption::create([
            'promotion_id' => $promo->id,
            'business_id' => $promo->business_id,
            'redeemed_at' => now(),
        ]);
        Redemption::create([
            'promotion_id' => $promo->id,
            'business_id' => $promo->business_id,
            'redeemed_at' => now(),
        ]);

        $res = $promo->fresh()->canRedeem();
        $this->assertFalse($res['allowed']);
        $this->assertSame('Daily limit reached for this offer. Try again tomorrow!', $res['reason']);
    }

    public function test_can_redeem_enforces_valid_days_and_hours_including_overnight_window(): void
    {
        // Overnight window: 22:00 -> 02:00
        // Promotion::canRedeem checks valid_hours in a localized timezone (defaults to America/Toronto
        // when app.timezone is UTC). Set the test "now" in that timezone so the window math matches.
        Carbon::setTestNow(Carbon::parse('2026-01-14 23:30:00', 'America/Toronto')); // Wednesday

        $promo = Promotion::factory()->create([
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'rules' => [
                'valid_days' => ['wednesday'],
                'valid_hours' => ['start' => '22:00', 'end' => '02:00'],
            ],
        ]);

        $ok = $promo->canRedeem();
        $this->assertTrue($ok['allowed']);

        // Same overnight window but on wrong day should reject
        Carbon::setTestNow(Carbon::parse('2026-01-15 23:30:00', 'America/Toronto')); // Thursday
        $bad = $promo->fresh()->canRedeem();
        $this->assertFalse($bad['allowed']);
        $this->assertStringContainsString('only valid between', strtolower((string) $bad['reason']));
    }

    public function test_calculate_discount_percentage_accepts_decimal_and_whole_number_formats(): void
    {
        $promoDecimal = Promotion::factory()->create([
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 0.15, // 15%
        ]);
        $res1 = $promoDecimal->calculateDiscount(100.00, 1);
        $this->assertEquals(15.00, round($res1['discount'], 2));

        $promoWhole = Promotion::factory()->create([
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 15, // 15%
        ]);
        $res2 = $promoWhole->calculateDiscount(100.00, 1);
        $this->assertEquals(15.00, round($res2['discount'], 2));
    }

    public function test_has_reached_per_user_limit_returns_false_when_unlimited(): void
    {
        $promo = Promotion::factory()->create(['rules' => []]);
        $user = User::factory()->create();
        $this->assertFalse($promo->hasReachedPerUserLimit($user->id));
    }

    public function test_has_reached_per_user_limit_returns_false_when_under_limit(): void
    {
        $promo = Promotion::factory()->create([
            'rules' => ['max_redemptions_per_user' => 2],
        ]);
        $user = User::factory()->create();
        Redemption::create([
            'promotion_id' => $promo->id,
            'business_id' => $promo->business_id,
            'customer_user_id' => $user->id,
            'redeemed_at' => now(),
        ]);
        $this->assertFalse($promo->hasReachedPerUserLimit($user->id));
    }

    public function test_has_reached_per_user_limit_returns_true_when_redemptions_at_limit(): void
    {
        $promo = Promotion::factory()->create([
            'rules' => ['max_redemptions_per_user' => 2],
        ]);
        $user = User::factory()->create();
        Redemption::create([
            'promotion_id' => $promo->id,
            'business_id' => $promo->business_id,
            'customer_user_id' => $user->id,
            'redeemed_at' => now(),
        ]);
        Redemption::create([
            'promotion_id' => $promo->id,
            'business_id' => $promo->business_id,
            'customer_user_id' => $user->id,
            'redeemed_at' => now(),
        ]);
        $this->assertTrue($promo->hasReachedPerUserLimit($user->id));
    }

    public function test_has_reached_per_user_limit_counts_unredeemed_game_rewards(): void
    {
        Notification::fake();
        $promo = Promotion::factory()->create([
            'rules' => ['max_redemptions_per_user' => 1],
        ]);
        $user = User::factory()->create(['role' => 'customer']);
        GameReward::factory()->create([
            'user_id' => $user->id,
            'promotion_id' => $promo->id,
            'business_id' => $promo->business_id,
            'status' => GameReward::STATUS_AVAILABLE,
        ]);
        $this->assertTrue($promo->hasReachedPerUserLimit($user->id));
    }

    public function test_has_reached_per_user_limit_does_not_count_redeemed_game_rewards(): void
    {
        Notification::fake();
        $promo = Promotion::factory()->create([
            'rules' => ['max_redemptions_per_user' => 2],
        ]);
        $user = User::factory()->create(['role' => 'customer']);
        GameReward::factory()->create([
            'user_id' => $user->id,
            'promotion_id' => $promo->id,
            'business_id' => $promo->business_id,
            'status' => GameReward::STATUS_REDEEMED,
        ]);
        $this->assertFalse($promo->hasReachedPerUserLimit($user->id));
    }
}


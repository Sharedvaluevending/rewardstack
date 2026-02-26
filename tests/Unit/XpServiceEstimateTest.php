<?php

namespace Tests\Unit;

use App\Models\Badge;
use App\Models\Business;
use App\Models\QRCode;
use App\Models\Scan;
use App\Models\User;
use App\Services\XpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XpServiceEstimateTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimate_redemption_xp_from_savings_returns_zero_for_zero_savings(): void
    {
        $service = new XpService();
        $this->assertSame(0, $service->estimateRedemptionXpFromSavings(0.0));
    }

    public function test_estimate_redemption_xp_from_savings_returns_positive_xp_for_positive_savings(): void
    {
        $service = new XpService();
        $xp = $service->estimateRedemptionXpFromSavings(10.0);
        $this->assertGreaterThan(0, $xp);
    }

    public function test_estimate_redemption_xp_from_savings_returns_zero_for_negative_savings(): void
    {
        $service = new XpService();
        $this->assertSame(0, $service->estimateRedemptionXpFromSavings(-5.0));
    }

    public function test_source_constants_are_defined(): void
    {
        $this->assertSame('scan', XpService::SOURCE_SCAN);
        $this->assertSame('game', XpService::SOURCE_GAME);
        $this->assertSame('redemption', XpService::SOURCE_REDEMPTION);
        $this->assertSame('reward_redemption', XpService::SOURCE_REWARD_REDEMPTION);
        $this->assertSame('badge', XpService::SOURCE_BADGE);
        $this->assertSame('merch_scan', XpService::SOURCE_MERCH_SCAN);
        $this->assertSame('merch_redemption', XpService::SOURCE_MERCH_REDEMPTION);
    }

    public function test_award_for_badge_returns_zero_when_badge_has_zero_points(): void
    {
        $user = User::factory()->create();
        $badge = Badge::create(['name' => 'Zero Badge', 'slug' => 'zero-badge-' . uniqid(), 'points' => 0]);
        $service = new XpService();
        $amount = $service->awardForBadge($user, $badge);
        $this->assertSame(0, $amount);
    }

    public function test_award_for_badge_awards_xp_when_badge_has_points(): void
    {
        $user = User::factory()->create();
        $badge = Badge::create(['name' => 'Points Badge', 'slug' => 'points-badge-' . uniqid(), 'points' => 5]);
        $service = new XpService();
        $amount = $service->awardForBadge($user, $badge);
        $this->assertGreaterThan(0, $amount);
    }

    public function test_award_for_scan_awards_xp_within_cap(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $scan = Scan::create([
            'qr_code_id' => $qrCode->id,
            'business_id' => $business->id,
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now(),
        ]);
        $service = new XpService();
        $amount = $service->awardForScan($user, $scan, $qrCode);
        $this->assertGreaterThan(0, $amount);
    }
}

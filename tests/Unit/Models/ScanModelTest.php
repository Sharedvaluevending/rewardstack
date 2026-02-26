<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\QRCode;
use App\Models\Redemption;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_type_constants_are_defined(): void
    {
        $this->assertSame('promotion', Scan::TYPE_PROMOTION);
        $this->assertSame('qrcade_game', Scan::TYPE_QRCADE_GAME);
        $this->assertSame('qrcade_leaderboard', Scan::TYPE_QRCADE_LEADERBOARD);
        $this->assertSame('info', Scan::TYPE_INFO);
        $this->assertSame('stackable', Scan::TYPE_STACKABLE);
        $this->assertSame('cross_promo', Scan::TYPE_CROSS_PROMO);
        $this->assertSame('punch_card', Scan::TYPE_PUNCH_CARD);
    }

    public function test_scan_belongs_to_business(): void
    {
        $business = Business::factory()->create();
        $scan = Scan::create([
            'qr_code_id' => QRCode::factory()->create(['business_id' => $business->id])->id,
            'business_id' => $business->id,
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now(),
        ]);
        $this->assertInstanceOf(Business::class, $scan->business);
        $this->assertEquals($business->id, $scan->business->id);
    }

    public function test_scan_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $qr = QRCode::factory()->create();
        $scan = Scan::create([
            'qr_code_id' => $qr->id,
            'business_id' => $qr->business_id,
            'user_id' => $user->id,
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now(),
        ]);
        $this->assertInstanceOf(User::class, $scan->user);
        $this->assertEquals($user->id, $scan->user->id);
    }

    public function test_scan_has_one_redemption(): void
    {
        $promo = \App\Models\Promotion::factory()->create();
        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $promo->business_id,
            'promotion_id' => $promo->id,
        ]);
        $scan = Scan::create([
            'qr_code_id' => $qr->id,
            'business_id' => $qr->business_id,
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now(),
        ]);
        Redemption::create([
            'scan_id' => $scan->id,
            'qr_code_id' => $qr->id,
            'business_id' => $qr->business_id,
            'promotion_id' => $promo->id,
            'discount_amount' => 5,
            'original_amount' => 20,
            'final_amount' => 15,
            'redeemed_at' => now(),
        ]);
        $this->assertInstanceOf(Redemption::class, $scan->fresh()->redemption);
    }
}

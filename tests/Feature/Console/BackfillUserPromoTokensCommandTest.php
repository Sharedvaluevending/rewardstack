<?php

namespace Tests\Feature\Console;

use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillUserPromoTokensCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exits_success_when_no_scans(): void
    {
        $this->artisan('promo:backfill-tokens')
            ->expectsOutputToContain('Found 0 scans to process')
            ->expectsOutputToContain('Backfill complete!')
            ->assertExitCode(0);
    }

    public function test_command_creates_token_for_scan_without_token(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $promotion = Promotion::factory()->create();
        $qrCode = QRCode::factory()->promotion()->create([
            'business_id' => $promotion->business_id,
            'promotion_id' => $promotion->id,
        ]);
        Scan::create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'business_id' => $qrCode->business_id,
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now(),
        ]);

        $this->artisan('promo:backfill-tokens')
            ->expectsOutputToContain('Found 1 scans to process')
            ->expectsOutputToContain('Created: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('user_promo_tokens', [
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
        ]);
    }

    public function test_command_skips_when_token_already_exists(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $promotion = Promotion::factory()->create();
        $qrCode = QRCode::factory()->promotion()->create([
            'business_id' => $promotion->business_id,
            'promotion_id' => $promotion->id,
        ]);
        Scan::create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'business_id' => $qrCode->business_id,
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now(),
        ]);
        \App\Models\UserPromoToken::create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'promotion_id' => $promotion->id,
            'business_id' => $qrCode->business_id,
            'code' => 'UP-TEST-1234',
        ]);

        $this->artisan('promo:backfill-tokens')
            ->expectsOutputToContain('Found 1 scans to process')
            ->expectsOutputToContain('Skipped: 1')
            ->assertExitCode(0);
    }

    public function test_command_filters_by_user_id_option(): void
    {
        $user1 = User::factory()->create(['role' => 'user']);
        $user2 = User::factory()->create(['role' => 'user']);
        $promotion = Promotion::factory()->create();
        $qrCode = QRCode::factory()->promotion()->create([
            'business_id' => $promotion->business_id,
            'promotion_id' => $promotion->id,
        ]);
        Scan::create([
            'user_id' => $user1->id,
            'qr_code_id' => $qrCode->id,
            'business_id' => $qrCode->business_id,
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now(),
        ]);
        Scan::create([
            'user_id' => $user2->id,
            'qr_code_id' => $qrCode->id,
            'business_id' => $qrCode->business_id,
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now(),
        ]);

        $this->artisan('promo:backfill-tokens', ['--user-id' => (string) $user1->id])
            ->expectsOutputToContain('Found 1 scans to process')
            ->assertExitCode(0);
    }
}

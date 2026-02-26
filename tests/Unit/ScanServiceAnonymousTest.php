<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\QRCode;
use App\Models\Scan;
use App\Models\User;
use App\Services\ScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanServiceAnonymousTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_anonymous_scan_puts_scan_id_in_session(): void
    {
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $scan = Scan::create([
            'qr_code_id' => $qrCode->id,
            'business_id' => $business->id,
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now(),
        ]);

        $service = new ScanService();
        $service->storeAnonymousScan($scan);

        $anonymous = session()->get('anonymous_scans', []);
        $this->assertCount(1, $anonymous);
        $this->assertSame($scan->id, (int) ($anonymous[0]['scan_id'] ?? 0));
    }

    public function test_store_anonymous_scan_ignores_duplicate(): void
    {
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $scan = Scan::create([
            'qr_code_id' => $qrCode->id,
            'business_id' => $business->id,
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now(),
        ]);

        $service = new ScanService();
        $service->storeAnonymousScan($scan);
        $service->storeAnonymousScan($scan);

        $anonymous = session()->get('anonymous_scans', []);
        $this->assertCount(1, $anonymous);
    }

    public function test_store_anonymous_scan_ignores_invalid_scan_id(): void
    {
        $service = new ScanService();
        $scan = new Scan();
        $scan->id = 0;

        $service->storeAnonymousScan($scan);

        $this->assertEmpty(session()->get('anonymous_scans', []));
    }

    public function test_associate_anonymous_scans_attaches_scans_to_user(): void
    {
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $scan = Scan::create([
            'qr_code_id' => $qrCode->id,
            'business_id' => $business->id,
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now(),
            'user_id' => null,
        ]);
        session()->put('anonymous_scans', [['scan_id' => $scan->id]]);
        $user = User::factory()->create();
        $service = new ScanService();

        $updated = $service->associateAnonymousScans($user);

        $this->assertSame(1, $updated);
        $scan->refresh();
        $this->assertSame((int) $user->id, (int) $scan->user_id);
        $this->assertNull(session()->get('anonymous_scans'));
    }

    public function test_associate_anonymous_scans_skips_already_owned_scan(): void
    {
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $otherUser = User::factory()->create();
        $scan = Scan::create([
            'qr_code_id' => $qrCode->id,
            'business_id' => $business->id,
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now(),
            'user_id' => $otherUser->id,
        ]);
        session()->put('anonymous_scans', [['scan_id' => $scan->id]]);
        $user = User::factory()->create();
        $service = new ScanService();

        $updated = $service->associateAnonymousScans($user);

        $this->assertSame(0, $updated);
        $scan->refresh();
        $this->assertSame((int) $otherUser->id, (int) $scan->user_id);
    }

    public function test_associate_anonymous_scans_with_pre_login_session_id(): void
    {
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $preLoginSessionId = 'old-session-123';
        $scan = Scan::create([
            'qr_code_id' => $qrCode->id,
            'business_id' => $business->id,
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now()->subHours(2),
            'session_id' => $preLoginSessionId,
            'user_id' => null,
        ]);
        session()->put('anonymous_scans', []);
        $user = User::factory()->create();
        $service = new ScanService();

        $updated = $service->associateAnonymousScans($user, $preLoginSessionId);

        $this->assertSame(1, $updated);
        $scan->refresh();
        $this->assertSame((int) $user->id, (int) $scan->user_id);
    }

    public function test_associate_anonymous_scans_ignores_invalid_scan_id_in_session(): void
    {
        session()->put('anonymous_scans', [['scan_id' => 0], ['scan_id' => 99999]]);
        $user = User::factory()->create();
        $service = new ScanService();

        $updated = $service->associateAnonymousScans($user);

        $this->assertSame(0, $updated);
        $this->assertNull(session()->get('anonymous_scans'));
    }
}

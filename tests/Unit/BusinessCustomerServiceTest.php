<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\BusinessCustomer;
use App\Models\QRCode;
use App\Models\Scan;
use App\Models\User;
use App\Services\BusinessCustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessCustomerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_scan_returns_early_when_scan_has_no_user_id(): void
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

        $service = new BusinessCustomerService();
        $service->recordScan($scan);

        $this->assertDatabaseCount('business_customers', 0);
    }

    public function test_record_saved_creates_or_updates_business_customer(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();

        $service = new BusinessCustomerService();
        $service->recordSaved($business->id, $user->id);

        $this->assertDatabaseHas('business_customers', [
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);
        $row = BusinessCustomer::where('business_id', $business->id)->where('user_id', $user->id)->first();
        $this->assertGreaterThanOrEqual(1, (int) $row->saved_count);
    }

    public function test_record_saved_returns_early_when_user_not_found(): void
    {
        $business = Business::factory()->create();
        $service = new BusinessCustomerService();

        $service->recordSaved($business->id, 99999);

        $this->assertDatabaseCount('business_customers', 0);
    }
}

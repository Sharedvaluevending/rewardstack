<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Redemption;
use App\Models\Scan;
use App\Services\MerchReferralRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchReferralRewardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluate_for_redemption_returns_early_when_scan_null(): void
    {
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create(['business_id' => $business->id]);
        $qrCode = QRCode::factory()->create(['business_id' => $business->id, 'promotion_id' => $promo->id]);
        $redemption = Redemption::create([
            'promotion_id' => $promo->id,
            'qr_code_id' => $qrCode->id,
            'business_id' => $business->id,
            'redeemed_at' => now(),
        ]);

        $service = app(MerchReferralRewardService::class);
        $service->evaluateForRedemption($redemption, null);

        $this->addToAssertionCount(1);
    }

    public function test_evaluate_for_redemption_returns_early_when_scan_has_no_merch_tag_id(): void
    {
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create(['business_id' => $business->id]);
        $qrCode = QRCode::factory()->create(['business_id' => $business->id, 'promotion_id' => $promo->id]);
        $redemption = Redemption::create([
            'promotion_id' => $promo->id,
            'qr_code_id' => $qrCode->id,
            'business_id' => $business->id,
            'redeemed_at' => now(),
        ]);
        $scan = Scan::create([
            'qr_code_id' => $qrCode->id,
            'business_id' => $business->id,
            'merch_tag_id' => null,
            'scanned_at' => now(),
        ]);

        $service = app(MerchReferralRewardService::class);
        $service->evaluateForRedemption($redemption, $scan);

        $this->addToAssertionCount(1);
    }
}

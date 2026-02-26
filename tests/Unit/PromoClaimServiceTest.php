<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\SavedQRCode;
use App\Models\Scan;
use App\Models\User;
use App\Services\BusinessCustomerService;
use App\Services\PromoClaimService;
use App\Services\UserPromoTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PromoClaimServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeService(): PromoClaimService
    {
        return new PromoClaimService(
            app(UserPromoTokenService::class),
            app(BusinessCustomerService::class)
        );
    }

    public function test_remember_pending_promo_stores_code_in_session(): void
    {
        $request = Request::create('/');
        $request->setLaravelSession(session());
        $service = $this->makeService();

        $service->rememberPendingPromo($request, 'PROMO123');

        $this->assertSame('PROMO123', $request->session()->get('pending_promo_code'));
        $this->assertNotNull($request->session()->get('pending_promo_set_at'));
    }

    public function test_clear_pending_promo_removes_session_keys(): void
    {
        $request = Request::create('/');
        $request->setLaravelSession(session());
        $request->session()->put('pending_promo_code', 'PROMO123');
        $request->session()->put('pending_promo_set_at', now()->timestamp);
        $service = $this->makeService();

        $service->clearPendingPromo($request);

        $this->assertNull($request->session()->get('pending_promo_code'));
        $this->assertNull($request->session()->get('pending_promo_set_at'));
    }

    public function test_claim_pending_promo_returns_false_when_no_code_in_session(): void
    {
        $request = Request::create('/');
        $request->setLaravelSession(session());
        $user = User::factory()->create();
        $service = $this->makeService();

        $result = $service->claimPendingPromo($request, $user);

        $this->assertFalse($result);
    }

    public function test_claim_pending_promo_returns_false_when_code_not_found(): void
    {
        $request = Request::create('/');
        $request->setLaravelSession(session());
        $request->session()->put('pending_promo_code', 'INVALID_CODE');
        $user = User::factory()->create();
        $service = $this->makeService();

        $result = $service->claimPendingPromo($request, $user);

        $this->assertFalse($result);
        $this->assertNull($request->session()->get('pending_promo_code'));
    }

    public function test_claim_pending_promo_returns_false_when_qr_not_promotion_type(): void
    {
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'OTHER123',
            'type' => 'game',
        ]);
        $request = Request::create('/');
        $request->setLaravelSession(session());
        $request->session()->put('pending_promo_code', 'OTHER123');
        $user = User::factory()->create();
        $service = $this->makeService();

        $result = $service->claimPendingPromo($request, $user);

        $this->assertFalse($result);
    }

    public function test_claim_pending_promo_claims_and_clears_session_when_valid(): void
    {
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create(['business_id' => $business->id]);
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'code' => 'VALIDPROMO',
            'type' => 'promotion',
        ]);
        $request = Request::create('/');
        $request->setLaravelSession(session());
        $request->session()->put('pending_promo_code', 'VALIDPROMO');
        $user = User::factory()->create();

        $service = $this->makeService();
        $result = $service->claimPendingPromo($request, $user);

        $this->assertTrue($result);
        $this->assertNull($request->session()->get('pending_promo_code'));
        $this->assertTrue(SavedQRCode::where('user_id', $user->id)->where('qr_code_id', $qrCode->id)->exists());
    }

    public function test_claim_promo_creates_saved_qr_and_ensures_token(): void
    {
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create(['business_id' => $business->id]);
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'type' => 'promotion',
        ]);
        $request = Request::create('/');
        $request->setLaravelSession(session());
        $user = User::factory()->create();
        $service = $this->makeService();

        $service->claimPromo($request, $user, $qrCode);

        $saved = SavedQRCode::where('user_id', $user->id)->where('qr_code_id', $qrCode->id)->first();
        $this->assertNotNull($saved);
    }

    public function test_claim_promo_attaches_recent_scan_by_session(): void
    {
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create(['business_id' => $business->id]);
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'type' => 'promotion',
        ]);
        $sessionId = session()->getId();
        $scan = Scan::create([
            'qr_code_id' => $qrCode->id,
            'business_id' => $business->id,
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now()->subMinutes(5),
            'session_id' => $sessionId,
            'user_id' => null,
        ]);
        $request = Request::create('/');
        $request->setLaravelSession(session());
        $user = User::factory()->create();
        $service = $this->makeService();

        $service->claimPromo($request, $user, $qrCode);

        $scan->refresh();
        $this->assertSame((int) $user->id, (int) $scan->user_id);
    }
}

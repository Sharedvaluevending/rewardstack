<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\MerchReferralAward;
use App\Models\MerchReferralReward;
use App\Models\MerchTag;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Scan;
use App\Models\User;
use App\Models\UserPromoToken;
use App\Services\BusinessCustomerService;
use App\Services\QRGeneratorService;
use App\Services\XpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalMerchAmbassadorFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_merch_tag_claim_requires_login_then_claims_and_shows_in_portal_merch(): void
    {
        $business = Business::factory()->create();
        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
        ]);
        $qrCode = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promotion->id,
            'type' => 'merch_referral',
        ]);

        $tag = MerchTag::create([
            'code' => 'TAGCLAIM01',
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'owner_user_id' => null,
            'claimed_at' => null,
            'is_active' => true,
        ]);

        $this->get('/m/' . $tag->code . '/claim')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/MerchClaim')
                ->where('tag.code', $tag->code)
                ->where('tag.is_claimed', false)
            );

        // POST without auth should redirect to login with redirect_to param.
        $this->post('/m/' . $tag->code . '/claim')
            ->assertStatus(302)
            ->assertRedirectContains('/login');

        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->post('/m/' . $tag->code . '/claim')
            ->assertStatus(302)
            ->assertRedirect(route('scan', $qrCode->code))
            ->assertSessionHas('success', 'Merch claimed! You can now share this code with others.');

        $tag->refresh();
        $this->assertSame($customer->id, $tag->owner_user_id);
        $this->assertNotNull($tag->claimed_at);

        $this->actingAs($customer)
            ->get('/portal/merch')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Merch/Index')
                ->has('items', 1)
                ->where('items.0.code', $tag->code)
                ->where('summary.total_merch', 1)
            );
    }

    public function test_portal_merch_index_includes_scan_redemption_and_award_stats(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $business = Business::factory()->create();

        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
        ]);
        $qrCode = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promotion->id,
            'type' => 'merch_referral',
        ]);

        MerchReferralReward::create([
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'reward_type' => 'percent',
            'reward_value' => 20,
            'reward_item_value' => 15,
            'reward_description' => '20% off',
            'redemptions_required' => 2,
            'is_active' => true,
        ]);

        $tag = MerchTag::create([
            'code' => 'TAGPORTAL01',
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'owner_user_id' => $customer->id,
            'claimed_at' => now(),
            'is_active' => true,
        ]);

        // 3 scans, 2 unique sessions.
        Scan::create([
            'qr_code_id' => $qrCode->id,
            'merch_tag_id' => $tag->id,
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'session_id' => 'sess-a',
            'scanned_at' => now()->subMinutes(2),
        ]);
        Scan::create([
            'qr_code_id' => $qrCode->id,
            'merch_tag_id' => $tag->id,
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'session_id' => 'sess-a',
            'scanned_at' => now()->subMinute(),
        ]);
        $latestScan = Scan::create([
            'qr_code_id' => $qrCode->id,
            'merch_tag_id' => $tag->id,
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'session_id' => 'sess-b',
            'scanned_at' => now(),
        ]);

        // Award record (with an attached token) to show in portal.
        $token = UserPromoToken::create([
            'user_id' => $customer->id,
            'qr_code_id' => $qrCode->id,
            'promotion_id' => $promotion->id,
            'business_id' => $business->id,
            'code' => 'UP-TEST-0001',
            'qr_image_path' => 'qrcodes/test.png',
        ]);
        MerchReferralAward::create([
            'merch_tag_id' => $tag->id,
            'owner_user_id' => $customer->id,
            'reward_id' => $qrCode->merchReferralReward->id,
            'user_promo_token_id' => $token->id,
            'redemptions_count_snapshot' => 2,
            'awarded_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get('/portal/merch')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Merch/Index')
                ->has('items', 1)
                ->where('items.0.code', $tag->code)
                ->where('items.0.stats.total_scans', 3)
                ->where('items.0.stats.unique_scans', 2)
                ->where('items.0.awards_count', 1)
                ->where('items.0.awards.0.token_code', $token->code)
            );

        $this->actingAs($customer)
            ->get('/portal/merch/ping')
            ->assertStatus(200)
            ->assertJson([
                'scan_id' => $latestScan->id,
                'merch_tag_id' => $tag->id,
            ]);
    }

    public function test_employee_redemption_of_merch_referral_creates_ambassador_award_with_reward_token(): void
    {
        $this->withoutExceptionHandling();

        // Avoid any expensive QR generation; just return a stable storage path.
        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andReturn('qrcodes/reward-token.png');
        });
        $this->mock(BusinessCustomerService::class, function ($mock) {
            $mock->shouldReceive('recordRedemption')->andReturnNull();
        });
        $this->mock(XpService::class, function ($mock) {
            $mock->shouldReceive('awardForRedemption')->andReturn(0);
            $mock->shouldReceive('awardForMerchRedemption')->andReturn(0);
        });

        $businessOwner = User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create(['user_id' => $businessOwner->id]);

        $employeeUser = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'business_id' => $business->id,
            'user_id' => $employeeUser->id,
            'role' => 'employee',
            'can_redeem' => true,
            'is_active' => true,
        ]);

        $promotion = Promotion::factory()->percentage(20)->create([
            'business_id' => $business->id,
            'original_price' => null, // requires calculator input
        ]);
        $merchReferralQr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promotion->id,
            'type' => 'merch_referral',
        ]);

        // Reward QR + promotion used for the ambassador reward token.
        $rewardPromo = Promotion::factory()->fixedAmount(5)->create([
            'business_id' => $business->id,
        ]);
        $rewardQr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $rewardPromo->id,
            'type' => 'promotion',
        ]);

        $ambassador = User::factory()->create(['role' => 'customer']);
        $tag = MerchTag::create([
            'code' => 'TAGAMB001',
            'business_id' => $business->id,
            'qr_code_id' => $merchReferralQr->id,
            'owner_user_id' => $ambassador->id,
            'claimed_at' => now(),
            'is_active' => true,
        ]);

        MerchReferralReward::create([
            'business_id' => $business->id,
            'qr_code_id' => $merchReferralQr->id,
            'reward_type' => 'promo_token',
            'reward_value' => null,
            'reward_item_value' => null,
            'reward_description' => 'Ambassador reward',
            'reward_qr_code_id' => $rewardQr->id,
            'redemptions_required' => 1,
            'is_active' => true,
        ]);

        $customer = User::factory()->create(['role' => 'customer']);

        // Create customer token + scan so redemption can link scan->merch_tag_id.
        $customerToken = UserPromoToken::create([
            'user_id' => $customer->id,
            'qr_code_id' => $merchReferralQr->id,
            'promotion_id' => $promotion->id,
            'business_id' => $business->id,
            'code' => 'UP-AAAA-BBBB',
        ]);
        Scan::create([
            'qr_code_id' => $merchReferralQr->id,
            'merch_tag_id' => $tag->id,
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'session_id' => 'sess-cust',
            'scanned_at' => now(),
        ]);

        $this->actingAs($employeeUser)
            ->postJson('/employee/redeem/' . $customerToken->code, [
                'original_amount' => 100.00,
            ])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseCount('merch_referral_awards', 1);
        $award = MerchReferralAward::query()->first();
        $this->assertNotNull($award);
        $this->assertSame($tag->id, $award->merch_tag_id);
        $this->assertSame($ambassador->id, $award->owner_user_id);
        $this->assertNotNull($award->user_promo_token_id);

        $rewardToken = UserPromoToken::query()->find($award->user_promo_token_id);
        $this->assertNotNull($rewardToken);
        $this->assertSame('qrcodes/reward-token.png', $rewardToken->qr_image_path);
        $this->assertSame($ambassador->id, $rewardToken->user_id);
        $this->assertSame($rewardPromo->id, $rewardToken->promotion_id);
        $this->assertSame($rewardQr->id, $rewardToken->qr_code_id);
    }
}


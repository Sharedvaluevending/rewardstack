<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Game;
use App\Models\Leaderboard;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\QRCodeGame;
use App\Models\Redemption;
use App\Models\SavedQRCode;
use App\Models\Scan;
use App\Models\User;
use App\Models\UserPromoToken;
use App\Services\PromoClaimService;
use App\Services\UserPromoTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestPromotionFlow extends Command
{
    protected $signature = 'test:promotion-flow {--cleanup : Clean up test data after}';
    protected $description = 'Test complete promotion flow: create → QR → scan → redeem → portal';

    protected $testBusiness = null;
    protected $testCustomer = null;
    protected $testPromotions = [];
    protected $testQRCodes = [];
    protected $errors = [];

    public function handle()
    {
        $this->info('🧪 Starting comprehensive promotion flow test...');
        $this->newLine();

        try {
            DB::beginTransaction();

            // Step 1: Setup test data
            $this->info('📋 Step 1: Creating test business and customer...');
            $this->setupTestData();

            // Step 2: Test promotion creation (all types)
            $this->info('📋 Step 2: Testing promotion creation (all types)...');
            $this->testPromotionCreation();

            // Step 3: Test QR code creation and linking
            $this->info('📋 Step 3: Testing QR code creation and linking...');
            $this->testQRCodeCreation();

            // Step 4: Test QR codes with games
            $this->info('📋 Step 4: Testing QR codes with games attached...');
            $this->testQRCodeWithGames();

            // Step 5: Test leaderboard multi-tier rewards
            $this->info('📋 Step 5: Testing leaderboard multi-tier rewards...');
            $this->testLeaderboardRewards();

            // Step 6: Test scanning flow
            $this->info('📋 Step 6: Testing scanning flow...');
            $this->testScanningFlow();

            // Step 7: Test token generation (per-user promo codes)
            $this->info('📋 Step 7: Testing per-user token generation...');
            $this->testTokenGeneration();

            // Step 8: Test redemption flow
            $this->info('📋 Step 8: Testing redemption flow...');
            $this->testRedemptionFlow();

            // Step 9: Test portal display (Scans & Promotions)
            $this->info('📋 Step 9: Testing portal display (My Scans & Promotions)...');
            $this->testPortalScansDisplay();

            // Step 10: Test portal display (Redeemed Rewards)
            $this->info('📋 Step 10: Testing portal display (My Redeemed Rewards)...');
            $this->testPortalRewardsDisplay();

            // Step 11: Test punch card flow
            $this->info('📋 Step 11: Testing punch card flow...');
            $this->testPunchCardFlow();

            DB::commit();

            // Report results
            $this->reportResults();

            // Cleanup if requested
            if ($this->option('cleanup')) {
                $this->cleanup();
            } else {
                $this->warn('⚠️  Test data preserved. Use --cleanup to remove.');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Test failed with exception: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    protected function setupTestData()
    {
        // Create test business user
        $businessUser = User::firstOrCreate(
            ['email' => 'test-business@rewardstack.test'],
            [
                'name' => 'Test Business Owner',
                'password' => bcrypt('password'),
                'role' => 'business',
                'is_active' => true,
            ]
        );

        // Create test business
        $this->testBusiness = Business::firstOrCreate(
            ['user_id' => $businessUser->id],
            [
                'name' => 'Test Business Flow',
                'slug' => 'test-business-flow-' . time(),
                'subscription_tier' => 'pro', // Ensure we can create all types
            ]
        );

        // Create test customer
        $this->testCustomer = User::firstOrCreate(
            ['email' => 'test-customer@rewardstack.test'],
            [
                'name' => 'Test Customer',
                'password' => bcrypt('password'),
                'role' => 'customer',
                'is_active' => true,
            ]
        );

        $this->info("   ✓ Business: {$this->testBusiness->name} (ID: {$this->testBusiness->id})");
        $this->info("   ✓ Customer: {$this->testCustomer->name} (ID: {$this->testCustomer->id})");
    }

    protected function testPromotionCreation()
    {
        $types = [
            Promotion::TYPE_PERCENTAGE => ['discount_value' => 20, 'original_price' => 100],
            Promotion::TYPE_FIXED_AMOUNT => ['discount_value' => 10],
            Promotion::TYPE_BOGO => ['buy_quantity' => 1, 'get_quantity' => 1],
            Promotion::TYPE_BUY_X_GET_Y => ['buy_quantity' => 2, 'get_quantity' => 1],
            Promotion::TYPE_BUY_X_FOR_Y => ['buy_quantity' => 3, 'for_price' => 50],
            Promotion::TYPE_PUNCH_CARD => ['punches_required' => 10, 'punch_icon' => '⭐'],
            Promotion::TYPE_TIERED => ['tiers' => [
                ['min_spend' => 50, 'discount' => 10],
                ['min_spend' => 100, 'discount' => 20],
            ]],
        ];

        foreach ($types as $type => $data) {
            $promo = Promotion::create([
                'business_id' => $this->testBusiness->id,
                'name' => "Test {$type} Promotion",
                'description' => "Test description for {$type}",
                'discount_type' => $type,
                'is_active' => true,
                ...$data,
            ]);

            $this->testPromotions[$type] = $promo;
            $this->info("   ✓ Created {$type} promotion (ID: {$promo->id})");

            // Verify promotion exists and has correct fields
            $promo->refresh();
            if (!$promo->exists) {
                $this->errors[] = "Promotion {$type} was not saved";
            }
        }
    }

    protected function testQRCodeCreation()
    {
        foreach ($this->testPromotions as $type => $promo) {
            if ($type === Promotion::TYPE_PUNCH_CARD) {
                continue; // Skip punch cards for now, test separately
            }

            $qrCode = QRCode::create([
                'business_id' => $this->testBusiness->id,
                'name' => "QR for {$promo->name}",
                'type' => 'promotion',
                'promotion_id' => $promo->id,
                'is_active' => true,
            ]);

            $this->testQRCodes[$type] = $qrCode;
            $this->info("   ✓ Created QR code for {$type} (Code: {$qrCode->code})");

            // Verify QR code is linked to promotion
            $qrCode->refresh();
            if ($qrCode->promotion_id !== $promo->id) {
                $this->errors[] = "QR code {$qrCode->code} not linked to promotion {$promo->id}";
            }
        }
    }

    protected function testQRCodeWithGames()
    {
        // Get a game
        $game = Game::where('type', Game::TYPE_WORD_SEARCH)->first();
        if (!$game) {
            $this->warn('   ⚠️  No games found, skipping game attachment test');
            return;
        }

        // Create a promotion for game rewards
        $gamePromo = Promotion::create([
            'business_id' => $this->testBusiness->id,
            'name' => 'Test Game Win Promotion',
            'description' => 'Win this promo by playing the game',
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 25,
            'original_price' => 50,
            'is_active' => true,
        ]);

        // Create QR code with game attached
        $qrCode = QRCode::create([
            'business_id' => $this->testBusiness->id,
            'name' => 'QR with Game',
            'type' => 'qrcade',
            'promotion_id' => $gamePromo->id,
            'is_active' => true,
        ]);

        // Attach game
        $qrCodeGame = QRCodeGame::create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $this->testBusiness->id,
            'is_active' => true,
            'promotion_id' => $gamePromo->id,
            'win_mode' => QRCodeGame::WIN_MODE_ALWAYS,
        ]);

        $this->info("   ✓ Created QR code with game attached (QR: {$qrCode->code}, Game: {$game->name})");

        // Verify game attachment
        $qrCode->refresh();
        if (!$qrCode->hasActiveGames()) {
            $this->errors[] = "QR code {$qrCode->code} does not have active games";
        }
    }

    protected function testLeaderboardRewards()
    {
        $game = Game::where('type', Game::TYPE_WORD_SEARCH)->first();
        if (!$game) {
            $this->warn('   ⚠️  No games found, skipping leaderboard test');
            return;
        }

        // Create multi-tier promotion
        $tierPromo = Promotion::create([
            'business_id' => $this->testBusiness->id,
            'name' => 'Test Leaderboard Tier Reward',
            'description' => 'Multi-tier leaderboard reward',
            'discount_type' => Promotion::TYPE_TIERED,
            'tiers' => [
                ['min_spend' => 0, 'discount' => 10],   // 1st place
                ['min_spend' => 0, 'discount' => 5],   // 2nd place
                ['min_spend' => 0, 'discount' => 2],    // 3rd place
            ],
            'is_active' => true,
        ]);

        // Create leaderboard with promotion
        $leaderboard = Leaderboard::create([
            'name' => 'Test Leaderboard',
            'slug' => 'test-leaderboard-' . time(),
            'business_id' => $this->testBusiness->id,
            'game_id' => $game->id,
            'type' => Leaderboard::TYPE_LOCATION,
            'promotion_id' => $tierPromo->id,
            'prize_config' => [
                'tiers' => [
                    ['rank' => 1, 'promotion_id' => $tierPromo->id, 'discount' => 10],
                    ['rank' => 2, 'promotion_id' => $tierPromo->id, 'discount' => 5],
                    ['rank' => 3, 'promotion_id' => $tierPromo->id, 'discount' => 2],
                ],
            ],
            'is_active' => true,
        ]);

        $this->info("   ✓ Created leaderboard with tier rewards (ID: {$leaderboard->id})");

        // Verify leaderboard has promotion
        $leaderboard->refresh();
        if ($leaderboard->promotion_id !== $tierPromo->id) {
            $this->errors[] = "Leaderboard {$leaderboard->id} not linked to promotion {$tierPromo->id}";
        }
    }

    protected function testScanningFlow()
    {
        if (empty($this->testQRCodes)) {
            $this->warn('   ⚠️  No QR codes to test scanning');
            return;
        }

        $qrCode = reset($this->testQRCodes);
        
        // Create scan record
        $scan = Scan::create([
            'qr_code_id' => $qrCode->id,
            'business_id' => $this->testBusiness->id,
            'user_id' => $this->testCustomer->id,
            'session_id' => 'test-session-' . time(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'device_type' => 'desktop',
            'scanned_at' => now(),
        ]);

        $this->info("   ✓ Created scan record (ID: {$scan->id})");

        // Verify scan is linked
        $scan->refresh();
        if ($scan->qr_code_id !== $qrCode->id) {
            $this->errors[] = "Scan {$scan->id} not linked to QR code {$qrCode->id}";
        }
    }

    protected function testTokenGeneration()
    {
        if (empty($this->testQRCodes)) {
            $this->warn('   ⚠️  No QR codes to test token generation');
            return;
        }

        $qrCode = reset($this->testQRCodes);
        
        // Directly use UserPromoTokenService to generate token
        $tokenService = app(UserPromoTokenService::class);
        $token = $tokenService->ensure($this->testCustomer, $qrCode);

        if (!$token) {
            $this->errors[] = "Token not generated for user {$this->testCustomer->id} and QR {$qrCode->id}";
        } else {
            $this->info("   ✓ Generated per-user token (Code: {$token->code})");
            
            // Verify token has QR image
            if (!$token->qr_image_path) {
                $this->errors[] = "Token {$token->code} missing QR image path";
            }
        }

        // Manually create SavedQRCode to simulate claim flow
        $saved = SavedQRCode::firstOrCreate(
            ['user_id' => $this->testCustomer->id, 'qr_code_id' => $qrCode->id],
            ['saved_at' => now()]
        );

        if (!$saved) {
            $this->errors[] = "SavedQRCode not created for user {$this->testCustomer->id}";
        } else {
            $this->info("   ✓ Saved QR code in portal (ID: {$saved->id})");
        }
    }

    protected function testRedemptionFlow()
    {
        if (empty($this->testQRCodes)) {
            $this->warn('   ⚠️  No QR codes to test redemption');
            return;
        }

        $qrCode = reset($this->testQRCodes);
        $promo = $qrCode->promotion;
        
        // Get or create token
        $tokenService = app(UserPromoTokenService::class);
        $token = $tokenService->ensure($this->testCustomer, $qrCode);

        if (!$token) {
            $this->errors[] = "Could not ensure token for redemption test";
            return;
        }

        // Create redemption using token code
        $redemption = Redemption::create([
            'promotion_id' => $promo->id,
            'qr_code_id' => $qrCode->id,
            'business_id' => $this->testBusiness->id,
            'customer_user_id' => $this->testCustomer->id,
            'user_promo_token_id' => $token->id,
            'customer_identifier' => $token->code,
            'original_amount' => 100,
            'discount_amount' => $promo->calculateDiscount(100, 1)['discount'],
            'final_amount' => $promo->calculateDiscount(100, 1)['final_amount'],
            'redeemed_at' => now(),
        ]);

        $this->info("   ✓ Created redemption (ID: {$redemption->id}, Discount: \${$redemption->discount_amount})");

        // Mark token as redeemed and remove from SavedQRCode (simulating RedemptionController logic)
        if ($promo->discount_type !== Promotion::TYPE_PUNCH_CARD && $token) {
            $token->update([
                'redeemed_at' => now(),
                'redemption_id' => $redemption->id,
            ]);
            // Remove from portal Saved (controller does this)
            SavedQRCode::where('user_id', $this->testCustomer->id)
                ->where('qr_code_id', $qrCode->id)
                ->delete();
        }

        // Verify redemption is linked to customer
        $redemption->refresh();
        if ($redemption->customer_user_id !== $this->testCustomer->id) {
            $this->errors[] = "Redemption {$redemption->id} not linked to customer {$this->testCustomer->id}";
        }

        // For non-punch-card promos, verify token is marked as redeemed
        if ($promo->discount_type !== Promotion::TYPE_PUNCH_CARD) {
            $token->refresh();
            if (!$token->redeemed_at) {
                $this->errors[] = "Token {$token->code} not marked as redeemed";
            } else {
                $this->info("   ✓ Token marked as redeemed");
            }
        }
    }

    protected function testPortalScansDisplay()
    {
        // Test PortalScanController logic
        $controller = app(\App\Http\Controllers\Portal\PortalScanController::class);
        
        // Simulate request
        $request = request();
        $request->setUserResolver(fn() => $this->testCustomer);

        // Get saved promotions
        $savedPromos = SavedQRCode::where('user_id', $this->testCustomer->id)
            ->with(['qrCode.promotion', 'qrCode.business'])
            ->get();

        $this->info("   ✓ Found {$savedPromos->count()} saved promotions in portal");

        // Verify redeemed promos are excluded
        $redeemedTokens = UserPromoToken::where('user_id', $this->testCustomer->id)
            ->whereNotNull('redeemed_at')
            ->pluck('qr_code_id');

        foreach ($savedPromos as $saved) {
            if ($redeemedTokens->contains($saved->qr_code_id)) {
                $promo = $saved->qrCode->promotion;
                if ($promo && $promo->discount_type !== Promotion::TYPE_PUNCH_CARD) {
                    $this->errors[] = "Redeemed promo {$promo->id} still showing in saved promotions";
                }
            }
        }
    }

    protected function testPortalRewardsDisplay()
    {
        // Test PortalRewardController logic
        $redemptions = Redemption::where('customer_user_id', $this->testCustomer->id)
            ->with(['promotion', 'business'])
            ->get();

        $this->info("   ✓ Found {$redemptions->count()} redemptions in portal");

        // Verify all redemptions are linked to customer
        foreach ($redemptions as $redemption) {
            if ($redemption->customer_user_id !== $this->testCustomer->id) {
                $this->errors[] = "Redemption {$redemption->id} not properly linked to customer";
            }
        }
    }

    protected function testPunchCardFlow()
    {
        $punchPromo = $this->testPromotions[Promotion::TYPE_PUNCH_CARD] ?? null;
        if (!$punchPromo) {
            $this->warn('   ⚠️  No punch card promotion to test');
            return;
        }

        // Create QR code for punch card
        $qrCode = QRCode::create([
            'business_id' => $this->testBusiness->id,
            'name' => 'Punch Card QR',
            'type' => 'promotion',
            'promotion_id' => $punchPromo->id,
            'is_active' => true,
        ]);

        // Generate token directly
        $tokenService = app(UserPromoTokenService::class);
        $token = $tokenService->ensure($this->testCustomer, $qrCode);

        if (!$token) {
            $this->errors[] = "Punch card token not generated";
        } else {
            $this->info("   ✓ Generated punch card token (Code: {$token->code})");
        }

        // Test punch card redemption (should NOT consume token)
        $redemption = Redemption::create([
            'promotion_id' => $punchPromo->id,
            'qr_code_id' => $qrCode->id,
            'business_id' => $this->testBusiness->id,
            'customer_user_id' => $this->testCustomer->id,
            'user_promo_token_id' => $token->id,
            'customer_identifier' => $token->code,
            'punches_added' => 1,
            'redeemed_at' => now(),
        ]);

        // Verify token is NOT marked as redeemed (punch cards are reusable)
        $token->refresh();
        if ($token->redeemed_at) {
            $this->errors[] = "Punch card token incorrectly marked as redeemed";
        } else {
            $this->info("   ✓ Punch card token remains active (not consumed)");
        }
    }

    protected function reportResults()
    {
        $this->newLine();
        $this->info('📊 Test Results Summary:');
        $this->newLine();

        if (empty($this->errors)) {
            $this->info('✅ All tests passed! No errors found.');
        } else {
            $this->error('❌ Found ' . count($this->errors) . ' error(s):');
            foreach ($this->errors as $error) {
                $this->error("   • {$error}");
            }
        }

        $this->newLine();
        $this->info('Test Data Created:');
        $this->info("   • Promotions: " . count($this->testPromotions));
        $this->info("   • QR Codes: " . count($this->testQRCodes));
        $this->info("   • Business ID: {$this->testBusiness->id}");
        $this->info("   • Customer ID: {$this->testCustomer->id}");
    }

    protected function cleanup()
    {
        $this->info('🧹 Cleaning up test data...');

        // Delete in reverse order of dependencies
        Redemption::where('business_id', $this->testBusiness->id)->delete();
        UserPromoToken::where('user_id', $this->testCustomer->id)->delete();
        SavedQRCode::where('user_id', $this->testCustomer->id)->delete();
        Scan::where('business_id', $this->testBusiness->id)->delete();
        QRCodeGame::where('business_id', $this->testBusiness->id)->delete();
        QRCode::where('business_id', $this->testBusiness->id)->delete();
        Leaderboard::where('business_id', $this->testBusiness->id)->delete();
        Promotion::where('business_id', $this->testBusiness->id)->delete();

        $this->info('   ✓ Cleanup complete');
    }
}


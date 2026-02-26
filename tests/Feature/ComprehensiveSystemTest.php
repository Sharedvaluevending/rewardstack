<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\Game;
use App\Models\GamePlay;
use App\Models\GameReward;
use App\Models\GameSession;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\QRCodeGame;
use App\Models\Redemption;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class ComprehensiveSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $business;
    protected $employee;
    protected $customer;
    protected $game;
    protected $promotion;

    protected function setUp(): void
    {
        parent::setUp();

        // This suite exercises web POST routes; disable CSRF so we can use postJson safely.
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        
        $this->business = Business::factory()->create();
        
        // Create a user for the employee
        $employeeUser = User::factory()->create(['role' => 'employee']);
        
        // Create an employee manually since factory might be missing
        $this->employee = Employee::create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'role' => 'employee',
            'can_redeem' => true,
            'is_active' => true,
        ]);
        
        // Reload employee to get relationships if needed
        $this->employee->load('user');
        
        $this->customer = User::factory()->create(['role' => 'customer']);
        
        $this->game = Game::factory()->create();
        $this->promotion = Promotion::factory()->create([
            'business_id' => $this->business->id,
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 20,
        ]);
    }

    /**
     * @test
     * @group games
     */
    public function play_for_fun_gives_no_reward()
    {
        $qrCode = QRCode::factory()->create(['business_id' => $this->business->id]);
        
        // Setup QRCodeGame with NO promotion (Play for Fun)
        QRCodeGame::create([
            'business_id' => $this->business->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $this->game->id,
            'promotion_id' => null,
            'is_active' => true,
            'win_probability' => 0, 
            'win_mode' => 'random', // Explicitly set to random so probability 0 takes effect
        ]);

        $session = GameSession::factory()->create([
            'user_id' => $this->customer->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $this->game->id,
            'business_id' => $this->business->id,
        ]);

        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $this->customer->id,
            'game_id' => $this->game->id,
            'business_id' => $this->business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subMinute(),
            'completed_at' => null,
        ]);

        // Submit score
        $response = $this->actingAs($this->customer)
            ->withoutMiddleware([\App\Http\Middleware\HandleInertiaRequests::class])
            ->postJson("/api/play/session/{$session->session_token}/score", [
                'score' => 1000,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['result' => ['won' => false]]);
        
        // Verify NO reward created
        $this->assertDatabaseMissing('game_rewards', [
            'user_id' => $this->customer->id,
            'business_id' => $this->business->id,
        ]);
    }

    /**
     * @test
     * @group games
     */
    public function play_to_win_gives_reward_with_100_percent_odds()
    {
        $qrCode = QRCode::factory()->create(['business_id' => $this->business->id]);
        
        $qcg = QRCodeGame::create([
            'business_id' => $this->business->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $this->game->id,
            'promotion_id' => $this->promotion->id,
            'is_active' => true,
            'win_probability' => 100,
            'win_mode' => 'random',
        ]);
        
        // Debug: verify what was saved
        // if ($qcg->win_mode !== 'random') {
        //      dump("QRCodeGame 100% test: win_mode is {$qcg->win_mode}, expected 'random'", $qcg->toArray());
        // }

        $session = GameSession::factory()->create([
            'user_id' => $this->customer->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $this->game->id,
            'business_id' => $this->business->id,
        ]);

        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $this->customer->id,
            'game_id' => $this->game->id,
            'business_id' => $this->business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subMinute(),
            'completed_at' => null,
        ]);

        // Submit score
        $response = $this->actingAs($this->customer)
            ->withoutMiddleware([\App\Http\Middleware\HandleInertiaRequests::class])
            ->postJson("/api/play/session/{$session->session_token}/score", [
                'score' => 1000,
            ]);

        if ($response->json('result.won') === false) {
             // dump($response->json());
        }

        $response->assertStatus(200);
        $response->assertJson(['result' => ['won' => true]]);
        
        // Check if token exists
        // $tokenCheck = UserPromoToken::where('user_id', $this->customer->id)->where('promotion_id', $this->promotion->id)->first();
        // if (!$tokenCheck) {
        //      dump("Token missing. QR Promotion ID: {$qrCode->promotion_id}, Game ID: {$this->game->id}");
        // }

        // Verify token created instead of GameReward (new behavior)
        $this->assertDatabaseHas('user_promo_tokens', [
            'user_id' => $this->customer->id,
            'business_id' => $this->business->id,
            'promotion_id' => $this->promotion->id,
        ]);
    }

    /**
     * @test
     * @group games
     */
    public function play_to_win_fails_with_0_percent_odds()
    {
        $qrCode = QRCode::factory()->create(['business_id' => $this->business->id]);
        
        // Setup QRCodeGame with Promotion but 0% probability
        QRCodeGame::create([
            'business_id' => $this->business->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $this->game->id,
            'promotion_id' => $this->promotion->id,
            'is_active' => true,
            'win_probability' => 0,
            'win_mode' => 'random',
        ]);

        $session = GameSession::factory()->create([
            'user_id' => $this->customer->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $this->game->id,
            'business_id' => $this->business->id,
        ]);

        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $this->customer->id,
            'game_id' => $this->game->id,
            'business_id' => $this->business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subMinute(),
            'completed_at' => null,
        ]);

        // Submit score
        $response = $this->actingAs($this->customer)
            ->withoutMiddleware([\App\Http\Middleware\HandleInertiaRequests::class])
            ->postJson("/api/play/session/{$session->session_token}/score", [
                'score' => 1000,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['result' => ['won' => false]]);
        
        // Verify NO reward created
        $this->assertDatabaseMissing('game_rewards', [
            'user_id' => $this->customer->id,
            'business_id' => $this->business->id,
        ]);
    }

    /**
     * @test
     * @group redemption
     */
    public function redemption_respects_max_per_user_limit()
    {
        $limit = 1;
        $this->promotion->update([
            'rules' => ['max_redemptions_per_user' => $limit]
        ]);
        
        $qrCode = QRCode::factory()->create([
            'business_id' => $this->business->id,
            'promotion_id' => $this->promotion->id,
        ]);

        // Create a token for the customer
        $token1 = UserPromoToken::create([
            'user_id' => $this->customer->id,
            'qr_code_id' => $qrCode->id,
            'promotion_id' => $this->promotion->id,
            'business_id' => $this->business->id,
            'code' => 'UP-TEST-LIMIT-1',
        ]);

        // First redemption should succeed
        $response = $this->actingAs($this->employee->user)->postJson("/employee/redeem/{$token1->code}", [
            'original_amount' => 100,
            'quantity' => 1,
        ]);
        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('redemptions', [
            'user_promo_token_id' => $token1->id,
            'customer_user_id' => $this->customer->id,
        ]);

        // Create a SECOND token (new scan) for the SAME customer
        $token2 = UserPromoToken::create([
            'user_id' => $this->customer->id,
            'qr_code_id' => $qrCode->id,
            'promotion_id' => $this->promotion->id,
            'business_id' => $this->business->id,
            'code' => 'UP-TEST-LIMIT-2',
        ]);

        // Second redemption should FAIL due to per-user limit
        $response = $this->actingAs($this->employee->user)->postJson("/employee/redeem/{$token2->code}", [
            'original_amount' => 100,
            'quantity' => 1,
        ]);
        $response->assertStatus(400)->assertJson(['success' => false]);
        
        $this->assertDatabaseMissing('redemptions', [
            'user_promo_token_id' => $token2->id,
        ]);
    }

    /**
     * @test
     * @group redemption
     */
    public function redemption_respects_expiry_date()
    {
        // Set promotion as expired
        $this->promotion->update([
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
        
        $qrCode = QRCode::factory()->create([
            'business_id' => $this->business->id,
            'promotion_id' => $this->promotion->id,
        ]);

        $token = UserPromoToken::create([
            'user_id' => $this->customer->id,
            'qr_code_id' => $qrCode->id,
            'promotion_id' => $this->promotion->id,
            'business_id' => $this->business->id,
            'code' => 'UP-TEST-EXP',
        ]);

        // Refresh promotion to ensure dates are persisted
        $this->promotion->refresh();

        // Attempt redemption
        $response = $this->actingAs($this->employee->user)->postJson("/employee/redeem/{$token->code}", [
            'original_amount' => 100,
            'quantity' => 1,
        ]);

        $response->assertStatus(400)->assertJson([
            'success' => false,
            'message' => 'This promotion has expired',
        ]);
    }

    /**
     * @test
     * @group flow
     */
    public function full_qr_promotion_flow()
    {
        // 1. Create Public QR Code
        $qrCode = QRCode::factory()->create([
            'business_id' => $this->business->id,
            'promotion_id' => $this->promotion->id,
            'code' => 'TESTQR01'
        ]);

        // 2. Customer scans QR (visits public page) - This creates the token
        $response = $this->actingAs($this->customer)
            ->get("/promo/{$qrCode->code}");
        
        $response->assertStatus(200);
        
        // Find the token created for this user
        $token = UserPromoToken::where('user_id', $this->customer->id)
            ->where('qr_code_id', $qrCode->id)
            ->first();
            
        $this->assertNotNull($token, "UserPromoToken was not created upon visiting the promo page");

        // 3. Customer shows Token Code to Employee
        // 4. Employee enters Token Code (via Quick Redeem lookup or directly)
        // Simulate Quick Redeem page load
        $response = $this->actingAs($this->employee->user)
            ->get("/redeem/token/{$token->code}");
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Employee/QuickRedeem')
            ->where('prefillCustomerPromoCode', $token->code)
        );

        // 5. Employee submits redemption
        $response = $this->actingAs($this->employee->user)
            ->withHeaders([
                'Referer' => 'http://localhost/redeem/token/' . $token->code,
                'X-Inertia' => 'true'
            ])
            ->post("/employee/redeem/{$token->code}", [
                'original_amount' => 50.00,
            ]);

        // Check for session errors if it fails
        // if (session('errors')) {
        //    dump(session('errors')->all());
        // }
        
        $response->assertSessionHas('success');
        
        // Verify redemption record
        $this->assertDatabaseHas('redemptions', [
            'promotion_id' => $this->promotion->id,
            'customer_user_id' => $this->customer->id,
            'original_amount' => 50.00,
            'discount_amount' => 10.00, // 20% of 50
            'final_amount' => 40.00,
        ]);
        
        // Verify token is marked redeemed
        $this->assertNotNull($token->fresh()->redeemed_at);
    }

    /**
     * @test
     * @group flow
     */
    public function full_qr_game_flow_play_to_win()
    {
        // 1. Setup Game QR
        $qrCode = QRCode::factory()->create([
            'business_id' => $this->business->id,
            'code' => 'TESTQR02'
        ]);
        
        QRCodeGame::create([
            'business_id' => $this->business->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $this->game->id,
            'promotion_id' => $this->promotion->id,
            'is_active' => true,
            'win_probability' => 100, // Guarantee win
            'win_mode' => 'random',
        ]);

        // 2. Customer Scans (Start Session)
        // Using GameService directly or via API endpoint logic
        $session = GameSession::factory()->create([
            'user_id' => $this->customer->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $this->game->id,
            'business_id' => $this->business->id,
        ]);
        
        // 3. Customer Plays & Submits Score
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $this->customer->id,
            'game_id' => $this->game->id,
            'business_id' => $this->business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subMinute(),
            'completed_at' => null,
        ]);

        $response = $this->actingAs($this->customer)
            ->withoutMiddleware([\App\Http\Middleware\HandleInertiaRequests::class])
            ->postJson("/api/play/session/{$session->session_token}/score", [
                'score' => 500,
            ]);

        $response->assertJson(['result' => ['won' => true]]);
        
        // 4. Token is created (new behavior)
        $token = UserPromoToken::where('user_id', $this->customer->id)
            ->where('business_id', $this->business->id)
            ->first();
            
        $this->assertNotNull($token, "UserPromoToken was not created");

        // 5. Customer shows Token Code to Employee
        // 6. Employee Scans/Enters Token Code
        $response = $this->actingAs($this->employee->user)
            ->get("/redeem/token/{$token->code}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Employee/QuickRedeem')
            ->where('prefillCustomerPromoCode', $token->code)
        );

        // 7. Employee Redeems Token
        $response = $this->actingAs($this->employee->user)
            ->post("/employee/redeem/{$token->code}", [
                'original_amount' => 50.00,
            ]);

        $response->assertSessionHas('success');

        // Verify token is marked redeemed
        $this->assertNotNull($token->fresh()->redeemed_at);
    }
}

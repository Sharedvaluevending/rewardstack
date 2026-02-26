<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\Game;
use App\Models\GamePlay;
use App\Models\GameSession;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\QRCodeGame;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $business;
    protected $customer;
    protected $employee; // Add this
    protected $game;
    protected $promotion;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->business = Business::factory()->create();
        $this->customer = User::factory()->create(['role' => 'customer']);
        
        // Create employee
        $employeeUser = User::factory()->create(['role' => 'employee']);
        $this->employee = Employee::create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'role' => 'employee',
            'can_redeem' => true,
            'is_active' => true,
        ]);
        $this->employee->load('user');

        $this->game = Game::factory()->create();
        $this->promotion = Promotion::factory()->create([
            'business_id' => $this->business->id,
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 20,
        ]);
    }

    /** @test */
    public function play_for_fun_gives_no_reward()
    {
        $qrCode = QRCode::factory()->create(['business_id' => $this->business->id]);
        
        // "Play for Fun" = No Promotion attached
        QRCodeGame::create([
            'business_id' => $this->business->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $this->game->id,
            'promotion_id' => null,
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

        // CRITICAL FIX: Ensure completed_at is null so the system processes the result
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
            ->withoutExceptionHandling() // Force exception display
            ->postJson("/api/play/session/{$session->session_token}/score", [
                'score' => 1000,
            ]);

        $response->assertStatus(200);
        
        // Should lose (won: false) because no promotion is attached (fun only)
        // Note: GameService sets 'result' => 'complete' (which implies lost/finished without win)
        // The JSON structure returns 'result' object with 'won' boolean.
        $response->assertJson(['result' => ['won' => false]]);
        
        $this->assertDatabaseMissing('game_rewards', [
            'user_id' => $this->customer->id,
        ]);
    }

    /** @test */
    public function play_to_win_gives_reward_with_100_percent_odds()
    {
        $qrCode = QRCode::factory()->create(['business_id' => $this->business->id]);
        
        // 100% Win Probability
        QRCodeGame::create([
            'business_id' => $this->business->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $this->game->id,
            'promotion_id' => $this->promotion->id,
            'is_active' => true,
            'win_probability' => 100,
            'win_mode' => 'random',
        ]);

        $session = GameSession::factory()->create([
            'user_id' => $this->customer->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $this->game->id,
            'business_id' => $this->business->id,
        ]);

        // CRITICAL FIX: Ensure completed_at is null
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
            ->withoutExceptionHandling() // Force exception display
            ->postJson("/api/play/session/{$session->session_token}/score", [
                'score' => 1000,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['result' => ['won' => true]]);
        
        // Verify UserPromoToken is created (Modern Flow)
        $this->assertDatabaseHas('user_promo_tokens', [
            'user_id' => $this->customer->id,
            'business_id' => $this->business->id,
            'promotion_id' => $this->promotion->id,
        ]);
    }

    /** @test */
    public function play_to_win_fails_with_0_percent_odds()
    {
        $qrCode = QRCode::factory()->create(['business_id' => $this->business->id]);
        
        // 0% Win Probability
        QRCodeGame::create([
            'business_id' => $this->business->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $this->game->id,
            'promotion_id' => $this->promotion->id,
            'is_active' => true,
            'win_probability' => 0,
            'win_mode' => 'random',
            'prize_config' => ['win_probability' => 0], // Ensure config matches
        ]);

        $session = GameSession::factory()->create([
            'user_id' => $this->customer->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $this->game->id,
            'business_id' => $this->business->id,
        ]);

        // CRITICAL FIX: Ensure completed_at is null
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
            ->withoutExceptionHandling() // Force exception display
            ->postJson("/api/play/session/{$session->session_token}/score", [
                'score' => 1000,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['result' => ['won' => false]]);
        
        // Verify NO token created
        $this->assertDatabaseMissing('user_promo_tokens', [
            'user_id' => $this->customer->id,
            'business_id' => $this->business->id,
        ]);
    }

    /** @test */
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
        $response = $this->actingAs($this->employee->user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post("/employee/redeem/{$token1->code}", [
                'original_amount' => 100,
            ]);
            
        $response->assertSessionHas('success');
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
        $response = $this->actingAs($this->employee->user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post("/employee/redeem/{$token2->code}", [
                'original_amount' => 100,
            ]);
            
        $response->assertSessionHasErrors('message');
        
        $this->assertDatabaseMissing('redemptions', [
            'user_promo_token_id' => $token2->id,
        ]);
    }

    /** @test */
    public function play_to_win_respects_score_threshold()
    {
        $qrCode = QRCode::factory()->create(['business_id' => $this->business->id]);
        
        // Win if score >= 1000
        QRCodeGame::create([
            'business_id' => $this->business->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $this->game->id,
            'promotion_id' => $this->promotion->id,
            'is_active' => true,
            'win_mode' => 'skill', // Database uses 'skill' for score-based
            'win_threshold_score' => 1000,
        ]);

        $session = GameSession::factory()->create([
            'user_id' => $this->customer->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $this->game->id,
            'business_id' => $this->business->id,
        ]);

        // Attempt 1: Score 999 (Fail)
        $gamePlayFail = GamePlay::factory()->create([
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
                'score' => 999,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['result' => ['won' => false]]);

        // Attempt 2: Score 1000 (Win) - Use a NEW session
        $sessionWin = GameSession::factory()->create([
            'user_id' => $this->customer->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $this->game->id,
            'business_id' => $this->business->id,
        ]);

        $gamePlayWin = GamePlay::factory()->create([
            'game_session_id' => $sessionWin->id,
            'user_id' => $this->customer->id,
            'game_id' => $this->game->id,
            'business_id' => $this->business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subMinute(),
            'completed_at' => null,
        ]);

        $response = $this->actingAs($this->customer)
            ->withoutMiddleware([\App\Http\Middleware\HandleInertiaRequests::class])
            ->postJson("/api/play/session/{$sessionWin->session_token}/score", [
                'score' => 1000,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['result' => ['won' => true]]);
        
        $this->assertDatabaseHas('user_promo_tokens', [
            'user_id' => $this->customer->id,
            'promotion_id' => $this->promotion->id,
        ]);
    }

    /** @test */
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

        // Attempt redemption
        $response = $this->actingAs($this->employee->user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post("/employee/redeem/{$token->code}", [
                'original_amount' => 100,
            ]);

        // Should fail due to expiry (checked in canRedeem)
        $response->assertSessionHasErrors('message');
    }
}

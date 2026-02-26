<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CrossPromotion;
use App\Models\Game;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\QRCodeGame;
use App\Models\StackablePool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class QRCodeScanTest extends TestCase
{
    use RefreshDatabase;

    protected $business;
    protected $customer;
    protected $employee;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a pro business to avoid feature gating issues
        $this->business = Business::factory()->create([
            'subscription_tier' => 'pro',
            'is_testing_account' => true,
        ]);
        
        $this->customer = User::factory()->create(['role' => 'customer', 'level' => 1]);
        
        // Create an employee for staff checks if needed
        $this->employee = User::factory()->create(['role' => 'employee']);
        \App\Models\Employee::create([
            'user_id' => $this->employee->id,
            'business_id' => $this->business->id,
            'role' => 'staff',
            'is_active' => true,
            'can_redeem' => true,
        ]);
    }

    /** @test */
    public function scan_redirects_to_game_if_active_game_exists()
    {
        // Game is global, no business_id
        $game = Game::factory()->create([
            'is_active' => true,
            'slug' => 'test-game',
        ]);

        $qrCode = QRCode::factory()->create([
            'business_id' => $this->business->id,
            'type' => 'qrcade', // Or implied via game attachment
        ]);

        QRCodeGame::create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $this->business->id,
            'is_active' => true,
            'win_mode' => 'random',
        ]);

        $response = $this->actingAs($this->customer)
            ->get("/s/{$qrCode->code}"); // Correct route

        $response->assertRedirect(route('play.game', [
            'code' => $qrCode->code,
            'game' => $game->slug,
        ]));
    }

    /** @test */
    public function scan_redirects_to_promotion_page_for_promotion_type()
    {
        $promotion = Promotion::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);

        $qrCode = QRCode::factory()->create([
            'business_id' => $this->business->id,
            'type' => 'promotion',
            'promotion_id' => $promotion->id,
        ]);

        $response = $this->actingAs($this->customer)
            ->get("/s/{$qrCode->code}"); // Correct route

        $response->assertRedirect(route('promotion.show', $qrCode->code));
    }

    /** @test */
    public function scan_redirects_to_destination_for_static_type()
    {
        $url = 'https://example.com';
        $qrCode = QRCode::factory()->create([
            'business_id' => $this->business->id,
            'type' => 'static',
            'destination_url' => $url,
        ]);

        $response = $this->actingAs($this->customer)
            ->get("/s/{$qrCode->code}"); // Correct route

        $response->assertRedirect($url);
    }

    /** @test */
    public function scan_renders_stackable_pool_page()
    {
        // Manual creation since factory is missing/failing
        $pool = StackablePool::create([
            'business_id' => $this->business->id,
            'name' => 'Downtown Deals',
            'description' => 'Great deals',
            'city' => 'New York',
            'is_active' => true,
            'code' => 'POOL-' . \Illuminate\Support\Str::random(8), // Add code
        ]);

        $qrCode = QRCode::factory()->create([
            'business_id' => $this->business->id,
            'type' => 'stackable',
            'stackable_pool_id' => $pool->id,
        ]);

        $response = $this->actingAs($this->customer)
            ->get("/s/{$qrCode->code}"); // Correct route

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Public/StackablePool')
            ->where('pool.name', 'Downtown Deals')
        );
    }

    /** @test */
    public function scan_renders_cross_promo_page()
    {
        // Setup for Cross Promotion
        $partnerBusiness = Business::factory()->create();
        $promo1 = Promotion::factory()->create(['business_id' => $this->business->id]);
        $promo2 = Promotion::factory()->create(['business_id' => $partnerBusiness->id]);

        // Manual creation with code
        $crossPromo = CrossPromotion::create([
            'business_1_id' => $this->business->id,
            'business_2_id' => $partnerBusiness->id,
            'promotion_1_id' => $promo1->id,
            'promotion_2_id' => $promo2->id,
            'name' => 'Partner Deal',
            'code' => 'CP-' . \Illuminate\Support\Str::random(8), // Add code
            'status' => 'accepted',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'display_mode' => 'side_by_side',
            'chain_mode' => 'parallel',
        ]);

        $qrCode = QRCode::factory()->create([
            'business_id' => $this->business->id,
            'type' => 'cross_promo',
            'cross_promotion_id' => $crossPromo->id,
        ]);

        $response = $this->actingAs($this->customer)
            ->get("/s/{$qrCode->code}"); // Correct route

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Public/CrossPromo')
            ->where('crossPromo.name', 'Partner Deal')
        );
    }

    /** @test */
    public function scan_blocks_level_exclusive_if_level_too_low()
    {
        $qrCode = QRCode::factory()->create([
            'business_id' => $this->business->id,
            'type' => 'level_exclusive',
            'required_level' => 5,
        ]);

        // User is level 1 (default from setUp)
        $response = $this->actingAs($this->customer)
            ->get("/s/{$qrCode->code}"); // Correct route

        $response->assertRedirect(route('promotion.show', $qrCode->code));
    }
    
    /** @test */
    public function scan_allows_level_exclusive_if_level_met()
    {
        $qrCode = QRCode::factory()->create([
            'business_id' => $this->business->id,
            'type' => 'level_exclusive',
            'required_level' => 5,
            'promotion_id' => Promotion::factory()->create(['business_id' => $this->business->id])->id,
        ]);

        // Update user to level 5
        $this->customer->update(['level' => 5]);

        $response = $this->actingAs($this->customer)
            ->get("/s/{$qrCode->code}"); // Correct route
            
        // Should redirect to promotion page
        $response->assertRedirect(route('promotion.show', $qrCode->code));
    }
}

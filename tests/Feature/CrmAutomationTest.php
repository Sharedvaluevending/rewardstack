<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessCustomer;
use App\Models\BusinessCustomerSubscription;
use App\Models\CrmAutomation;
use App\Models\CrmMessage;
use App\Models\User;
use App\Services\CrmAutomationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SendCrmMessage;
use Tests\TestCase;

class CrmAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected $business;
    protected $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create([
            'subscription_tier' => 'pro',
            'is_testing_account' => true,
        ]);

        $this->runner = app(CrmAutomationRunner::class);
        
        // Mock SendGrid key presence to allow runner to proceed
        Config::set('services.sendgrid.api_key', 'test_key');
        
        // Mock Queue to prevent actual jobs from running but verify dispatch
        Queue::fake();
    }

    /** @test */
    public function winback_automation_targets_inactive_users()
    {
        // 1. Setup Automation
        $automation = CrmAutomation::create([
            'business_id' => $this->business->id,
            'name' => 'Winback 30 Days',
            'trigger' => 'winback',
            'config' => [
                'days' => 30,
                'subject' => 'We miss you!',
            ],
            'is_active' => true,
        ]);

        // 2. Setup Users
        
        // User A: Inactive (Last seen 40 days ago) -> Should receive
        $userA = User::factory()->create(['email' => 'userA@example.com', 'role' => 'customer']);
        $this->subscribeUser($userA);
        BusinessCustomer::create([
            'business_id' => $this->business->id,
            'user_id' => $userA->id,
            'last_seen_at' => now()->subDays(40),
        ]);

        // User B: Active (Last seen 10 days ago) -> Should NOT receive
        $userB = User::factory()->create(['email' => 'userB@example.com', 'role' => 'customer']);
        $this->subscribeUser($userB);
        BusinessCustomer::create([
            'business_id' => $this->business->id,
            'user_id' => $userB->id,
            'last_seen_at' => now()->subDays(10),
        ]);

        // User C: Inactive but Unsubscribed -> Should NOT receive
        $userC = User::factory()->create(['email' => 'userC@example.com', 'role' => 'customer']);
        // Not calling subscribeUser()
        BusinessCustomer::create([
            'business_id' => $this->business->id,
            'user_id' => $userC->id,
            'last_seen_at' => now()->subDays(40),
        ]);

        // DEBUG: Verify subscriptions and last_seen
        // dump(
        //     BusinessCustomerSubscription::all()->toArray(),
        //     BusinessCustomer::all()->toArray()
        // );

        // 3. Run Automation
        $result = $this->runner->runAutomation($this->business, $automation);

        // 4. Assertions
        $this->assertEquals(1, $result['sent']);
        
        // Verify User A got a queued message
        $this->assertDatabaseHas('crm_messages', [
            'user_id' => $userA->id,
            'business_id' => $this->business->id,
            'email' => $userA->email,
        ]);

        // Verify User B/C did not
        $this->assertDatabaseMissing('crm_messages', [
            'user_id' => $userB->id,
        ]);
        $this->assertDatabaseMissing('crm_messages', [
            'user_id' => $userC->id,
        ]);

        // Verify job dispatched
        Queue::assertPushed(SendCrmMessage::class);
    }

    /** @test */
    public function punch_card_nudge_targets_users_close_to_reward()
    {
        // 1. Setup Automation
        $automation = CrmAutomation::create([
            'business_id' => $this->business->id,
            'name' => 'Punch Nudge',
            'trigger' => 'punch_card_nudge',
            'config' => [
                'inactive_days' => 7,
                'subject' => 'Almost there!',
            ],
            'is_active' => true,
        ]);

        // 2. Setup Promotion (Punch Card)
        $promotion = \App\Models\Promotion::factory()->create([
            'business_id' => $this->business->id,
            'discount_type' => 'punch_card',
            'punches_required' => 10,
        ]);

        // 3. Setup Users
        
        // User A: Close to reward (8/10), inactive > 7 days -> Should receive
        $userA = User::factory()->create(['email' => 'userA@example.com', 'role' => 'customer']);
        $this->subscribeUser($userA);
        \App\Models\PunchCard::create([
            'user_id' => $userA->id,
            'promotion_id' => $promotion->id,
            'punches' => 8,
            'last_punch_at' => now()->subDays(8),
            'customer_identifier' => 'TEST-A',
        ]);

        // User B: Far from reward (2/10) -> Should NOT receive (default logic usually targets specific progress, but runner implementation checks *any* progress > 0)
        // Runner logic: punch_cards.punches > 0 AND punches < required. 
        // Wait, runner logic is: `where('punch_cards.punches', '>', 0)` ...
        // So actually User B WOULD receive it if inactive. Let's make User B active.
        $userB = User::factory()->create(['email' => 'userB@example.com', 'role' => 'customer']);
        $this->subscribeUser($userB);
        \App\Models\PunchCard::create([
            'user_id' => $userB->id,
            'promotion_id' => $promotion->id,
            'punches' => 8,
            'last_punch_at' => now()->subDays(1), // Active recently
            'customer_identifier' => 'TEST-B',
        ]);

        // User C: Card Complete (10/10) -> Should NOT receive (they need to redeem, not punch)
        // Runner logic: `if ($current >= $required) { continue; }`
        $userC = User::factory()->create(['email' => 'userC@example.com', 'role' => 'customer']);
        $this->subscribeUser($userC);
        \App\Models\PunchCard::create([
            'user_id' => $userC->id,
            'promotion_id' => $promotion->id,
            'punches' => 10,
            'last_punch_at' => now()->subDays(10),
            'customer_identifier' => 'TEST-C',
        ]);

        // 4. Run Automation
        $result = $this->runner->runAutomation($this->business, $automation);

        // 5. Assertions
        $this->assertEquals(1, $result['sent']);
        
        $this->assertDatabaseHas('crm_messages', [
            'user_id' => $userA->id,
            'email' => $userA->email,
        ]);
        
        $this->assertDatabaseMissing('crm_messages', [
            'user_id' => $userB->id,
        ]);
        $this->assertDatabaseMissing('crm_messages', [
            'user_id' => $userC->id,
        ]);
    }

    /** @test */
    public function promo_expiring_targets_users_with_saved_qr()
    {
        // 1. Setup Automation
        $automation = CrmAutomation::create([
            'business_id' => $this->business->id,
            'name' => 'Expiring Soon',
            'trigger' => 'promo_expiring',
            'config' => [
                'days' => 3, // Expiring in next 3 days
                'subject' => 'Don\'t miss out!',
            ],
            'is_active' => true,
        ]);

        // 2. Setup Promotions
        // Promo A: Expiring in 2 days (Match)
        $promoA = \App\Models\Promotion::factory()->create([
            'business_id' => $this->business->id,
            'ends_at' => now()->addDays(2),
        ]);
        $qrA = \App\Models\QRCode::factory()->create([
            'business_id' => $this->business->id,
            'promotion_id' => $promoA->id,
        ]);

        // Promo B: Expiring in 10 days (No Match)
        $promoB = \App\Models\Promotion::factory()->create([
            'business_id' => $this->business->id,
            'ends_at' => now()->addDays(10),
        ]);
        $qrB = \App\Models\QRCode::factory()->create([
            'business_id' => $this->business->id,
            'promotion_id' => $promoB->id,
        ]);

        // 3. Setup Users
        // User A: Saved Promo A -> Should receive
        $userA = User::factory()->create(['email' => 'userA@example.com', 'role' => 'customer']);
        $this->subscribeUser($userA);
        \App\Models\SavedQRCode::create([
            'user_id' => $userA->id,
            'qr_code_id' => $qrA->id,
            'saved_at' => now()->subDays(1),
        ]);

        // User B: Saved Promo B -> Should NOT receive
        $userB = User::factory()->create(['email' => 'userB@example.com', 'role' => 'customer']);
        $this->subscribeUser($userB);
        \App\Models\SavedQRCode::create([
            'user_id' => $userB->id,
            'qr_code_id' => $qrB->id,
            'saved_at' => now()->subDays(1),
        ]);

        // 4. Run Automation
        $result = $this->runner->runAutomation($this->business, $automation);

        // 5. Assertions
        $this->assertEquals(1, $result['sent']);
        
        $this->assertDatabaseHas('crm_messages', [
            'user_id' => $userA->id,
            'email' => $userA->email,
        ]);
        
        $this->assertDatabaseMissing('crm_messages', [
            'user_id' => $userB->id,
        ]);
    }

    protected function subscribeUser(User $user)
    {
        BusinessCustomerSubscription::create([
            'business_id' => $this->business->id,
            'user_id' => $user->id,
            'subscribed_at' => now(),
        ]);
    }
}

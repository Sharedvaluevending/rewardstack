<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessCustomerSubscription;
use App\Models\CrmCampaign;
use App\Models\Promotion;
use App\Models\Redemption;
use App\Models\User;
use App\Services\BusinessCustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $business;
    protected $owner;
    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->business = Business::factory()->create([
            'is_testing_account' => true, // Bypass subscription checks
            'subscription_tier' => 'pro', // Ensure features are enabled
        ]);
        $this->owner = $this->business->owner;
        $this->owner->update(['role' => 'business']); // Ensure role is set
        
        $this->customer = User::factory()->create([
            'role' => 'customer',
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
    }

    /** @test */
    public function business_can_view_subscribed_customers()
    {
        // Subscribe the customer
        app(BusinessCustomerService::class)->subscribe($this->business->id, $this->customer->id);

        $response = $this->actingAs($this->owner)
            ->get('/business/crm/customers');

        $response->assertStatus(200);
        
        // Check Inertia response contains the customer
        $response->assertInertia(fn ($page) => $page
            ->component('Business/CRM/Customers')
            ->has('customers.data', 1)
            ->where('customers.data.0.user.email', 'john@example.com')
        );
    }

    /** @test */
    public function redemption_updates_customer_engagement_stats()
    {
        // Subscribe
        app(BusinessCustomerService::class)->subscribe($this->business->id, $this->customer->id);

        // Create a promotion and redemption
        $promotion = Promotion::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Test Promo'
        ]);

        $redemption = Redemption::create([
            'business_id' => $this->business->id,
            'promotion_id' => $promotion->id,
            'customer_user_id' => $this->customer->id,
            'redeemed_by_user_id' => $this->owner->id,
            'redeemed_at' => now(),
            'discount_amount' => 15.50, // $15.50
            'final_amount' => 84.50,
            'original_amount' => 100.00,
        ]);

        // Manually trigger the service (mimicking RedemptionController)
        app(BusinessCustomerService::class)->recordRedemption($redemption);

        // Check CRM page
        $response = $this->actingAs($this->owner)
            ->get('/business/crm/customers');

        $response->assertStatus(200);
        
        $response->assertInertia(fn ($page) => $page
            ->where('customers.data.0.user.email', 'john@example.com')
            ->where('customers.data.0.engagement.redemptions', 1)
            // Expecting string format matching the controller output
            ->where('customers.data.0.engagement.lifetime_savings', '15.50') 
        );
    }

    /** @test */
    public function business_can_create_crm_campaign()
    {
        $campaignData = [
            'name' => 'Summer Sale',
            'subject' => 'Big Discounts inside!',
            'content_html' => '<p>Hello world</p>', // Corrected key
            'scheduled_at' => now()->addDay()->toDateTimeString(),
            'segment_id' => null, // All customers
        ];

        $response = $this->actingAs($this->owner)
            ->post('/business/crm/campaigns', $campaignData);

        // Expect redirect to the Show page, not Index
        // Since we don't know the ID yet, we can check the pattern or just check database
        // But assertRedirect expects exact match. 
        // We can check if it redirects to ANY child page of campaigns
        // Or inspect the created campaign to know the ID.
        
        $campaign = CrmCampaign::where('name', 'Summer Sale')->first();
        $this->assertNotNull($campaign);
        $response->assertRedirect("/business/crm/campaigns/{$campaign->id}");
        
        $this->assertDatabaseHas('crm_campaigns', [
            'business_id' => $this->business->id,
            'name' => 'Summer Sale',
            'subject' => 'Big Discounts inside!',
            'status' => 'draft',
        ]);
    }
}

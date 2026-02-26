<?php

namespace Tests\Feature\Crm;

use App\Models\Business;
use App\Models\CrmCampaign;
use App\Models\CrmMessage;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CampaignPromoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_redirects_to_home_with_error_when_message_not_found(): void
    {
        $url = URL::signedRoute('crm.campaigns.promo.save', [
            'campaign' => 999,
            'message' => 999,
            'promo' => 1,
            'user' => 1,
            'business' => 1,
        ]);

        $response = $this->get($url);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error', 'This link is invalid or has expired.');
    }

    public function test_save_redirects_to_home_with_error_when_promotion_not_found(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create(['role' => 'user']);
        $campaign = CrmCampaign::create([
            'business_id' => $business->id,
            'name' => 'Test Campaign',
            'subject' => 'Test',
            'content_html' => '<p>Test</p>',
            'content_text' => 'Test',
            'status' => CrmCampaign::STATUS_SENT,
            'promotion_id' => null,
        ]);
        $message = CrmMessage::create([
            'business_id' => $business->id,
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'sent',
        ]);

        $url = URL::signedRoute('crm.campaigns.promo.save', [
            'campaign' => $campaign->id,
            'message' => $message->id,
            'promo' => 999,
            'user' => $user->id,
            'business' => $business->id,
        ]);

        $response = $this->get($url);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error', 'This promotion is no longer available.');
    }

    public function test_save_redirects_to_home_with_error_when_user_mismatch(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create(['role' => 'user']);
        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
        ]);
        $campaign = CrmCampaign::create([
            'business_id' => $business->id,
            'name' => 'Test Campaign',
            'subject' => 'Test',
            'content_html' => '<p>Test</p>',
            'content_text' => 'Test',
            'status' => CrmCampaign::STATUS_SENT,
            'promotion_id' => $promotion->id,
        ]);
        $message = CrmMessage::create([
            'business_id' => $business->id,
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'sent',
        ]);

        // Use otherUser in URL (wrong user)
        $url = URL::signedRoute('crm.campaigns.promo.save', [
            'campaign' => $campaign->id,
            'message' => $message->id,
            'promo' => $promotion->id,
            'user' => $otherUser->id,
            'business' => $business->id,
        ]);

        $response = $this->get($url);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error', 'This link is not valid for this account.');
    }

    public function test_save_redirects_to_home_with_error_when_promotion_expired(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create(['role' => 'user']);
        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(),
        ]);
        $campaign = CrmCampaign::create([
            'business_id' => $business->id,
            'name' => 'Test Campaign',
            'subject' => 'Test',
            'content_html' => '<p>Test</p>',
            'content_text' => 'Test',
            'status' => CrmCampaign::STATUS_SENT,
            'promotion_id' => $promotion->id,
        ]);
        $message = CrmMessage::create([
            'business_id' => $business->id,
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'sent',
        ]);

        $url = URL::signedRoute('crm.campaigns.promo.save', [
            'campaign' => $campaign->id,
            'message' => $message->id,
            'promo' => $promotion->id,
            'user' => $user->id,
            'business' => $business->id,
        ]);

        $response = $this->get($url);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error', 'This promotion has expired.');
    }

    public function test_save_redirects_to_home_with_error_when_no_active_qr_code(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create(['role' => 'user']);
        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
        ]);
        // No QRCode for this promotion
        $campaign = CrmCampaign::create([
            'business_id' => $business->id,
            'name' => 'Test Campaign',
            'subject' => 'Test',
            'content_html' => '<p>Test</p>',
            'content_text' => 'Test',
            'status' => CrmCampaign::STATUS_SENT,
            'promotion_id' => $promotion->id,
        ]);
        $message = CrmMessage::create([
            'business_id' => $business->id,
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'sent',
        ]);

        $url = URL::signedRoute('crm.campaigns.promo.save', [
            'campaign' => $campaign->id,
            'message' => $message->id,
            'promo' => $promotion->id,
            'user' => $user->id,
            'business' => $business->id,
        ]);

        $response = $this->get($url);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error', 'No active QR code is available for this promotion.');
    }

    public function test_save_claims_promo_and_redirects_to_portal_scans(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create(['role' => 'user']);
        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
        ]);
        $qrCode = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promotion->id,
            'is_active' => true,
        ]);
        $campaign = CrmCampaign::create([
            'business_id' => $business->id,
            'name' => 'Test Campaign',
            'subject' => 'Test',
            'content_html' => '<p>Test</p>',
            'content_text' => 'Test',
            'status' => CrmCampaign::STATUS_SENT,
            'promotion_id' => $promotion->id,
        ]);
        $message = CrmMessage::create([
            'business_id' => $business->id,
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'sent',
        ]);

        $url = URL::signedRoute('crm.campaigns.promo.save', [
            'campaign' => $campaign->id,
            'message' => $message->id,
            'promo' => $promotion->id,
            'user' => $user->id,
            'business' => $business->id,
        ]);

        $response = $this->get($url);

        $response->assertRedirect(route('portal.scans'));
        $response->assertSessionHas('success', 'Promotion saved to your wallet.');

        $this->assertDatabaseHas('saved_qr_codes', [
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReferralLandingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_landing_redirects_to_pricing_when_invalid_code(): void
    {
        $response = $this->get(route('referral.landing', ['code' => 'INVALID-CODE']));

        $response->assertRedirect(route('pricing'));
    }

    public function test_referral_landing_renders_page_when_valid_code(): void
    {
        $referrer = User::factory()->create();
        $referrer->forceFill(['referral_code' => 'JOIN-ME-123'])->save();

        $response = $this->get(route('referral.landing', ['code' => 'JOIN-ME-123']));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/ReferralLanding')
                ->where('referrerName', $referrer->name)
                ->where('referralCode', 'JOIN-ME-123')
            );
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReferralLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_referral_code_redirects_to_pricing(): void
    {
        $this->get('/join/NOPE-0000')->assertRedirect('/pricing');
    }

    public function test_valid_referral_code_renders_landing_and_sets_session(): void
    {
        $referrer = User::factory()->create([
            'name' => 'Referrer',
            'referral_code' => 'TEST-ABCD',
        ]);

        $this->get('/join/' . $referrer->referral_code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/ReferralLanding')
                    ->where('referrerName', 'Referrer')
                    ->where('referralCode', 'TEST-ABCD')
            )
            ->assertSessionHas('referral_code', 'TEST-ABCD');
    }
}


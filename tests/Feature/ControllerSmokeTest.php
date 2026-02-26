<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControllerSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_scan_error_routes(): void
    {
        $this->get('/s/missing')->assertStatus(200);
    }

    public function test_public_referral_landing_invalid_code(): void
    {
        $this->get('/r/INVALIDCODE')->assertStatus(302);
    }
}


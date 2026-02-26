<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoints_return_ok(): void
    {
        $this->get('/health')
            ->assertStatus(200)
            ->assertJson([
                'ok' => true,
            ]);

        $this->get('/api/health')
            ->assertStatus(200)
            ->assertJson([
                'ok' => true,
            ]);
    }

    public function test_health_with_db_check_enabled_includes_db_ok(): void
    {
        config(['app.healthcheck_db' => true]);

        $response = $this->getJson('/health');

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'checks' => [
                    'db' => 'ok',
                ],
            ]);
    }

    public function test_health_response_has_service_env_version_time(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'ok',
                'service',
                'env',
                'version',
                'time',
            ]);
    }
}


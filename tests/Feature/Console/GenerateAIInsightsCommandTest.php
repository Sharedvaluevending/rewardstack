<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateAIInsightsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exits_with_error_when_deepseek_not_configured(): void
    {
        config(['services.deepseek.api_key' => '']);

        $this->artisan('ai-insights:generate')
            ->expectsOutputToContain('DeepSeek API is not configured')
            ->assertExitCode(1);
    }

    public function test_command_exits_with_error_when_business_id_not_found(): void
    {
        config(['services.deepseek.api_key' => 'sk-test-fake']);

        $this->artisan('ai-insights:generate', ['--business-id' => 99999])
            ->expectsOutputToContain('not found')
            ->assertExitCode(1);
    }
}

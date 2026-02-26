<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetEnvSecretCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_fails_when_key_is_whitespace(): void
    {
        $this->artisan('env:set-secret', ['key' => '   '])
            ->expectsOutputToContain('Key is required')
            ->assertExitCode(1);
    }
}

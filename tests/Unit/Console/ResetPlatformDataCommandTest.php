<?php

namespace Tests\Unit\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResetPlatformDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_reset_with_force_runs_without_prompt(): void
    {
        $exitCode = Artisan::call('platform:reset', ['--force' => true]);

        $this->assertSame(0, $exitCode);
    }
}

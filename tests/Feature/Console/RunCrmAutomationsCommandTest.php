<?php

namespace Tests\Feature\Console;

use App\Models\Business;
use App\Services\CrmAutomationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunCrmAutomationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_processes_businesses_and_reports_count(): void
    {
        Business::factory()->count(2)->create(['is_active' => true]);

        $this->mock(CrmAutomationRunner::class, function ($mock) {
            $mock->shouldReceive('runForBusiness')
                ->twice()
                ->andReturn(['automations' => [['sent' => 0]]]);
        });

        $this->artisan('crm:run-automations')
            ->expectsOutputToContain('Processed 2 business(es)')
            ->assertExitCode(0);
    }

    public function test_command_filters_by_business_id_option(): void
    {
        $business = Business::factory()->create(['is_active' => true]);

        $this->mock(CrmAutomationRunner::class, function ($mock) {
            $mock->shouldReceive('runForBusiness')->once()->andReturn(['automations' => []]);
        });

        $this->artisan('crm:run-automations', ['--business_id' => (string) $business->id])
            ->expectsOutputToContain('Processed 1 business(es)')
            ->assertExitCode(0);
    }

    public function test_command_skips_inactive_businesses(): void
    {
        Business::factory()->create(['is_active' => false]);

        $this->artisan('crm:run-automations')
            ->expectsOutputToContain('Processed 0 business(es)')
            ->assertExitCode(0);
    }
}

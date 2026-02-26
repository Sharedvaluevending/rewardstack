<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\CrmAutomation;
use App\Services\CrmAutomationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CrmAutomationRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_for_business_returns_early_when_sendgrid_not_configured(): void
    {
        Config::set('services.sendgrid.api_key', '');
        $business = Business::factory()->create();
        $runner = app(CrmAutomationRunner::class);

        $result = $runner->runForBusiness($business);

        $this->assertSame($business->id, $result['business_id']);
        $this->assertSame([], $result['automations']);
        $this->assertSame('SendGrid not configured', $result['note']);
    }

    public function test_run_for_business_returns_empty_automations_when_none_active(): void
    {
        Config::set('services.sendgrid.api_key', 'test_key');
        $business = Business::factory()->create();
        CrmAutomation::create([
            'business_id' => $business->id,
            'name' => 'Inactive',
            'trigger' => 'winback',
            'config' => [],
            'is_active' => false,
        ]);
        $runner = app(CrmAutomationRunner::class);

        $result = $runner->runForBusiness($business);

        $this->assertSame($business->id, $result['business_id']);
        $this->assertSame([], $result['automations']);
        $this->assertArrayNotHasKey('note', $result);
    }

    public function test_run_automation_returns_unknown_trigger_for_unsupported_trigger(): void
    {
        Config::set('services.sendgrid.api_key', 'test_key');
        $business = Business::factory()->create();
        $automation = CrmAutomation::create([
            'business_id' => $business->id,
            'name' => 'Unknown',
            'trigger' => 'unknown_trigger_type',
            'config' => [],
            'is_active' => true,
        ]);
        $runner = app(CrmAutomationRunner::class);
        Queue::fake();

        $result = $runner->runAutomation($business, $automation);

        $this->assertSame($automation->id, $result['id']);
        $this->assertSame('unknown_trigger_type', $result['trigger']);
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame('unknown trigger', $result['note']);
    }

    public function test_run_for_business_runs_automation_and_returns_summary_when_sendgrid_configured(): void
    {
        Config::set('services.sendgrid.api_key', 'test_key');
        $business = Business::factory()->create();
        $automation = CrmAutomation::create([
            'business_id' => $business->id,
            'name' => 'Winback',
            'trigger' => 'winback',
            'config' => ['days' => 30],
            'is_active' => true,
        ]);
        $runner = app(CrmAutomationRunner::class);
        Queue::fake();

        $result = $runner->runForBusiness($business);

        $this->assertSame($business->id, $result['business_id']);
        $this->assertCount(1, $result['automations']);
        $this->assertSame($automation->id, $result['automations'][0]['id']);
        $this->assertSame('winback', $result['automations'][0]['trigger']);
        $this->assertArrayHasKey('sent', $result['automations'][0]);
        $this->assertArrayHasKey('skipped', $result['automations'][0]);
    }

    public function test_run_automation_winback_with_no_eligible_users_returns_sent_zero(): void
    {
        Config::set('services.sendgrid.api_key', 'test_key');
        $business = Business::factory()->create();
        $automation = CrmAutomation::create([
            'business_id' => $business->id,
            'name' => 'Winback',
            'trigger' => 'winback',
            'config' => ['days' => 30],
            'is_active' => true,
        ]);
        $runner = app(CrmAutomationRunner::class);
        Queue::fake();

        $result = $runner->runAutomation($business, $automation);

        $this->assertSame($automation->id, $result['id']);
        $this->assertSame('winback', $result['trigger']);
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['skipped']);
    }

    public function test_run_automation_punch_card_nudge_with_no_eligible_cards_returns_sent_zero(): void
    {
        Config::set('services.sendgrid.api_key', 'test_key');
        $business = Business::factory()->create();
        $automation = CrmAutomation::create([
            'business_id' => $business->id,
            'name' => 'Punch Nudge',
            'trigger' => 'punch_card_nudge',
            'config' => ['inactive_days' => 7],
            'is_active' => true,
        ]);
        $runner = app(CrmAutomationRunner::class);
        Queue::fake();

        $result = $runner->runAutomation($business, $automation);

        $this->assertSame($automation->id, $result['id']);
        $this->assertSame('punch_card_nudge', $result['trigger']);
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['skipped']);
    }

    public function test_run_automation_promo_expiring_with_no_eligible_saves_returns_sent_zero(): void
    {
        Config::set('services.sendgrid.api_key', 'test_key');
        $business = Business::factory()->create();
        $automation = CrmAutomation::create([
            'business_id' => $business->id,
            'name' => 'Expiring',
            'trigger' => 'promo_expiring',
            'config' => ['days' => 3],
            'is_active' => true,
        ]);
        $runner = app(CrmAutomationRunner::class);
        Queue::fake();

        $result = $runner->runAutomation($business, $automation);

        $this->assertSame($automation->id, $result['id']);
        $this->assertSame('promo_expiring', $result['trigger']);
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['skipped']);
    }
}

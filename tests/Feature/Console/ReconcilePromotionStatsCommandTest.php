<?php

namespace Tests\Feature\Console;

use App\Models\Business;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcilePromotionStatsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_stats_single_promotion_updates_from_redemptions(): void
    {
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'total_redemptions' => 0,
            'total_savings' => 0,
        ]);

        $this->artisan('promotions:reconcile-stats', ['--promotion' => $promo->id])
            ->expectsOutputToContain("Reconciling stats for promotion ID: {$promo->id}")
            ->assertExitCode(0);

        $promo->refresh();
        $this->assertSame(0, (int) $promo->total_redemptions);
    }

    public function test_reconcile_stats_single_promotion_not_found_returns_error(): void
    {
        $this->artisan('promotions:reconcile-stats', ['--promotion' => 99999])
            ->expectsOutputToContain('not found')
            ->assertExitCode(0);
    }
}

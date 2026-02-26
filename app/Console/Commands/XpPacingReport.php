<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\XpService;
use Illuminate\Console\Command;

class XpPacingReport extends Command
{
    protected $signature = 'xp:pacing
        {--savings=8 : Avg savings per redemption (USD/CAD)}
        {--redemptions_per_week=3 : Redemptions per week}
        {--scan_xp_per_day=0 : Expected scan XP per day (after caps)}
        {--game_xp_per_day=0 : Expected game XP per day (after caps)}';

    protected $description = 'Print XP thresholds per level and estimate time-to-level under usage scenarios.';

    public function handle(XpService $xpService): int
    {
        $dummy = new User();

        $this->info('XP / Level Pacing Report');
        $this->line('Level curve: xp(level) = 5000 * (level - 1)^1.5 (Level 1 starts at 0)');
        $this->newLine();

        $this->line('Key thresholds:');
        foreach ([2, 3, 5, 10, 20, 30, 40, 50] as $lvl) {
            $this->line(sprintf('  Level %-2d: %s XP', $lvl, number_format($dummy->getXpForLevel($lvl))));
        }
        $this->newLine();

        $this->line('Per-level table (required XP and XP-to-next):');
        $rows = [];
        for ($lvl = 1; $lvl <= 50; $lvl++) {
            $atXp = $dummy->getXpForLevel($lvl);
            $nextXp = $dummy->getXpForLevel(min(51, $lvl + 1));
            $delta = ($lvl >= 50) ? 0 : max(0, $nextXp - $atXp);
            $rows[] = [
                'Level' => $lvl,
                'XP_at_level' => number_format($atXp),
                'XP_to_next' => number_format($delta),
            ];
        }
        $this->table(['Level', 'XP_at_level', 'XP_to_next'], $rows);

        $this->newLine();
        $avgSavings = (float) $this->option('savings');
        $redemptionsPerWeek = (int) $this->option('redemptions_per_week');
        $scanXpPerDay = (int) $this->option('scan_xp_per_day');
        $gameXpPerDay = (int) $this->option('game_xp_per_day');

        $redemptionsPerWeek = max(0, $redemptionsPerWeek);
        $scanXpPerDay = max(0, $scanXpPerDay);
        $gameXpPerDay = max(0, $gameXpPerDay);

        $xpPerRedemption = $xpService->estimateRedemptionXpFromSavings($avgSavings);
        $weeklyXp = ($redemptionsPerWeek * $xpPerRedemption) + (($scanXpPerDay + $gameXpPerDay) * 7);
        $monthlyXp = (int) round($weeklyXp * 4.345); // avg weeks/month

        $targetXp = $dummy->getXpForLevel(50);
        $monthsTo50 = $monthlyXp > 0 ? ($targetXp / $monthlyXp) : INF;

        $this->info('Scenario estimate (inputs)');
        $this->line(sprintf('  Avg savings per redemption: $%s', number_format($avgSavings, 2)));
        $this->line(sprintf('  Redemptions per week: %d', $redemptionsPerWeek));
        $this->line(sprintf('  Estimated XP per redemption: %s', number_format($xpPerRedemption)));
        $this->line(sprintf('  Scan XP per day (expected): %d', $scanXpPerDay));
        $this->line(sprintf('  Game XP per day (expected): %d', $gameXpPerDay));
        $this->newLine();

        $this->info('Scenario estimate (results)');
        $this->line(sprintf('  Estimated XP per month: %s', number_format($monthlyXp)));
        if (is_infinite($monthsTo50)) {
            $this->line('  Estimated time to Level 50: n/a (0 XP/month)');
        } else {
            $this->line(sprintf('  Estimated time to Level 50: %.1f months (%.2f years)', $monthsTo50, $monthsTo50 / 12));
        }

        $this->newLine();
        $this->line('Note: non-monetary XP (scan/game) is capped daily by config(xp.non_monetary.daily_cap).');

        return self::SUCCESS;
    }
}


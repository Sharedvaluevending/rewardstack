<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\BusinessHealthScore;
use App\Models\Scan;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateBusinessHealth extends Command
{
    protected $signature = 'business-health:calculate';
    protected $description = 'Calculate daily health scores for all active businesses';

    public function handle(): int
    {
        $today = today();
        $weekAgo = $today->copy()->subDays(7);

        $businesses = Business::where('is_active', true)
            ->with('owner')
            ->get();

        $this->info("Calculating health for {$businesses->count()} businesses...");

        $scansByBusiness = Scan::select('business_id', DB::raw('COUNT(*) as total'))
            ->groupBy('business_id')
            ->pluck('total', 'business_id');

        $weeklyScans = Scan::select('business_id', DB::raw('COUNT(*) as total'))
            ->where('scanned_at', '>=', $weekAgo)
            ->groupBy('business_id')
            ->pluck('total', 'business_id');

        foreach ($businesses as $business) {
            $totalScans = (int) ($scansByBusiness[$business->id] ?? 0);
            $scansThisWeek = (int) ($weeklyScans[$business->id] ?? 0);
            $activePromos = $business->promotions()
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->count();
            $qrCount = $business->qrCodes()->whereNull('deleted_at')->count();
            $customerCount = DB::table('business_customers')
                ->where('business_id', $business->id)
                ->count();
            $lastLogin = $business->owner?->last_login_at;

            $score = $this->calculateScore(
                $scansThisWeek,
                $activePromos,
                $lastLogin,
                $qrCount,
                $business->created_at
            );

            $status = match (true) {
                $score >= 70 => 'healthy',
                $score >= 40 => 'at_risk',
                default => 'inactive',
            };

            BusinessHealthScore::updateOrCreate(
                ['business_id' => $business->id, 'date' => $today],
                [
                    'total_scans' => $totalScans,
                    'scans_this_week' => $scansThisWeek,
                    'active_promotions' => $activePromos,
                    'qr_codes_count' => $qrCount,
                    'customers_count' => $customerCount,
                    'last_login_at' => $lastLogin,
                    'health_status' => $status,
                    'health_score' => $score,
                    'created_at' => now(),
                ]
            );
        }

        $this->info('Done.');
        Log::info('business-health:calculate completed', ['businesses' => $businesses->count()]);

        return Command::SUCCESS;
    }

    public function calculateScore(
        int $scansThisWeek,
        int $activePromos,
        ?Carbon $lastLogin,
        int $qrCodes,
        ?Carbon $createdAt
    ): int {
        $score = 0;

        // Scans this week (30 points max)
        $score += match (true) {
            $scansThisWeek >= 50 => 30,
            $scansThisWeek >= 20 => 20,
            $scansThisWeek >= 5 => 10,
            default => 0,
        };

        // Active promotions (20 points max)
        $score += match (true) {
            $activePromos > 2 => 20,
            $activePromos >= 1 => 10,
            default => 0,
        };

        // Last login recency (20 points max)
        if ($lastLogin) {
            $daysSinceLogin = $lastLogin->diffInDays(now());
            $score += match (true) {
                $daysSinceLogin < 7 => 20,
                $daysSinceLogin <= 14 => 10,
                default => 0,
            };
        }

        // QR codes created (15 points max)
        $score += match (true) {
            $qrCodes > 3 => 15,
            $qrCodes >= 2 => 10,
            $qrCodes >= 1 => 5,
            default => 0,
        };

        // Customers enrolled (15 points max)
        // Implicitly covered by scans — keeping base at 85 max from above,
        // then the account-age adjustment below can bring new accounts down.

        // Account age adjustment: first 7 days they're still setting up
        if ($createdAt && $createdAt->diffInDays(now()) < 7) {
            $score = max(0, $score - 20);
        }

        return min(100, max(0, $score));
    }
}

<?php

namespace App\Console\Commands;

use App\Models\QRCode;
use App\Models\Scan;
use App\Models\User;
use App\Services\UserPromoTokenService;
use Illuminate\Console\Command;

class BackfillUserPromoTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'promo:backfill-tokens {--user-id= : Backfill for specific user only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill UserPromoTokens for existing scans that don\'t have tokens';

    /**
     * Execute the console command.
     */
    public function handle(UserPromoTokenService $tokenService)
    {
        $this->info('Starting UserPromoToken backfill...');

        $query = Scan::query()
            ->whereNotNull('user_id')
            ->whereHas('qrCode', function ($q) {
                $q->where('type', 'promotion')
                  ->whereHas('promotion');
            })
            ->with(['qrCode.promotion', 'user']);

        if ($this->option('user-id')) {
            $query->where('user_id', $this->option('user-id'));
        }

        $scans = $query->get();
        $this->info("Found {$scans->count()} scans to process");

        $created = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($scans as $scan) {
            if (!$scan->user || !$scan->qrCode || !$scan->qrCode->promotion) {
                $skipped++;
                continue;
            }

            // Check if token already exists
            $existingToken = \App\Models\UserPromoToken::where('user_id', $scan->user_id)
                ->where('qr_code_id', $scan->qr_code_id)
                ->first();

            if ($existingToken) {
                $skipped++;
                continue;
            }

            try {
                // Ensure token exists (will create if missing)
                $token = $tokenService->ensure($scan->user, $scan->qrCode);
                if ($token) {
                    $created++;
                    $this->line("Created token {$token->code} for user {$scan->user->name} (QR: {$scan->qrCode->code})");
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error("Error creating token for scan {$scan->id}: {$e->getMessage()}");
            }
        }

        $this->info("\nBackfill complete!");
        $this->info("Created: {$created}");
        $this->info("Skipped: {$skipped}");
        $this->info("Errors: {$errors}");
    }
}

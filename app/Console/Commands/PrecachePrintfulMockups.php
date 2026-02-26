<?php

namespace App\Console\Commands;

use App\Services\PrintfulService;
use Illuminate\Console\Command;

class PrecachePrintfulMockups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:precache-printful-mockups {--force : Force re-cache all mockups}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-cache blank Printful mockups for common colors to avoid API calls during live editing';

    protected PrintfulService $printfulService;

    public function __construct(PrintfulService $printfulService)
    {
        parent::__construct();
        $this->printfulService = $printfulService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Printful mockup pre-caching...');

        $force = $this->option('force');
        if ($force) {
            $this->warn('Force mode enabled - will re-cache all mockups');
        }

        try {
            $this->printfulService->precacheCommonMockups();
            $this->info('Mockup pre-caching completed successfully!');
            $this->info('Common t-shirt and hoodie mockups are now cached locally.');
            $this->info('Live editing will now use cached mockups instead of API calls.');
        } catch (\Exception $e) {
            $this->error('Failed to pre-cache mockups: ' . $e->getMessage());
            \Log::error('Precache command failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return 1;
        }

        return 0;
    }
}

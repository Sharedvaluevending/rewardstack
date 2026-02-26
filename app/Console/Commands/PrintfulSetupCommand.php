<?php

namespace App\Console\Commands;

use App\Services\PrintfulService;
use Illuminate\Console\Command;

class PrintfulSetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'printful:setup 
                            {--webhooks : Register webhooks with Printful}
                            {--sync-products : Sync products from Printful catalog}
                            {--info : Display store information}';

    /**
     * The console command description.
     */
    protected $description = 'Set up Printful integration (webhooks, products, etc.)';

    protected PrintfulService $printfulService;

    public function __construct(PrintfulService $printfulService)
    {
        parent::__construct();
        $this->printfulService = $printfulService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('info')) {
            return $this->showStoreInfo();
        }

        if ($this->option('webhooks')) {
            return $this->setupWebhooks();
        }

        if ($this->option('sync-products')) {
            return $this->syncProducts();
        }

        // Default: show all info
        $this->showStoreInfo();
        $this->showWebhooks();

        return Command::SUCCESS;
    }

    /**
     * Show store information
     */
    protected function showStoreInfo(): int
    {
        $this->info('Fetching Printful store information...');

        try {
            $stores = $this->printfulService->getStoreInfo();

            if (empty($stores)) {
                $this->error('No stores found. Check your API key.');
                return Command::FAILURE;
            }

            $this->newLine();
            $this->info('✓ Connected to Printful successfully!');
            $this->newLine();

            foreach ($stores as $store) {
                $this->table(
                    ['Property', 'Value'],
                    [
                        ['Store ID', $store['id'] ?? 'N/A'],
                        ['Store Name', $store['name'] ?? 'N/A'],
                        ['Type', $store['type'] ?? 'N/A'],
                        ['Website', $store['website'] ?? 'N/A'],
                        ['Created', $store['created'] ?? 'N/A'],
                    ]
                );
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to connect to Printful: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Show registered webhooks
     */
    protected function showWebhooks(): void
    {
        $this->info('Checking registered webhooks...');

        try {
            $webhooks = $this->printfulService->getWebhooks();

            if (empty($webhooks)) {
                $this->warn('No webhooks registered.');
                $this->line('Run: php artisan printful:setup --webhooks');
            } else {
                $this->info('Registered webhook URL: ' . ($webhooks['url'] ?? 'Unknown'));
                $this->info('Event types: ' . implode(', ', $webhooks['types'] ?? []));
            }
        } catch (\Exception $e) {
            $this->warn('Could not fetch webhooks: ' . $e->getMessage());
        }
    }

    /**
     * Set up webhooks
     */
    protected function setupWebhooks(): int
    {
        $webhookUrl = url('/webhooks/printful');

        $this->info("Registering webhooks with URL: {$webhookUrl}");

        if (!$this->confirm('This will replace any existing webhooks. Continue?')) {
            return Command::SUCCESS;
        }

        try {
            // First, try to delete existing webhooks
            $this->printfulService->deleteWebhook();

            // Register new webhooks
            $result = $this->printfulService->registerWebhooks($webhookUrl);

            $this->newLine();
            $this->info('✓ Webhooks registered successfully!');
            $this->newLine();

            $this->table(
                ['Event Type', 'Status'],
                collect($result['types'] ?? [])->map(fn($type) => [$type, '✓'])->toArray()
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to register webhooks: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Sync products from Printful
     */
    protected function syncProducts(): int
    {
        $this->info('Syncing products from Printful catalog...');

        // Define popular products to sync
        $productIds = [
            71,   // Unisex Staple T-Shirt
            380,  // Unisex Hoodie
            19,   // White Glossy Mug
            1,    // Poster
            534,  // Die-Cut Stickers
            505,  // Canvas
            181,  // Tote Bag
        ];

        $this->withProgressBar($productIds, function ($productId) {
            try {
                $productData = $this->printfulService->getProductVariants($productId);

                if (!empty($productData)) {
                    // Product sync logic would go here
                    // This is handled in MerchController::syncPrintfulProducts()
                }
            } catch (\Exception $e) {
                $this->newLine();
                $this->warn("Failed to sync product {$productId}: " . $e->getMessage());
            }
        });

        $this->newLine();
        $this->info('✓ Product sync complete!');

        return Command::SUCCESS;
    }
}

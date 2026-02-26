<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\PrintfulService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SyncPrintfulProducts extends Command
{
    protected $signature = 'printful:sync {--force : Force update all products} {--markup= : Markup percentage (default: 15)} {--product-ids= : Comma-separated list of specific catalog product IDs to sync} {--all : Sync all catalog products} {--store-product-ids= : Comma-separated list of specific store product IDs to sync} {--generate-mockups : Generate blank mockups for all views (back, sleeves, wrap) using Printful API}';
    protected $description = 'Sync products from Printful catalog';

    protected PrintfulService $printfulService;
    
    // Default markup percentage (15% covers Stripe fees + small profit)
    protected float $markupPercent = 15;

    // Popular QR-friendly product IDs from Printful
    protected array $productIds = [
        71,   // Unisex Staple T-Shirt (Bella + Canvas 3001)
        380,  // Unisex Hoodie
        19,   // White Glossy Mug
        1,    // Poster
        534,  // Die-Cut Stickers
        505,  // Canvas
        88,   // All-Over Print T-Shirt
        181,  // Tote Bag
        206,  // Baseball Cap
        382,  // Unisex Zip Hoodie
        473,  // Canvas Shopping Bag
        20,   // Black Glossy Mug
        157,  // Ceramic Coaster
        367,  // Phone Case (iPhone)
    ];

    public function __construct(PrintfulService $printfulService)
    {
        parent::__construct();
        $this->printfulService = $printfulService;
    }

    public function handle()
    {
        // Get markup from option or config or default
        $this->markupPercent = (float) ($this->option('markup') ?? config('merch.markup_percent', 15));
        
        // Determine which products to sync
        $productIds = $this->productIds; // Default list

        if ($this->option('product-ids')) {
            // Sync specific product IDs
            $productIds = explode(',', $this->option('product-ids'));
            $productIds = array_map('intval', array_filter($productIds));
            $this->info('🎯 Syncing specific product IDs: ' . implode(', ', $productIds));
        } else        if ($this->option('all')) {
            // Sync all products from store
            $this->info('🌍 Syncing all products from Printful store...');
            $storeProducts = $this->printfulService->getStoreProducts();
            $productIds = collect($storeProducts)->pluck('id')->filter()->values()->toArray();
            $this->info("Found " . count($productIds) . " products in store");
        } else {
            $this->info('🔄 Syncing default popular products...');
        }

        $this->info("📊 Using {$this->markupPercent}% markup");
        $this->newLine();

        $bar = $this->output->createProgressBar(count($productIds));
        $bar->start();

        $synced = 0;
        $failed = 0;

        foreach ($productIds as $productId) {
            try {
                $productData = $this->printfulService->getProductVariants($productId);
                
                if (empty($productData)) {
                    $failed++;
                    $bar->advance();
                    continue;
                }

                $product = $productData['product'] ?? null;
                $variants = $productData['variants'] ?? [];
                
                if (!$product) {
                    $failed++;
                    $bar->advance();
                    continue;
                }

                // Map category
                $category = $this->mapCategory($product['type_name'] ?? 'other');

                // Get the cheapest variant as base (cost) price
                $costPrice = collect($variants)->min('price') ?? 15.00;
                
                // Calculate our price with markup percentage
                $ourPrice = round($costPrice * (1 + ($this->markupPercent / 100)), 2);
                
                // Calculate suggested retail (30% above our price)
                $suggestedRetail = round($ourPrice * 1.30, 2);

                // Format variants for storage
                $formattedVariants = $this->formatVariants($variants, $costPrice);

                // Get product images
                $images = $this->getProductImages($product, $variants);

                // Download and cache variant images by color (for use as base mockups)
                $productSlug = Str::slug($product['title'] ?? $product['name'] ?? 'product-' . $productId);
                
                // First, download front view from variant images (fast, free)
                $this->downloadVariantMockups($productSlug, $variants, $category);
                
                // Then, generate blank mockups for all views (back, sleeves, wrap) using Printful API
                if ($this->option('generate-mockups')) {
                    $this->generateAllViewMockups($productId, $productSlug, $variants, $category);
                }

                // Create or update product
                Product::updateOrCreate(
                    ['printful_product_id' => (string) $productId],
                    [
                        'name' => $product['title'] ?? $product['name'] ?? 'Product',
                        'slug' => $productSlug,
                        'description' => $product['description'] ?? 'High-quality print-on-demand product featuring your QR code design.',
                        'category' => $category,
                        'base_price' => $ourPrice, // Your price with markup
                        'cost_price' => $costPrice, // What Printful charges (for reference)
                        'suggested_retail' => $suggestedRetail, // If business wants to resell
                        'variants' => $formattedVariants,
                        'print_areas' => $this->getPrintAreas($category),
                        'images' => $images,
                        'is_active' => true,
                    ]
                );

                $synced++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->warn("Failed to sync product {$productId}: " . $e->getMessage());
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Synced: {$synced} products");
        $this->info("💰 Markup: {$this->markupPercent}%");
        if ($failed > 0) {
            $this->warn("⚠️  Failed: {$failed} products");
        }

        return Command::SUCCESS;
    }

    protected function mapCategory(string $typeName): string
    {
        $mapping = [
            'T-SHIRTS' => 't-shirt',
            'TANK TOPS' => 't-shirt',
            'HOODIES & SWEATSHIRTS' => 'hoodie',
            'JACKETS' => 'hoodie',
            'MUGS' => 'mug',
            'POSTERS' => 'poster',
            'CANVAS' => 'poster',
            'STICKERS' => 'sticker',
            'BAGS' => 'bag',
            'HATS' => 'hat',
            'PHONE CASES' => 'phone_case',
            'COASTERS' => 'home',
        ];

        foreach ($mapping as $type => $category) {
            if (stripos($typeName, $type) !== false) {
                return $category;
            }
        }

        return 'other';
    }

    protected function formatVariants(array $variants, float $basePrice): array
    {
        return collect($variants)
            ->groupBy('size')
            ->map(function ($sizeGroup, $size) use ($basePrice) {
                $colors = $sizeGroup->map(fn($v) => [
                    'name' => $v['color'] ?? 'Default',
                    'code' => $v['color_code'] ?? '#000000',
                    'variant_id' => $v['id'],
                ])->values()->toArray();
                
                $minPrice = $sizeGroup->min('price');
                
                return [
                    'name' => $size ?: 'Standard',
                    'colors' => $colors,
                    'price_modifier' => round($minPrice - $basePrice, 2),
                    'variant_ids' => $sizeGroup->pluck('id')->values()->toArray(),
                ];
            })
            ->values()
            ->toArray();
    }

    protected function getProductImages(array $product, array $variants): array
    {
        $images = [];
        
        // Main product image
        if (!empty($product['image'])) {
            $images[] = $product['image'];
        }

        // Get a few variant images for different colors
        $variantImages = collect($variants)
            ->pluck('image')
            ->filter()
            ->unique()
            ->take(4)
            ->values()
            ->toArray();

        return array_merge($images, $variantImages);
    }

    /**
     * Download variant images from Printful and cache them locally as base mockups
     */
    protected function downloadVariantMockups(string $productSlug, array $variants, string $category): void
    {
        // Group variants by color to get one image per color
        $colorsSeen = [];
        
        foreach ($variants as $variant) {
            $color = $variant['color'] ?? 'default';
            $colorNormalized = $this->normalizeColorName($color);
            $imageUrl = $variant['image'] ?? null;
            
            if (!$imageUrl || isset($colorsSeen[$colorNormalized])) {
                continue;
            }
            
            $colorsSeen[$colorNormalized] = true;
            
            try {
                // Download image
                $response = Http::timeout(30)->get($imageUrl);
                if (!$response->successful()) {
                    continue;
                }
                
                $imageData = $response->body();
                
                // Determine file extension
                $ext = 'jpg';
                if (str_contains($imageUrl, '.png')) {
                    $ext = 'png';
                }
                
                // Save as front view mockup (Printful variant images are usually front-only)
                $path = "mockups/{$productSlug}/{$colorNormalized}/front.{$ext}";
                Storage::disk('public')->put($path, $imageData);
                
                $this->info("  ✓ Cached mockup: {$color} ({$path})");
            } catch (\Exception $e) {
                // Silently skip failed downloads
            }
        }
    }

    protected function normalizeColorName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', '-', $name);
        return trim($name, '-');
    }

    /**
     * Generate blank mockups for all views (back, sleeves, wrap) using Printful API
     */
    protected function generateAllViewMockups(int $catalogProductId, string $productSlug, array $variants, string $category): void
    {
        $this->info("  🔄 Generating blank mockups for all views via Printful API...");
        
        // Group variants by color (one variant per color is enough)
        $colorsSeen = [];
        $generated = 0;
        
        foreach ($variants as $variant) {
            $color = $variant['color'] ?? 'default';
            $colorNormalized = $this->normalizeColorName($color);
            $variantId = $variant['id'] ?? null;
            
            if (!$variantId || isset($colorsSeen[$colorNormalized])) {
                continue;
            }
            
            $colorsSeen[$colorNormalized] = true;
            
            try {
                $this->info("    Generating mockups for {$color}...");
                $results = $this->printfulService->generateBlankMockups(
                    $catalogProductId,
                    $variantId,
                    $productSlug,
                    $colorNormalized,
                    $category
                );
                
                foreach ($results as $view => $path) {
                    $this->info("      ✓ {$view}: {$path}");
                    $generated++;
                }
            } catch (\Exception $e) {
                $this->warn("      ✗ Failed for {$color}: " . $e->getMessage());
            }
        }
        
        if ($generated > 0) {
            $this->info("  ✓ Generated {$generated} blank mockups");
        }
    }

    protected function getPrintAreas(string $category): array
    {
        $areas = [
            't-shirt' => [
                ['name' => 'front', 'width' => 1800, 'height' => 2400, 'default' => true],
                ['name' => 'back', 'width' => 1800, 'height' => 2400, 'default' => false],
            ],
            'hoodie' => [
                ['name' => 'front', 'width' => 1800, 'height' => 2400, 'default' => true],
                ['name' => 'back', 'width' => 1800, 'height' => 2400, 'default' => false],
            ],
            'mug' => [
                ['name' => 'wrap', 'width' => 2700, 'height' => 1100, 'default' => true],
            ],
            'poster' => [
                ['name' => 'front', 'width' => 3600, 'height' => 4800, 'default' => true],
            ],
            'sticker' => [
                ['name' => 'front', 'width' => 1800, 'height' => 1800, 'default' => true],
            ],
            'bag' => [
                ['name' => 'front', 'width' => 1200, 'height' => 1200, 'default' => true],
            ],
            'hat' => [
                ['name' => 'front', 'width' => 1200, 'height' => 600, 'default' => true],
            ],
        ];

        return $areas[$category] ?? [
            ['name' => 'front', 'width' => 1800, 'height' => 1800, 'default' => true],
        ];
    }
}

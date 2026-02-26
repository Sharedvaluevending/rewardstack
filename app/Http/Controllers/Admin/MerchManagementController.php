<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Product;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Services\PrintfulService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MerchManagementController extends Controller
{
    protected PrintfulService $printfulService;
    protected StripeService $stripeService;

    public function __construct(PrintfulService $printfulService, StripeService $stripeService)
    {
        $this->printfulService = $printfulService;
        $this->stripeService = $stripeService;
    }

    /**
     * Display merch dashboard with products and orders
     */
    public function index(Request $request)
    {
        $businesses = Business::query()
            ->orderBy('name')
            ->get(['id', 'name', 'subscription_tier']);

        $storeBusiness = null;
        if ($request->filled('business_id')) {
            $storeBusiness = $businesses->firstWhere('id', (int) $request->input('business_id'));
        }

        if (!$storeBusiness) {
            $defaultId = config('merch.default_store_business_id');
            $defaultName = config('merch.default_store_business_name');
            if ($defaultId) {
                $storeBusiness = $businesses->firstWhere('id', $defaultId);
            }
            if (!$storeBusiness && $defaultName) {
                $storeBusiness = Business::query()
                    ->whereRaw('LOWER(name) = ?', [strtolower($defaultName)])
                    ->first();
            }
        }

        if (!$storeBusiness) {
            $storeBusiness = $businesses->first();
        }

        $storeEnabled = false;
        $allowedCategories = [];
        if ($storeBusiness) {
            $plan = SubscriptionPlan::where('slug', $storeBusiness->subscription_tier)->first();
            $features = is_array($plan?->features) ? $plan->features : [];
            $storeEnabled = (bool) ($features['merch_store'] ?? false);
            $allowedCategories = $features['merch_categories'] ?? [];
            $allowedCategories = is_array($allowedCategories) ? $allowedCategories : [];
        }

        if (in_array('all', $allowedCategories, true)) {
            $allowedCategories = ['t-shirt', 'hoodie', 'mug', 'sticker', 'poster', 'bag', 'hat', 'phone_case'];
        } elseif (count($allowedCategories) === 0) {
            $allowedCategories = ['t-shirt'];
        }

        $products = Product::query()
            ->withCount('orderItems')
            ->orderBy('sort_order')
            ->orderBy('category')
            ->get();

        $products = $products->map(function ($product) use ($storeEnabled, $allowedCategories) {
            $storeVisible = false;
            $storeReason = 'Store disabled';

            if ($storeEnabled) {
                if (!$product->is_active) {
                    $storeReason = 'Disabled in admin';
                } elseif (empty($product->printful_product_id)) {
                    $storeReason = 'Missing Printful store ID';
                } elseif ($product->category === 'other') {
                    $storeReason = 'Category not shown in store';
                } elseif (!in_array($product->category, $allowedCategories, true)) {
                    $storeReason = 'Not in plan categories';
                } elseif ($product->category === 'mug') {
                    $storeReason = 'Mugs hidden in store UI';
                } else {
                    $storeVisible = true;
                    $storeReason = 'Visible in store';
                }
            }

            return [
                ...$product->toArray(),
                'store_visible' => $storeVisible,
                'store_reason' => $storeReason,
            ];
        });

        $stats = [
            'total_products' => $products->count(),
            'active_products' => $products->where('is_active', true)->count(),
            'total_orders' => Order::where('type', 'merch')->count(),
            'pending_orders' => Order::where('type', 'merch')->where('status', 'pending')->count(),
            'revenue' => Order::where('type', 'merch')->where('payment_status', 'paid')->sum('total'),
        ];

        $recentOrders = Order::where('type', 'merch')
            ->with(['business', 'items.product'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('Admin/Merch/Index', [
            'products' => $products,
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'storeBusiness' => $storeBusiness ? [
                'id' => $storeBusiness->id,
                'name' => $storeBusiness->name,
                'subscription_tier' => $storeBusiness->subscription_tier,
            ] : null,
            'storeBusinesses' => $businesses->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
            ]),
            'storeEnabled' => $storeEnabled,
        ]);
    }

    /**
     * Sync products from Printful
     */
    public function sync(Request $request)
    {
        try {
            // Default behavior: sync all store products
            if (!$request->filled('product_ids') && !$request->filled('store_product_ids') && !$request->boolean('sync_all')) {
                return $this->syncAllStoreProducts();
            }

            // Check if specific catalog product IDs were provided
            if ($request->filled('product_ids')) {
                $productIds = preg_replace('/[^0-9,]/', '', $request->input('product_ids'));
                Artisan::call('printful:sync', ['--product-ids' => $productIds]);
                return back()->with('success', 'Specific catalog products synced successfully!');
            }

            // Check if store product IDs were provided
            if ($request->filled('store_product_ids')) {
                $input = $request->input('store_product_ids');
                $storeProductIds = is_array($input) ? implode(',', $input) : (string) $input;
                return $this->syncStoreProducts($storeProductIds);
            }

            // Check if sync all catalog products was requested
            if ($request->boolean('sync_all')) {
                Artisan::call('printful:sync', ['--all' => true]);
                return back()->with('success', 'All catalog products synced successfully!');
            }

            return back()->with('error', 'No sync option specified');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to sync products: ' . $e->getMessage());
        }
    }

    /**
     * Sync all store products (default behavior)
     */
    protected function syncAllStoreProducts()
    {
        try {
            $printfulService = app(PrintfulService::class);
            $storeProducts = $printfulService->getStoreProducts();

            if (empty($storeProducts)) {
                return back()->with('error', 'No store products found in Printful');
            }

            $storeProductIds = collect($storeProducts)->pluck('id')->toArray();
            return $this->syncStoreProducts(implode(',', $storeProductIds));

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to sync store products: ' . $e->getMessage());
        }
    }

    /**
     * Sync store products (custom products from user's Printful store)
     */
    protected function syncStoreProducts(string $storeProductIds)
    {
        $ids = explode(',', $storeProductIds);
        $ids = array_map('intval', array_filter($ids));

        $synced = 0;
        $failed = 0;

        foreach ($ids as $storeProductId) {
            try {
                $storeProductData = $this->printfulService->getStoreProduct($storeProductId);

                if (empty($storeProductData)) {
                    $failed++;
                    continue;
                }

                // Get the catalog product ID from the first variant
                $catalogProductId = null;
                if (isset($storeProductData['sync_variants']) && is_array($storeProductData['sync_variants']) && count($storeProductData['sync_variants']) > 0) {
                    $firstVariant = $storeProductData['sync_variants'][0];
                    $catalogProductId = $firstVariant['product']['product_id'] ?? null;
                }

                if (!$catalogProductId) {
                    $failed++;
                    continue;
                }

                // Get catalog product details
                $catalogData = $this->printfulService->getProductVariants($catalogProductId);
                if (empty($catalogData)) {
                    $failed++;
                    continue;
                }

                // Use catalog data but override with store-specific info
                $product = $catalogData['product'] ?? null;
                $variants = $catalogData['variants'] ?? [];

                if (!$product) {
                    $failed++;
                    continue;
                }

                // Override product name with store product name
                $storeProductName = $storeProductData['sync_product']['name'] ?? null;
                if ($storeProductName) {
                    $product['name'] = $storeProductName;
                    $product['title'] = $storeProductName;
                }

                // Map category and create product (similar to sync command)
                $category = $this->mapCategory($product['type_name'] ?? 'other');

                $costPrice = collect($variants)->min('price') ?? 15.00;
                $markupPercent = config('merch.markup_percent', 15);
                $ourPrice = round($costPrice * (1 + ($markupPercent / 100)), 2);

                $formattedVariants = $this->formatStoreVariants($storeProductData['sync_variants'] ?? [], $costPrice);
                $images = $this->getStoreProductImages($storeProductData);

                Product::updateOrCreate(
                    ['printful_product_id' => (string) $storeProductId],
                    [
                        'name' => $product['name'] ?? $product['title'] ?? 'Product',
                        'slug' => Str::slug($product['name'] ?? $product['title'] ?? 'product-' . $storeProductId),
                        'description' => $product['description'] ?? 'Custom product from your Printful store.',
                        'category' => $category,
                        'base_price' => $ourPrice,
                        'cost_price' => $costPrice,
                        'variants' => $formattedVariants,
                        'print_areas' => $this->getPrintAreas($category),
                        'images' => $images,
                        'is_active' => true,
                    ]
                );

                $synced++;
            } catch (\Exception $e) {
                $failed++;
            }
        }

        if ($synced > 0) {
            return back()->with('success', "Synced {$synced} store products successfully!" . ($failed > 0 ? " {$failed} failed." : ''));
        } else {
            return back()->with('error', 'Failed to sync any store products.');
        }
    }

    // Helper methods copied from sync command
    protected function mapCategory(string $typeName): string
    {
        $mapping = [
            'T-SHIRTS' => 't-shirt',
            'TANK TOPS' => 't-shirt',
            'T-SHIRT' => 't-shirt',
            'HOODIES & SWEATSHIRTS' => 'hoodie',
            'JACKETS' => 'hoodie',
            'MUGS' => 'mug',
            'MUG' => 'mug',
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

        if (!empty($product['image'])) {
            $images[] = $product['image'];
        }

        $variantImages = collect($variants)
            ->pluck('image')
            ->filter()
            ->unique()
            ->take(4)
            ->values()
            ->toArray();

        return array_merge($images, $variantImages);
    }

    protected function getStoreProductImages(array $storeProductData): array
    {
        $images = [];

        // Add the main product thumbnail
        if (!empty($storeProductData['sync_product']['thumbnail_url'])) {
            $images[] = $storeProductData['sync_product']['thumbnail_url'];
        }

        // Add preview images from variants (these have the custom designs)
        if (isset($storeProductData['sync_variants']) && is_array($storeProductData['sync_variants'])) {
            foreach ($storeProductData['sync_variants'] as $variant) {
                if (isset($variant['files']) && is_array($variant['files'])) {
                    foreach ($variant['files'] as $file) {
                        // Include all preview-related images (preview, default, etc.)
                        if (!empty($file['preview_url'])) {
                            $images[] = $file['preview_url'];
                        }
                    }
                }
            }
        }

        // Remove duplicates and limit to reasonable number
        return array_unique(array_slice($images, 0, 6));
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

    protected function formatStoreVariants(array $storeVariants, float $basePrice): array
    {
        return collect($storeVariants)
            ->groupBy('size')
            ->map(function ($sizeGroup, $size) use ($basePrice) {
                $colors = $sizeGroup->map(fn($v) => [
                    'name' => $v['color'] ?? 'Default',
                    'code' => '#FFFFFF', // Store variants may not have color codes
                    'variant_id' => $v['variant_id'],
                ])->values()->toArray();

                $minPrice = $sizeGroup->min('retail_price');

                return [
                    'name' => $size ?: 'Standard',
                    'colors' => $colors,
                    'price_modifier' => round((float)$minPrice - $basePrice, 2),
                    'variant_ids' => $sizeGroup->pluck('variant_id')->values()->toArray(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Toggle product active status
     */
    public function toggle(Product $product)
    {
        $product->update(['is_active' => !$product->is_active]);

        return back()->with('success', $product->is_active ? 'Product enabled' : 'Product disabled');
    }

    /**
     * Update product details
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'base_price' => 'sometimes|numeric|min:0',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'print_config' => 'sometimes|array',
            'print_config.logo' => 'sometimes|array',
            'print_config.logo.placement' => 'sometimes|string',
            'print_config.logo.size' => 'sometimes|string',
            'print_config.qr_code' => 'sometimes|array',
            'print_config.qr_code.placement' => 'sometimes|string',
            'print_config.qr_code.size' => 'sometimes|string',
        ]);

        // Convert size to actual dimensions if print_config is being updated
        if (isset($validated['print_config'])) {
            $validated['print_config'] = $this->convertPrintConfigSizes($validated['print_config']);
        }

        $product->update($validated);

        return back()->with('success', 'Product updated successfully');
    }

    /**
     * Convert size names to actual pixel dimensions
     */
    protected function convertPrintConfigSizes(array $config): array
    {
        $sizes = [
            'tiny' => ['width' => 150, 'height' => 150],
            'small' => ['width' => 300, 'height' => 300],
            'medium' => ['width' => 600, 'height' => 600],
            'large' => ['width' => 800, 'height' => 800],
            'xlarge' => ['width' => 1200, 'height' => 1200],
        ];

        $placements = [
            'front' => ['top' => 400, 'left' => 600],
            'back' => ['top' => 400, 'left' => 600],
            'sleeve_left' => ['top' => 200, 'left' => 150],
            'sleeve_right' => ['top' => 200, 'left' => 450],
            'default' => ['top' => 300, 'left' => 600],
        ];

        if (isset($config['logo']['size'])) {
            $sizeKey = $config['logo']['size'];
            if (isset($sizes[$sizeKey])) {
                $config['logo']['width'] = $sizes[$sizeKey]['width'];
                $config['logo']['height'] = $sizes[$sizeKey]['height'];
            }
        }

        if (isset($config['logo']['placement'])) {
            $placementKey = $config['logo']['placement'];
            if (isset($placements[$placementKey])) {
                $config['logo']['top'] = $placements[$placementKey]['top'];
                $config['logo']['left'] = $placements[$placementKey]['left'];
            }
        }

        if (isset($config['qr_code']['size'])) {
            $sizeKey = $config['qr_code']['size'];
            if (isset($sizes[$sizeKey])) {
                $config['qr_code']['width'] = $sizes[$sizeKey]['width'];
                $config['qr_code']['height'] = $sizes[$sizeKey]['height'];
            }
        }

        if (isset($config['qr_code']['placement'])) {
            $placementKey = $config['qr_code']['placement'];
            if (isset($placements[$placementKey])) {
                $config['qr_code']['top'] = $placements[$placementKey]['top'];
                $config['qr_code']['left'] = $placements[$placementKey]['left'];
            }
        }

        return $config;
    }

    /**
     * Upload custom product image
     */
    public function uploadImage(Request $request, Product $product)
    {
        $request->validate([
            'image' => 'required|image|max:5120', // 5MB max
        ]);

        $path = $request->file('image')->store('products', 'public');

        // Store path in canonical format (/storage/products/xxx) so removal matches
        // regardless of APP_URL or how the frontend resolves the URL
        $storedUrl = '/storage/' . trim($path, '/');

        $images = $product->images ?? [];
        array_unshift($images, $storedUrl);
        
        $product->update(['images' => $images]);

        return back()->with('success', 'Image uploaded successfully');
    }

    /**
     * Remove product image
     */
    public function removeImage(Request $request, Product $product)
    {
        $request->validate([
            'image_url' => 'required|string',
        ]);

        $normalize = fn($url) => rtrim(parse_url($url, PHP_URL_PATH) ?: $url, '/');
        $targetPath = $normalize($request->image_url);

        $images = collect($product->images ?? [])
            ->filter(fn($img) => $normalize($img) !== $targetPath)
            ->values()
            ->toArray();

        $product->update(['images' => $images]);

        return back()->with('success', 'Image removed');
    }

    /**
     * View all orders
     */
    public function orders(Request $request)
    {
        $query = Order::where('type', 'merch')
            ->with(['business', 'items.product']);

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('business', fn($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        $orders = $query->orderByDesc('created_at')->paginate(20);

        return Inertia::render('Admin/Merch/Orders', [
            'orders' => $orders,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    /**
     * View order details
     */
    public function orderShow(Order $order)
    {
        $order->load(['business', 'items.product', 'items.qrCode']);

        // Get Printful status if order was submitted
        $printfulStatus = null;
        if ($order->printful_order_id) {
            try {
                $printfulStatus = $this->printfulService->getOrderStatus($order->printful_order_id);
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        return Inertia::render('Admin/Merch/OrderShow', [
            'order' => $order,
            'printfulStatus' => $printfulStatus,
        ]);
    }

    /**
     * Update order status
     */
    public function orderUpdate(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
            'tracking_number' => 'nullable|string|max:100',
            'tracking_url' => 'nullable|url|max:500',
        ]);

        $order->update($validated);

        return back()->with('success', 'Order updated');
    }

    /**
     * Refund order
     */
    public function orderRefund(Order $order)
    {
        // Prevent double-refunding
        if ($order->payment_status === 'refunded') {
            return back()->with('error', 'This order has already been refunded.');
        }

        // Disputed orders must be resolved in Stripe Dashboard, not via this refund button
        if ($order->payment_status === 'disputed') {
            return back()->with('error', 'This order is under dispute. Resolve the dispute in the Stripe Dashboard—do not use this refund button.');
        }

        // Process actual Stripe refund if payment intent exists
        if ($order->stripe_payment_intent_id) {
            $refundResult = $this->stripeService->refundPayment($order->stripe_payment_intent_id);

            if (!$refundResult['success']) {
                Log::error('Admin order refund failed', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'error' => $refundResult['error'] ?? 'Unknown error',
                ]);
                return back()->with('error', 'Stripe refund failed: ' . ($refundResult['error'] ?? 'Unknown error. Please try again or process manually in the Stripe dashboard.'));
            }
        } else {
            Log::warning('Order refunded without Stripe payment intent', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
        }

        $order->update([
            'status' => 'cancelled',
            'payment_status' => 'refunded',
            'refunded_at' => now(),
        ]);

        // Cancel Printful order if exists
        if ($order->printful_order_id) {
            try {
                $this->printfulService->cancelOrder($order->printful_order_id);
            } catch (\Exception $e) {
                Log::warning('Failed to cancel Printful order during refund', [
                    'order_id' => $order->id,
                    'printful_order_id' => $order->printful_order_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', 'Order refunded successfully. Payment has been returned to the customer.');
    }

    /**
     * Create a new product manually
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'category' => 'required|string|max:50',
            'base_price' => 'required|numeric|min:0',
            'printful_product_id' => 'nullable|string',
        ]);

        $validated['slug'] = \Str::slug($validated['name']);
        $validated['is_active'] = true;
        $validated['sort_order'] = Product::max('sort_order') + 1;
        $validated['variants'] = [];
        $validated['print_areas'] = [
            ['name' => 'front', 'width' => 1800, 'height' => 1800, 'default' => true],
        ];
        $validated['images'] = [];

        Product::create($validated);

        return back()->with('success', 'Product created');
    }

    /**
     * Delete product
     */
    public function destroy(Product $product)
    {
        // Check if product has orders
        if ($product->orderItems()->exists()) {
            return back()->with('error', 'Cannot delete product with existing orders. Disable it instead.');
        }

        $product->delete();

        return back()->with('success', 'Product deleted');
    }

    /**
     * Reorder products
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->products as $item) {
            Product::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return back()->with('success', 'Products reordered');
    }
}

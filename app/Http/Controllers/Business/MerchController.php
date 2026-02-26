<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\QRCode;
use App\Models\SubscriptionPlan;
use App\Models\MerchTag;
use App\Services\PrintfulService;
use App\Services\QRGeneratorService;
use App\Services\StripeService;
use App\Services\PreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Stripe\Stripe;

class MerchController extends Controller
{
    protected PrintfulService $printfulService;
    protected QRGeneratorService $qrService;
    protected StripeService $stripeService;
    protected PreviewService $previewService;

    public function __construct(
        PrintfulService $printfulService,
        QRGeneratorService $qrService,
        StripeService $stripeService,
        PreviewService $previewService
    ) {
        $this->printfulService = $printfulService;
        $this->qrService = $qrService;
        $this->stripeService = $stripeService;
        $this->previewService = $previewService;
    }

    public function index(Request $request)
    {
        $business = $request->user()->business;

        // Handle case where user doesn't have a business
        if (!$business) {
            return redirect()->route('business.dashboard')
                ->with('error', 'Please complete your business profile first.');
        }

        // Get recent orders
        $recentOrders = $business->orders()
            ->where('type', 'merch')
            ->with('items.product')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Get available products with preview data
        // Only show main Printful products (not templates or extras)
        $subscriptionTier = $business->subscription_tier ?? 'starter';

        // Enforce merch gating using the DB plan feature map (canonical).
        $plan = SubscriptionPlan::where('slug', $subscriptionTier)->first();
        $features = is_array($plan?->features) ? $plan->features : [];

        $merchEnabled = (bool) ($features['merch_store'] ?? false);
        if (!$merchEnabled) {
            return redirect()->route('business.billing')
                ->with('error', 'Merch store access is not included in your current plan. Upgrade to unlock merch.');
        }

        $allowedCategories = $features['merch_categories'] ?? [];
        $allowedCategories = is_array($allowedCategories) ? $allowedCategories : [];

        $productsQuery = Product::where('is_active', true)
            ->whereNotNull('printful_product_id') // Only Printful products
            ->whereNotIn('category', ['other']); // Exclude misc categories
        
        // Filter by allowed categories from plan.
        if (in_array('all', $allowedCategories, true)) {
            $productsQuery->whereIn('category', ['t-shirt', 'hoodie', 'mug', 'sticker', 'poster', 'bag']);
        } else {
            // Safety: If plan has no categories configured, default to t-shirt only.
            if (count($allowedCategories) === 0) {
                $allowedCategories = ['t-shirt'];
            }
            $productsQuery->whereIn('category', $allowedCategories);
        }
        
        $products = $productsQuery
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($product) {
                return [
                    ...$product->toArray(),
                    'preview_template_url' => $product->getPreviewTemplateUrl(),
                    'preview_config' => $product->getPreviewConfigWithDefaults(),
                ];
            });

        // Get QR codes for selection
        $merchReferralEnabled = (bool) ($features['merch_referral_qr'] ?? false);

        $qrCodesQuery = $business->qrCodes()
            ->visibleToBusiness()
            ->where('is_active', true);
        if ($merchReferralEnabled) {
            $qrCodesQuery->where('type', 'merch_referral');
        } else {
            $qrCodesQuery->where('type', '!=', 'merch_referral');
        }
        $qrCodes = $qrCodesQuery->get(['id', 'name', 'code', 'design', 'type']);

        $defaultCountry = $business->country ?: 'CA';
        $merchCurrency = strtoupper($defaultCountry) === 'US' ? 'USD' : 'CAD';

        return Inertia::render('Business/Merch/Index', [
            'products' => $products,
            'qrCodes' => $qrCodes,
            'recentOrders' => $recentOrders,
            'businessLogo' => $business->logo_url,
            'merchCurrency' => $merchCurrency,
            'defaultShipping' => [
                'name' => $business->owner?->name ?: ($business->name ?: ''),
                'address_1' => $business->address_line1 ?: '',
                'address_2' => $business->address_line2 ?: '',
                'city' => $business->city ?: '',
                'state' => $business->state ?: '',
                'zip' => $business->postal_code ?: '',
                'country' => $defaultCountry,
                'phone' => $business->phone ?: '',
            ],
        ]);
    }

    /**
     * Quote shipping/tax before creating an order.
     */
    public function quote(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping' => 'required|array',
            'shipping.country' => 'required|string|max:2',
            // Printful rates are much more accurate with these filled.
            'shipping.state' => 'required|string|max:100',
            'shipping.zip' => 'required|string|max:20',
            'shipping.city' => 'required|string|max:100',
            'shipping.address_1' => 'required|string|max:255',
        ]);

        $business = $request->user()->business;

        $subtotal = 0.0;
        $printfulItems = [];

        foreach ($validated['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            try {
                $variantId = $this->getVariantId($product, $item['variant'] ?? null);
            } catch (\RuntimeException $e) {
                return back()->withErrors(['items' => $e->getMessage()]);
            }

            $subtotal += ((float) $product->base_price) * (int) $item['quantity'];

            // Unbundle into per-unit entries so the shipping quote matches
            // the actual Printful order (each shirt has a unique QR image).
            $qty = (int) $item['quantity'];
            for ($i = 0; $i < $qty; $i++) {
                $printfulItems[] = [
                    'variant_id' => $variantId,
                    'quantity' => 1,
                ];
            }
        }

        $shippingAddress = [
            'address1' => $validated['shipping']['address_1'],
            'city' => $validated['shipping']['city'],
            'state_code' => $validated['shipping']['state'],
            'country_code' => $validated['shipping']['country'],
            'zip' => $validated['shipping']['zip'],
        ];

        $rates = [];
        try {
            $rates = $this->printfulService->getShippingRates($printfulItems, $shippingAddress);
        } catch (\Throwable $e) {
            // ignore; we will fall back below
        }

        // Normalize and pick cheapest (same logic as order() uses today).
        $normalizedRates = collect($rates)->map(function ($r) {
            return [
                'id' => $r['id'] ?? null,
                'name' => $r['name'] ?? ($r['title'] ?? 'Shipping'),
                'rate' => (float) ($r['rate'] ?? 0),
                'currency' => strtoupper((string) ($r['currency'] ?? 'USD')),
            ];
        })->filter(fn ($r) => $r['rate'] > 0)->values();

        $cheapest = $normalizedRates->sortBy('rate')->first();
        $shippingCost = (float) ($cheapest['rate'] ?? 5.99);
        $currency = (string) ($cheapest['currency'] ?? (strtoupper($validated['shipping']['country']) === 'US' ? 'USD' : 'CAD'));

        $tax = $this->calculateTax(
            (float) $subtotal,
            (string) $validated['shipping']['state'],
            (string) $validated['shipping']['country']
        );

        return response()->json([
            'subtotal' => round($subtotal, 2),
            'shipping_cost' => round($shippingCost, 2),
            'tax' => round($tax, 2),
            'total' => round($subtotal + $shippingCost + $tax, 2),
            'currency' => $currency,
            'rates' => $normalizedRates,
            'cheapest_rate' => $cheapest,
        ]);
    }

    /**
     * Generate preview for product with QR code and logo
     */
    public function generatePreview(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'qr_code_id' => 'nullable|exists:qr_codes,id',
            'variant_id' => 'nullable|integer',
            'view' => 'nullable|string',
            'color' => 'nullable|string',
            // Presets (no user drag/scale)
            'logo_position' => 'nullable|string|in:front_center,back_center',
            'logo_size' => 'nullable|string|in:small,medium',
            'qr_position' => 'nullable|string|in:sleeve_left,sleeve_right,back,front',
            'qr_size' => 'nullable|string|in:small,medium,xl',
            'logo_bg_white' => 'nullable|boolean',
            // Live editing parameters
            'logo_x' => 'nullable|integer',
            'logo_y' => 'nullable|integer',
            'logo_width' => 'nullable|integer|min:50|max:800',
            'logo_height' => 'nullable|integer|min:50|max:800',
            'qr_x' => 'nullable|integer',
            'qr_y' => 'nullable|integer',
            'qr_width' => 'nullable|integer|min:50|max:600',
            'qr_height' => 'nullable|integer|min:50|max:600',
        ]);


        $business = $request->user()->business;
        $product = Product::findOrFail($validated['product_id']);

        $qrCode = null;
        if ($validated['qr_code_id']) {
            $qrCode = $business->qrCodes()
                ->visibleToBusiness()
                ->where('id', $validated['qr_code_id'])
                ->where('is_active', true)
                ->firstOrFail();
        }

        try {
            // ALWAYS USE LOCAL PREVIEWS FOR LIVE EDITING - NO API CALLS!
            // This provides instant drag & drop positioning without Printful delays
            $logoUrl = $business->logo_url;

            $view = $validated['view'] ?? 'front';
            $color = $validated['color'] ?? null;
            $variantId = $validated['variant_id'] ?? $this->getDefaultVariantId($product);

            // Default: keep existing behavior (draw based on preview config)
            $options = [
                'view' => $view,
                'color' => $color,
                'variant_id' => $variantId,
                // Live editing overrides (legacy)
                'logo_x' => $validated['logo_x'] ?? null,
                'logo_y' => $validated['logo_y'] ?? null,
                'logo_width' => $validated['logo_width'] ?? null,
                'logo_height' => $validated['logo_height'] ?? null,
                'qr_x' => $validated['qr_x'] ?? null,
                'qr_y' => $validated['qr_y'] ?? null,
                'qr_width' => $validated['qr_width'] ?? null,
                'qr_height' => $validated['qr_height'] ?? null,
            ];

            // New: preset-driven placement/sizing for apparel (reliable)
            if (in_array($product->category, ['t-shirt', 'hoodie'], true)) {
                $previewConfig = $product->getPreviewConfigWithDefaults();
                $viewConfig = $previewConfig['views'][$view] ?? $previewConfig;
                $canvasWidth = (int) ($viewConfig['canvas']['width'] ?? ($previewConfig['canvas']['width'] ?? 800));
                $canvasHeight = (int) ($viewConfig['canvas']['height'] ?? ($previewConfig['canvas']['height'] ?? 1000));

                $logoSize = $validated['logo_size'] ?? 'medium';
                $qrSize = $validated['qr_size'] ?? 'medium';
                
                // Logo sizes
                $logoSizeMap = ['small' => 175, 'medium' => 350];
                
                // QR code sizes: Made even smaller to fit on sleeve
                $qrSizeMap = ['small' => 100, 'medium' => 150, 'xl' => 250];
                
                // For back view, default to XL if not specified (or use current size if it's already XL/large)
                if ($view === 'back' && !isset($validated['qr_size'])) {
                    $qrSize = 'xl'; // Default to XL for back view
                }
                
                $logoDim = $logoSizeMap[$logoSize] ?? 450;
                $qrDim = $qrSizeMap[$qrSize] ?? 200;

                $logoPosition = $validated['logo_position'] ?? 'front_center';
                $qrPosition = $validated['qr_position'] ?? 'sleeve_left';

                // Position logo on upper chest area (front center)
                $logoY = (int) round($canvasHeight * 0.155);
                
                // QR Y position: adjust based on view
                // - Back view: higher up
                // - Sleeves: lower so it doesn't cover shoulder seams
                if ($view === 'back') {
                    $qrY = (int) round($canvasHeight * 0.25);
                } elseif ($view === 'sleeve_left' || $view === 'sleeve_right') {
                    // Move up a bit so it's not too close to the elbow
                    $qrY = (int) round($canvasHeight * 0.40);
                } else {
                    $qrY = (int) round($canvasHeight * 0.35);
                }

                $renderLogo = false;
                $logoX = null;

                if ($view === 'front' && $logoPosition === 'front_center') {
                    $renderLogo = true;
                    $logoX = (int) round(($canvasWidth - $logoDim) / 2);
                }
                if ($view === 'back' && $logoPosition === 'back_center') {
                    $renderLogo = true;
                    $logoX = (int) round(($canvasWidth - $logoDim) / 2);
                }

                $renderQr = false;
                if (
                    ($view === 'sleeve_left' && $qrPosition === 'sleeve_left') ||
                    ($view === 'sleeve_right' && $qrPosition === 'sleeve_right') ||
                    ($view === 'back' && $qrPosition === 'back')
                ) {
                    $renderQr = true;
                }

                // Calculate QR X position.
                $qrXCenter = (int) round(($canvasWidth - $qrDim) / 2);
                $qrX = $qrXCenter;

                if ($view === 'sleeve_left' || $view === 'sleeve_right') {
                    // Use Printful template print_area coordinates (most accurate across products).
                    // This fixes "way off" placement differences between products and between
                    // sleeve_left vs sleeve_right.
                    try {
                        $tpl = $this->printfulService->getGhostTemplateEntry(
                            (int) $product->printful_product_id,
                            (int) $variantId,
                            $view
                        );

                        if (
                            is_array($tpl) &&
                            isset($tpl['print_area_left'], $tpl['print_area_top'], $tpl['print_area_width'], $tpl['print_area_height']) &&
                            isset($tpl['template_width'], $tpl['template_height']) &&
                            (int) $tpl['template_width'] > 0 &&
                            (int) $tpl['template_height'] > 0
                        ) {
                            $xScale = $canvasWidth / (int) $tpl['template_width'];
                            $yScale = $canvasHeight / (int) $tpl['template_height'];

                            $areaLeft = (float) $tpl['print_area_left'] * $xScale;
                            $areaTop = (float) $tpl['print_area_top'] * $yScale;
                            $areaWidth = (float) $tpl['print_area_width'] * $xScale;
                            $areaHeight = (float) $tpl['print_area_height'] * $yScale;

                            // Center QR within the sleeve print area
                            $qrX = (int) round($areaLeft + (($areaWidth - $qrDim) / 2));

                            // Keep sleeve QR a bit higher than dead-center (matches your feedback)
                            $qrY = (int) round($areaTop + ($areaHeight * 0.40) - ($qrDim / 2));
                        }
                    } catch (\Throwable $e) {
                        // If Printful template lookup fails, fall back to our generic centering.
                    }
                }

                // Clamp to canvas bounds
                $qrX = max(0, min($qrX, $canvasWidth - $qrDim));
                $qrY = max(0, min($qrY, $canvasHeight - $qrDim));
                
                $options = array_merge($options, [
                    'render_logo' => $renderLogo,
                    'logo_width' => $logoDim,
                    'logo_height' => $logoDim,
                    'logo_x' => $logoX,
                    'logo_y' => $logoY,
                    'render_qr' => $renderQr,
                    'qr_width' => $qrDim,
                    'qr_height' => $qrDim,
                    'qr_x' => $qrX,
                    'qr_y' => $qrY,
                ]);
            }

            // Pass white background option for logo
            if (!empty($validated['logo_bg_white'])) {
                $options['logo_bg_white'] = true;
            }

            $previewData = $this->previewService->getPreviewData($product, $qrCode, $business, $options);
            $previewData['provider'] = 'local';
            
            \Log::info('Preview generated', [
                'product_id' => $product->id,
                'preview_url' => $previewData['preview_url'],
                'has_qr' => $previewData['has_qr'],
                'has_logo' => $previewData['has_logo'],
            ]);
            
            return response()->json($previewData);
        } catch (\Exception $e) {
            \Log::error('Preview generation failed in controller', [
                'product_id' => $product->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to generate preview: ' . $e->getMessage(),
                'preview_url' => $product->images[0] ?? null, // Fallback
            ], 500);
        }
    }

    protected function getDefaultVariantId(Product $product): ?int
    {
        $variants = $product->variants ?? [];
        if (!is_array($variants) || empty($variants)) {
            return null;
        }

        $first = $variants[0] ?? null;
        if (!is_array($first)) {
            return null;
        }

        $ids = $first['variant_ids'] ?? null;
        if (is_array($ids) && !empty($ids)) {
            return (int) $ids[0];
        }

        $colors = $first['colors'] ?? null;
        if (is_array($colors) && !empty($colors) && isset($colors[0]['variant_id'])) {
            return (int) $colors[0]['variant_id'];
        }

        return null;
    }

    protected function toPublicAbsoluteUrl(Request $request, string $url): string
    {
        $url = trim($url);
        $host = $request->getSchemeAndHttpHost();

        if (preg_match('#^https?://#i', $url)) {
            return preg_replace('#^https?://localhost#i', $host, $url);
        }

        if (str_starts_with($url, '/storage/')) {
            return $host . $url;
        }

        return $url;
    }

    protected function createPublicQrPngUrl(Request $request, QRCode $qrCode): string
    {
        $dataUri = $this->qrService->generate(
            $qrCode->getScanUrl(),
            $qrCode->getDesignWithDefaults()
        );

        $binary = base64_decode(preg_replace('#^data:image/[^;]+;base64,#', '', $dataUri));
        $path = 'previews/qr_printful_' . $qrCode->id . '_' . time() . '.png';
        Storage::disk('public')->put($path, $binary);

        return $request->getSchemeAndHttpHost() . '/storage/' . $path;
    }

    /**
     * Sync popular Printful products to local catalog
     */
    protected function syncPrintfulProducts(): void
    {
        // Define the product IDs we want to offer (popular QR-friendly items)
        $printfulProductIds = [
            71,   // Unisex Staple T-Shirt (Bella + Canvas 3001)
            380,  // Unisex Hoodie
            19,   // White Glossy Mug
            1,    // Poster
            534,  // Die-Cut Stickers
            505,  // Canvas
            88,   // All-Over Print T-Shirt
            181,  // Tote Bag
        ];

        foreach ($printfulProductIds as $productId) {
            try {
                $productData = $this->printfulService->getProductVariants($productId);
                
                if (empty($productData)) continue;

                $product = $productData['product'] ?? null;
                $variants = $productData['variants'] ?? [];
                
                if (!$product) continue;

                // Determine category
                $category = $this->mapCategory($product['type_name'] ?? 'other');

                // Get the cheapest variant as base price
                $basePrice = collect($variants)->min('price') ?? 15.00;

                // Format variants
                $formattedVariants = collect($variants)
                    ->groupBy('size')
                    ->map(function ($sizeGroup, $size) use ($basePrice) {
                        $colors = $sizeGroup->pluck('color_code')->unique()->values();
                        $minPrice = $sizeGroup->min('price');
                        return [
                            'name' => $size ?: 'Standard',
                            'colors' => $colors,
                            'price_modifier' => round($minPrice - $basePrice, 2),
                            'variant_ids' => $sizeGroup->pluck('id')->values(),
                        ];
                    })
                    ->values()
                    ->toArray();

                // Create or update product
                Product::updateOrCreate(
                    ['printful_product_id' => (string) $productId],
                    [
                        'name' => $product['title'] ?? $product['name'] ?? 'Product',
                        'slug' => Str::slug($product['title'] ?? $product['name'] ?? 'product-' . $productId),
                        'description' => $product['description'] ?? 'High-quality print-on-demand product',
                        'category' => $category,
                        'base_price' => $basePrice + 5.00, // Add margin
                        'variants' => $formattedVariants,
                        'print_areas' => $this->getPrintAreas($product),
                        'images' => [$product['image'] ?? null],
                        'is_active' => true,
                    ]
                );
            } catch (\Exception $e) {
                Log::warning("Failed to sync Printful product {$productId}: " . $e->getMessage());
            }
        }
    }

    /**
     * Map Printful product type to our categories
     */
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
        ];

        foreach ($mapping as $type => $category) {
            if (stripos($typeName, $type) !== false) {
                return $category;
            }
        }

        return 'other';
    }

    /**
     * Get print areas for a product
     */
    protected function getPrintAreas(array $product): array
    {
        return [
            [
                'name' => 'front',
                'width' => 1800,
                'height' => 1800,
                'default' => true,
            ],
        ];
    }

    public function products()
    {
        $products = Product::where('is_active', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');

        return Inertia::render('Business/Merch/Products', [
            'productsByCategory' => $products,
        ]);
    }

    public function order(Request $request)
    {
        $business = $request->user()->business;
        $tier = $business->subscription_tier ?? 'starter';
        $plan = SubscriptionPlan::where('slug', $tier)->first();
        $features = is_array($plan?->features) ? $plan->features : [];
        $merchReferralEnabled = (bool) ($features['merch_referral_qr'] ?? false);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qr_code_id' => [
                'required',
                Rule::exists('qr_codes', 'id')->where(function ($query) use ($business, $merchReferralEnabled) {
                    $query->where('business_id', $business->id);
                    $query->where(function ($q) {
                        $q->whereNull('intended_use')
                            ->orWhere('intended_use', '!=', QRCode::INTENDED_USE_LEADERBOARD_PRIZE);
                    });
                    if ($merchReferralEnabled) {
                        $query->where('type', 'merch_referral');
                    } else {
                        $query->where('type', '!=', 'merch_referral');
                    }
                    return $query;
                }),
            ],
            'items.*.variant' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            // Design selections (optional) - used to align Printful output with preview UI.
            'items.*.logo_position' => 'nullable|string|in:front_center,back_center',
            'items.*.logo_size' => 'nullable|string|in:small,medium',
            'items.*.qr_position' => 'nullable|string|in:sleeve_left,sleeve_right,back,front',
            'items.*.qr_size' => 'nullable|string|in:small,medium,xl',
            'items.*.logo_bg_white' => 'nullable|boolean',
            'shipping' => 'required|array',
            'shipping.name' => 'required|string|max:255',
            'shipping.address_1' => 'required|string|max:255',
            'shipping.address_2' => 'nullable|string|max:255',
            'shipping.city' => 'required|string|max:100',
            'shipping.state' => 'required|string|max:100',
            'shipping.zip' => 'required|string|max:20',
            'shipping.country' => 'required|string|max:2',
            'shipping.phone' => 'nullable|string|max:20',
        ]);

        // Get business logo URL (if they have one)
        $logoUrl = $business->logo_url;

        // Calculate totals and prepare items
        $subtotal = 0;
        $orderUnits = [];
        $printfulItems = [];

        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);
            $qrCode = QRCode::where('id', $item['qr_code_id'])
                ->where('business_id', $business->id)
                ->visibleToBusiness()
                ->when($merchReferralEnabled, fn ($q) => $q->where('type', 'merch_referral'))
                ->when(!$merchReferralEnabled, fn ($q) => $q->where('type', '!=', 'merch_referral'))
                ->firstOrFail();

            // Build per-item print config based on user selections (fallback to product defaults).
            $printConfig = $product->getPrintConfigWithDefaults();

            $logoPosition = $item['logo_position'] ?? null;
            $logoSize = $item['logo_size'] ?? null;
            $qrPosition = $item['qr_position'] ?? null;
            $qrSize = $item['qr_size'] ?? null;
            $logoBgWhite = !empty($item['logo_bg_white']);

            // Map UI preset sizes to print pixel sizes (not the same as preview canvas pixels).
            $logoSizeMap = ['small' => 350, 'medium' => 450];
            $qrSizeMap = ['small' => 150, 'medium' => 200, 'xl' => 300];

            if (!isset($printConfig['logo']) || !is_array($printConfig['logo'])) {
                $printConfig['logo'] = [];
            }
            if (!isset($printConfig['qr_code']) || !is_array($printConfig['qr_code'])) {
                $printConfig['qr_code'] = [];
            }

            if ($logoPosition) {
                if ($logoPosition === 'front_center') {
                    $printConfig['logo']['placement'] = 'front';
                    $printConfig['logo']['position'] = 'center';
                } elseif ($logoPosition === 'back_center') {
                    $printConfig['logo']['placement'] = 'back';
                    $printConfig['logo']['position'] = 'center';
                }
            }
            if ($logoSize && isset($logoSizeMap[$logoSize])) {
                $printConfig['logo']['size'] = $logoSize;
                $printConfig['logo']['width'] = $logoSizeMap[$logoSize];
                $printConfig['logo']['height'] = $logoSizeMap[$logoSize];
            }
            if ($logoBgWhite) {
                $printConfig['logo']['bg_white'] = true;
            }

            if ($qrPosition) {
                $printConfig['qr_code']['placement'] = $qrPosition;
                $printConfig['qr_code']['position'] = 'center';
            }
            if ($qrSize && isset($qrSizeMap[$qrSize])) {
                $printConfig['qr_code']['size'] = $qrSize;
                $printConfig['qr_code']['width'] = $qrSizeMap[$qrSize];
                $printConfig['qr_code']['height'] = $qrSizeMap[$qrSize];
            }

            $qty = max(1, (int) $item['quantity']);
            $subtotal += $product->base_price * $qty;

            try {
                $variantId = $this->getVariantId($product, $item['variant']);
            } catch (\RuntimeException $e) {
                return back()->withErrors(['items' => $e->getMessage()]);
            }

            // Unbundle into per-unit entries so the shipping quote matches
            // the actual Printful order (each shirt has a unique QR image).
            for ($j = 0; $j < $qty; $j++) {
                $printfulItems[] = [
                    'variant_id' => $variantId,
                    'quantity' => 1,
                ];
            }

            // Expand into per-unit order items so each shirt can have a unique merch tag.
            for ($i = 0; $i < $qty; $i++) {
                $orderUnits[] = [
                    'product' => $product,
                    'qr_code' => $qrCode,
                    'variant' => $item['variant'],
                    'quantity' => 1,
                    'unit_price' => $product->base_price,
                    'total_price' => $product->base_price,
                    'logo_url' => $logoUrl,
                    'variant_id' => $variantId,
                    'logo_position' => $logoPosition,
                    'logo_size' => $logoSize,
                    'qr_position' => $qrPosition,
                    'qr_size' => $qrSize,
                    'logo_bg_white' => $logoBgWhite,
                    'print_config' => $printConfig,
                ];
            }

            // Prepare for Printful shipping calculation
            // (Shipping is handled above using the full quantity.)
        }

        // Calculate shipping using Printful API
        $shippingAddress = [
            'address1' => $validated['shipping']['address_1'],
            'city' => $validated['shipping']['city'],
            'state_code' => $validated['shipping']['state'],
            'country_code' => $validated['shipping']['country'],
            'zip' => $validated['shipping']['zip'],
        ];

        $shippingCost = $this->calculateShipping($printfulItems, $shippingAddress);
        
        // Calculate tax (using Printful's tax calculation or estimate)
        $tax = $this->calculateTax($subtotal, $validated['shipping']['state'], $validated['shipping']['country']);
        
        $total = $subtotal + $shippingCost + $tax;

        // Create order
        $order = Order::create([
            'business_id' => $business->id,
            'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            'type' => 'merch',
            'status' => 'pending',
            'shipping_name' => $validated['shipping']['name'],
            'shipping_address_1' => $validated['shipping']['address_1'],
            'shipping_address_2' => $validated['shipping']['address_2'] ?? null,
            'shipping_city' => $validated['shipping']['city'],
            'shipping_state' => $validated['shipping']['state'],
            'shipping_zip' => $validated['shipping']['zip'],
            'shipping_country' => $validated['shipping']['country'],
            'shipping_phone' => $validated['shipping']['phone'] ?? null,
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'tax' => $tax,
            'total' => $total,
        ]);

        // Create order items
        foreach ($orderUnits as $item) {
            // Create a unique merch tag for this physical item.
            $merchTag = MerchTag::create([
                'code' => MerchTag::generateUniqueCode(),
                'business_id' => $business->id,
                'qr_code_id' => $item['qr_code']->id,
                'order_id' => $order->id,
                'is_active' => true,
            ]);

            // Generate QR code image for the merch tag (high-res for printing).
            $qrImagePath = $this->qrService->generateFile(
                url('/m/' . $merchTag->code),
                $item['qr_code']->getDesignWithDefaults(),
                'png',
                1200 // Higher resolution for print
            );
            $merchTag->update(['qr_image_path' => $qrImagePath]);

            // Get full URLs for Printful
            $qrFullUrl = url('storage/' . $qrImagePath);

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product']->id,
                'qr_code_id' => $item['qr_code']->id,
                'product_name' => $item['product']->name,
                'variant' => $item['variant'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['total_price'],
                'preview_url' => asset('storage/' . $qrImagePath),
                'design_data' => [
                    'printful_variant_id' => $item['variant_id'],
                    'qr_url' => $qrFullUrl,
                    'logo_url' => $item['logo_url'],
                    // Persist selected placements so Printful order matches the preview UI.
                    'logo_position' => $item['logo_position'],
                    'logo_size' => $item['logo_size'],
                    'qr_position' => $item['qr_position'],
                    'qr_size' => $item['qr_size'],
                    'logo_bg_white' => $item['logo_bg_white'],
                    'merch_tag_id' => $merchTag->id,
                    'merch_tag_code' => $merchTag->code,
                    'print_config' => $item['print_config'],
                ],
            ]);

            $merchTag->update([
                'order_item_id' => $orderItem->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'order' => $order->load('items'),
            'checkout_url' => route('business.merch.checkout', $order),
        ]);
    }

    /**
     * Get Printful variant ID from product and variant name
     */
    protected function getVariantId(Product $product, ?string $variantName): int
    {
        if (!$variantName || empty($product->variants)) {
            $firstVariant = collect($product->variants)->first();
            if ($firstVariant && !empty($firstVariant['variant_ids'])) {
                return $firstVariant['variant_ids'][0];
            }
            throw new \RuntimeException("No variant data available for product [{$product->id}]. Please update the product's variant configuration.");
        }

        foreach ($product->variants as $variant) {
            if ($variant['name'] === $variantName && !empty($variant['variant_ids'])) {
                return $variant['variant_ids'][0];
            }
        }

        throw new \RuntimeException("Variant \"{$variantName}\" not found for product [{$product->id}]. Available: " . collect($product->variants)->pluck('name')->implode(', '));
    }

    /**
     * Calculate shipping cost using Printful API
     */
    protected function calculateShipping(array $items, array $address): float
    {
        try {
            $rates = $this->printfulService->getShippingRates($items, $address);
            
            if (!empty($rates)) {
                // Get the cheapest shipping option
                $cheapest = collect($rates)->sortBy('rate')->first();
                return (float) ($cheapest['rate'] ?? 5.99);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get Printful shipping rates: ' . $e->getMessage());
        }

        // Fallback to estimated shipping
        return 5.99;
    }

    /**
     * Calculate tax based on location
     */
    protected function calculateTax(float $subtotal, string $state, string $country): float
    {
        // US tax rates by state (simplified)
        $taxRates = [
            'CA' => 0.0725, // California
            'NY' => 0.08,   // New York
            'TX' => 0.0625, // Texas
            'FL' => 0.06,   // Florida
            'WA' => 0.065,  // Washington
            // Add more as needed
        ];

        if ($country === 'US' && isset($taxRates[$state])) {
            return round($subtotal * $taxRates[$state], 2);
        }

        // Default 8% for US, 0 for international (VAT handled separately)
        if ($country === 'US') {
            return round($subtotal * 0.08, 2);
        }

        return 0;
    }

    public function orders(Request $request)
    {
        $business = $request->user()->business;

        if (!$business) {
            return redirect()->route('business.dashboard')
                ->with('error', 'Please complete your business profile first.');
        }

        $status = $request->get('status');
        $query = trim((string) $request->get('q', ''));
        $from = $request->get('from');
        $to = $request->get('to');
        $perPage = (int) $request->get('per_page', 20);
        $perPage = in_array($perPage, [20, 50, 100], true) ? $perPage : 20;

        $baseQuery = $business->orders()
            ->where('type', 'merch');

        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $ordersQuery = (clone $baseQuery)
            ->with(['items.product', 'items.qrCode:id,code,name']);

        if (!empty($status) && $status !== 'all') {
            $ordersQuery->where('status', $status);
        }

        if (!empty($query)) {
            $ordersQuery->where(function ($q) use ($query) {
                $q->where('order_number', 'like', '%' . $query . '%')
                    ->orWhere('tracking_number', 'like', '%' . $query . '%')
                    ->orWhere('shipping_name', 'like', '%' . $query . '%')
                    ->orWhere('printful_order_id', 'like', '%' . $query . '%')
                    ->orWhereHas('items', function ($items) use ($query) {
                        $items->where('product_name', 'like', '%' . $query . '%')
                            ->orWhereHas('qrCode', function ($qr) use ($query) {
                                $qr->where('code', 'like', '%' . $query . '%')
                                    ->orWhere('name', 'like', '%' . $query . '%');
                            });
                    });
            });
        }

        if (!empty($from)) {
            $ordersQuery->whereDate('created_at', '>=', $from);
        }
        if (!empty($to)) {
            $ordersQuery->whereDate('created_at', '<=', $to);
        }

        $orders = $ordersQuery
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Business/Merch/Orders', [
            'orders' => $orders,
            'statusCounts' => $statusCounts,
            'filters' => [
                'status' => $status,
                'q' => $query,
                'from' => $from,
                'to' => $to,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function checkout(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        return Inertia::render('Business/Merch/Checkout', [
            'order' => $order->load('items.product', 'items.qrCode'),
        ]);
    }

    public function processPayment(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        // Check if Stripe is configured
        if (!$this->stripeService->isConfigured()) {
            return back()->with('error', 'Payment system is not configured. Please contact support.');
        }

        try {
            $business = $request->user()->business;
            $currency = strtoupper((string) ($order->shipping_country ?? 'CA')) === 'US' ? 'usd' : 'cad';

            // Create Stripe checkout session for one-time payment
            $session = \Stripe\Checkout\Session::create([
                'customer' => $this->stripeService->getOrCreateCustomer($business)->id,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => 'Merch Order #' . $order->order_number,
                            'description' => 'Custom QR code merchandise order',
                        ],
                        'unit_amount' => (int)($order->total * 100), // Convert to cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('business.merch.orders') . '?order=success&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('business.merch.checkout', $order),
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'business_id' => $business->id,
                ],
            ]);

            // If the frontend is calling this via fetch(), return JSON so it can redirect cleanly.
            if ($request->expectsJson()) {
                return response()->json([
                    'checkout_url' => $session->url,
                ]);
            }

            return redirect($session->url);

        } catch (\Exception $e) {
            \Log::error('Merch payment error: ' . $e->getMessage());
            return back()->with('error', 'Failed to process payment: ' . $e->getMessage());
        }
    }
}


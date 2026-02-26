<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class PrintfulService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.printful.com';
    protected ImageManager $imageManager;

    public function __construct()
    {
        $this->apiKey = config('services.printful.api_key');
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Get available products from Printful catalog
     */
    public function getProducts(): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/products");

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        return [];
    }

    /**
     * Get products from your Printful store
     */
    public function getStoreProducts(): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/store/products");

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        return [];
    }

    /**
     * Get store product details (including variants)
     */
    public function getStoreProduct(int $storeProductId): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/store/products/{$storeProductId}");

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        return [];
    }

    /**
     * Get product variants
     */
    public function getProductVariants(int $productId): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/products/{$productId}");

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        return [];
    }

    /**
     * Calculate shipping rates
     */
    public function getShippingRates(array $items, array $address): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/shipping/rates", [
                'recipient' => $address,
                'items' => $items,
            ]);

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        return [];
    }

    /**
     * Create an order in Printful with logo + QR code on different placements
     */
    public function createOrder(Order $order): array
    {
        $items = [];

        foreach ($order->items as $item) {
            $files = $this->buildPrintFiles($item);
            
            $items[] = [
                'variant_id' => $item->design_data['printful_variant_id'] ?? $item->product->printful_product_id,
                'quantity' => $item->quantity,
                'files' => $files,
            ];
        }

        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/orders", [
                // Used by Printful webhooks so we can reconcile their events back to our order.
                // (Our PrintfulWebhookController looks up Order by order_number == external_id.)
                'external_id' => $order->order_number,
                'recipient' => [
                    'name' => $order->shipping_name,
                    'address1' => $order->shipping_address_1,
                    'address2' => $order->shipping_address_2,
                    'city' => $order->shipping_city,
                    'state_code' => $order->shipping_state,
                    'zip' => $order->shipping_zip,
                    'country_code' => $order->shipping_country,
                    'phone' => $order->shipping_phone,
                ],
                'items' => $items,
                'retail_costs' => [
                    'subtotal' => (string) $order->subtotal,
                    'shipping' => (string) $order->shipping_cost,
                    'tax' => (string) $order->tax,
                    'total' => (string) $order->total,
                ],
            ]);

        if ($response->successful()) {
            $result = $response->json()['result'];
            
            $order->update([
                'printful_order_id' => $result['id'],
                'printful_status' => $result['status'],
            ]);

            return $result;
        }

        throw new \Exception('Failed to create Printful order: ' . $response->body());
    }

    /**
     * Build print files array for logo + QR code placements
     */
    protected function buildPrintFiles($orderItem): array
    {
        $files = [];
        $product = $orderItem->product;
        $designData = is_array($orderItem->design_data) ? $orderItem->design_data : [];
        // Prefer per-order-item print config (selected in UI) over product defaults.
        $printConfig = (isset($designData['print_config']) && is_array($designData['print_config']))
            ? $designData['print_config']
            : $product->getPrintConfigWithDefaults();
        $variantId = (int) ($designData['printful_variant_id'] ?? 0);
        $catalogProductId = null;
        try {
            // For merch in this app this is typically already a catalog product id.
            $catalogProductId = $this->resolveCatalogProductIdForMockups($product);
        } catch (\Throwable $e) {
            $catalogProductId = (int) ($product->printful_product_id ?? 0);
        }

        // Add logo file if business has one
        if (!empty($designData['logo_url'])) {
            $logoConfig = $printConfig['logo'] ?? [];
            $placement = $this->mapPlacementToType($logoConfig['placement'] ?? 'front', $product->category);

            $logoWidth = (int) ($logoConfig['width'] ?? 600);
            $logoHeight = (int) ($logoConfig['height'] ?? 600);
            $logoTop = (int) ($logoConfig['top'] ?? 400);
            $logoLeft = (int) ($logoConfig['left'] ?? 600);

            $mode = (string) ($logoConfig['position'] ?? '');
            if ($mode === 'center' || $mode === 'left_chest') {
                $area = $this->getPrintAreaDimensions($catalogProductId, $variantId, $placement);
                $areaW = (int) ($area['width'] ?? 0);
                if ($areaW <= 0) {
                    $areaW = $this->getAreaWidth($product->category, $placement);
                }

                if ($areaW > 0) {
                    if ($mode === 'center') {
                        // True center: lock X to the center of the print area.
                        $logoLeft = (int) round(($areaW - $logoWidth) / 2);
                    } else {
                        // Wearer's LEFT chest = viewer's RIGHT.
                        // Place the design center at ~60% of the print area width.
                        $logoLeft = (int) round(($areaW * 0.60) - ($logoWidth / 2));
                    }

                    // Clamp within bounds.
                    $logoLeft = max(0, min($logoLeft, max(0, $areaW - $logoWidth)));
                }
            }

            $pos = $this->buildPosition(
                $catalogProductId,
                $variantId,
                $placement,
                $logoWidth,
                $logoHeight,
                $logoTop,
                $logoLeft
            );
            $files[] = [
                'type' => $placement,
                'url' => $designData['logo_url'],
                'position' => $pos,
            ];
        }

        // Add primary QR code file
        if (!empty($designData['qr_url'])) {
            $qrConfig = $printConfig['qr_code'] ?? [];
            $placement = $this->mapPlacementToType($qrConfig['placement'] ?? 'left', $product->category);

            $qrWidth = (int) ($qrConfig['width'] ?? 250);
            $qrHeight = (int) ($qrConfig['height'] ?? 250);

            $area = $this->getPrintAreaDimensions($catalogProductId, $variantId, $placement);
            $areaW = (int) ($area['width'] ?? 0);
            $areaH = (int) ($area['height'] ?? 0);

            $qrUrl = (string) $designData['qr_url'];

            // Printful validates file aspect ratio against the print area for some placements
            // (notably sleeves, which are commonly 600x525). If we upload a square QR PNG directly,
            // Printful can reject it. To keep the QR the same visual size, we render the QR onto
            // a full print-area canvas (correct aspect ratio), centered at the intended size.
            if ($areaW > 0 && $areaH > 0) {
                $qrUrl = $this->renderQrOntoPrintAreaCanvas(
                    $qrUrl,
                    $areaW,
                    $areaH,
                    $qrWidth,
                    $qrHeight,
                    $placement,
                    (int) ($orderItem->id ?? 0)
                );
            }

            $pos = ($areaW > 0 && $areaH > 0)
                ? [
                    'area_width' => $areaW,
                    'area_height' => $areaH,
                    'width' => $areaW,
                    'height' => $areaH,
                    'top' => 0,
                    'left' => 0,
                ]
                : $this->buildPosition($catalogProductId, $variantId, $placement, $qrWidth, $qrHeight, null, null, true);
            $files[] = [
                'type' => $placement,
                'url' => $qrUrl,
                'position' => $pos,
            ];
        }

        // Add secondary QR code file (for double QR designs)
        if (!empty($designData['qr_url_2'])) {
            $qrConfig2 = $printConfig['qr_code_2'] ?? [];
            $placement = $this->mapPlacementToType($qrConfig2['placement'] ?? 'right', $product->category);

            $qrWidth = (int) ($qrConfig2['width'] ?? 250);
            $qrHeight = (int) ($qrConfig2['height'] ?? 250);

            $area = $this->getPrintAreaDimensions($catalogProductId, $variantId, $placement);
            $areaW = (int) ($area['width'] ?? 0);
            $areaH = (int) ($area['height'] ?? 0);

            $qrUrl = (string) $designData['qr_url_2'];
            if ($areaW > 0 && $areaH > 0) {
                $qrUrl = $this->renderQrOntoPrintAreaCanvas(
                    $qrUrl,
                    $areaW,
                    $areaH,
                    $qrWidth,
                    $qrHeight,
                    $placement,
                    (int) ($orderItem->id ?? 0)
                );
            }

            $pos = ($areaW > 0 && $areaH > 0)
                ? [
                    'area_width' => $areaW,
                    'area_height' => $areaH,
                    'width' => $areaW,
                    'height' => $areaH,
                    'top' => 0,
                    'left' => 0,
                ]
                : $this->buildPosition($catalogProductId, $variantId, $placement, $qrWidth, $qrHeight, null, null, true);
            $files[] = [
                'type' => $placement,
                'url' => $qrUrl,
                'position' => $pos,
            ];
        }

        // Fallback: if no files, use the preview_url as default
        if (empty($files) && $orderItem->preview_url) {
            $files[] = [
                'type' => 'default',
                'url' => $orderItem->preview_url,
            ];
        }

        return $files;
    }

    /**
     * Build a Printful position block with correct print area dimensions.
     *
     * Printful validates aspect ratios between the real print area and the
     * "specified print area" we send. Some placements (e.g. sleeves) are not square,
     * so we must use Printful's own printfile dimensions for area_width/area_height.
     *
     * @return array<string,mixed>
     */
    protected function buildPosition(
        ?int $catalogProductId,
        int $variantId,
        string $placement,
        int $width,
        int $height,
        ?int $top,
        ?int $left,
        bool $center = false
    ): array {
        $width = max(1, $width);
        $height = max(1, $height);

        $area = $this->getPrintAreaDimensions($catalogProductId, $variantId, $placement);
        $areaW = (int) ($area['width'] ?? 0);
        $areaH = (int) ($area['height'] ?? 0);

        if ($center && $areaW > 0 && $areaH > 0) {
            $left = (int) round(($areaW - $width) / 2);
            $top = (int) round(($areaH - $height) / 2);
        }

        // If we don't have area dimensions, fall back to provided coordinates.
        $pos = [
            'width' => $width,
            'height' => $height,
            'top' => $top ?? 0,
            'left' => $left ?? 0,
        ];

        if ($areaW > 0 && $areaH > 0) {
            $pos['area_width'] = $areaW;
            $pos['area_height'] = $areaH;
        }

        return $pos;
    }

    /**
     * Look up Printful print area dimensions for a specific catalog product + variant + placement.
     *
     * @return array{width?:int,height?:int}
     */
    protected function getPrintAreaDimensions(?int $catalogProductId, int $variantId, string $placement): array
    {
        $catalogProductId = (int) ($catalogProductId ?? 0);
        if ($catalogProductId <= 0 || $variantId <= 0 || $placement === '') {
            return [];
        }

        try {
            $info = $this->getPrintFileInfo($catalogProductId);
            $variantPrintfiles = $info['variant_printfiles'] ?? [];
            $printfiles = $info['printfiles'] ?? [];

            $printfileId = null;
            foreach ($variantPrintfiles as $vp) {
                if (!is_array($vp)) {
                    continue;
                }
                if ((int) ($vp['variant_id'] ?? 0) !== $variantId) {
                    continue;
                }
                $placements = $vp['placements'] ?? [];
                if (is_array($placements) && isset($placements[$placement])) {
                    $printfileId = (int) $placements[$placement];
                    break;
                }
            }

            if (!$printfileId) {
                return [];
            }

            foreach ($printfiles as $pf) {
                if (!is_array($pf)) {
                    continue;
                }
                if ((int) ($pf['printfile_id'] ?? 0) !== $printfileId) {
                    continue;
                }
                return [
                    'width' => (int) ($pf['width'] ?? 0),
                    'height' => (int) ($pf['height'] ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }

        return [];
    }

    /**
     * Render a square QR image onto a print-area-sized canvas (correct aspect ratio).
     * This avoids Printful rejecting square files for non-square print areas (e.g. sleeves 600x525).
     *
     * Returns an absolute URL under /storage/... suitable for Printful.
     */
    protected function renderQrOntoPrintAreaCanvas(
        string $qrUrl,
        int $areaWidth,
        int $areaHeight,
        int $qrWidth,
        int $qrHeight,
        string $placement,
        int $orderItemId = 0
    ): string {
        try {
            $areaWidth = max(1, $areaWidth);
            $areaHeight = max(1, $areaHeight);
            $qrWidth = max(1, $qrWidth);
            $qrHeight = max(1, $qrHeight);

            // Try to resolve the local disk path from a /storage/... URL.
            $rel = null;
            if (preg_match('#/storage/(.+)$#i', $qrUrl, $m)) {
                $rel = $m[1];
            }

            $qrBytes = null;
            if ($rel && Storage::disk('public')->exists($rel)) {
                $qrBytes = Storage::disk('public')->get($rel);
            } else {
                $resp = Http::timeout(20)->get($qrUrl);
                if ($resp->successful()) {
                    $qrBytes = $resp->body();
                }
            }

            if (!$qrBytes) {
                return $qrUrl;
            }

            $qrImg = $this->imageManager->read($qrBytes);
            $qrImg->resize($qrWidth, $qrHeight);

            $canvas = $this->imageManager->create($areaWidth, $areaHeight)->fill('rgba(0,0,0,0)');
            $x = (int) round(($areaWidth - $qrWidth) / 2);
            $y = (int) round(($areaHeight - $qrHeight) / 2);
            $canvas->place($qrImg, 'top-left', $x, $y);

            $outRel = 'previews/printful_qr_' . ($orderItemId ?: 'x') . '_' . $placement . '_' . time() . '.png';
            Storage::disk('public')->put($outRel, $canvas->toPng()->toString());

            // Build an absolute URL (Printful requires absolute), preferring the same host
            // as the original QR URL to avoid domain mismatches.
            $pathUrl = '/storage/' . ltrim($outRel, '/');

            $scheme = (string) (parse_url($qrUrl, PHP_URL_SCHEME) ?: '');
            $host = (string) (parse_url($qrUrl, PHP_URL_HOST) ?: '');
            $base = '';
            if ($host !== '') {
                $scheme = $scheme !== '' ? $scheme : 'https';
                $scheme = strtolower($scheme) === 'http' ? 'https' : $scheme;
                $base = $scheme . '://' . $host;
            } else {
                $base = (string) config('app.url', 'https://localhost');
                $base = preg_replace('#^http://#', 'https://', $base);
            }

            $u = rtrim($base, '/') . $pathUrl;
            return $u;
        } catch (\Throwable $e) {
            return $qrUrl;
        }
    }

    /**
     * Map our placement names to Printful's file types
     */
    protected function mapPlacementToType(string $placement, string $category): string
    {
        // Printful placement types vary by product
        $mappings = [
            't-shirt' => [
                'front' => 'front',
                'back' => 'back',
                'sleeve_left' => 'sleeve_left',
                'sleeve_right' => 'sleeve_right',
            ],
            'hoodie' => [
                'front' => 'front',
                'back' => 'back',
                'sleeve_left' => 'sleeve_left',
                'sleeve_right' => 'sleeve_right',
            ],
            'mug' => [
                'front' => 'default', // Mugs use wrap/default
                'default' => 'default',
            ],
            'poster' => [
                'front' => 'default',
            ],
            'sticker' => [
                'front' => 'default',
            ],
        ];

        return $mappings[$category][$placement] ?? 'default';
    }

    /**
     * Get print area width for a product/placement combo
     */
    protected function getAreaWidth(string $category, string $placement): int
    {
        $areas = [
            't-shirt' => ['front' => 1800, 'back' => 1800, 'sleeve_left' => 600, 'sleeve_right' => 600],
            'hoodie' => ['front' => 1800, 'back' => 1800, 'sleeve_left' => 600, 'sleeve_right' => 600],
            'mug' => ['default' => 2700, 'front' => 2700],
            'poster' => ['default' => 3600, 'front' => 3600],
            'sticker' => ['default' => 1800, 'front' => 1800],
        ];

        return $areas[$category][$placement] ?? 1800;
    }

    /**
     * Get print area height for a product/placement combo
     */
    protected function getAreaHeight(string $category, string $placement): int
    {
        $areas = [
            't-shirt' => ['front' => 2400, 'back' => 2400, 'sleeve_left' => 600, 'sleeve_right' => 600],
            'hoodie' => ['front' => 2400, 'back' => 2400, 'sleeve_left' => 600, 'sleeve_right' => 600],
            'mug' => ['default' => 1100, 'front' => 1100],
            'poster' => ['default' => 4800, 'front' => 4800],
            'sticker' => ['default' => 1800, 'front' => 1800],
        ];

        return $areas[$category][$placement] ?? 1800;
    }

    /**
     * Get order status from Printful
     */
    public function getOrderStatus(string $printfulOrderId): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/orders/{$printfulOrderId}");

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        return [];
    }

    /**
     * Confirm/approve an order for fulfillment
     */
    public function confirmOrder(string $printfulOrderId): bool
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/orders/{$printfulOrderId}/confirm");

        return $response->successful();
    }

    /**
     * Cancel an order
     */
    public function cancelOrder(string $printfulOrderId): bool
    {
        $response = Http::withHeaders($this->getHeaders())
            ->delete("{$this->baseUrl}/orders/{$printfulOrderId}");

        return $response->successful();
    }

    /**
     * Create mockup for product with QR code (legacy helper)
     */
    public function createMockup(int $productId, string $qrImageUrl): array
    {
        return $this->createMockupTask($productId, [], [
            [
                'placement' => 'front',
                'image_url' => $qrImageUrl,
            ],
        ], 'png');
    }

    /**
     * Create a mockup-generator task for a catalog product.
     *
     * @param int $catalogProductId Printful catalog product id (NOT store product id)
     * @param array<int> $variantIds Catalog variant ids (empty = Printful may pick defaults)
     * @param array<int,array<string,mixed>> $files [{placement,image_url,position?}, ...]
     */
    public function createMockupTask(int $catalogProductId, array $variantIds, array $files, string $format = 'png'): array
    {
        $payload = [
            'variant_ids' => array_values(array_filter($variantIds)),
            'format' => $format,
            'files' => $files,
        ];

        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/mockup-generator/create-task/{$catalogProductId}", $payload);

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        throw new \Exception('Printful mockup task failed: ' . $response->status() . ' ' . $response->body());
    }

    /**
     * Get mockup generation result
     */
    public function getMockupResult(string $taskKey): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/mockup-generator/task", [
                'task_key' => $taskKey,
            ]);

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        return [];
    }

    /**
     * Generate a realistic Printful mockup URL for a Product.
     * Works for:
     * - Catalog products (printful_product_id is catalog product id)
     * - Store products (printful_product_id is store product id; we resolve to catalog product id via /store/products/{id})
     */
    public function generateRealisticMockupUrl(Product $product, int $variantId, string $logoUrl, string $qrUrl, int $timeoutSeconds = 20): ?string
    {
        $catalogProductId = $this->resolveCatalogProductIdForMockups($product);

        $files = $this->buildMockupFiles($product, $logoUrl, $qrUrl);

        $task = $this->createMockupTask($catalogProductId, [$variantId], $files, 'png');
        $taskKey = $task['task_key'] ?? null;
        if (!$taskKey) {
            return null;
        }

        $deadline = time() + max(5, $timeoutSeconds);
        do {
            $result = $this->getMockupResult($taskKey);
            $status = $result['status'] ?? null;

            if ($status === 'completed') {
                // Typical shape: result.mockups[0].mockup_url
                $mockups = $result['mockups'] ?? [];
                $first = $mockups[0] ?? null;
                $url = $first['mockup_url'] ?? $first['mockup_url_large'] ?? $first['url'] ?? null;
                return $url;
            }

            if ($status === 'failed') {
                return null;
            }

            usleep(800000); // 0.8s
        } while (time() < $deadline);

        return null;
    }

    protected function resolveCatalogProductIdForMockups(Product $product): int
    {
        $id = (int) $product->printful_product_id;
        // Heuristic: large IDs are store product ids
        if ($id > 1000000) {
            $store = $this->getStoreProduct($id);
            $firstVariant = $store['sync_variants'][0] ?? null;
            $catalogId = $firstVariant['product']['product_id'] ?? null;
            if (!$catalogId) {
                throw new \Exception('Unable to resolve catalog product id for store product');
            }
            return (int) $catalogId;
        }

        return $id;
    }

    /**
     * Build mockup-generator file placements using our print config.
     */
    protected function buildMockupFiles(Product $product, string $logoUrl, string $qrUrl): array
    {
        $files = [];
        $printConfig = $product->getPrintConfigWithDefaults();

        if (!empty($logoUrl)) {
            $logoConfig = $printConfig['logo'] ?? [];
            $placement = $this->mapPlacementToType($logoConfig['placement'] ?? 'front', $product->category);
            $files[] = [
                'placement' => $placement,
                'image_url' => $logoUrl,
                'position' => [
                    'area_width' => $this->getAreaWidth($product->category, $placement),
                    'area_height' => $this->getAreaHeight($product->category, $placement),
                    'width' => $logoConfig['width'] ?? 600,
                    'height' => $logoConfig['height'] ?? 600,
                    'top' => $logoConfig['top'] ?? 400,
                    'left' => $logoConfig['left'] ?? 600,
                ],
            ];
        }

        if (!empty($qrUrl)) {
            $qrConfig = $printConfig['qr_code'] ?? [];
            $placement = $this->mapPlacementToType($qrConfig['placement'] ?? 'front', $product->category);
            $files[] = [
                'placement' => $placement,
                'image_url' => $qrUrl,
                'position' => [
                    'area_width' => $this->getAreaWidth($product->category, $placement),
                    'area_height' => $this->getAreaHeight($product->category, $placement),
                    'width' => $qrConfig['width'] ?? 250,
                    'height' => $qrConfig['height'] ?? 250,
                    'top' => $qrConfig['top'] ?? 350,
                    'left' => $qrConfig['left'] ?? 100,
                ],
            ];
        }

        return $files;
    }

    /**
     * Get store information
     */
    public function getStoreInfo(): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/stores");

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        return [];
    }

    /**
     * Register webhooks with Printful
     */
    public function registerWebhooks(string $webhookUrl): array
    {
        // Note: Printful's `stock_updated` webhook requires product_ids params.
        // We don't need it for order fulfillment, so omit it to avoid 400 errors.
        $webhookTypes = [
            'package_shipped',
            'order_created',
            'order_updated', 
            'order_failed',
            'order_canceled',
            'order_put_hold',
            'product_synced',
        ];

        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/webhooks", [
                'url' => $webhookUrl,
                'types' => $webhookTypes,
            ]);

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        throw new \Exception('Failed to register webhooks: ' . $response->body());
    }

    /**
     * Get registered webhooks
     */
    public function getWebhooks(): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/webhooks");

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        return [];
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook(): bool
    {
        $response = Http::withHeaders($this->getHeaders())
            ->delete("{$this->baseUrl}/webhooks");

        return $response->successful();
    }

    /**
     * Estimate order costs without creating
     */
    public function estimateOrderCosts(array $recipient, array $items): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/orders/estimate-costs", [
                'recipient' => $recipient,
                'items' => $items,
            ]);

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        return [];
    }

    /**
     * Get countries list
     */
    public function getCountries(): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/countries");

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        return [];
    }

    /**
     * Get tax rate for location
     */
    public function getTaxRate(string $countryCode, ?string $stateCode = null): float
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/tax/rates", [
                'recipient' => [
                    'country_code' => $countryCode,
                    'state_code' => $stateCode,
                ],
            ]);

        if ($response->successful()) {
            $result = $response->json()['result'] ?? [];
            return (float) ($result['rate'] ?? 0);
        }

        return 0;
    }

    /**
     * Get print file info for a product
     */
    public function getPrintFileInfo(int $productId): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/mockup-generator/printfiles/{$productId}");

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        return [];
    }

    /**
     * Pre-cache blank mockups for common product/color combinations
     * This prevents API calls during live editing
     */
    public function precacheCommonMockups(): void
    {
        \Log::info('Starting mockup precaching process');

        $commonColors = [
            'white' => 'white',
            'black' => 'black',
            'navy' => 'navy',
            'red' => 'red',
            'gray' => 'grey',
            'green' => 'green'
        ];

        $products = [
            // T-shirt (Bella Canvas 3001) - Just front view for testing
            71 => ['slug' => 'unisex-staple-t-shirt-bella-canvas-3001', 'category' => 't-shirt'],
        ];

        // Test with just white and all views to see if sleeves work
        $commonColors = [
            'white' => 'white',
        ];

        \Log::info('Processing products for mockup precaching', ['products' => array_keys($products)]);

        foreach ($products as $catalogId => $productInfo) {
            \Log::info("Processing product {$catalogId} ({$productInfo['slug']})");
            foreach ($commonColors as $colorName => $colorNormalized) {
                try {
                    \Log::info("Processing color {$colorName} for product {$catalogId}");
                    // Get product variants to find the right variant ID for this color
                    \Log::info("Fetching variants for product {$catalogId}");
                    $productData = $this->getProductVariants($catalogId);
                    if (empty($productData['variants'])) {
                        \Log::warning("No variants found for product {$catalogId}");
                        continue;
                    }
                    \Log::info("Found " . count($productData['variants']) . " variants for product {$catalogId}");

                    // Find variant that matches this color
                    $variant = null;
                    \Log::info("Looking for color '{$colorName}' (normalized: '{$colorNormalized}') in " . count($productData['variants']) . " variants");
                    foreach ($productData['variants'] as $v) {
                        $variantColor = strtolower($v['color'] ?? '');
                        $normalizedVariantColor = $this->normalizeColorName($variantColor);
                        \Log::info("Checking variant: '{$variantColor}' -> '{$normalizedVariantColor}'");

                        if (str_contains($variantColor, $colorName) ||
                            $normalizedVariantColor === $colorNormalized) {
                            $variant = $v;
                            \Log::info("Found matching variant: {$v['id']} for color '{$colorName}'");
                            break;
                        }
                    }

                    if (!$variant) {
                        \Log::warning("No variant found for color '{$colorName}', skipping");
                        continue;
                    }

                    $variantId = $variant['id'];
                    $views = $this->getViewsForCategory($productInfo['category']);

                    foreach ($views as $view) {
                        $cachePath = "mockups/{$productInfo['slug']}/{$colorNormalized}/{$view}.png";

                        // Skip if already cached (but allow .jpg files to be converted to .png)
                        if (Storage::disk('public')->exists($cachePath)) {
                            \Log::info("Mockup already cached: {$cachePath}");
                            continue;
                        }

                        // Check if .jpg version exists and convert it
                        $jpgPath = "mockups/{$productInfo['slug']}/{$colorNormalized}/{$view}.jpg";
                        if (Storage::disk('public')->exists($jpgPath)) {
                            try {
                                // Copy .jpg to .png for consistency
                                $jpgContent = Storage::disk('public')->get($jpgPath);
                                Storage::disk('public')->put($cachePath, $jpgContent);
                                \Log::info("Converted existing mockup: {$jpgPath} -> {$cachePath}");
                                continue;
                            } catch (\Exception $e) {
                                \Log::warning("Failed to convert existing mockup: {$e->getMessage()}");
                            }
                        }

                        // Generate new blank mockup for this view
                        $placementType = $this->mapPlacementToType($view, $productInfo['category']);
                        $files = [[
                            'placement' => $placementType,
                            'image_url' => $this->getBlankDesignUrl(),
                        ]];

                        try {
                            $task = $this->createMockupTask($catalogId, [$variantId], $files, 'png');
                            $taskKey = $task['task_key'] ?? null;

                            if ($taskKey) {
                                \Log::info("Created mockup task for {$view} on product {$catalogId}, variant {$variantId}, task: {$taskKey}");
                                $mockupUrl = $this->pollMockupTask($taskKey, 30);

                                if ($mockupUrl) {
                                    \Log::info("Got mockup URL for {$cachePath}: {$mockupUrl}");
                                    $response = Http::timeout(30)->get($mockupUrl);
                                    if ($response->successful()) {
                                        Storage::disk('public')->put($cachePath, $response->body());
                                        \Log::info("Generated and cached mockup: {$cachePath}");
                                    } else {
                                        \Log::warning("Failed to download mockup for {$cachePath}, HTTP status: {$response->status()}");
                                    }
                                } else {
                                    \Log::warning("No mockup URL returned for {$cachePath}, task key: {$taskKey}");
                                }
                            } else {
                                \Log::warning("No task key returned for {$cachePath}, task response: " . json_encode($task));
                            }
                        } catch (\Exception $e) {
                            \Log::warning("Failed to cache mockup {$cachePath}: " . $e->getMessage());
                        }

                        // Rate limiting - don't overwhelm Printful
                        sleep(45); // Long delay to avoid rate limiting (Printful allows ~2 requests per minute)
                    }
                } catch (\Exception $e) {
                    \Log::warning("Failed to precache for product {$catalogId}, color {$colorName}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Generate blank base mockups for all views using Printful's mockup generator
     * Returns array of [view => url] for cached mockups
     */
    public function generateBlankMockups(int $catalogProductId, int $variantId, string $productSlug, string $colorNormalized, string $category): array
    {
        $views = $this->getViewsForCategory($category);
        $blankDesignUrl = $this->getBlankDesignUrl();
        $results = [];

        foreach ($views as $view) {
            $placementType = $this->mapPlacementToType($view, $category);
            
            // Build files array with blank design for this view
            $files = [[
                'type' => $placementType,
                'url' => $blankDesignUrl,
            ]];

            try {
                // Create mockup task
                $task = $this->createMockupTask($catalogProductId, [$variantId], $files, 'png');
                $taskKey = $task['task_key'] ?? null;
                
                if (!$taskKey) {
                    continue;
                }

                // Poll for result (with timeout)
                $mockupUrl = $this->pollMockupTask($taskKey, 30);
                
                if ($mockupUrl) {
                    // Download and cache the mockup
                    $response = Http::timeout(30)->get($mockupUrl);
                    if ($response->successful()) {
                        $path = "mockups/{$productSlug}/{$colorNormalized}/{$view}.png";
                        Storage::disk('public')->put($path, $response->body());
                        $results[$view] = $path;
                    }
                }
            } catch (\Exception $e) {
                // Skip failed views
                continue;
            }
        }

        return $results;
    }

    /**
     * Get views available for a category
     */
    protected function getViewsForCategory(string $category): array
    {
        return match($category) {
            't-shirt', 'hoodie' => ['front', 'back', 'sleeve_left', 'sleeve_right'],
            'mug' => ['wrap'],
            default => ['front'],
        };
    }

    /**
     * Get URL to a tiny transparent PNG (blank design)
     * We'll create this once and reuse it
     */
    protected function getBlankDesignUrl(): string
    {
        // Check if we already have a blank design cached
        $blankPath = 'mockups/_blank-design.png';
        
        if (!Storage::disk('public')->exists($blankPath)) {
            // Create a 1x1 transparent PNG
            $image = imagecreatetruecolor(1, 1);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefill($image, 0, 0, $transparent);
            
            ob_start();
            imagepng($image);
            $pngData = ob_get_clean();
            imagedestroy($image);
            
            Storage::disk('public')->put($blankPath, $pngData);
        }

        // Return public HTTPS URL (Printful needs absolute URL)
        $url = Storage::disk('public')->url($blankPath);
        
        // Convert relative URL to absolute HTTPS URL
        if (str_starts_with($url, '/')) {
            $appUrl = config('app.url', 'https://localhost');
            // Ensure HTTPS
            $appUrl = preg_replace('#^http://#', 'https://', $appUrl);
            $url = rtrim($appUrl, '/') . $url;
        }
        
        return $url;
    }

    /**
     * Create (and cache) a blank mockup image for a specific view.
     * This is used to obtain realistic base images for back/left-sleeve/right-sleeve previews.
     *
     * Returns the public-disk relative path (e.g. mockups/slug/color/back.png) if created/found.
     */
    public function getOrCreateBlankMockupCached(
        int $catalogProductId,
        ?int $variantId,
        string $productSlug,
        string $colorNormalized,
        string $category,
        string $view,
        int $timeoutSeconds = 30
    ): ?string {
        $view = $view ?: 'front';
        $cachePath = "mockups/{$productSlug}/{$colorNormalized}/{$view}.png";

        if (Storage::disk('public')->exists($cachePath)) {
            return $cachePath;
        }

        // Generate new blank mockup for this view
        $placementType = $this->mapPlacementToType($view, $category);
        $files = [[
            'placement' => $placementType,
            'image_url' => $this->getBlankDesignUrl(),
        ]];

        $task = $this->createMockupTask($catalogProductId, $variantId ? [$variantId] : [], $files, 'png');
        $taskKey = $task['task_key'] ?? null;
        if (!$taskKey) {
            return null;
        }

        $mockupUrl = $this->pollMockupTask($taskKey, $timeoutSeconds);
        if (!$mockupUrl) {
            return null;
        }

        $response = Http::timeout(max(10, $timeoutSeconds))->get($mockupUrl);
        if (!$response->successful()) {
            return null;
        }

        Storage::disk('public')->put($cachePath, $response->body());
        return $cachePath;
    }

    /**
     * Poll mockup task until complete or timeout
     */
    protected function pollMockupTask(string $taskKey, int $timeoutSeconds = 30): ?string
    {
        $startTime = time();
        $maxAttempts = 20;
        $attempt = 0;

        while ($attempt < $maxAttempts && (time() - $startTime) < $timeoutSeconds) {
            $result = $this->getMockupResult($taskKey);
            
            $status = $result['status'] ?? null;
            
            if ($status === 'completed') {
                // Printful returns mockups in result['mockups'] array
                $mockups = $result['mockups'] ?? [];
                if (!empty($mockups)) {
                    $firstMockup = $mockups[0];
                    return $firstMockup['mockup_url'] ?? $firstMockup['mockup_url_large'] ?? $firstMockup['url'] ?? null;
                }
                
                // Fallback: check variants structure (some API versions)
                $variants = $result['variants'] ?? [];
                if (!empty($variants)) {
                    $firstVariant = $variants[0];
                    $files = $firstVariant['files'] ?? [];
                    if (!empty($files)) {
                        return $files[0]['url'] ?? null;
                    }
                }
                return null;
            }
            
            if ($status === 'failed') {
                return null;
            }
            
            // Wait before next poll
            sleep(2);
            $attempt++;
        }

        return null;
    }

    /**
     * Get mockup templates for a product (includes print area dimensions and positioning)
     */
    public function getMockupTemplates(int $productId): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/mockup-generator/templates/{$productId}");

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        return [];
    }

    /**
     * Get a product-only (no human model) "ghost" template image URL for a specific placement.
     * Uses Printful mockup templates mapping for the given catalog variant.
     */
    public function getGhostTemplateImageUrl(int $catalogProductId, int $variantId, string $placement): ?string
    {
        $placement = $placement ?: 'front';
        $result = $this->getMockupTemplates($catalogProductId);

        $variantMappings = $result['variant_mapping'] ?? [];
        $templateId = null;
        foreach ($variantMappings as $vm) {
            if (!is_array($vm)) {
                continue;
            }
            if (($vm['variant_id'] ?? null) == $variantId) {
                foreach (($vm['templates'] ?? []) as $tplRef) {
                    if (!is_array($tplRef)) {
                        continue;
                    }
                    if (($tplRef['placement'] ?? null) === $placement) {
                        $templateId = $tplRef['template_id'] ?? null;
                        break 2;
                    }
                }
            }
        }

        if (!$templateId) {
            return null;
        }

        foreach (($result['templates'] ?? []) as $tpl) {
            if (!is_array($tpl)) {
                continue;
            }
            if (($tpl['template_id'] ?? null) == $templateId) {
                return $tpl['image_url'] ?? null;
            }
        }

        return null;
    }

    /**
     * Return the full template entry for a placement (includes background_url + image_url).
     */
    public function getGhostTemplateEntry(int $catalogProductId, int $variantId, string $placement): ?array
    {
        $placement = $placement ?: 'front';
        $result = $this->getMockupTemplates($catalogProductId);

        $variantMappings = $result['variant_mapping'] ?? [];
        $templateId = null;
        foreach ($variantMappings as $vm) {
            if (!is_array($vm)) {
                continue;
            }
            if (($vm['variant_id'] ?? null) == $variantId) {
                foreach (($vm['templates'] ?? []) as $tplRef) {
                    if (!is_array($tplRef)) {
                        continue;
                    }
                    if (($tplRef['placement'] ?? null) === $placement) {
                        $templateId = $tplRef['template_id'] ?? null;
                        break 2;
                    }
                }
            }
        }

        if (!$templateId) {
            return null;
        }

        foreach (($result['templates'] ?? []) as $tpl) {
            if (!is_array($tpl)) {
                continue;
            }
            if (($tpl['template_id'] ?? null) == $templateId) {
                return $tpl;
            }
        }

        return null;
    }

    /**
     * Download and cache a ghost template image for fast local previews.
     * Returns the public-disk relative path if cached.
     */
    public function getOrCacheGhostTemplate(
        int $catalogProductId,
        int $variantId,
        string $productSlug,
        string $colorNormalized,
        string $category,
        string $placement,
        int $timeoutSeconds = 30
    ): ?string {
        $placement = $placement ?: 'front';
        $path = "mockups/{$productSlug}/{$colorNormalized}/{$placement}.png";

        \Log::info('getOrCacheGhostTemplate called', [
            'catalog_product_id' => $catalogProductId,
            'variant_id' => $variantId,
            'product_slug' => $productSlug,
            'color_normalized' => $colorNormalized,
            'placement' => $placement,
            'cache_path' => $path,
        ]);

        if (Storage::disk('public')->exists($path)) {
            \Log::info('Ghost template already cached', ['path' => $path]);
            return $path;
        }

        \Log::info('Fetching ghost template entry from Printful', [
            'catalog_product_id' => $catalogProductId,
            'variant_id' => $variantId,
            'placement' => $placement,
        ]);

        $tpl = $this->getGhostTemplateEntry($catalogProductId, $variantId, $placement);
        if (!$tpl) {
            \Log::warning('getGhostTemplateEntry returned null', [
                'catalog_product_id' => $catalogProductId,
                'variant_id' => $variantId,
                'placement' => $placement,
            ]);
            return null;
        }

        \Log::info('Got ghost template entry', [
            'template_id' => $tpl['template_id'] ?? null,
            'has_image_url' => !empty($tpl['image_url']),
            'has_background_url' => !empty($tpl['background_url']),
            'has_background_color' => !empty($tpl['background_color']),
        ]);

        $bgUrl = null; // NEVER use background_url (often lifestyle/model images)
        $bgColor = $tpl['background_color'] ?? '#ffffff';
        $imgUrl = $tpl['image_url'] ?? null;

        if (!$imgUrl) {
            return null;
        }

        // Download ghost image (PNG, contains the product cutout)
        $imgResp = Http::timeout(max(10, $timeoutSeconds))->get($imgUrl);
        if (!$imgResp->successful()) {
            return null;
        }

        $w = (int) ($tpl['template_width'] ?? 0);
        $h = (int) ($tpl['template_height'] ?? 0);
        if ($w <= 0 || $h <= 0) {
            // Fallback: cache the ghost PNG as-is (still product-only)
            Storage::disk('public')->put($path, $imgResp->body());
            return $path;
        }

        $ghost = $this->imageManager->read($imgResp->body());
        
        // Resize ghost to template dimensions
        $ghost->resize($w, $h);

        // If we have a background color (but no URL), create a solid color background
        if ($bgColor) {
            // Parse hex color to RGB
            $hex = ltrim($bgColor, '#');
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            
            // Create background image with GD
            $bgGd = imagecreatetruecolor($w, $h);
            $bgColorGd = imagecolorallocate($bgGd, $r, $g, $b);
            imagefill($bgGd, 0, 0, $bgColorGd);
            
            // Get ghost image as GD resource
            $ghostPng = $ghost->toPng()->toString();
            $ghostGd = imagecreatefromstring($ghostPng);
            
            if ($ghostGd) {
                $ghostW = imagesx($ghostGd);
                $ghostH = imagesy($ghostGd);
                
                // Enable alpha blending
                imagealphablending($bgGd, true);
                imagesavealpha($bgGd, true);
                imagealphablending($ghostGd, false);
                imagesavealpha($ghostGd, true);
                
                // Copy ghost onto background with proper alpha handling
                imagecopyresampled($bgGd, $ghostGd, 0, 0, 0, 0, $w, $h, $ghostW, $ghostH);
                
                // Save with alpha channel
                ob_start();
                imagealphablending($bgGd, false);
                imagesavealpha($bgGd, true);
                imagepng($bgGd, null, 9); // Highest quality
                $pngData = ob_get_clean();
                Storage::disk('public')->put($path, $pngData);
                
                imagedestroy($ghostGd);
                imagedestroy($bgGd);
                
                \Log::info('Created ghost template with solid color background', [
                    'path' => $path,
                    'color' => $bgColor,
                    'rgb' => [$r, $g, $b],
                    'dimensions' => [$w, $h],
                ]);
                return $path;
            }
            
            imagedestroy($bgGd);
        }
        
        // Fallback: just resize ghost and save as-is
        Storage::disk('public')->put($path, $ghost->toPng()->toString());
        return $path;
        
        // No background - just resize and save the ghost image as-is (it has white background)
        Storage::disk('public')->put($path, $ghost->toPng()->toString());
        return $path;

        // Fallback: cache the ghost PNG as-is (still product-only, but might be white/transparent)
        Storage::disk('public')->put($path, $imgResp->body());
        return $path;
    }

    /**
     * Get optimal placement coordinates for centering a design
     * Uses Printful's printfile dimensions AND template data for accuracy
     */
    public function getOptimalPlacement(int $productId, string $placement, int $designWidth, int $designHeight): ?array
    {
        $printFileInfo = $this->getPrintFileInfo($productId);
        $templates = $this->getMockupTemplates($productId);

        // Find printfile for this placement
        $placementPrintfileId = null;
        foreach ($printFileInfo['variant_printfiles'] ?? [] as $variantPrintfile) {
            if (isset($variantPrintfile['placements'][$placement])) {
                $placementPrintfileId = $variantPrintfile['placements'][$placement];
                break;
            }
        }

        if (!$placementPrintfileId) {
            return null;
        }

        // Get printfile dimensions
        $printfile = null;
        foreach ($printFileInfo['printfiles'] ?? [] as $pf) {
            if ($pf['printfile_id'] == $placementPrintfileId) {
                $printfile = $pf;
                break;
            }
        }

        if (!$printfile) {
            return null;
        }

        $areaWidth = $printfile['width'];
        $areaHeight = $printfile['height'];

        // Try to find template with print area positioning info
        $template = null;
        foreach ($templates as $t) {
            // Match template by placement type if available
            if (isset($t['placement']) && $t['placement'] === $placement) {
                $template = $t;
                break;
            }
        }
        
        // If no specific template, use first one as fallback (templates may not be 0-indexed)
        if (!$template && !empty($templates) && is_array($templates)) {
            $templatesList = array_values($templates);
            $template = $templatesList[0] ?? null;
        }

        // Calculate center position using printfile dimensions
        $left = ($areaWidth - $designWidth) / 2;
        $top = ($areaHeight - $designHeight) / 2;

        // If template has print_area positioning, adjust coordinates
        if ($template && isset($template['print_area_left']) && isset($template['print_area_top'])) {
            // Template coordinates are relative to template image, not print area
            // We're working in print area coordinates, so this is already correct
        }

        return [
            'area_width' => $areaWidth,
            'area_height' => $areaHeight,
            'width' => $designWidth,
            'height' => $designHeight,
            'top' => max(0, round($top, 2)),
            'left' => max(0, round($left, 2)),
            'printfile_id' => $placementPrintfileId,
            'dpi' => $printfile['dpi'] ?? 150,
            'fill_mode' => $printfile['fill_mode'] ?? 'fit',
        ];
    }

    /**
     * Get product categories from Printful
     */
    public function getCategories(): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/categories");

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        return [];
    }

    /**
     * Get variant details (more info than product variants endpoint)
     */
    public function getVariantDetails(int $variantId): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/products/variant/{$variantId}");

        if ($response->successful()) {
            return $response->json()['result'] ?? [];
        }

        return [];
    }

    /**
     * Normalize color names for consistent caching
     */
    protected function normalizeColorName(string $color): string
    {
        $color = strtolower(trim($color));
        $color = preg_replace('/[^a-z0-9]+/', '-', $color);
        return trim($color, '-');
    }

    protected function getHeaders(): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        // If the Printful API token can access multiple stores, explicitly pin requests
        // to the intended store to avoid submitting orders into the wrong shop.
        $storeId = config('services.printful.store_id');
        if (is_string($storeId) && trim($storeId) !== '') {
            $headers['X-PF-Store-ID'] = trim($storeId);
        } elseif (is_int($storeId) && $storeId > 0) {
            $headers['X-PF-Store-ID'] = (string) $storeId;
        }

        return $headers;
    }
}


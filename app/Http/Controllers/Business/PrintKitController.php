<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\QRCode;
use App\Services\AveryDpoService;
use App\Services\QRGeneratorService;
use App\Services\StripeService;
use App\Support\BusinessCardQr;
use App\Support\OnboardingQr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PrintKitController extends Controller
{
    public function __construct(
        protected StripeService $stripeService,
        protected AveryDpoService $avery,
        protected QRGeneratorService $qrService,
    ) {
    }

    public function index(Request $request)
    {
        $business = $request->user()->business;

        $canAccess = $business->canAccess('print_kits');

        $qrCodes = $business->qrCodes()
            ->visibleToBusiness()
            ->where('is_active', true)
            ->whereNotIn('code', [OnboardingQr::CODE, BusinessCardQr::CODE])
            ->get(['id', 'name', 'code', 'design']);

        $sizesConfig = (array) config('printkits.sizes', []);
        $sizes = collect($sizesConfig)->map(fn ($size, $key) => [
            'key' => (string) $key,
            'name' => (string) ($size['name'] ?? $key),
            'template_number' => (string) ($size['template_number'] ?? ''),
            'avery_template_url' => (string) ($size['avery_template_url'] ?? ''),
            'labels_per_sheet' => (int) ($size['labels_per_sheet'] ?? 0),
        ])->values();

        return Inertia::render('Business/PrintKits/Index', [
            'qrCodes' => $qrCodes,
            'canAccess' => $canAccess,
            'sizes' => $sizes,
        ]);
    }

    public function createOrder(Request $request)
    {
        $business = $request->user()->business;

        // Check if business can access print kits feature
        if (!$business->canAccess('print_kits')) {
            return back()->withErrors([
                'subscription' => 'Sticker kits are only available on Growth, Pro, and Enterprise plans. Please upgrade to access this feature.',
            ]);
        }

        $sizeKeys = array_keys((array) config('printkits.sizes', []));

        $validated = $request->validate([
            'size' => ['required', 'string', 'in:' . implode(',', $sizeKeys)],
            'items' => 'required|array|min:1|max:25',
            'items.*.qr_code_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1|max:5000',
        ]);

        $sizeKey = (string) $validated['size'];
        $size = (array) config("printkits.sizes.$sizeKey", []);
        $pricePerLabel = (float) ($size['price_per_label'] ?? 0);

        $items = collect($validated['items'])
            ->map(fn ($it) => [
                'qr_code_id' => (int) ($it['qr_code_id'] ?? 0),
                'quantity' => (int) ($it['quantity'] ?? 0),
            ])
            ->filter(fn ($it) => $it['qr_code_id'] > 0 && $it['quantity'] > 0)
            ->values();

        if ($items->isEmpty()) {
            return back()->withErrors(['items' => 'Select at least one QR code and quantity.']);
        }

        $totalLabels = (int) $items->sum('quantity');
        if ($totalLabels < 1) {
            return back()->withErrors(['items' => 'Select at least one label to print.']);
        }

        // Safety cap to avoid enormous POST payloads to Avery.
        if ($totalLabels > 5000) {
            return back()->withErrors(['items' => 'Too many labels selected. Please reduce quantity (max 5,000 labels per order).']);
        }

        $qrIds = $items->pluck('qr_code_id')->unique()->values();
        $qrCodes = QRCode::query()
            ->whereIn('id', $qrIds)
            ->where('business_id', $business->id)
            ->whereNotIn('code', [OnboardingQr::CODE, BusinessCardQr::CODE])
            ->visibleToBusiness()
            ->get(['id', 'name', 'code']);

        if ($qrCodes->count() !== $qrIds->count()) {
            abort(404);
        }

        $subtotal = round($totalLabels * $pricePerLabel, 2);
        $tax = 0.0;
        $shipping = 0.0;
        $total = $subtotal + $tax + $shipping;

        // Generate a unique order number with retry to handle rare collisions.
        $orderNumber = null;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = 'SK-' . Str::upper(Str::random(8));
            if (!Order::where('order_number', $candidate)->exists()) {
                $orderNumber = $candidate;
                break;
            }
        }
        if (!$orderNumber) {
            $orderNumber = 'SK-' . Str::upper(Str::random(12));
        }

        $orderData = [
            'business_id' => $business->id,
            'order_number' => $orderNumber,
            'type' => 'print_kits',
            'status' => 'pending',
            'subtotal' => $subtotal,
            'shipping_cost' => $shipping,
            'tax' => $tax,
            'total' => $total,
            'payment_status' => 'pending',
        ];

        $order = Order::create($orderData);

        foreach ($items as $it) {
            $qrCode = $qrCodes->firstWhere('id', $it['qr_code_id']);
            if (!$qrCode) {
                continue;
            }

            $qty = (int) $it['quantity'];
            $rowTotal = round($qty * $pricePerLabel, 2);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => null,
                'qr_code_id' => $qrCode->id,
                'product_name' => (string) ($size['name'] ?? 'Sticker Kit'),
                'variant' => 'Avery DPO (print yourself)',
                'quantity' => $qty,
                'unit_price' => $pricePerLabel,
                'total_price' => $rowTotal,
                'preview_url' => null,
                'design_data' => [
                    'size_key' => $sizeKey,
                ],
            ]);
        }

        return redirect()->route('business.print-kits.checkout', $order);
    }

    public function checkout(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        if ($order->type !== 'print_kits') {
            abort(404);
        }

        $order->load('items.qrCode');
        $sizeKey = (string) ($order->items->first()?->design_data['size_key'] ?? '');
        $size = $sizeKey ? (array) config("printkits.sizes.$sizeKey", []) : [];

        return Inertia::render('Business/PrintKits/Checkout', [
            'order' => $order,
            'currency' => config('printkits.currency'),
            'stripeConfigured' => $this->stripeService->isConfigured(),
            'size' => [
                'key' => $sizeKey,
                'name' => (string) ($size['name'] ?? $sizeKey),
            ],
        ]);
    }

    public function pay(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        if ($order->type !== 'print_kits') {
            abort(404);
        }

        // Prevent double-payment: if already paid or has a payment intent, don't create another session.
        if ($order->payment_status === 'paid' || $order->paid_at) {
            return redirect()->route('business.print-kits.checkout', $order)
                ->with('info', 'This order has already been paid.');
        }

        if (!$this->stripeService->isConfigured()) {
            return back()->with('error', 'Payment system is not configured. Please contact support.');
        }

        $business = $request->user()->business;

        $customer = $this->stripeService->getOrCreateCustomer($business);
        if (!$customer) {
            Log::error('PrintKits pay: could not create Stripe customer', ['business_id' => $business->id]);
            return back()->with('error', 'Unable to set up payment. Please try again or contact support.');
        }

        $currency = config('printkits.currency', 'cad');
        $order->loadMissing('items');
        $labelCount = (int) $order->items->sum('quantity');

        try {
            $session = \Stripe\Checkout\Session::create([
                'customer' => $customer->id,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => 'Sticker Kit Order #' . $order->order_number,
                            'description' => $labelCount > 0 ? "{$labelCount} label(s) • print in Avery" : 'Print in Avery',
                        ],
                        'unit_amount' => (int) round((float) $order->total * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('business.print-kits.checkout', $order) . '?order=success&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('business.print-kits.checkout', $order),
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'business_id' => $business->id,
                    'order_type' => 'print_kits',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('PrintKits pay: Stripe session creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Unable to start checkout. Please try again or contact support.');
        }

        // If this request came from Inertia, use Inertia::location so the client performs a full redirect.
        // Otherwise, use a normal external redirect.
        if ($request->header('X-Inertia')) {
            return Inertia::location($session->url);
        }

        return redirect()->away($session->url);
    }

    public function orders(Request $request)
    {
        $business = $request->user()->business;

        $orders = $business->orders()
            ->where('type', 'print_kits')
            ->with('items.qrCode')
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Business/PrintKits/Orders', [
            'orders' => $orders,
        ]);
    }

    public function avery(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        if ($order->type !== 'print_kits') {
            abort(404);
        }

        $order->load('items.qrCode');

        $sizeKey = (string) ($order->items->first()?->design_data['size_key'] ?? '');
        if ($sizeKey === '') {
            abort(400, 'Missing sticker size for this order.');
        }

        try {
            $bundleUrl = $this->avery->bundleUrlForSize($sizeKey);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Sticker template bundle is not available. Please contact support.',
            ], 400);
        }

        if ($this->isLocalBundleMissing($bundleUrl)) {
            return response($this->buildAveryErrorHtml(
                'Sticker template file is missing on server. Please contact support to upload the .avery bundle, then retry this paid order.'
            ), 400)->header('Content-Type', 'text/html; charset=UTF-8');
        }
        $column = $this->avery->mergeColumn();
        $rows = [];
        foreach ($order->items as $item) {
            $qrCode = $item->qrCode;
            $code = $qrCode?->code;
            if (!$code || !$qrCode) {
                continue;
            }

            $scanUrl = route('scan', ['code' => $code]);
            $imageUrl = $this->resolveQrImageUrl($qrCode, $scanUrl);
            $qty = (int) ($item->quantity ?? 0);
            for ($i = 0; $i < $qty; $i++) {
                $rows[] = [
                    'qr_url' => $scanUrl,
                    // Template can bind this image URL to preserve app QR colors/styles.
                    'qr_image_url' => $imageUrl,
                ];
            }
        }

        if (empty($rows)) {
            return response()->json([
                'error' => 'No QR codes found for this order. Please verify the QR codes still exist.',
            ], 400);
        }

        $headers = array_values(array_filter(array_unique([$column, 'qr_url', 'qr_image_url'])));
        $csv = $this->avery->buildCsv($rows, $headers);
        $actionUrl = $this->avery->directMergeActionUrl();
        $payload = [
            'mergeData' => $csv,
            'mergeDataFormat' => 'csv',
            'averyBundleUrl' => $bundleUrl,
        ];

        // Preferred path: post to Avery server-side, then redirect user to Avery's destination URL.
        // This avoids browser-side blank tabs caused by blocked cross-site form submits.
        try {
            $averyResponse = Http::asForm()
                ->timeout(20)
                ->withOptions(['allow_redirects' => false])
                ->post($actionUrl, $payload);

            $status = $averyResponse->status();
            $location = $averyResponse->header('Location');

            if (in_array($status, [301, 302, 303, 307, 308], true) && is_string($location) && $location !== '') {
                return redirect()->away($location);
            }
        } catch (\Throwable $e) {
            Log::warning('Avery launch redirect failed, falling back to client-side handoff', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback path: original client-side auto-post handoff page.
        $html = $this->avery->buildAutoPostHtml($actionUrl, $payload);
        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    protected function resolveQrImageUrl(QRCode $qrCode, string $fallbackScanUrl): string
    {
        $existing = $qrCode->image_url;
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        try {
            $path = $this->qrService->generateFile(
                $fallbackScanUrl,
                $qrCode->getDesignWithDefaults(),
                'png'
            );

            $design = $qrCode->design ?? [];
            $design['generated_path'] = $path;
            $qrCode->updateQuietly(['design' => $design]);
            $qrCode->refresh();

            $generated = $qrCode->image_url;
            if (is_string($generated) && $generated !== '') {
                return $generated;
            }
        } catch (\Throwable $e) {
            Log::warning('PrintKits could not generate qr image for avery merge', [
                'qr_code_id' => $qrCode->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Final fallback keeps existing templates (qr_url) usable.
        return $fallbackScanUrl;
    }

    protected function isLocalBundleMissing(string $bundleUrl): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        $bundleParts = parse_url($bundleUrl);
        $appParts = parse_url((string) config('app.url', ''));

        $bundleHost = $bundleParts['host'] ?? null;
        $appHost = $appParts['host'] ?? null;

        // Only enforce local-file existence when bundle URL points to this app host.
        if (!$bundleHost || !$appHost || strcasecmp($bundleHost, $appHost) !== 0) {
            return false;
        }

        $path = (string) ($bundleParts['path'] ?? '');
        $filename = basename($path);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return true;
        }

        return !is_file(public_path('avery/bundles/' . $filename));
    }

    protected function buildAveryErrorHtml(string $message): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8" />'
            . '<meta name="viewport" content="width=device-width,initial-scale=1" />'
            . '<title>Avery setup needed</title>'
            . '<style>body{font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;background:#0b1220;color:#e5e7eb;margin:0;}'
            . '.wrap{max-width:760px;margin:64px auto;padding:24px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:16px;}'
            . 'a{color:#93c5fd;text-decoration:none;}a:hover{text-decoration:underline;}</style></head><body>'
            . '<div class="wrap"><h1 style="margin:0 0 12px 0;font-size:20px;">Avery template not found</h1>'
            . '<p style="margin:0 0 12px 0;color:#d1d5db;">' . e($message) . '</p>'
            . '<p style="margin:0;color:#9ca3af;">Your order is still paid. After upload, open this Avery step again from '
            . '<a href="/business/print-kits/orders" target="_self">Print Kit Orders</a>.</p></div></body></html>';
    }

    /**
     * Launch the Avery Design & Print editor for a given sticker size.
     * Uses the public Direct Merge API to drop the customer straight into the
     * customize page with the correct template loaded.
     * Falls back to the Avery template page if the merge API fails.
     */
    public function launchAvery(Request $request, string $sizeKey)
    {
        $business = $request->user()->business;

        if (!$business->canAccess('print_kits')) {
            abort(403, 'Sticker kits require a Growth plan or above.');
        }

        $sizes = (array) config('printkits.sizes', []);
        if (!array_key_exists($sizeKey, $sizes)) {
            abort(404, 'Unknown sticker size.');
        }

        try {
            $bundleUrl = $this->avery->bundleUrlForSize($sizeKey);
        } catch (\Throwable $e) {
            // Fall back to template page link.
            return $this->fallbackToTemplatePage($sizes[$sizeKey]);
        }

        if ($this->isLocalBundleMissing($bundleUrl)) {
            return $this->fallbackToTemplatePage($sizes[$sizeKey]);
        }

        // Send one placeholder row so Avery accepts the payload.
        $column = $this->avery->mergeColumn();
        $csv = $this->avery->buildSingleColumnCsv($column, ['placeholder']);

        $actionUrl = $this->avery->directMergeActionUrl();
        $payload = [
            'mergeData' => $csv,
            'mergeDataFormat' => 'csv',
            'averyBundleUrl' => $bundleUrl,
        ];

        // Server-side POST → redirect straight to the Avery customize page.
        try {
            $averyResponse = Http::asForm()
                ->timeout(20)
                ->withOptions(['allow_redirects' => false])
                ->post($actionUrl, $payload);

            $status = $averyResponse->status();
            $location = $averyResponse->header('Location');

            if (in_array($status, [301, 302, 303, 307, 308], true) && is_string($location) && $location !== '') {
                return redirect()->away($location);
            }
        } catch (\Throwable $e) {
            Log::warning('Avery direct launch failed, falling back to template page', [
                'size_key' => $sizeKey,
                'error' => $e->getMessage(),
            ]);
        }

        // If merge API didn't redirect, fall back to template page.
        return $this->fallbackToTemplatePage($sizes[$sizeKey]);
    }

    protected function fallbackToTemplatePage(array $size): \Illuminate\Http\RedirectResponse
    {
        $url = (string) ($size['avery_template_url'] ?? 'https://www.avery.com/templates');
        return redirect()->away($url);
    }

    public function destroy(Request $request, Order $order)
    {
        $this->authorize('delete', $order);

        if ($order->type !== 'print_kits') {
            abort(404);
        }

        // Safety: only allow delete if the order is truly not paid.
        if (($order->payment_status ?? null) === 'paid' || $order->paid_at) {
            return back()->with('error', 'Paid orders cannot be removed.');
        }

        // Soft delete the order (keeps audit trail).
        $order->delete();

        return back()->with('success', 'Order removed.');
    }
}



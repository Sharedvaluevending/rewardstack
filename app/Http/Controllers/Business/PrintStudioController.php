<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\QRCode;
use App\Services\QRGeneratorService;
use App\Support\BusinessCardQr;
use App\Support\OnboardingQr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PrintStudioController extends Controller
{
    protected QRGeneratorService $qrService;
    /**
     * Print layout heuristics assume 300dpi inputs (qr_size/spacing) and a small page margin.
     * We keep QR size "as requested" and instead reduce rows/cols when the chosen layout won't fit.
     */
    private const PRINT_DPI = 300;
    private const PRINT_MARGIN_IN = 0.25;
    private const LABEL_HEIGHT_IN = 0.20;

    public function __construct(QRGeneratorService $qrService)
    {
        $this->qrService = $qrService;
    }

    public function index(Request $request)
    {
        $business = $request->user()->business;

        $qrCodes = $business->qrCodes()
            ->visibleToBusiness()
            ->where('is_active', true)
            ->whereNotIn('code', [OnboardingQr::CODE, BusinessCardQr::CODE])
            ->get(['id', 'name', 'code', 'design']);

        // Ensure images are generated for all QR codes
        foreach ($qrCodes as $qrCode) {
            try {
                if (empty($qrCode->design['generated_path']) || 
                    !Storage::disk('public')->exists($qrCode->design['generated_path'])) {
                    // Generate and store image
                    $path = $this->qrService->generateFile(
                        $qrCode->getScanUrl(),
                        $qrCode->getDesignWithDefaults()
                    );
                    // Store path in design
                    $design = $qrCode->design ?? [];
                    $design['generated_path'] = $path;
                    $qrCode->update(['design' => $design]);
                    $qrCode->refresh();
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to generate QR code image in print studio', [
                    'qr_code_id' => $qrCode->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return Inertia::render('Business/PrintStudio/Index', [
            'qrCodes' => $qrCodes,
            'layouts' => $this->getLayoutOptions(),
            'paperSizes' => $this->getPaperSizes(),
            'templates' => $this->getTemplates(),
            'fonts' => $this->getFonts(),
        ]);
    }

    public function generate(Request $request)
    {
        if (!app()->environment('testing')) {
            @set_time_limit(120);
        }

        $validated = $request->validate([
            'qr_code_ids' => 'required|array|min:1',
            'qr_code_ids.*' => 'exists:qr_codes,id',
            'quantity' => 'required|integer|min:1|max:100',
            'layout' => 'required|string',
            'paper_size' => 'required|string',
            // Print Studio supports up to 4" QR codes at 300dpi (~1200px).
            'qr_size' => 'required|integer|min:50|max:1200',
            'spacing' => 'required|integer|min:0|max:50',
            'include_text' => 'boolean',
            'include_border' => 'boolean',
            'header_text' => 'nullable|string|max:64',
            'footer_text' => 'nullable|string|max:64',
            'header_footer_font' => 'nullable|string|max:64',
            'header_footer_color' => 'nullable|string|max:16',
            'header_font_size' => 'nullable|numeric|min:8|max:72',
            'footer_font_size' => 'nullable|numeric|min:8|max:72',
            'header_footer_outline' => 'boolean',
            'header_footer_outline_color' => 'nullable|string|max:16',
        ]);

        $business = $request->user()->business;

        // Verify all QR codes belong to this business
        $qrCodes = QRCode::whereIn('id', $validated['qr_code_ids'])
            ->where('business_id', $business->id)
            ->visibleToBusiness()
            ->get();

        if ($qrCodes->count() !== count($validated['qr_code_ids'])) {
            return back()->with('error', 'Invalid QR code selection.');
        }

        // Duplicate QR codes based on quantity
        $expandedQRCodes = collect();
        for ($i = 0; $i < $validated['quantity']; $i++) {
            $expandedQRCodes = $expandedQRCodes->merge($qrCodes);
        }

        // Generate the print sheet
        $sheetData = $this->generatePrintSheet($expandedQRCodes, $validated);

        // Save to storage
        $filename = 'print_sheet_' . now()->format('Y-m-d_His') . '.pdf';
        $path = 'print_jobs/' . $business->id . '/' . $filename;
        
        // For now, return the data for client-side generation
        // In production, you'd use a PDF library like TCPDF or DomPDF

        return response()->json([
            'success' => true,
            'sheet' => $sheetData,
            'download_url' => null, // Would be generated with actual PDF
        ]);
    }

    protected function generatePrintSheet($qrCodes, array $options): array
    {
        $paperSize = $this->getPaperConfig($options['paper_size']);
        $requestedLayout = $this->getLayoutConfig($options['layout']);
        $layout = $this->fitLayoutToPaper($requestedLayout, $paperSize, $options);
        
        $sheets = [];
        $qrPerSheet = $layout['columns'] * $layout['rows'];
        $chunks = $qrCodes->chunk($qrPerSheet);
        $imageCache = [];

        foreach ($chunks as $chunk) {
            $sheetQRs = [];
            
            foreach ($chunk as $qrCode) {
                $sheetQRs[] = [
                    'id' => $qrCode->id,
                    'name' => $qrCode->name,
                    'code' => $qrCode->code,
                    'image' => $this->getPrintImageUrl($qrCode, $options, $imageCache),
                ];
            }

            $sheets[] = [
                'qr_codes' => $sheetQRs,
                'layout' => $layout,
                'layout_requested' => $requestedLayout,
                'paper' => $paperSize,
                'options' => $options,
            ];
        }

        return $sheets;
    }

    protected function getPrintImageUrl(QRCode $qrCode, array $options, array &$cache): string
    {
        $design = $qrCode->getDesignWithDefaults();
        unset($design['generated_path']);
        $design['size'] = (int) ($options['qr_size'] ?? 150);
        $design['preview_mode'] = false;

        $hash = sha1($qrCode->id . '|' . json_encode($design));
        if (isset($cache[$hash])) {
            return $cache[$hash];
        }

        $relativePath = 'qrcodes/print/' . $qrCode->id . '/' . $hash . '.png';
        $disk = Storage::disk('public');

        if (!$disk->exists($relativePath)) {
            $base64 = $this->qrService->generate($qrCode->getScanUrl(), $design);
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
            $disk->put($relativePath, $imageData);
        }

        $url = url('/storage/' . ltrim($relativePath, '/'));
        $cache[$hash] = $url;

        return $url;
    }

    protected function getLayoutOptions(): array
    {
        return [
            // Auto-fits columns/rows based on qr_size, spacing, paper size, and label option.
            // Note: columns/rows are placeholders; the real values are computed server-side.
            'auto' => ['name' => 'Auto (fit by size)', 'columns' => 0, 'rows' => 0],
            '1x1' => ['name' => '1 per page', 'columns' => 1, 'rows' => 1],
            '2x2' => ['name' => '4 per page', 'columns' => 2, 'rows' => 2],
            '2x3' => ['name' => '6 per page', 'columns' => 2, 'rows' => 3],
            '3x3' => ['name' => '9 per page', 'columns' => 3, 'rows' => 3],
            '3x4' => ['name' => '12 per page', 'columns' => 3, 'rows' => 4],
            '4x4' => ['name' => '16 per page', 'columns' => 4, 'rows' => 4],
            '4x5' => ['name' => '20 per page', 'columns' => 4, 'rows' => 5],
            '5x6' => ['name' => '30 per page', 'columns' => 5, 'rows' => 6],
        ];
    }

    protected function getPaperSizes(): array
    {
        return [
            'letter' => ['name' => 'Letter (8.5" x 11")', 'width' => 8.5, 'height' => 11],
            'legal' => ['name' => 'Legal (8.5" x 14")', 'width' => 8.5, 'height' => 14],
            'a4' => ['name' => 'A4 (210 x 297mm)', 'width' => 8.27, 'height' => 11.69],
            'a3' => ['name' => 'A3 (297 x 420mm)', 'width' => 11.69, 'height' => 16.54],
        ];
    }

    protected function getTemplates(): array
    {
        return [
            [
                'id' => 'stickers',
                'name' => 'Sticker Sheet',
                'description' => 'Perfect for printing on Avery sticker sheets',
                'layout' => '4x5',
                'paper_size' => 'letter',
            ],
            [
                'id' => 'table_tents',
                'name' => 'Table Tents',
                'description' => 'Large QR codes for table displays',
                'layout' => '2x2',
                'paper_size' => 'letter',
            ],
            [
                'id' => 'posters',
                'name' => 'Poster',
                'description' => 'Single large QR code for posters',
                'layout' => '1x1',
                'paper_size' => 'a3',
            ],
        ];
    }

    protected function getFonts(): array
    {
        return [
            // Clean / modern
            'Inter' => 'Inter (Clean Sans)',
            'Poppins' => 'Poppins (Rounded Sans)',
            // Serif
            'Playfair Display' => 'Playfair Display (Serif)',
            'Merriweather' => 'Merriweather (Serif)',
            // Condensed / headline
            'Oswald' => 'Oswald (Condensed)',
            'Bebas Neue' => 'Bebas Neue (Caps)',
            // Script
            'Pacifico' => 'Pacifico (Script)',
            'Dancing Script' => 'Dancing Script (Script)',
            // Playful / handwritten
            'Permanent Marker' => 'Permanent Marker (Marker)',
            'Indie Flower' => 'Indie Flower (Handwritten)',
            'Amatic SC' => 'Amatic SC (Tall Handwritten)',
            'Fredoka One' => 'Fredoka One (Rounded Playful)',
        ];
    }

    protected function getLayoutConfig(string $layout): array
    {
        $layouts = $this->getLayoutOptions();
        return $layouts[$layout] ?? $layouts['2x2'];
    }

    protected function getPaperConfig(string $size): array
    {
        $sizes = $this->getPaperSizes();
        return $sizes[$size] ?? $sizes['letter'];
    }

    /**
     * Compute a layout that actually fits on the chosen paper at the requested QR size.
     * - If "auto" is selected: use the maximum rows/cols that fit.
     * - If a fixed layout is selected: clamp rows/cols down so it fits (QR size remains the requested size).
     */
    protected function fitLayoutToPaper(array $requestedLayout, array $paper, array $options): array
    {
        $paperW = (float) ($paper['width'] ?? 8.5);
        $paperH = (float) ($paper['height'] ?? 11);

        $qrPx = (int) ($options['qr_size'] ?? 150);
        $gapPx = (int) ($options['spacing'] ?? 10);
        $includeText = (bool) ($options['include_text'] ?? false);

        $qrIn = max(0.01, $qrPx / self::PRINT_DPI);
        $gapIn = max(0.0, $gapPx / self::PRINT_DPI);
        $labelIn = $includeText ? self::LABEL_HEIGHT_IN : 0.0;

        $availW = max(0.1, $paperW - self::PRINT_MARGIN_IN * 2);
        $availH = max(0.1, $paperH - self::PRINT_MARGIN_IN * 2);

        // n*qr + (n-1)*gap <= avail  -> n <= (avail+gap)/(qr+gap)
        $maxCols = max(1, (int) floor(($availW + $gapIn) / ($qrIn + $gapIn)));
        // rows account for label height under each QR when enabled
        $maxRows = max(1, (int) floor(($availH + $gapIn) / (($qrIn + $labelIn) + $gapIn)));

        $isAuto = (($options['layout'] ?? '') === 'auto');
        $reqCols = (int) ($requestedLayout['columns'] ?? 2);
        $reqRows = (int) ($requestedLayout['rows'] ?? 2);

        $cols = $isAuto ? $maxCols : max(1, min($reqCols, $maxCols));
        $rows = $isAuto ? $maxRows : max(1, min($reqRows, $maxRows));

        return [
            'name' => $isAuto ? "Auto ({$cols}×{$rows})" : ($requestedLayout['name'] ?? "{$cols}×{$rows}"),
            'columns' => $cols,
            'rows' => $rows,
            'max_columns' => $maxCols,
            'max_rows' => $maxRows,
        ];
    }

    public function download(Request $request, string $job)
    {
        $business = $request->user()->business;
        $path = 'print_jobs/' . $business->id . '/' . $job;

        if (!Storage::exists($path)) {
            abort(404, 'Print job not found.');
        }

        return Storage::download($path);
    }
}


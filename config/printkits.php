<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sticker Kits (Avery "print yourself")
    |--------------------------------------------------------------------------
    |
    | Customers download their QR code images, then open an Avery template
    | via direct link, upload the image, and order/print through their own
    | Avery account.
    */

    // CSV merge column header (legacy, kept for existing orders).
    'merge_column' => env('STICKER_KITS_MERGE_COLUMN', 'qr_url'),

    // Public base URL where .avery bundles are hosted (legacy).
    'bundle_base_url' => env('STICKER_KITS_BUNDLE_BASE_URL', rtrim(env('APP_URL', ''), '/') . '/avery/bundles'),

    // Currency (legacy, kept for existing order records).
    'currency' => env('STICKER_KITS_CURRENCY', 'cad'),

    /*
     * The sticker-size presets we offer.
     *
     * - key: internal id (legacy keys kept for existing orders)
     * - name: UI label
     * - template_number: Avery product/template number
     * - avery_template_url: direct link to Avery Design & Print for this template
     * - labels_per_sheet: how many labels fit on one letter-size sheet
     * - bundle_filename: .avery bundle file (legacy)
     * - price_per_label: legacy pricing (kept for existing order records)
     */
    'sizes' => [
        'round_1in' => [
            'name' => '1" Square QR Stickers',
            'template_number' => '94103',
            'avery_template_url' => 'https://www.avery.com/templates/94103',
            'labels_per_sheet' => 48,
            'bundle_filename' => 'square-1in.avery',
            'price_per_label' => (float) env('STICKER_KITS_PRICE_PER_LABEL_1IN', 0.25),
        ],
        'round_1_5in' => [
            'name' => '1.5" Square QR Stickers',
            'template_number' => '22805',
            'avery_template_url' => 'https://www.avery.com/templates/22805',
            'labels_per_sheet' => 24,
            'bundle_filename' => 'square-1_5in.avery',
            'price_per_label' => (float) env('STICKER_KITS_PRICE_PER_LABEL_1_5IN', 0.35),
        ],
        'round_2in' => [
            'name' => '2" Square QR Stickers',
            'template_number' => '22806',
            'avery_template_url' => 'https://www.avery.com/templates/22806',
            'labels_per_sheet' => 12,
            'bundle_filename' => 'square-2in.avery',
            'price_per_label' => (float) env('STICKER_KITS_PRICE_PER_LABEL_2IN', 0.45),
        ],
        'round_4in' => [
            'name' => '4" Square QR Stickers',
            'template_number' => '60504',
            'avery_template_url' => 'https://www.avery.com/templates/60504',
            'labels_per_sheet' => 4,
            'bundle_filename' => 'square-4in.avery',
            'price_per_label' => (float) env('STICKER_KITS_PRICE_PER_LABEL_4IN', 1.25),
        ],
    ],
];

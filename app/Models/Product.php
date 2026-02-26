<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category',
        'base_price',
        'cost_price',
        'suggested_retail',
        'printful_product_id',
        'variants',
        'print_areas',
        'print_config',
        'preview_template',
        'preview_config',
        'images',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'suggested_retail' => 'decimal:2',
        'variants' => 'array',
        'print_areas' => 'array',
        'print_config' => 'array',
        'preview_config' => 'array',
        'images' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the print configuration with defaults
     */
    public function getPrintConfigWithDefaults(): array
    {
        $defaults = $this->getDefaultPrintConfig();
        return array_merge($defaults, $this->print_config ?? []);
    }

    /**
     * Get default print config based on category
     */
    protected function getDefaultPrintConfig(): array
    {
        $configs = [
            't-shirt' => [
                'logo' => [
                    'placement' => 'front',
                    'position' => 'left',
                    'size' => 'medium',
                    'width' => 600,
                    'height' => 600,
                    'top' => 400,
                    'left' => 300,
                ],
                'qr_code' => [
                    // Use explicit sleeve placement names (matches Printful + our preview views)
                    'placement' => 'sleeve_left',
                    'position' => 'center',
                    'size' => 'small',
                    'width' => 250,
                    'height' => 250,
                    'top' => 350,
                    'left' => 100,
                ],
                'qr_code_2' => [
                    'placement' => 'sleeve_right',
                    'position' => 'center',
                    'size' => 'small',
                    'width' => 250,
                    'height' => 250,
                    'top' => 350,
                    'left' => 450,
                ],
            ],
            'hoodie' => [
                'logo' => [
                    'placement' => 'front',
                    'position' => 'center',
                    'size' => 'medium',
                    'width' => 600,
                    'height' => 600,
                    'top' => 500,
                    'left' => 400,
                ],
                'qr_code' => [
                    'placement' => 'sleeve_left',
                    'position' => 'center',
                    'size' => 'small',
                    'width' => 250,
                    'height' => 250,
                    'top' => 450,
                    'left' => 120,
                ],
                'qr_code_2' => [
                    'placement' => 'sleeve_right',
                    'position' => 'center',
                    'size' => 'small',
                    'width' => 250,
                    'height' => 250,
                    'top' => 450,
                    'left' => 530,
                ],
            ],
            'hat' => [
                'logo' => [
                    'placement' => 'front',
                    'position' => 'center',
                    'size' => 'small',
                    'width' => 400,
                    'height' => 400,
                    'top' => 200,
                    'left' => 300,
                ],
                'qr_code' => [
                    'placement' => 'left',
                    'position' => 'center',
                    'size' => 'small',
                    'width' => 200,
                    'height' => 200,
                    'top' => 300,
                    'left' => 150,
                ],
            ],
            'mug' => [
                'logo' => [
                    'placement' => 'back',
                    'position' => 'center',
                    'size' => 'small',
                    'width' => 300,
                    'height' => 300,
                    'top' => 150,
                    'left' => 250,
                ],
                'qr_code' => [
                    'placement' => 'front',
                    'position' => 'center',
                    'size' => 'small',
                    'width' => 250,
                    'height' => 250,
                    'top' => 180,
                    'left' => 160,
                ],
            ],
            'bag' => [
                'logo' => [
                    'placement' => 'back',
                    'position' => 'center',
                    'size' => 'medium',
                    'width' => 400,
                    'height' => 400,
                    'top' => 150,
                    'left' => 250,
                ],
                'qr_code' => [
                    'placement' => 'front',
                    'position' => 'center',
                    'size' => 'small',
                    'width' => 300,
                    'height' => 300,
                    'top' => 200,
                    'left' => 150,
                ],
            ],
        ];

        return $configs[$this->category] ?? [
            'logo' => [
                'placement' => 'front',
                'position' => 'center',
                'size' => 'medium',
                'width' => 600,
                'height' => 600,
                'top' => 600,
                'left' => 600,
            ],
            'qr_code' => [
                'placement' => 'front',
                'position' => 'bottom_right',
                'size' => 'small',
                'width' => 300,
                'height' => 300,
                'top' => 1400,
                'left' => 1400,
            ],
        ];
    }

    /**
     * Get preview template URL
     */
    public function getPreviewTemplateUrl(): ?string
    {
        if ($this->preview_template) {
            return '/storage/products/templates/' . $this->preview_template;
        }

        // Fallback to default template based on category
        $defaultTemplates = [
            't-shirt' => 't-shirt-blank.png',
            'hoodie' => 'hoodie-blank.png',
            'mug' => 'mug-blank.png',
            'poster' => 'poster-blank.png',
            'bag' => 'bag-blank.png',
            'sticker' => 'default-blank.png',
            'hat' => 'hat-blank.png',
        ];

        $template = $defaultTemplates[$this->category] ?? 'default-blank.png';
        return '/storage/products/templates/' . $template;
    }

    /**
     * Get preview configuration with defaults
     */
    public function getPreviewConfigWithDefaults(): array
    {
        $defaults = $this->getDefaultPreviewConfig();
        return array_merge($defaults, $this->preview_config ?? []);
    }

    /**
     * Get default preview config based on category
     */
    protected function getDefaultPreviewConfig(): array
    {
        $configs = [
            't-shirt' => [
                'logo' => [
                    'x' => 300, 'y' => 250,  // Left chest area
                    'width' => 150, 'height' => 150,
                    'rotation' => 0
                ],
                'qr_code' => [
                    'x' => 100, 'y' => 350,  // Left sleeve
                    'width' => 100, 'height' => 100,
                    'rotation' => 0
                ],
                'qr_code_2' => [  // Optional second QR on right sleeve
                    'x' => 600, 'y' => 350,
                    'width' => 100, 'height' => 100,
                    'rotation' => 0
                ],
                'canvas' => [
                    'width' => 800, 'height' => 1000
                ],
                // Multi-view configs (front/back/sleeves)
                'views' => [
                    'front' => [
                        'canvas' => ['width' => 800, 'height' => 1000],
                        'logo' => ['x' => null, 'y' => 200, 'width' => 550, 'height' => 550, 'rotation' => 0], // CENTERED horizontally
                        'qr_code' => ['x' => null, 'y' => null, 'width' => 0, 'height' => 0, 'rotation' => 0], // NO QR on front
                    ],
                    'back' => [
                        'canvas' => ['width' => 800, 'height' => 1000],
                        'logo' => ['x' => null, 'y' => 200, 'width' => 550, 'height' => 550, 'rotation' => 0], // CENTERED horizontally
                        'qr_code' => ['x' => null, 'y' => null, 'width' => 0, 'height' => 0, 'rotation' => 0], // NO QR on back
                    ],
                    'sleeve_left' => [
                        'canvas' => ['width' => 800, 'height' => 1000],
                        'logo' => ['x' => null, 'y' => null, 'width' => 0, 'height' => 0, 'rotation' => 0], // NO LOGO on sleeves
                        'qr_code' => ['x' => null, 'y' => 350, 'width' => 400, 'height' => 400, 'rotation' => 0], // CENTERED QR on left sleeve
                    ],
                    'sleeve_right' => [
                        'canvas' => ['width' => 800, 'height' => 1000],
                        'logo' => ['x' => null, 'y' => null, 'width' => 0, 'height' => 0, 'rotation' => 0], // NO LOGO on sleeves
                        'qr_code' => ['x' => null, 'y' => 350, 'width' => 400, 'height' => 400, 'rotation' => 0], // CENTERED QR on right sleeve
                    ],
                ],
            ],
            'hoodie' => [
                'logo' => [
                    'x' => 350, 'y' => 300,  // Center chest
                    'width' => 150, 'height' => 150,
                    'rotation' => 0
                ],
                'qr_code' => [
                    'x' => 120, 'y' => 450,  // Left sleeve
                    'width' => 100, 'height' => 100,
                    'rotation' => 0
                ],
                'qr_code_2' => [  // Optional second QR on right sleeve
                    'x' => 580, 'y' => 450,
                    'width' => 100, 'height' => 100,
                    'rotation' => 0
                ],
                'canvas' => [
                    'width' => 800, 'height' => 1000
                ],
                'views' => [
                    'front' => [
                        'canvas' => ['width' => 800, 'height' => 1000],
                        'logo' => ['x' => null, 'y' => null, 'width' => 550, 'height' => 550, 'rotation' => 0], // EVEN BIGGER, auto-centered
                        'qr_code' => ['x' => 50, 'y' => 450, 'width' => 320, 'height' => 320, 'rotation' => 0], // EVEN BIGGER
                    ],
                    'back' => [
                        'canvas' => ['width' => 800, 'height' => 1000],
                        'logo' => ['x' => null, 'y' => null, 'width' => 550, 'height' => 550, 'rotation' => 0], // EVEN BIGGER, auto-centered
                    ],
                    'sleeve_left' => [
                        'canvas' => ['width' => 800, 'height' => 1000],
                        'qr_code' => ['x' => null, 'y' => 350, 'width' => 400, 'height' => 400, 'rotation' => 0], // MUCH BIGGER, auto-centered
                    ],
                    'sleeve_right' => [
                        'canvas' => ['width' => 800, 'height' => 1000],
                        'qr_code' => ['x' => null, 'y' => 350, 'width' => 400, 'height' => 400, 'rotation' => 0], // MUCH BIGGER, auto-centered
                    ],
                ],
            ],
            'hat' => [
                'logo' => [
                    'x' => 300, 'y' => 200,  // Front center
                    'width' => 120, 'height' => 120,
                    'rotation' => 0
                ],
                'qr_code' => [
                    'x' => 150, 'y' => 300,  // Left side
                    'width' => 80, 'height' => 80,
                    'rotation' => 0
                ],
                'canvas' => [
                    'width' => 600, 'height' => 600
                ]
            ],
            'mug' => [
                'logo' => [
                    'x' => 250, 'y' => 150,  // Back side
                    'width' => 80, 'height' => 80,
                    'rotation' => 0
                ],
                'qr_code' => [
                    'x' => 160, 'y' => 180,  // Front side
                    'width' => 80, 'height' => 80,
                    'rotation' => 0
                ],
                'canvas' => [
                    'width' => 400, 'height' => 400
                ],
                // Wrap view (single wide canvas for mug wrap mockups)
                'views' => [
                    'wrap' => [
                        'canvas' => ['width' => 2700, 'height' => 1100], // Mug wrap dimensions (Printful standard)
                        'logo' => ['x' => 1200, 'y' => 300, 'width' => 300, 'height' => 300, 'rotation' => 0], // Centered, bigger
                        'qr_code' => ['x' => 1220, 'y' => 650, 'width' => 260, 'height' => 260, 'rotation' => 0], // Centered below logo
                    ],
                ],
            ],
            'bag' => [
                'logo' => [
                    'x' => 250, 'y' => 150,  // Back side
                    'width' => 120, 'height' => 120,
                    'rotation' => 0
                ],
                'qr_code' => [
                    'x' => 150, 'y' => 200,  // Front side
                    'width' => 100, 'height' => 100,
                    'rotation' => 0
                ],
                'canvas' => [
                    'width' => 400, 'height' => 500
                ]
            ],
            'poster' => [
                'logo' => [
                    'x' => 250, 'y' => 150,  // Top center
                    'width' => 150, 'height' => 150,
                    'rotation' => 0
                ],
                'qr_code' => [
                    'x' => 100, 'y' => 400,  // Bottom left
                    'width' => 100, 'height' => 100,
                    'rotation' => 0
                ],
                'canvas' => [
                    'width' => 600, 'height' => 800
                ]
            ],
            'sticker' => [
                'logo' => [
                    'x' => 150, 'y' => 100,  // Top area
                    'width' => 100, 'height' => 100,
                    'rotation' => 0
                ],
                'qr_code' => [
                    'x' => 250, 'y' => 200,  // Bottom area
                    'width' => 80, 'height' => 80,
                    'rotation' => 0
                ],
                'canvas' => [
                    'width' => 400, 'height' => 400
                ]
            ],
        ];

        return $configs[$this->category] ?? [
            'logo' => [
                'x' => 100, 'y' => 100,
                'width' => 100, 'height' => 100,
                'rotation' => 0
            ],
            'qr_code' => [
                'x' => 200, 'y' => 200,
                'width' => 80, 'height' => 80,
                'rotation' => 0
            ],
            'canvas' => [
                'width' => 400, 'height' => 400
            ]
        ];
    }
}


<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class ProductTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Setting up product preview templates...');

        // Create template images directory if it doesn't exist
        Storage::disk('public')->makeDirectory('products/templates');

        // Create basic template images (simple colored rectangles for now)
        $this->createTemplateImage('t-shirt-blank.png', 800, 1000, '#f0f0f0');
        $this->createTemplateImage('hoodie-blank.png', 800, 1000, '#e0e0e0');
        $this->createTemplateImage('mug-blank.png', 400, 400, '#ffffff');
        $this->createTemplateImage('poster-blank.png', 600, 800, '#ffffff');
        $this->createTemplateImage('default-blank.png', 400, 400, '#f5f5f5');

        // Update existing products with preview configurations
        $products = Product::all();

        foreach ($products as $product) {
            $config = $this->getDefaultConfigForCategory($product->category);
            $product->update([
                'preview_config' => $config,
                'preview_template' => $this->getTemplateForCategory($product->category),
            ]);
        }

        $this->command->info('Product preview templates configured successfully.');
    }

    private function createTemplateImage($filename, $width, $height, $color)
    {
        // Create a simple colored image using GD
        $image = imagecreatetruecolor($width, $height);
        $rgb = $this->hexToRgb($color);
        $fillColor = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
        imagefill($image, 0, 0, $fillColor);

        // Add a simple border
        $borderColor = imagecolorallocate($image, 200, 200, 200);
        imagerectangle($image, 0, 0, $width-1, $height-1, $borderColor);

        // Save the image
        $path = storage_path('app/public/products/templates/' . $filename);
        imagepng($image, $path);
        imagedestroy($image);
    }

    private function hexToRgb($hex)
    {
        $hex = ltrim($hex, '#');
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    private function getDefaultConfigForCategory($category)
    {
        $configs = [
            't-shirt' => [
                'logo' => [
                    'x' => 300, 'y' => 200,
                    'width' => 200, 'height' => 200,
                    'rotation' => 0
                ],
                'qr_code' => [
                    'x' => 100, 'y' => 400,
                    'width' => 120, 'height' => 120,
                    'rotation' => 0
                ],
                'canvas' => [
                    'width' => 800, 'height' => 1000
                ]
            ],
            'hoodie' => [
                'logo' => [
                    'x' => 350, 'y' => 250,
                    'width' => 200, 'height' => 200,
                    'rotation' => 0
                ],
                'qr_code' => [
                    'x' => 150, 'y' => 450,
                    'width' => 120, 'height' => 120,
                    'rotation' => 0
                ],
                'canvas' => [
                    'width' => 800, 'height' => 1000
                ]
            ],
            'mug' => [
                'logo' => [
                    'x' => 150, 'y' => 100,
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
            ],
            'poster' => [
                'logo' => [
                    'x' => 200, 'y' => 100,
                    'width' => 150, 'height' => 150,
                    'rotation' => 0
                ],
                'qr_code' => [
                    'x' => 50, 'y' => 300,
                    'width' => 100, 'height' => 100,
                    'rotation' => 0
                ],
                'canvas' => [
                    'width' => 600, 'height' => 800
                ]
            ],
        ];

        return $configs[$category] ?? $configs['t-shirt'];
    }

    private function getTemplateForCategory($category)
    {
        $templates = [
            't-shirt' => 't-shirt-blank.png',
            'hoodie' => 'hoodie-blank.png',
            'mug' => 'mug-blank.png',
            'poster' => 'poster-blank.png',
        ];

        return $templates[$category] ?? 'default-blank.png';
    }
}

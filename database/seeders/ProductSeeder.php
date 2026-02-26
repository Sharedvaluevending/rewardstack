<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'Premium QR T-Shirt',
                'slug' => 'premium-qr-t-shirt',
                'description' => 'Soft, comfortable unisex t-shirt perfect for displaying your custom QR code. Made from 100% ring-spun cotton for a smooth feel.',
                'category' => 't-shirt',
                'base_price' => 24.99,
                'printful_product_id' => '71',
                'variants' => [
                    ['name' => 'S', 'variant_ids' => [4011], 'price_modifier' => 0],
                    ['name' => 'M', 'variant_ids' => [4012], 'price_modifier' => 0],
                    ['name' => 'L', 'variant_ids' => [4013], 'price_modifier' => 0],
                    ['name' => 'XL', 'variant_ids' => [4014], 'price_modifier' => 0],
                    ['name' => '2XL', 'variant_ids' => [4015], 'price_modifier' => 2.00],
                    ['name' => '3XL', 'variant_ids' => [4016], 'price_modifier' => 3.00],
                ],
                'print_areas' => [
                    ['name' => 'front', 'width' => 4500, 'height' => 5100, 'default' => true],
                    ['name' => 'back', 'width' => 4500, 'height' => 5100, 'default' => false],
                ],
                'images' => ['https://files.cdn.printful.com/products/71/4012_1517927381.jpg'],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Classic QR Hoodie',
                'slug' => 'classic-qr-hoodie',
                'description' => 'Cozy unisex hoodie with your QR code. Features a front pouch pocket and double-lined hood.',
                'category' => 'hoodie',
                'base_price' => 44.99,
                'printful_product_id' => '380',
                'variants' => [
                    ['name' => 'S', 'variant_ids' => [9425], 'price_modifier' => 0],
                    ['name' => 'M', 'variant_ids' => [9426], 'price_modifier' => 0],
                    ['name' => 'L', 'variant_ids' => [9427], 'price_modifier' => 0],
                    ['name' => 'XL', 'variant_ids' => [9428], 'price_modifier' => 0],
                    ['name' => '2XL', 'variant_ids' => [9429], 'price_modifier' => 3.00],
                ],
                'print_areas' => [
                    ['name' => 'front', 'width' => 4500, 'height' => 4500, 'default' => true],
                    ['name' => 'back', 'width' => 4500, 'height' => 4500, 'default' => false],
                ],
                'images' => ['https://files.cdn.printful.com/products/380/9426_1568798316.jpg'],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'QR Code Mug',
                'slug' => 'qr-code-mug',
                'description' => 'White ceramic mug with your QR code. 11oz capacity, dishwasher and microwave safe.',
                'category' => 'mug',
                'base_price' => 14.99,
                'printful_product_id' => '19',
                'variants' => [
                    ['name' => '11oz', 'variant_ids' => [1320], 'price_modifier' => 0],
                    ['name' => '15oz', 'variant_ids' => [4830], 'price_modifier' => 3.00],
                ],
                'print_areas' => [
                    ['name' => 'default', 'width' => 2475, 'height' => 1000, 'default' => true],
                ],
                'images' => ['https://files.cdn.printful.com/products/19/1320_1581417706.jpg'],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'QR Code Poster',
                'slug' => 'qr-code-poster',
                'description' => 'Museum-quality poster with vibrant colors on thick, durable matte paper. Perfect for walls, storefronts, or events.',
                'category' => 'poster',
                'base_price' => 19.99,
                'printful_product_id' => '1',
                'variants' => [
                    ['name' => '12×16"', 'variant_ids' => [1], 'price_modifier' => 0],
                    ['name' => '18×24"', 'variant_ids' => [2], 'price_modifier' => 5.00],
                    ['name' => '24×36"', 'variant_ids' => [3], 'price_modifier' => 10.00],
                ],
                'print_areas' => [
                    ['name' => 'default', 'width' => 7200, 'height' => 10800, 'default' => true],
                ],
                'images' => ['https://files.cdn.printful.com/products/1/product_1551099920.jpg'],
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'QR Code Stickers',
                'slug' => 'qr-code-stickers',
                'description' => 'Die-cut stickers with your QR code. Waterproof, UV resistant, and perfect for laptops, water bottles, or giveaways.',
                'category' => 'sticker',
                'base_price' => 4.99,
                'printful_product_id' => '534',
                'variants' => [
                    ['name' => '2×2"', 'variant_ids' => [10163], 'price_modifier' => 0],
                    ['name' => '3×3"', 'variant_ids' => [10165], 'price_modifier' => 1.50],
                    ['name' => '4×4"', 'variant_ids' => [10167], 'price_modifier' => 2.50],
                    ['name' => '5.5×5.5"', 'variant_ids' => [10169], 'price_modifier' => 4.00],
                ],
                'print_areas' => [
                    ['name' => 'default', 'width' => 1800, 'height' => 1800, 'default' => true],
                ],
                'images' => ['https://files.cdn.printful.com/products/534/10163_1612260710.jpg'],
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'QR Canvas Print',
                'slug' => 'qr-canvas-print',
                'description' => 'Premium canvas print with your QR code. Stretched on a wooden frame, ready to hang.',
                'category' => 'poster',
                'base_price' => 34.99,
                'printful_product_id' => '505',
                'variants' => [
                    ['name' => '12×12"', 'variant_ids' => [9933], 'price_modifier' => 0],
                    ['name' => '16×16"', 'variant_ids' => [9935], 'price_modifier' => 10.00],
                    ['name' => '18×24"', 'variant_ids' => [9943], 'price_modifier' => 15.00],
                ],
                'print_areas' => [
                    ['name' => 'default', 'width' => 3600, 'height' => 3600, 'default' => true],
                ],
                'images' => ['https://files.cdn.printful.com/products/505/9933_1584694869.jpg'],
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'QR Tote Bag',
                'slug' => 'qr-tote-bag',
                'description' => 'Spacious and durable tote bag featuring your QR code. Made from 100% cotton, perfect for shopping or everyday use.',
                'category' => 'bag',
                'base_price' => 16.99,
                'printful_product_id' => '181',
                'variants' => [
                    ['name' => 'Standard', 'variant_ids' => [6505], 'price_modifier' => 0],
                ],
                'print_areas' => [
                    ['name' => 'front', 'width' => 3300, 'height' => 3300, 'default' => true],
                    ['name' => 'back', 'width' => 3300, 'height' => 3300, 'default' => false],
                ],
                'images' => ['https://files.cdn.printful.com/products/181/6505_1568797094.jpg'],
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Business Card Pack',
                'slug' => 'business-card-pack',
                'description' => 'Pack of 100 premium business cards with your QR code. 14pt cardstock with matte finish.',
                'category' => 'business_card',
                'base_price' => 29.99,
                'printful_product_id' => null, // Custom product, not from Printful
                'variants' => [
                    ['name' => '100 cards', 'variant_ids' => [], 'price_modifier' => 0],
                    ['name' => '250 cards', 'variant_ids' => [], 'price_modifier' => 25.00],
                    ['name' => '500 cards', 'variant_ids' => [], 'price_modifier' => 45.00],
                ],
                'print_areas' => [
                    ['name' => 'front', 'width' => 1050, 'height' => 600, 'default' => true],
                    ['name' => 'back', 'width' => 1050, 'height' => 600, 'default' => false],
                ],
                'images' => null,
                'is_active' => true,
                'sort_order' => 8,
            ],
        ];

        foreach ($products as $productData) {
            Product::updateOrCreate(
                ['slug' => $productData['slug']],
                $productData
            );
        }

        $this->command->info('Created ' . count($products) . ' merch products.');
    }
}

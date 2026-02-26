<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'print_config')) {
                $table->json('print_config')->nullable()->after('print_areas');
            }
        });

        // Set default print configs for existing products
        $this->setDefaultConfigs();
    }

    protected function setDefaultConfigs(): void
    {
        $configs = [
            't-shirt' => [
                'logo' => [
                    'placement' => 'front',
                    'position' => 'center',
                    'size' => 'medium',
                    'offset_x' => 0,
                    'offset_y' => -100, // Slightly above center
                ],
                'qr_code' => [
                    'placement' => 'sleeve_left',
                    'position' => 'center',
                    'size' => 'small',
                    'offset_x' => 0,
                    'offset_y' => 0,
                ],
            ],
            'hoodie' => [
                'logo' => [
                    'placement' => 'front',
                    'position' => 'center',
                    'size' => 'medium',
                    'offset_x' => 0,
                    'offset_y' => -100,
                ],
                'qr_code' => [
                    'placement' => 'sleeve_left',
                    'position' => 'center',
                    'size' => 'small',
                    'offset_x' => 0,
                    'offset_y' => 0,
                ],
            ],
            'mug' => [
                'logo' => [
                    'placement' => 'front',
                    'position' => 'center',
                    'size' => 'medium',
                    'offset_x' => -200, // Left side of wrap
                    'offset_y' => 0,
                ],
                'qr_code' => [
                    'placement' => 'front', // Mugs have one wrap area
                    'position' => 'center',
                    'size' => 'small',
                    'offset_x' => 200, // Right side of wrap (back when held)
                    'offset_y' => 0,
                ],
            ],
            'poster' => [
                'logo' => [
                    'placement' => 'front',
                    'position' => 'top',
                    'size' => 'large',
                    'offset_x' => 0,
                    'offset_y' => 0,
                ],
                'qr_code' => [
                    'placement' => 'front',
                    'position' => 'bottom_right',
                    'size' => 'small',
                    'offset_x' => 0,
                    'offset_y' => 0,
                ],
            ],
            'sticker' => [
                'logo' => [
                    'placement' => 'front',
                    'position' => 'center',
                    'size' => 'large',
                    'offset_x' => 0,
                    'offset_y' => -50,
                ],
                'qr_code' => [
                    'placement' => 'front',
                    'position' => 'bottom',
                    'size' => 'small',
                    'offset_x' => 0,
                    'offset_y' => 100,
                ],
            ],
            'bag' => [
                'logo' => [
                    'placement' => 'front',
                    'position' => 'center',
                    'size' => 'medium',
                    'offset_x' => 0,
                    'offset_y' => -50,
                ],
                'qr_code' => [
                    'placement' => 'front',
                    'position' => 'bottom',
                    'size' => 'small',
                    'offset_x' => 0,
                    'offset_y' => 100,
                ],
            ],
            'hat' => [
                'logo' => [
                    'placement' => 'front',
                    'position' => 'center',
                    'size' => 'small',
                    'offset_x' => 0,
                    'offset_y' => 0,
                ],
                'qr_code' => [
                    'placement' => 'back', // Back of hat or side
                    'position' => 'center',
                    'size' => 'tiny',
                    'offset_x' => 0,
                    'offset_y' => 0,
                ],
            ],
        ];

        foreach ($configs as $category => $config) {
            \DB::table('products')
                ->where('category', $category)
                ->whereNull('print_config')
                ->update(['print_config' => json_encode($config)]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('print_config');
        });
    }
};

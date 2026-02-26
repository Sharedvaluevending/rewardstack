<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;

$products = Product::all(['id', 'name', 'category', 'is_active']);

echo "Current products in database:\n";
echo "================================\n";

foreach ($products as $product) {
    echo sprintf("%d: %s (%s) - %s\n",
        $product->id,
        $product->name,
        $product->category,
        $product->is_active ? 'Active' : 'Inactive'
    );
}
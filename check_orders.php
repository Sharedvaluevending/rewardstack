<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\OrderItem;

// Product IDs to check
$productIds = [4, 5, 6, 7, 8];

echo "Checking for orders on products to be deleted:\n";
echo "===============================================\n";

foreach ($productIds as $productId) {
    $product = Product::find($productId);
    if (!$product) {
        echo "Product ID {$productId} not found\n";
        continue;
    }

    $orderCount = OrderItem::where('product_id', $productId)->count();

    echo sprintf("Product: %s (ID: %d) - %d orders\n",
        $product->name,
        $product->id,
        $orderCount
    );

    if ($orderCount > 0) {
        echo "  ⚠️  WARNING: This product has existing orders and cannot be deleted!\n";
    }
}

echo "\nDone.\n";
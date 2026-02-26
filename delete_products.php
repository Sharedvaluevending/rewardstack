<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\OrderItem;

// Product IDs to delete
$productIds = [4, 5, 6, 7, 8];
$productNames = [
    4 => 'Enhanced Matte Paper Poster (in)',
    5 => 'Jigsaw Puzzle',
    6 => 'Kiss-Cut Sticker Sheet',
    7 => 'Clear Case for iPhone®',
    8 => 'Business Card Pack'
];

echo "Deleting merchandise products:\n";
echo "===============================\n";

foreach ($productIds as $productId) {
    $product = Product::find($productId);
    if (!$product) {
        echo "❌ Product ID {$productId} not found\n";
        continue;
    }

    // Double-check for orders (safety measure)
    $orderCount = OrderItem::where('product_id', $productId)->count();
    if ($orderCount > 0) {
        echo "❌ Cannot delete '{$product->name}' - has {$orderCount} orders\n";
        continue;
    }

    try {
        $product->delete();
        echo "✅ Deleted: {$product->name} (ID: {$productId})\n";
    } catch (\Exception $e) {
        echo "❌ Failed to delete '{$product->name}': {$e->getMessage()}\n";
    }
}

echo "\nVerifying deletions:\n";
echo "===================\n";

$remainingProducts = Product::whereIn('id', $productIds)->count();
if ($remainingProducts === 0) {
    echo "✅ All target products successfully deleted!\n";
} else {
    echo "⚠️  {$remainingProducts} products still remain in database\n";
}

echo "\nDone.\n";
<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->boot();

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Size;

echo "Fixing order item sizes...\n";

// Get all order items that don't have a size_id
$orderItems = OrderItem::whereNull('size_id')->get();

echo "Found " . $orderItems->count() . " order items without size_id\n";

foreach ($orderItems as $orderItem) {
    // Try to get size from the associated product
    if ($orderItem->product_id && $orderItem->product) {
        $product = $orderItem->product;
        if ($product->size_id) {
            $orderItem->update(['size_id' => $product->size_id]);
            echo "Updated order item {$orderItem->id} with size from product {$product->id}\n";
            continue;
        }
    }
    
    // If no product or product has no size, assign a random size
    $randomSize = Size::inRandomOrder()->first();
    if ($randomSize) {
        $orderItem->update(['size_id' => $randomSize->id]);
        echo "Updated order item {$orderItem->id} with random size {$randomSize->name}\n";
    }
}

echo "Finished updating order item sizes!\n";

// Show summary
$totalItems = OrderItem::count();
$itemsWithSize = OrderItem::whereNotNull('size_id')->count();
echo "\nSummary:\n";
echo "Total order items: {$totalItems}\n";
echo "Items with size: {$itemsWithSize}\n";
echo "Items without size: " . ($totalItems - $itemsWithSize) . "\n";

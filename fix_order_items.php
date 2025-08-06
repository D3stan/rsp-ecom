<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== FIXING ORDER ITEMS FOR TAX-INCLUSIVE PRICING ===\n\n";

// Get orders with order items
$orders = \App\Models\Order::with(['orderItems'])->get();

echo "Found " . $orders->count() . " orders to check\n\n";

foreach ($orders as $order) {
    if ($order->orderItems->isEmpty()) {
        continue;
    }
    
    // Calculate what the tax-inclusive subtotal should be
    $correctTaxInclusiveSubtotal = $order->subtotal + $order->tax_amount;
    
    // Get current item totals
    $currentItemsTotal = $order->orderItems->sum(function($item) {
        return $item->price * $item->quantity;
    });
    
    // Check if there's a mismatch
    $difference = abs($currentItemsTotal - $correctTaxInclusiveSubtotal);
    
    if ($difference > 0.01) { // More than 1 cent difference
        echo "Order ID {$order->id}: Mismatch detected\n";
        echo "  Items total: €" . number_format($currentItemsTotal, 2) . "\n";
        echo "  Should be: €" . number_format($correctTaxInclusiveSubtotal, 2) . "\n";
        echo "  Difference: €" . number_format($difference, 2) . "\n";
        
        // Calculate the ratio to adjust item prices
        if ($currentItemsTotal > 0) {
            $adjustmentRatio = $correctTaxInclusiveSubtotal / $currentItemsTotal;
            
            foreach ($order->orderItems as $item) {
                $newPrice = round($item->price * $adjustmentRatio, 2);
                $newTotal = $newPrice * $item->quantity;
                
                echo "    Item '{$item->product_name}': €{$item->price} → €{$newPrice}\n";
                
                $item->update([
                    'price' => $newPrice,
                    'total' => $newTotal
                ]);
            }
        }
        echo "  Fixed!\n\n";
    }
}

echo "Order items alignment complete!\n";

// Test Order 105 again
echo "\n=== ORDER 105 VERIFICATION ===\n";
$order105 = \App\Models\Order::with('orderItems')->find(105);
if ($order105) {
    echo "Order 105 after OrderItems fix:\n";
    $itemsTotal = 0;
    foreach ($order105->orderItems as $item) {
        $itemTotal = $item->price * $item->quantity;
        $itemsTotal += $itemTotal;
        echo "  {$item->product_name}: €{$item->price} × {$item->quantity} = €{$itemTotal}\n";
    }
    echo "Items total: €" . number_format($itemsTotal, 2) . "\n";
    echo "Order subtotal (net): €" . number_format($order105->subtotal, 2) . "\n";
    echo "Order tax: €" . number_format($order105->tax_amount, 2) . "\n";
    echo "Tax-inclusive subtotal: €" . number_format($order105->subtotal + $order105->tax_amount, 2) . "\n";
    echo "Shipping: €" . number_format($order105->shipping_amount, 2) . "\n";
    echo "Total: €" . number_format($order105->total_amount, 2) . "\n";
    
    $itemsMatchTaxInclusive = abs($itemsTotal - ($order105->subtotal + $order105->tax_amount)) < 0.01;
    echo "Items match tax-inclusive subtotal: " . ($itemsMatchTaxInclusive ? "✅ YES" : "❌ NO") . "\n";
}

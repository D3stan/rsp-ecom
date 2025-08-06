<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->boot();

use App\Models\Order;

// Get the latest order
$order = Order::latest()->first();

if ($order) {
    echo "Testing Order ID: {$order->id}\n";
    echo "Order Number: {$order->order_number}\n";
    
    // Test hasEmailBeenSent method
    try {
        $emailSent = $order->hasEmailBeenSent();
        echo "Email sent status: " . ($emailSent ? 'true' : 'false') . " (type: " . gettype($emailSent) . ")\n";
    } catch (Exception $e) {
        echo "Error with hasEmailBeenSent(): " . $e->getMessage() . "\n";
    }
    
    // Test toFrontendArray method
    try {
        $frontendData = $order->toFrontendArray();
        echo "\nFrontend Data Types:\n";
        echo "- subtotal: {$frontendData['subtotal']} (type: " . gettype($frontendData['subtotal']) . ")\n";
        echo "- tax_amount: {$frontendData['tax_amount']} (type: " . gettype($frontendData['tax_amount']) . ")\n";
        echo "- shipping_amount: {$frontendData['shipping_amount']} (type: " . gettype($frontendData['shipping_amount']) . ")\n";
        echo "- total_amount: {$frontendData['total_amount']} (type: " . gettype($frontendData['total_amount']) . ")\n";
        
        // Test JavaScript .toFixed() would work
        echo "\nTesting .toFixed() compatibility:\n";
        echo "- subtotal.toFixed(2) would work: " . (is_numeric($frontendData['subtotal']) ? 'YES' : 'NO') . "\n";
        echo "- total_amount.toFixed(2) would work: " . (is_numeric($frontendData['total_amount']) ? 'YES' : 'NO') . "\n";
        
    } catch (Exception $e) {
        echo "Error with toFrontendArray(): " . $e->getMessage() . "\n";
    }
    
} else {
    echo "No orders found in database.\n";
}

<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$order = \App\Models\Order::find(105);

if ($order) {
    echo "Order 105 original data:\n";
    echo "subtotal: " . json_encode($order->subtotal) . " (type: " . gettype($order->subtotal) . ")\n";
    echo "total_amount: " . json_encode($order->total_amount) . " (type: " . gettype($order->total_amount) . ")\n";
    
    echo "\nOrder 105 frontend data:\n";
    $frontendData = $order->toFrontendArray();
    echo "subtotal: " . json_encode($frontendData['subtotal']) . " (type: " . gettype($frontendData['subtotal']) . ")\n";
    echo "total_amount: " . json_encode($frontendData['total_amount']) . " (type: " . gettype($frontendData['total_amount']) . ")\n";
} else {
    echo "Order not found\n";
}

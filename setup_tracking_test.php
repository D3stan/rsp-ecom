<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->boot();

use App\Models\Order;
use App\Models\User;

echo "Setting up test tracking data...\n";

// Find the test user
$testUser = User::where('email', 'test@example.com')->first();

if (!$testUser) {
    echo "Test user not found!\n";
    exit(1);
}

// Get first order for test user and update it to shipped with tracking
$order = Order::where('user_id', $testUser->id)->first();

if ($order) {
    $order->update([
        'status' => 'shipped',
        'tracking_number' => 'TRK-' . strtoupper(bin2hex(random_bytes(6)))
    ]);
    
    echo "Updated Order #{$order->id} to shipped status\n";
    echo "Tracking Number: {$order->tracking_number}\n";
} else {
    echo "No orders found for test user!\n";
}

// Update another order to delivered for review testing
$secondOrder = Order::where('user_id', $testUser->id)->skip(1)->first();

if ($secondOrder) {
    $secondOrder->update([
        'status' => 'delivered'
    ]);
    
    echo "Updated Order #{$secondOrder->id} to delivered status for review testing\n";
} else {
    echo "No second order found for review testing\n";
}

echo "Test data setup complete!\n";

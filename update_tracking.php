<?php

require_once 'vendor/autoload.php';

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Order;

// Update one of the shipped orders to have a tracking number
$shippedOrder = Order::where('status', 'shipped')->first();

if ($shippedOrder) {
    $shippedOrder->update([
        'tracking_number' => 'TRK-' . strtoupper(uniqid())
    ]);
    echo "Updated order #{$shippedOrder->id} with tracking number: {$shippedOrder->tracking_number}\n";
} else {
    // Create a shipped order with tracking
    $user = \App\Models\User::where('email', 'test@example.com')->first();
    $address = \App\Models\Address::where('user_id', $user->id)->first();
    $products = \App\Models\Product::inRandomOrder()->limit(1)->get();
    
    $order = Order::create([
        'order_number' => 'ORD-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8)),
        'user_id' => $user->id,
        'billing_address_id' => $address->id,
        'shipping_address_id' => $address->id,
        'status' => 'shipped',
        'subtotal' => 100,
        'tax_amount' => 10,
        'shipping_amount' => 5.99,
        'total_amount' => 115.99,
        'currency' => 'EUR',
        'payment_status' => 'succeeded',
        'payment_method' => 'card',
        'tracking_number' => 'TRK-' . strtoupper(uniqid()),
        'confirmation_email_sent' => true,
        'created_at' => now()->subDays(3),
    ]);
    
    // Add order item
    \App\Models\OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $products->first()->id,
        'product_name' => $products->first()->name,
        'quantity' => 1,
        'price' => 100,
        'total' => 100,
    ]);
    
    echo "Created shipped order #{$order->id} with tracking number: {$order->tracking_number}\n";
}

echo "Test data updated!\n";

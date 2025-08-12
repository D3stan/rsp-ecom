<?php

require_once 'vendor/autoload.php';

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Address;
use Illuminate\Support\Str;

// Get a non-admin user
$user = User::where('role', '!=', 'admin')->where('email_verified_at', '!=', null)->first();

if (!$user) {
    echo "No verified non-admin user found!\n";
    exit;
}

echo "Test user email: " . $user->email . "\n";
echo "User ID: " . $user->id . "\n";
echo "User name: " . $user->name . "\n\n";

// Check if user has addresses
$address = Address::where('user_id', $user->id)->first();

if (!$address) {
    // Create a test address
    $address = Address::create([
        'user_id' => $user->id,
        'type' => 'both',
        'first_name' => $user->name,
        'last_name' => 'Test',
        'company' => null,
        'address_line_1' => '123 Test Street',
        'address_line_2' => null,
        'city' => 'Test City',
        'state' => 'Test State',
        'postal_code' => '12345',
        'country' => 'US',
        'phone' => '+1234567890',
        'is_default' => true,
    ]);
    echo "Created test address for user\n";
}

// Get some random products
$products = Product::inRandomOrder()->limit(10)->get();

if ($products->isEmpty()) {
    echo "No products found!\n";
    exit;
}

// Create 3 test orders
$statuses = ['pending', 'processing', 'shipped', 'delivered'];

for ($i = 1; $i <= 3; $i++) {
    $orderProducts = $products->random(rand(1, 3));
    $subtotal = 0;
    
    $order = Order::create([
        'order_number' => 'ORD-' . Str::upper(Str::random(8)),
        'user_id' => $user->id,
        'billing_address_id' => $address->id,
        'shipping_address_id' => $address->id,
        'status' => $statuses[array_rand($statuses)],
        'subtotal' => 0, // Will update after items
        'tax_amount' => 0,
        'shipping_amount' => 5.99,
        'total_amount' => 0, // Will update after items
        'currency' => 'EUR',
        'payment_status' => 'succeeded',
        'payment_method' => 'card',
        'confirmation_email_sent' => true,
        'created_at' => now()->subDays(rand(1, 30)),
    ]);
    
    // Create order items
    foreach ($orderProducts as $product) {
        $quantity = rand(1, 3);
        $price = $product->price;
        $total = $price * $quantity;
        $subtotal += $total;
        
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'price' => $price,
            'total' => $total,
        ]);
    }
    
    // Update order totals
    $tax = $subtotal * 0.1; // 10% tax
    $total = $subtotal + $tax + 5.99; // Add shipping
    
    $order->update([
        'subtotal' => $subtotal,
        'tax_amount' => $tax,
        'total_amount' => $total,
    ]);
    
    echo "Created order #{$order->id} with {$orderProducts->count()} items, total: €" . number_format($total, 2) . "\n";
}

echo "\nTest orders created successfully!\n";
echo "You can now login with email: " . $user->email . "\n";
echo "Password: Use the default password or create one if needed.\n";

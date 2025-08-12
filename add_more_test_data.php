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
use App\Models\Wishlist;
use Illuminate\Support\Str;

// Get the test user
$user = User::where('email', 'test@example.com')->first();
$address = Address::where('user_id', $user->id)->first();

// Create one more pending order (more recent)
$products = Product::inRandomOrder()->limit(2)->get();
$subtotal = 0;

$order = Order::create([
    'order_number' => 'ORD-' . Str::upper(Str::random(8)),
    'user_id' => $user->id,
    'billing_address_id' => $address->id,
    'shipping_address_id' => $address->id,
    'status' => 'pending',
    'subtotal' => 0,
    'tax_amount' => 0,
    'shipping_amount' => 5.99,
    'total_amount' => 0,
    'currency' => 'EUR',
    'payment_status' => 'pending',
    'payment_method' => 'card',
    'confirmation_email_sent' => false,
    'created_at' => now()->subHours(2), // Very recent
]);

foreach ($products as $product) {
    $quantity = rand(1, 2);
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

$tax = $subtotal * 0.1;
$total = $subtotal + $tax + 5.99;

$order->update([
    'subtotal' => $subtotal,
    'tax_amount' => $tax,
    'total_amount' => $total,
]);

echo "Created pending order #{$order->id} with {$products->count()} items, total: €" . number_format($total, 2) . "\n";

// Add some wishlist items
$wishlistProducts = Product::inRandomOrder()->limit(5)->get();
foreach ($wishlistProducts as $product) {
    Wishlist::firstOrCreate([
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
}

echo "Added {$wishlistProducts->count()} items to wishlist\n";

echo "\nAll test data created!\n";
echo "Login with: test@example.com\n";

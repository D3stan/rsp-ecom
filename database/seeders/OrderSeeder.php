<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Product;
use App\Models\Address;
use App\Models\Size;
use Database\Seeders\SeederConfig;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::where('role', 'customer')->get();
        $products = Product::all();
        
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Running UserSeeder first...');
            $this->call(UserSeeder::class);
            $users = User::where('role', 'customer')->get();
        }
        
        if ($products->isEmpty()) {
            $this->command->warn('No products found. Running ProductSeeder first...');
            $this->call(ProductSeeder::class);
            $products = Product::all();
        }

        $ordersCount = 100 * SeederConfig::VOLUME_MULTIPLIER;
        
        $this->command->info("Creating {$ordersCount} orders...");
        
        for ($i = 0; $i < $ordersCount; $i++) {
            $user = $users->random();
            
            // Get user's addresses
            $shippingAddress = $user->addresses()->where('type', 'shipping')->first() 
                ?? $user->addresses()->first();
            $billingAddress = $user->addresses()->where('type', 'billing')->first() 
                ?? $shippingAddress;
            
            if (!$shippingAddress) {
                // Create addresses for this user if they don't have any
                Address::factory()->shipping()->create(['user_id' => $user->id]);
                $shippingAddress = $user->addresses()->where('type', 'shipping')->first();
                $billingAddress = $shippingAddress;
            }
            
            // Create order
            $order = Order::factory()->create([
                'user_id' => $user->id,
                'shipping_address_id' => $shippingAddress->id,
                'billing_address_id' => $billingAddress->id,
            ]);
            
            // Add 1-5 items to the order
            $itemsCount = rand(1, 5);
            $orderSubtotal = 0;
            
            for ($j = 0; $j < $itemsCount; $j++) {
                $product = $products->random();
                $quantity = rand(1, 3);
                
                $orderItem = OrderItem::factory()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'price' => $product->price,
                    'total' => $product->price * $quantity,
                ]);
                
                $orderSubtotal += $orderItem->total;
            }
            
            // Update order totals
            $taxAmount = round($orderSubtotal * 0.20, 2); // 20% tax
            $shippingAmount = $orderSubtotal > 50 ? 0 : 5.99;
            $total = $orderSubtotal + $taxAmount + $shippingAmount;
            
            $order->update([
                'subtotal' => $orderSubtotal,
                'tax_amount' => $taxAmount,
                'shipping_amount' => $shippingAmount,
                'total_amount' => $total,
            ]);
            
            if ($i % 20 === 0) {
                $this->command->info("Created {$i}/{$ordersCount} orders...");
            }
        }

        $this->command->info('Orders seeded successfully!');
    }
}

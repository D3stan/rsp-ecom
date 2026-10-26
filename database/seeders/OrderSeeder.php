<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
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

            if (! $shippingAddress) {
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

            // Update order totals using tax-inclusive pricing (European VAT model): the product prices already
            // contain the VAT, tax_amount is the portion of the total at tax_rate (same rule as App\Support\Vat).
            $taxRate = 22.0;
            $shippingAmount = 9.99; // shipping is always charged
            $total = round($orderSubtotal + $shippingAmount, 2);
            $taxAmount = round($total * ($taxRate / 100) / (1 + $taxRate / 100), 2);

            $order->update([
                'subtotal' => $orderSubtotal, // VAT-inclusive
                'tax_amount' => $taxAmount, // VAT contained in the total
                'tax_rate' => $taxRate,
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

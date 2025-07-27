<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use Database\Seeders\SeederConfig;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::where('role', 'customer')->get();
        $products = Product::all();
        
        if ($users->isEmpty() || $products->isEmpty()) {
            $this->command->warn('Missing users or products. Running dependent seeders...');
            if ($users->isEmpty()) $this->call(UserSeeder::class);
            if ($products->isEmpty()) $this->call(ProductSeeder::class);
            
            $users = User::where('role', 'customer')->get();
            $products = Product::all();
        }

        $reviewsCount = 300 * SeederConfig::VOLUME_MULTIPLIER;
        
        $this->command->info("Creating {$reviewsCount} reviews...");
        
        // Get users who have made orders (for verified purchases)
        $usersWithOrders = User::whereHas('orders')->pluck('id')->toArray();
        
        for ($i = 0; $i < $reviewsCount; $i++) {
            $user = $users->random();
            $product = $products->random();
            
            // Check if this user already reviewed this product
            if (Review::where('user_id', $user->id)->where('product_id', $product->id)->exists()) {
                continue; // Skip duplicate reviews
            }
            
            // Determine if this should be approved
            $isApproved = rand(1, 100) <= 85;
            
            Review::factory()->create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'is_approved' => $isApproved,
            ]);
            
            if ($i % 50 === 0) {
                $this->command->info("Created {$i}/{$reviewsCount} reviews...");
            }
        }

        $this->command->info('Reviews seeded successfully!');
    }
}

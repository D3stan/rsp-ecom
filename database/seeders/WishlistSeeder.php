<?php

namespace Database\Seeders;

use App\Models\Wishlist;
use App\Models\User;
use App\Models\Product;
use Database\Seeders\SeederConfig;
use Illuminate\Database\Seeder;

class WishlistSeeder extends Seeder
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

        $this->command->info("Creating wishlist items...");
        
        // Each user gets 0-10 wishlist items (some users have no wishlist)
        foreach ($users as $user) {
            $wishlistCount = rand(0, 10);
            
            if ($wishlistCount === 0) continue; // This user has no wishlist
            
            // Get random products for this user's wishlist (no duplicates)
            $userProducts = $products->random(min($wishlistCount, $products->count()));
            
            foreach ($userProducts as $product) {
                Wishlist::factory()->create([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                ]);
            }
        }

        $totalWishlists = Wishlist::count();
        $this->command->info("Created {$totalWishlists} wishlist items successfully!");
    }
}

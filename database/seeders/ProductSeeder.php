<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use Database\Seeders\SeederConfig;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::all();
        
        if ($categories->isEmpty()) {
            $this->command->warn('No categories found. Running CategorySeeder first...');
            $this->call(CategorySeeder::class);
            $categories = Category::all();
        }

        $productsPerCategory = 15 * SeederConfig::VOLUME_MULTIPLIER;
        
        $this->command->info("Creating {$productsPerCategory} products per category...");
        
        foreach ($categories as $category) {
            $this->command->info("Creating products for category: {$category->name}");
            
            Product::factory()
                ->count($productsPerCategory)
                ->create([
                    'category_id' => $category->id,
                ]);
        }

        $this->command->info('Products seeded successfully!');
    }
}

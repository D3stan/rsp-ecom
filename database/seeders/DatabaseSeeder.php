<?php

namespace Database\Seeders;

use Database\Seeders\SeederConfig;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Production gets only what the shop needs to run: settings defaults (never overwrites existing rows)
        // and the admin from ADMIN_EMAIL / ADMIN_PASSWORD (UserSeeder). No demo data, no demo credentials.
        if (app()->isProduction()) {
            $this->call([SettingsSeeder::class, UserSeeder::class]);
            $this->command->warn('Production: demo data skipped (catalogue, pages, addresses, orders, reviews, wishlists).');

            return;
        }

        $this->command->info('Starting database seeding...');
        $this->command->info('Volume multiplier: ' . SeederConfig::VOLUME_MULTIPLIER);
        
        // Seed in the correct order to maintain foreign key constraints
        $this->call([
            // Master data first (no dependencies)
            CategorySeeder::class,
            SizeSeeder::class,
            SettingsSeeder::class,
            PageSeeder::class,
            
            // Users (no dependencies)
            UserSeeder::class,
            
            // Products (depends on categories)
            ProductSeeder::class,
            
            // Addresses (depends on users)
            AddressSeeder::class,
            
            // Orders (depends on users, addresses, products)
            OrderSeeder::class,
            
            // Reviews (depends on users, products)
            ReviewSeeder::class,
            
            // Wishlists (depends on users, products)
            WishlistSeeder::class,
        ]);
        
        $this->command->info('Database seeding completed successfully!');
        $this->showSeedingSummary();
    }
    
    /**
     * Show a summary of what was seeded
     */
    private function showSeedingSummary(): void
    {
        $this->command->info('');
        $this->command->info('=== SEEDING SUMMARY ===');
        $this->command->table(['Model', 'Count'], [
            ['Categories', \App\Models\Category::count()],
            ['Sizes', \App\Models\Size::count()],
            ['Settings', \App\Models\Setting::count()],
            ['Pages', \App\Models\Page::count()],
            ['Users', \App\Models\User::count()],
            ['Products', \App\Models\Product::count()],
            ['Addresses', \App\Models\Address::count()],
            ['Orders', \App\Models\Order::count()],
            ['Order Items', \App\Models\OrderItem::count()],
            ['Reviews', \App\Models\Review::count()],
            ['Wishlists', \App\Models\Wishlist::count()],
        ]);
        
        $this->command->info('');
        $this->command->info('Test accounts created:');
        $this->command->info('Admin: admin@example.com / password');
        $this->command->info('Test User: test@example.com / password');
    }
}

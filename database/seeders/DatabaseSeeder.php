<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Size;
use App\Models\User;
use App\Models\Wishlist;
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
        $this->command->info('Volume multiplier: '.SeederConfig::VOLUME_MULTIPLIER);

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
            ['Categories', Category::count()],
            ['Sizes', Size::count()],
            ['Settings', Setting::count()],
            ['Pages', Page::count()],
            ['Users', User::count()],
            ['Products', Product::count()],
            ['Addresses', Address::count()],
            ['Orders', Order::count()],
            ['Order Items', OrderItem::count()],
            ['Reviews', Review::count()],
            ['Wishlists', Wishlist::count()],
        ]);

        $this->command->info('');
        $this->command->info('Test accounts created:');
        $this->command->info('Admin: admin@example.com / password');
        $this->command->info('Test User: test@example.com / password');
    }
}

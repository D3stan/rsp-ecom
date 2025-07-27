<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create an admin user
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@rspecommerce.com',
            'role' => 'admin',
            'is_active' => true,
            'phone' => '+1234567890',
        ]);

        // Create a test customer
        User::factory()->create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'role' => 'customer',
            'is_active' => true,
            'phone' => '+1987654321',
        ]);

        // Seed master data
        $this->call([
            CategorySeeder::class,
            SizeSeeder::class,
            SettingSeeder::class,
        ]);
    }
}

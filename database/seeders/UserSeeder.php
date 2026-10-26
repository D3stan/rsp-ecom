<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Production never gets the demo logins or fake customers
        if (app()->isProduction()) {
            $this->seedProductionAdmin();

            return;
        }

        // Create admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'], // Find by email
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        $this->command->info('Admin user created/updated: admin@example.com / password');

        // Create test user
        $testUser = User::updateOrCreate(
            ['email' => 'test@example.com'], // Find by email
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'customer',
                'is_active' => true,
            ]
        );

        $this->command->info('Test user created/updated: test@example.com / password');

        // Create regular users
        $regularUsersCount = 50 * SeederConfig::VOLUME_MULTIPLIER;

        $this->command->info("Creating {$regularUsersCount} regular users...");

        User::factory()
            ->count($regularUsersCount)
            ->create();

        $this->command->info('Users seeded successfully!');
    }

    /**
     * The production admin comes from ADMIN_EMAIL / ADMIN_PASSWORD (config app.admin_seed); without both, none.
     */
    private function seedProductionAdmin(): void
    {
        $email = config('app.admin_seed.email');
        $password = config('app.admin_seed.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn('Production: no admin user created. Set ADMIN_EMAIL and ADMIN_PASSWORD to create one.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('app.admin_seed.name') ?: 'Admin',
                'email_verified_at' => now(),
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        $this->command?->info("Admin user created/updated: {$email}");
    }
}

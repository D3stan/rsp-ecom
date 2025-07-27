<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use Database\Seeders\SeederConfig;
use Illuminate\Database\Seeder;

class AddressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Running UserSeeder first...');
            $this->call(UserSeeder::class);
            $users = User::all();
        }

        $this->command->info("Creating addresses for {$users->count()} users...");
        
        foreach ($users as $user) {
            // Each user gets 1-3 addresses
            $addressCount = rand(1, 3);
            
            for ($i = 0; $i < $addressCount; $i++) {
                $type = $i === 0 ? 'shipping' : (rand(0, 1) ? 'shipping' : 'billing');
                
                Address::factory()
                    ->create([
                        'user_id' => $user->id,
                        'type' => $type,
                        'is_default' => $i === 0, // First address is default
                    ]);
            }
            
            // Ensure each user has at least one billing address if they have multiple addresses
            if ($addressCount > 1 && !$user->addresses()->where('type', 'billing')->exists()) {
                Address::factory()
                    ->billing()
                    ->create([
                        'user_id' => $user->id,
                        'is_default' => false,
                    ]);
            }
        }

        $this->command->info('Addresses seeded successfully!');
    }
}

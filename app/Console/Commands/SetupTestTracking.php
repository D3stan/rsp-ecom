<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\User;

class SetupTestTracking extends Command
{
    protected $signature = 'setup:test-tracking';
    protected $description = 'Set up test tracking numbers and order statuses';

    public function handle()
    {
        $this->info('Setting up test tracking data...');

        // Find the test user
        $testUser = User::where('email', 'test@example.com')->first();

        if (!$testUser) {
            $this->error('Test user not found!');
            return 1;
        }

        // Get first order for test user and update it to shipped with tracking
        $order = Order::where('user_id', $testUser->id)->first();

        if ($order) {
            $order->update([
                'status' => 'shipped',
                'tracking_number' => 'TRK-' . strtoupper(bin2hex(random_bytes(6)))
            ]);
            
            $this->info("Updated Order #{$order->id} to shipped status");
            $this->info("Tracking Number: {$order->tracking_number}");
        } else {
            $this->error('No orders found for test user!');
            return 1;
        }

        // Update another order to delivered for review testing
        $secondOrder = Order::where('user_id', $testUser->id)->skip(1)->first();

        if ($secondOrder) {
            $secondOrder->update([
                'status' => 'delivered'
            ]);
            
            $this->info("Updated Order #{$secondOrder->id} to delivered status for review testing");
        } else {
            $this->warn('No second order found for review testing');
        }

        $this->info('Test data setup complete!');
        
        return 0;
    }
}

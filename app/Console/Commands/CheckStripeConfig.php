<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\StripeService;
use App\Models\Setting;

class CheckStripeConfig extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'stripe:check-config';

    /**
     * The console command description.
     */
    protected $description = 'Check Stripe configuration status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== Stripe Configuration Check ===');
        
        // Check environment variables
        $this->info("\n--- Environment Variables ---");
        $envSecret = env('STRIPE_SECRET');
        $envKey = env('STRIPE_KEY');
        
        $this->info('STRIPE_SECRET: ' . ($envSecret ? (substr($envSecret, 0, 7) . '***') : 'NOT SET'));
        $this->info('STRIPE_KEY: ' . ($envKey ? (substr($envKey, 0, 7) . '***') : 'NOT SET'));
        
        // Check database settings
        $this->info("\n--- Database Settings ---");
        $dbSecret = Setting::get('stripe_secret_key', '');
        $dbKey = Setting::get('stripe_public_key', '');
        
        $this->info('stripe_secret_key: ' . ($dbSecret ? (substr($dbSecret, 0, 7) . '***') : 'NOT SET'));
        $this->info('stripe_public_key: ' . ($dbKey ? (substr($dbKey, 0, 7) . '***') : 'NOT SET'));
        
        // Check StripeService methods
        $this->info("\n--- StripeService Results ---");
        $serviceSecret = StripeService::getSecretKey();
        $servicePublic = StripeService::getPublicKey();
        
        $this->info('getSecretKey(): ' . ($serviceSecret ? (substr($serviceSecret, 0, 7) . '***') : 'NULL'));
        $this->info('getPublicKey(): ' . ($servicePublic ? (substr($servicePublic, 0, 7) . '***') : 'NULL'));
        $this->info('isConfigured(): ' . (StripeService::isConfigured() ? 'TRUE' : 'FALSE'));
        
        // Check Laravel configuration cache
        $this->info("\n--- Configuration Cache Status ---");
        $configCached = app()->configurationIsCached();
        $this->info('Configuration cached: ' . ($configCached ? 'YES' : 'NO'));
        
        if ($configCached) {
            $this->warn('Configuration is cached. Run "php artisan config:clear" to clear the cache.');
        }
        
        // Test Stripe client creation
        $this->info("\n--- Stripe Client Test ---");
        try {
            $client = StripeService::getClient();
            if ($client) {
                $this->info('✓ Stripe client created successfully');
            } else {
                $this->error('✗ Failed to create Stripe client');
            }
        } catch (\Exception $e) {
            $this->error('✗ Exception creating Stripe client: ' . $e->getMessage());
        }
        
        // Environment detection
        $this->info("\n--- Environment Info ---");
        $this->info('APP_ENV: ' . config('app.env'));
        $this->info('Current working directory: ' . getcwd());
        
        return Command::SUCCESS;
    }
}

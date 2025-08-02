<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure Cashier
        // Cashier::calculateTaxes(); // Uncomment to enable automatic tax calculation
        
        // Set currency (can also be done via CASHIER_CURRENCY env variable)
        // Cashier::useCurrency('usd', '$');
        
        // Configure custom models if needed
        // Cashier::useCustomerModel(User::class);
    }
}

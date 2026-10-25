<?php

namespace App\Providers;

use App\Shipping\Contracts\ShippingProviderManifest;
use App\Shipping\Rates\RateManager;
use Illuminate\Support\ServiceProvider;

class RateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RateManager::class);

        $this->callAfterResolving(RateManager::class, function (RateManager $manager) {
            foreach ((array) config('shipping.manifests', []) as $manifest) {
                if (! is_string($manifest) || ! is_subclass_of($manifest, ShippingProviderManifest::class) || ! $manifest::hasRates()) {
                    continue;
                }

                $name = $manifest::name();
                $manager->extend($name, fn () => $manifest::createRateDriver($manager->driverConfig($name)));
            }
        });
    }
}

<?php

namespace App\Providers;

use App\Shipping\Tracking\Drivers\Spediamo\SpediamoProvider;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the carrier tracking adapters with the TrackingManager (the manager itself and the
 * TrackingProvider binding are set up in AppServiceProvider). One line per adapter: adding or replacing
 * a carrier API never touches anything else. Which adapter is active is decided by config only
 * (SHIPPING_TRACKING_DRIVER); an adapter that is registered but not selected costs nothing.
 */
class TrackingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(TrackingManager::class, function (TrackingManager $manager) {
            $manager->extend('spediamo', fn () => new SpediamoProvider($manager->driverConfig('spediamo')));
        });
    }
}

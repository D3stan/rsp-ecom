<?php

namespace App\Providers;

use App\Shipping\Labels\Drivers\Spediamo\SpediamoLabelProvider;
use App\Shipping\Labels\LabelManager;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the label adapters with the LabelManager (the manager itself is bound in AppServiceProvider). One line per
 * adapter: adding or replacing a label carrier never touches anything else. Which adapter is active is decided by the
 * shipping.label_driver setting; an adapter that is registered but not selected costs nothing, and nothing is resolved
 * at all while label creation is off.
 */
class LabelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(LabelManager::class, function (LabelManager $manager) {
            $manager->extend('spediamo', fn () => new SpediamoLabelProvider($manager->driverConfig('spediamo')));
        });
    }
}

<?php

namespace App\Shipping\Labels;

use App\Shipping\Labels\Contracts\LabelProvider;
use App\Shipping\Labels\Drivers\FakeLabelProvider;
use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use Closure;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;

/**
 * Entry point of the modular label layer. Resolves a LabelProvider by name. Unlike the tracking manager there is no
 * default/manual provider: label creation is optional and ShippingService resolves a provider only when the
 * shipping.labels_enabled setting is on, so with labels off no provider is ever built.
 *
 * Adapters register themselves with one extend() line from a service provider:
 *
 *     $this->callAfterResolving(LabelManager::class, fn (LabelManager $manager) =>
 *         $manager->extend('example', fn () => new ExampleLabelProvider($manager->driverConfig('example'))));
 */
class LabelManager extends Manager
{
    public function getDefaultDriver(): string
    {
        $name = trim((string) $this->config->get('shipping.labels.driver', ''));

        if ($name === '') {
            throw new LabelProviderUnavailable('No label driver is configured (shipping.labels.driver).');
        }

        return $name;
    }

    public function createFakeDriver(): FakeLabelProvider
    {
        if (app()->isProduction()) {
            throw new LabelProviderUnavailable('The fake label driver is not available in production.');
        }

        return new FakeLabelProvider;
    }

    /**
     * Settings of one driver: merges shipping.providers.<name> with shipping.labels.drivers.<name>
     * at resolution time so runtime config overrides in tests take precedence.
     *
     * @return array<string, mixed>
     */
    public function driverConfig(string $name): array
    {
        $providerConfig = (array) $this->config->get("shipping.providers.{$name}", []);
        $driverConfig = (array) $this->config->get("shipping.labels.drivers.{$name}", []);

        return array_replace(
            $providerConfig,
            array_filter($driverConfig, static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * Names this installation can resolve (built-in + registered). `fake` is hidden in production.
     *
     * @return list<string>
     */
    public function driverNames(): array
    {
        $names = array_keys($this->customCreators);

        if (! app()->isProduction()) {
            $names[] = 'fake';
        }

        return array_values(array_unique($names));
    }

    /**
     * Filter configured carrier codes to those supported by a label driver.
     * An empty carrier_map in aggregator mode accepts all carriers.
     *
     * @param  list<string>  $carrierCodes
     * @return list<string>
     */
    public function supportedCarriersFor(string $driver, array $carrierCodes): array
    {
        try {
            $provider = $this->driver($driver);

            return array_values(array_filter($carrierCodes, fn (string $code) => $provider->supports($code)));
        } catch (\Throwable) {
            return $carrierCodes;
        }
    }

    /**
     * @param  string  $driver
     */
    public function extend($driver, Closure $callback)
    {
        unset($this->drivers[$driver]);

        return parent::extend($driver, $callback);
    }

    /**
     * @param  string  $driver
     */
    protected function createDriver($driver)
    {
        if (! isset($this->customCreators[$driver]) && ! method_exists($this, 'create'.Str::studly($driver).'Driver')) {
            throw new LabelProviderUnavailable(sprintf(
                'Unknown label driver [%s]. Available: %s.',
                $driver,
                implode(', ', $this->driverNames()) ?: '(none)',
            ));
        }

        $instance = parent::createDriver($driver);

        if (! $instance instanceof LabelProvider) {
            throw new LabelProviderUnavailable("Label driver [{$driver}] must implement ".LabelProvider::class.'.');
        }

        return $instance;
    }
}

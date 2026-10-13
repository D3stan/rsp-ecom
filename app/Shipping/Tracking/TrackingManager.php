<?php

namespace App\Shipping\Tracking;

use App\Shipping\Tracking\Contracts\PassiveProvider;
use App\Shipping\Tracking\Contracts\TrackingProvider;
use App\Shipping\Tracking\Drivers\FakeProvider;
use App\Shipping\Tracking\Drivers\ManualProvider;
use App\Shipping\Tracking\Drivers\NullProvider;
use App\Shipping\Tracking\Exceptions\TrackingException;
use App\Shipping\Tracking\Exceptions\UnknownTrackingDriver;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;
use Throwable;

/**
 * Entry point of the carrier tracking module. Resolves the active TrackingProvider
 * (config('shipping.tracking.driver'), env SHIPPING_TRACKING_DRIVER, default "manual").
 *
 * Built-in drivers: manual (links only, default), null (disabled), fake (tests/demos, refused in
 * production). Adapters register themselves with extend() from a service provider:
 *
 *     $this->callAfterResolving(TrackingManager::class, fn (TrackingManager $manager) =>
 *         $manager->extend('example', fn ($app) => new ExampleCarrierProvider(
 *             $manager->driverConfig('example'),
 *         )));
 *
 * Unknown method calls are forwarded to the active driver (Laravel's Manager behaviour).
 */
class TrackingManager extends Manager
{
    public function getDefaultDriver(): string
    {
        $name = trim((string) $this->config->get('shipping.tracking.driver', 'manual'));

        return $name !== '' ? $name : 'manual';
    }

    public function createManualDriver(): ManualProvider
    {
        return new ManualProvider;
    }

    public function createNullDriver(): NullProvider
    {
        return new NullProvider;
    }

    public function createFakeDriver(): FakeProvider
    {
        if (app()->isProduction()) {
            throw new UnknownTrackingDriver('The fake tracking driver is not available in production.');
        }

        return new FakeProvider($this->driverConfig('fake'));
    }

    /**
     * Settings of one driver: shipping.tracking.drivers.<name> (base_url, api_key, timeout, webhook_secret, ...).
     *
     * @return array<string, mixed>
     */
    public function driverConfig(string $name): array
    {
        return (array) $this->config->get("shipping.tracking.drivers.{$name}", []);
    }

    /**
     * Names that driver() can resolve on this installation.
     *
     * @return list<string>
     */
    public function driverNames(): array
    {
        $names = ['manual', 'null'];

        if (! app()->isProduction()) {
            $names[] = 'fake';
        }

        return array_values(array_unique(array_merge($names, array_keys($this->customCreators))));
    }

    /**
     * Register (or replace) a driver at runtime.
     *
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
            throw new UnknownTrackingDriver(sprintf(
                'Unknown shipping tracking driver [%s]. Available: %s. Check SHIPPING_TRACKING_DRIVER.',
                $driver,
                implode(', ', $this->driverNames()),
            ));
        }

        $instance = parent::createDriver($driver);

        if (! $instance instanceof TrackingProvider) {
            throw new TrackingException("Tracking driver [{$driver}] must implement ".TrackingProvider::class.'.');
        }

        return $instance;
    }

    /**
     * Does the active driver actually contact a carrier (so that polling makes sense)?
     * Never throws: a broken configuration simply means "no".
     */
    public function canPoll(): bool
    {
        try {
            return ! $this->driver() instanceof PassiveProvider;
        } catch (Throwable $e) {
            Log::warning('Shipping tracking driver cannot be resolved', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Public tracking page for a shipment: the active driver's answer, else the manual templates
     * (not when tracking is disabled with the null driver). Never throws and never calls the network:
     * a misconfigured driver must not break the emails that render this link.
     */
    public function trackingUrl(string $trackingNumber, ?string $carrierCode): ?string
    {
        try {
            $driver = $this->driver();
            $url = $driver->trackingUrl($trackingNumber, $carrierCode);

            if ($url === null && ! $driver instanceof PassiveProvider) {
                $url = $this->driver('manual')->trackingUrl($trackingNumber, $carrierCode);
            }

            return $url;
        } catch (Throwable $e) {
            Log::warning('Tracking URL could not be built by the configured driver, using the manual template', [
                'error' => $e->getMessage(),
            ]);

            return $this->driver('manual')->trackingUrl($trackingNumber, $carrierCode);
        }
    }

    /**
     * Carriers the admin can choose, from config('shipping.carriers').
     *
     * @return list<array{code: string, name: string}>
     */
    public function carriers(): array
    {
        $carriers = [];

        foreach ((array) $this->config->get('shipping.carriers', []) as $code => $carrier) {
            $carriers[] = [
                'code' => (string) $code,
                'name' => (string) (is_array($carrier) ? ($carrier['name'] ?? $code) : $code),
            ];
        }

        return $carriers;
    }

    /**
     * Display name of a carrier code: the configured name, else the code itself.
     */
    public function carrierName(string $carrierCode): string
    {
        $name = ((array) $this->config->get('shipping.carriers', []))[$carrierCode]['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : $carrierCode;
    }

    /**
     * @return list<string>
     */
    public function carrierCodes(): array
    {
        return array_column($this->carriers(), 'code');
    }
}

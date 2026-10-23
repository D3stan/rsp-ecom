<?php

namespace App\Shipping\Tracking;

use App\Models\Order;
use App\Models\User;
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
    /** The shape of a code in the shipping.carriers setting. */
    public const CARRIER_CODE_PATTERN = '/^[a-z0-9_-]{1,32}$/';

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
     * Settings of one driver: merges shipping.providers.<name> with shipping.tracking.drivers.<name>
     * at resolution time so runtime config overrides in tests take precedence.
     *
     * @return array<string, mixed>
     */
    public function driverConfig(string $name): array
    {
        $providerConfig = (array) $this->config->get("shipping.providers.{$name}", []);
        $driverConfig = (array) $this->config->get("shipping.tracking.drivers.{$name}", []);

        return array_replace(
            $providerConfig,
            array_filter($driverConfig, static fn (mixed $value): bool => $value !== null),
        );
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
     * Driver names selectable by an admin for per-order tracking (excludes 'null', and excludes 'fake'
     * unless 'fake' is the configured default driver).
     *
     * @return list<string>
     */
    public function selectableDriverNames(): array
    {
        $default = $this->getDefaultDriver();

        return array_values(array_filter(
            $this->driverNames(),
            fn (string $name) => $name !== 'null' && ($name !== 'fake' || $default === 'fake'),
        ));
    }

    /**
     * All registered driver names that actively poll a carrier API (non-passive).
     *
     * @return list<string>
     */
    public function pollableDriverNames(): array
    {
        return array_values(array_filter(
            $this->driverNames(),
            fn (string $name) => $this->canPoll($name),
        ));
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
     * Does the given driver (or the default driver when null) actually contact a carrier so polling makes sense?
     * Never throws: a broken configuration simply means "no".
     */
    public function canPoll(?string $driver = null): bool
    {
        try {
            $target = $driver !== null && trim($driver) !== '' ? trim($driver) : null;

            return ! $this->driver($target) instanceof PassiveProvider;
        } catch (Throwable $e) {
            Log::warning('Shipping tracking driver cannot be resolved', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Should the scheduler / sync command run? True when the default driver is pollable OR when any
     * shipped order in the database is assigned to a pollable tracking_driver.
     */
    public function canPollAny(): bool
    {
        if ($this->canPoll()) {
            return true;
        }

        $pollable = $this->pollableDriverNames();

        if ($pollable === []) {
            return false;
        }

        try {
            return Order::query()
                ->where('status', 'shipped')
                ->whereNotNull('tracking_driver')
                ->whereIn('tracking_driver', $pollable)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Public tracking page for a shipment: the specified (or default) driver's answer, else the manual
     * templates (not when tracking is disabled with the null driver). Never throws and never calls the
     * network: a misconfigured driver must not break the emails that render this link.
     */
    public function trackingUrl(string $trackingNumber, ?string $carrierCode, ?string $driver = null): ?string
    {
        try {
            $target = $driver !== null && trim($driver) !== '' ? trim($driver) : null;
            $resolved = $this->driver($target);
            $url = $resolved->trackingUrl($trackingNumber, $carrierCode);

            if ($url === null && ! $resolved instanceof PassiveProvider) {
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
     * Add a carrier to the shipping.carriers setting when it is not there yet, so a label bought for a courier the
     * admin never listed still ships under a named carrier.
     */
    public function registerCarrier(string $code, ?string $name, ?User $by = null): void
    {
        if (in_array($code, $this->carrierCodes(), true) || preg_match(self::CARRIER_CODE_PATTERN, $code) !== 1) {
            return;
        }

        $rows = (array) settings('shipping.carriers', []);
        $rows[] = ['code' => $code, 'name' => mb_substr(trim((string) $name) ?: strtoupper($code), 0, 80), 'tracking_url' => ''];

        settings()->set('shipping.carriers', $rows, $by);
    }

    /**
     * @return list<string>
     */
    public function carrierCodes(): array
    {
        return array_column($this->carriers(), 'code');
    }
}

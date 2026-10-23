<?php

namespace App\Shipping\Rates;

use App\Models\Order;
use App\Shipping\Rates\Contracts\InspectsBalance;
use App\Shipping\Rates\Contracts\RateProvider;
use App\Shipping\Rates\Drivers\FakeRateProvider;
use App\Shipping\Rates\DTO\RateAddress;
use App\Shipping\Rates\DTO\RateParcel;
use App\Shipping\Rates\DTO\RateQuote;
use App\Shipping\Rates\DTO\RateRequest;
use App\Shipping\Rates\Exceptions\RateProviderUnavailable;
use App\Shipping\Support\SenderAddress;
use Closure;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;
use Throwable;

class RateManager extends Manager
{
    public function getDefaultDriver(): string
    {
        $driver = trim((string) settings('shipping.rate_driver', $this->config->get('shipping.rates.default', 'fake')));

        return $driver !== '' ? $driver : 'fake';
    }

    public function createFakeDriver(): RateProvider
    {
        return new FakeRateProvider($this->driverConfig('fake'));
    }

    /**
     * @return array<string, mixed>
     */
    public function driverConfig(string $name): array
    {
        $providerConfig = (array) $this->config->get("shipping.providers.{$name}", []);
        $driverConfig = (array) $this->config->get("shipping.rates.drivers.{$name}", []);

        return array_replace(
            $providerConfig,
            array_filter($driverConfig, static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * @return list<string>
     */
    public function driverNames(): array
    {
        return array_values(array_unique(array_merge(
            array_keys((array) $this->config->get('shipping.rates.drivers', [])),
            array_keys($this->customCreators),
        )));
    }

    /**
     * @return list<string>
     */
    public function selectableDriverNames(): array
    {
        return array_values(array_filter(
            $this->driverNames(),
            fn (string $name) => $name !== 'fake' || app()->environment('testing', 'local'),
        ));
    }

    /**
     * @param  list<string>|null  $drivers
     * @return array{quotes: list<RateQuote>, errors: array<string, string>, balances: array<string, array<string, mixed>>}
     */
    public function quoteAll(RateRequest $request, ?array $drivers = null): array
    {
        $driverNames = $drivers ?? $this->selectableDriverNames();
        $quotes = [];
        $errors = [];
        $balances = [];

        foreach ($driverNames as $name) {
            try {
                $provider = $this->driver($name);
            } catch (Throwable $e) {
                $errors[$name] = $e->getMessage();
                continue;
            }

            try {
                $driverQuotes = $provider->quote($request);
                foreach ($driverQuotes as $quote) {
                    $quotes[] = $quote;
                }
            } catch (Throwable $e) {
                $errors[$name] = $e->getMessage();
            }

            if ($provider instanceof InspectsBalance) {
                try {
                    $balances[$name] = $provider->balance()->toArray();
                } catch (Throwable) {
                    // Ignore balance errors gracefully
                }
            }
        }

        usort($quotes, static fn (RateQuote $a, RateQuote $b): int => $a->totalPrice <=> $b->totalPrice);

        return [
            'quotes' => $quotes,
            'errors' => $errors,
            'balances' => $balances,
        ];
    }

    public function senderFromSettings(): RateAddress
    {
        $sender = SenderAddress::fromSettings();

        return new RateAddress(
            postalCode: $sender['postal_code'],
            city: $sender['city'],
            province: $sender['province'],
            country: $sender['country'],
            name: $sender['name'],
            street: $sender['street'],
            phone: $sender['phone'],
            email: $sender['email'],
        );
    }

    /**
     * @param  list<RateParcel|array<string, mixed>>  $parcels
     */
    public function requestFromOrder(
        Order $order,
        array $parcels = [],
        ?float $insurance = null,
        ?float $cod = null,
        ?string $coupon = null,
        ?string $carrierCode = null,
    ): RateRequest {
        $address = $order->shippingAddress;

        $recipientCountry = (string) ($address?->country ?? 'IT');
        $recipientPostal = trim((string) ($address?->postal_code ?? ''));
        if (strtoupper($recipientCountry) === 'IT' && is_numeric($recipientPostal) && $recipientPostal !== '') {
            $recipientPostal = str_pad($recipientPostal, 5, '0', STR_PAD_LEFT);
        }

        $recipient = new RateAddress(
            postalCode: $recipientPostal,
            city: (string) ($address?->city ?? ''),
            province: (string) ($address?->state ?? ''),
            country: $recipientCountry,
            name: trim((string) ($address?->first_name.' '.$address?->last_name)) ?: (string) $order->getCustomerName(),
            street: trim((string) ($address?->address_line_1.' '.$address?->address_line_2)),
            phone: (string) ($address?->phone ?? $order->guest_phone ?? ''),
            email: (string) ($order->getCustomerEmail() ?? ''),
        );

        $defaultWeight = max(0.01, (float) settings('shipping.default_parcel_weight_kg', 1.0));
        $builtParcels = [];

        foreach ($parcels as $parcel) {
            if ($parcel instanceof RateParcel) {
                $builtParcels[] = $parcel;
                continue;
            }

            if (is_array($parcel)) {
                $w = (float) ($parcel['weight_kg'] ?? $parcel['weight'] ?? 0);
                $builtParcels[] = new RateParcel(
                    weightKg: $w > 0 ? $w : $defaultWeight,
                    lengthCm: isset($parcel['length_cm']) && $parcel['length_cm'] !== '' ? (float) $parcel['length_cm'] : (isset($parcel['length']) && $parcel['length'] !== '' ? (float) $parcel['length'] : null),
                    widthCm: isset($parcel['width_cm']) && $parcel['width_cm'] !== '' ? (float) $parcel['width_cm'] : (isset($parcel['width']) && $parcel['width'] !== '' ? (float) $parcel['width'] : null),
                    heightCm: isset($parcel['height_cm']) && $parcel['height_cm'] !== '' ? (float) $parcel['height_cm'] : (isset($parcel['height']) && $parcel['height'] !== '' ? (float) $parcel['height'] : null),
                    quantity: isset($parcel['quantity']) ? max(1, (int) $parcel['quantity']) : 1,
                );
            }
        }

        if ($builtParcels === []) {
            $builtParcels = [new RateParcel(weightKg: $defaultWeight)];
        }

        return new RateRequest(
            sender: $this->senderFromSettings(),
            recipient: $recipient,
            parcels: $builtParcels,
            insuranceAmount: $insurance,
            codAmount: $cod,
            contentDescription: 'Merce varia',
            coupon: $coupon,
            carrierCode: $carrierCode,
        );
    }

    /**
     * @param  string|null  $driver
     * @return RateProvider
     */
    public function driver($driver = null)
    {
        return parent::driver($driver);
    }

    /**
     * @param  string  $driver
     */
    public function extend($driver, Closure $callback)
    {
        unset($this->drivers[$driver]);

        return parent::extend($driver, $callback);
    }

    public function purgeDriver(string $driver): self
    {
        unset($this->drivers[$driver]);

        return $this;
    }

    /**
     * @param  string  $driver
     */
    protected function createDriver($driver)
    {
        if (! isset($this->customCreators[$driver]) && ! method_exists($this, 'create'.Str::studly($driver).'Driver')) {
            throw new RateProviderUnavailable(sprintf(
                'Unknown rate driver [%s]. Available: %s.',
                $driver,
                implode(', ', $this->driverNames()) ?: '(none)',
            ));
        }

        $instance = parent::createDriver($driver);

        if (! $instance instanceof RateProvider) {
            throw new RateProviderUnavailable("Rate driver [{$driver}] must implement ".RateProvider::class.'.');
        }

        return $instance;
    }
}

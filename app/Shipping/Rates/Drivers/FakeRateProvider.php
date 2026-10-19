<?php

namespace App\Shipping\Rates\Drivers;

use App\Shipping\Rates\Contracts\InspectsBalance;
use App\Shipping\Rates\Contracts\RateProvider;
use App\Shipping\Rates\DTO\AccountBalance;
use App\Shipping\Rates\DTO\RateQuote;
use App\Shipping\Rates\DTO\RateRequest;
use App\Shipping\Rates\Exceptions\RateProviderUnavailable;
use App\Shipping\Rates\Exceptions\RateRejected;

class FakeRateProvider implements InspectsBalance, RateProvider
{
    public const NAME = 'fake';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config = [],
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    /**
     * @return list<RateQuote>
     */
    public function quote(RateRequest $request): array
    {
        $mode = $this->config['mode'] ?? config('shipping.rates.drivers.fake.mode');

        if ($mode === 'reject') {
            throw new RateRejected('Fake rate provider rejected quote request.');
        }

        if ($mode === 'outage') {
            throw new RateProviderUnavailable('Fake rate provider is temporarily unavailable.');
        }

        $quotes = [
            new RateQuote(
                provider: self::NAME,
                carrierCode: 'dhl',
                carrierName: 'DHL Express',
                rawCarrierCode: 'dhl',
                serviceName: 'Express',
                netPrice: 6.97,
                totalPrice: 8.50,
                listPrice: 10.00,
                currency: 'EUR',
                taxableWeightKg: 1.0,
                dimensionalWeightApplied: false,
                estimatedDelivery: '24/48h',
                quoteToken: 'fake-offer-dhl',
                surcharges: [],
                warnings: [],
                raw: ['source' => 'fake'],
            ),
            new RateQuote(
                provider: self::NAME,
                carrierCode: 'sda',
                carrierName: 'SDA',
                rawCarrierCode: 'sda',
                serviceName: 'Standard',
                netPrice: 5.08,
                totalPrice: 6.20,
                listPrice: 7.50,
                currency: 'EUR',
                taxableWeightKg: 1.0,
                dimensionalWeightApplied: false,
                estimatedDelivery: '48/72h',
                quoteToken: 'fake-offer-sda',
                surcharges: [],
                warnings: [],
                raw: ['source' => 'fake'],
            ),
        ];

        if ($request->carrierCode !== null && $request->carrierCode !== '') {
            $quotes = array_values(array_filter($quotes, fn (RateQuote $q) => $q->carrierCode === $request->carrierCode));
        }

        return $quotes;
    }

    public function balance(): AccountBalance
    {
        return new AccountBalance(
            provider: self::NAME,
            balance: 150.00,
            currency: 'EUR',
            raw: ['source' => 'fake'],
        );
    }
}

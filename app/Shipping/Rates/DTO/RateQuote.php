<?php

namespace App\Shipping\Rates\DTO;

final readonly class RateQuote
{
    /**
     * @param  array<string, mixed>  $surcharges
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $provider,
        public string $carrierCode,
        public string $carrierName,
        public string $rawCarrierCode,
        public string $serviceName,
        public float $netPrice,
        public float $totalPrice,
        public ?float $listPrice = null,
        public string $currency = 'EUR',
        public ?float $taxableWeightKg = null,
        public bool $dimensionalWeightApplied = false,
        public ?string $estimatedDelivery = null,
        public ?string $quoteToken = null,
        public array $surcharges = [],
        public array $warnings = [],
        public array $raw = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'carrier_code' => $this->carrierCode,
            'carrier_name' => $this->carrierName,
            'raw_carrier_code' => $this->rawCarrierCode,
            'service_name' => $this->serviceName,
            'net_price' => round($this->netPrice, 2),
            'total_price' => round($this->totalPrice, 2),
            'list_price' => $this->listPrice !== null ? round($this->listPrice, 2) : null,
            'currency' => $this->currency,
            'taxable_weight_kg' => $this->taxableWeightKg !== null ? round($this->taxableWeightKg, 2) : null,
            'dimensional_weight_applied' => $this->dimensionalWeightApplied,
            'estimated_delivery' => $this->estimatedDelivery,
            'quote_token' => $this->quoteToken,
            'surcharges' => $this->surcharges,
            'warnings' => $this->warnings,
        ];
    }
}

<?php

namespace App\Shipping\Rates\DTO;

final readonly class AccountBalance
{
    /**
     * @param  array<string, mixed>  $raw
     * @param  bool|null  $live  false: the account is in test mode (labels are simulated); null: the provider does not say
     */
    public function __construct(
        public string $provider,
        public float $balance,
        public string $currency = 'EUR',
        public array $raw = [],
        public ?bool $live = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'balance' => round($this->balance, 2),
            'currency' => $this->currency,
            'live' => $this->live,
        ];
    }
}

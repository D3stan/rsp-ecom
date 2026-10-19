<?php

namespace App\Shipping\Rates\DTO;

final readonly class AccountBalance
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $provider,
        public float $balance,
        public string $currency = 'EUR',
        public array $raw = [],
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
        ];
    }
}

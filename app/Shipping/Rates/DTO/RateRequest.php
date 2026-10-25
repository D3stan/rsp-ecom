<?php

namespace App\Shipping\Rates\DTO;

final readonly class RateRequest
{
    /**
     * @param  list<RateParcel>  $parcels
     */
    public function __construct(
        public RateAddress $sender,
        public RateAddress $recipient,
        public array $parcels,
        public ?float $insuranceAmount = null,
        public ?float $codAmount = null,
        public string $contentDescription = 'Merce varia',
        public ?string $coupon = null,
        public ?string $carrierCode = null,
        public ?string $pickupDate = null,
    ) {}
}

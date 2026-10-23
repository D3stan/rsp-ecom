<?php

use App\Shipping\Rates\DTO\RateAddress;
use App\Shipping\Support\RecipientAddressCheck;

it('flags missing and malformed recipient fields', function () {
    $issues = RecipientAddressCheck::issues(new RateAddress('481', 'Cervia', '', 'IT', 'Ada Rossi', '', ''));

    expect(collect($issues)->mapWithKeys(fn ($i) => [$i->field => $i->code])->all())->toBe([
        'recipient.postal_code' => 'invalid',
        'recipient.street' => 'required',
        'recipient.phone' => 'required',
        'recipient.province' => 'required',
    ]);
});

it('accepts a complete Italian address', function () {
    expect(RecipientAddressCheck::issues(new RateAddress('48015', 'Cervia', 'RA', 'IT', 'Ada Rossi', 'Via Roma 1', '3331234567')))->toBe([]);
});

it('does not apply Italian postcode and province rules abroad', function () {
    expect(RecipientAddressCheck::issues(new RateAddress('75001', 'Paris', '', 'FR', 'Ada Rossi', '1 Rue X', '+33123456789')))->toBe([]);
});

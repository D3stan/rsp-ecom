<?php

use App\Shipping\Support\SenderAddress;

it('resolves the sender from the settings, then the config, then the store identity', function () {
    config(['shipping.labels.sender.city' => 'Roma', 'shipping.labels.sender.postal_code' => '187']);
    settings()->set('shipping.sender_city', '');
    settings()->set('shipping.sender_postal_code', '');
    settings()->set('shipping.sender_name', 'Magazzino');
    settings()->set('shipping.sender_country', '');
    settings()->set('shipping.default_country', 'IT');
    settings()->set('contact.phone', '+39 06 123');

    $sender = SenderAddress::fromSettings();

    expect($sender['name'])->toBe('Magazzino')        // setting
        ->and($sender['city'])->toBe('Roma')          // config (env-backed) when the setting is empty
        ->and($sender['postal_code'])->toBe('00187')  // Italian CAP padded
        ->and($sender['country'])->toBe('IT')         // default country
        ->and($sender['phone'])->toBe('+39 06 123');  // store identity
});

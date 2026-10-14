<?php

use App\Services\Newsletter\MarketingConsent;
use Illuminate\Validation\ValidationException;

it('declares the newsletter group with its defaults', function () {
    $registry = settings()->registry();

    expect($registry->hasGroup('newsletter'))->toBeTrue()
        ->and(settings('newsletter.enabled'))->toBeFalse()
        ->and(settings('newsletter.sender_name'))->toBe('')
        ->and(settings('newsletter.send_rate_per_minute'))->toBe(60);

    // Public: the storefront hides every signup form while it is off
    expect($registry->get('newsletter.enabled')->public)->toBeTrue()
        ->and(array_key_exists('newsletter.enabled', settings()->public()))->toBeTrue()
        ->and($registry->get('newsletter.sender_name')->public)->toBeFalse()
        ->and($registry->get('newsletter.send_rate_per_minute')->public)->toBeFalse();
});

it('bounds the send rate between 1 and 600 per minute', function () {
    expect(fn () => settings()->set('newsletter.send_rate_per_minute', 0))->toThrow(ValidationException::class);
    expect(fn () => settings()->set('newsletter.send_rate_per_minute', 601))->toThrow(ValidationException::class);

    settings()->set('newsletter.send_rate_per_minute', 600);
    expect(settings('newsletter.send_rate_per_minute'))->toBe(600);
});

it('uses the store name as sender name until one is set', function () {
    settings()->set('store.name', 'Moto Luci');
    expect(app(MarketingConsent::class)->senderName())->toBe('Moto Luci');

    settings()->set('newsletter.sender_name', 'Moto Luci News');
    expect(app(MarketingConsent::class)->senderName())->toBe('Moto Luci News');
});

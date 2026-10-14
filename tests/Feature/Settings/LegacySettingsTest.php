<?php

use App\Models\Setting;
use App\Services\StripeService;
use App\Settings\Settings;
use App\Settings\SettingsRegistry;

it('maps every legacy key to a catalogue key', function () {
    $registry = app(SettingsRegistry::class);

    expect($registry->legacyAliases())->not->toBeEmpty();
    foreach ($registry->legacyAliases() as $old => $new) {
        expect($registry->has($new))->toBeTrue("{$old} -> {$new}");
    }
});

it('keeps the static wrappers working through the new keys', function () {
    Setting::set('tax_rate', '8.75', 'string');
    Setting::set('prices_include_tax', '0', 'boolean');
    Setting::set('order_shipped_enabled', false, 'boolean');
    Setting::set('site_name', 'Moto Shop');
    Setting::set('minimum_order_amount', '25');

    expect(Setting::getTaxRate())->toBe(8.75)
        ->and(Setting::getPricesIncludeTax())->toBeFalse()
        ->and(Setting::get('tax_rate'))->toBe(8.75)
        ->and(Setting::get('default_tax_rate'))->toBe(8.75)
        ->and(Setting::get('order_shipped_enabled'))->toBeFalse()
        ->and(Setting::get('order_delivered_enabled'))->toBeTrue()
        ->and(Setting::get('site_name'))->toBe('Moto Shop')
        ->and(app(Settings::class)->get('store.name'))->toBe('Moto Shop')
        ->and(Setting::get('minimum_order_amount'))->toBe(25.0)
        ->and(Setting::get('tax.rate'))->toBe(8.75);
});

it('writes catalogue values as JSON under the new key, not under the legacy one', function () {
    Setting::set('tax_rate', '8.75');

    expect(Setting::query()->where('key', 'tax.rate')->value('value'))->toBe('8.75')
        ->and(Setting::query()->where('key', 'tax_rate')->exists())->toBeFalse();
});

it('reads and writes unknown legacy keys as raw rows', function () {
    expect(Setting::get('stripe_secret_key', 'none'))->toBe('none');

    Setting::set('stripe_secret_key', 'sk_test_raw');

    expect(Setting::get('stripe_secret_key'))->toBe('sk_test_raw')
        ->and(Setting::query()->where('key', 'stripe_secret_key')->value('value'))->toBe('sk_test_raw');
});

it('still lets StripeService fall back to the legacy database keys', function () {
    config(['cashier.secret' => null, 'cashier.key' => null]);
    Setting::set('stripe_secret_key', 'sk_test_db');
    Setting::set('stripe_public_key', 'pk_test_db');

    expect(StripeService::getSecretKey())->toBe('sk_test_db')
        ->and(StripeService::getPublicKey())->toBe('pk_test_db')
        ->and(StripeService::isConfigured())->toBeTrue();

    config(['cashier.secret' => 'sk_env', 'cashier.key' => 'pk_env']);
    expect(StripeService::getSecretKey())->toBe('sk_env');
});

it('no longer carries the unused legacy helpers', function () {
    foreach (['getMany', 'getAllSettings', 'has', 'forget', 'getSiteName', 'getSiteDescription', 'getDefaultCurrency', 'getShippingEnabled'] as $method) {
        expect(method_exists(Setting::class, $method))->toBeFalse($method);
    }
});

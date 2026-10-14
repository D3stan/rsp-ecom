<?php

use App\Models\Setting;
use App\Settings\Settings;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Artisan;

function legacySecretRows(): void
{
    foreach (['stripe_secret_key' => 'sk_live_SECRETVALUE', 'stripe_public_key' => 'pk_live_PUBLICVALUE', 'stripe_webhook_secret' => 'whsec_HOOKVALUE', 'paypal_client_secret' => 'PAYPALVALUE', 'paypal_enabled' => '0'] as $key => $value) {
        Setting::create(['key' => $key, 'value' => $value, 'type' => 'string']);
    }
    Setting::create(['key' => 'tax.rate', 'value' => '10', 'type' => 'float']);
}

it('refuses to purge legacy secrets while the environment has no Stripe keys', function () {
    legacySecretRows();
    config(['cashier.secret' => null, 'cashier.key' => 'pk_env']);

    $this->artisan('settings:purge-legacy-secrets', ['--force' => true])
        ->expectsOutputToContain('Refusing to delete')
        ->assertFailed();

    expect(Setting::where('key', 'stripe_secret_key')->exists())->toBeTrue();
});

it('purges only the legacy secret rows, never prints their values, and keeps catalogue rows', function () {
    legacySecretRows();
    config(['cashier.secret' => 'sk_env', 'cashier.key' => 'pk_env']);

    Artisan::call('settings:purge-legacy-secrets', ['--force' => true]);
    $output = Artisan::output();

    expect($output)->toContain('stripe_secret_key')->toContain('paypal_client_secret')
        ->and($output)->not->toContain('SECRETVALUE')->not->toContain('PUBLICVALUE')->not->toContain('HOOKVALUE')->not->toContain('PAYPALVALUE')
        ->and(Setting::pluck('key')->all())->toBe(['tax.rate'])
        ->and(app(Settings::class)->get('tax.rate'))->toBe(10.0);
});

it('asks for confirmation without --force and aborts on no', function () {
    legacySecretRows();
    config(['cashier.secret' => 'sk_env', 'cashier.key' => 'pk_env']);

    $this->artisan('settings:purge-legacy-secrets')->expectsConfirmation('Delete these rows permanently?', 'no')->assertFailed();

    expect(Setting::where('key', 'stripe_secret_key')->exists())->toBeTrue();
});

it('lists settings with value and source, without secrets', function () {
    app(Settings::class)->set('catalog.per_page', 24);
    Setting::set('stripe_secret_key', 'sk_live_SECRETVALUE');

    Artisan::call('settings:list');
    $output = Artisan::output();

    expect($output)->toMatch('/catalog\.per_page\s*\|\s*24\s*\|\s*customised/')
        ->and($output)->toMatch('/tax\.rate\s*\|\s*22\.0\s*\|\s*default/')
        ->and($output)->not->toContain('SECRETVALUE');

    expect(Artisan::call('settings:list', ['--group' => 'nope']))->toBe(1);
});

it('seeds only missing rows and never overwrites', function () {
    app(Settings::class)->set('tax.rate', 8);

    $this->seed(SettingsSeeder::class);
    $this->seed(SettingsSeeder::class);

    $settings = app(Settings::class);

    expect($settings->get('tax.rate'))->toBe(8.0)
        // a seeded row only repeats the default: it exists, but it is not a customisation
        ->and(Setting::where('key', 'catalog.per_page')->exists())->toBeTrue()
        ->and($settings->source('catalog.per_page'))->toBe('default')
        ->and(Setting::where('key', 'tax.rate')->count())->toBe(1)
        // env-derived defaults keep following config
        ->and($settings->source('contact.support_email'))->toBe('default')
        ->and($settings->source('store.currency'))->toBe('default');
});

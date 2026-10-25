<?php

use App\Settings\ConfigBridge;
use App\Settings\Settings;
use App\Settings\SettingsRegistry;
use Artesaos\SEOTools\Facades\SEOMeta;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

function newBridge(): ConfigBridge
{
    return new ConfigBridge(app(Settings::class), app('config'));
}

it('overlays customised settings onto config and leaves untouched ones alone', function () {
    config(['mail.contact_email' => 'from-config@shop.test']);

    app(Settings::class)->setMany([
        'contact.support_email' => 'help@shop.test',
        'shipping.delivery_days_min' => 2,
        'shipping.delivery_days_max' => 4,
        'tracking.auto_deliver' => false,
    ]);

    expect(config('mail.support_address'))->toBe('help@shop.test')
        ->and(config('shipping.delivery_estimate.delivery_min'))->toBe(2)
        ->and(config('shipping.delivery_estimate.delivery_max'))->toBe(4)
        ->and(config('shipping.tracking.auto_deliver'))->toBeFalse()
        ->and(config('mail.contact_email'))->toBe('from-config@shop.test');
});

it('turns the carriers table into the keyed config array and back on reset', function () {
    app(Settings::class)->set('shipping.carriers', [
        ['code' => 'sda', 'name' => 'SDA', 'tracking_url' => 'https://t.example/?n={number}'],
        ['code' => 'brt', 'name' => 'BRT', 'tracking_url' => ''],
    ]);

    expect(config('shipping.carriers'))->toBe([
        'sda' => ['name' => 'SDA', 'tracking_url' => 'https://t.example/?n={number}'],
        'brt' => ['name' => 'BRT'],
    ]);

    app(Settings::class)->reset('shipping.carriers');

    expect(config('shipping.carriers'))->toBe([]);
});

it('restores the previous config value when a setting is reset', function () {
    config(['mail.support_address' => 'env@shop.test']);
    $settings = app(Settings::class);

    $settings->set('contact.support_email', 'help@shop.test');
    expect(config('mail.support_address'))->toBe('help@shop.test');

    $settings->reset('contact.support_email');
    expect(config('mail.support_address'))->toBe('env@shop.test');
});

it('re-applies on JobProcessing and Looping when another process changed a value', function () {
    $settings = app(Settings::class);
    $settings->set('contact.support_email', 'one@shop.test');
    expect(config('mail.support_address'))->toBe('one@shop.test');

    // another process writes (row + version bump, but no event in THIS process): config is stale until the worker event fires
    $other = new Settings(app(SettingsRegistry::class));
    $write = function (string $value) use ($other) {
        DB::table('settings')->where('key', 'contact.support_email')->update(['value' => json_encode($value)]);
        $other->flush();
    };
    $write('two@shop.test');
    expect(config('mail.support_address'))->toBe('one@shop.test');

    Event::dispatch(new JobProcessing('database', Mockery::mock(Job::class, ['payload' => []])));
    expect(config('mail.support_address'))->toBe('two@shop.test');

    $write('three@shop.test');
    Event::dispatch(new Looping('database', 'default'));
    expect(config('mail.support_address'))->toBe('three@shop.test');
});

it('does nothing when the settings table is missing', function () {
    config(['mail.support_address' => 'env@shop.test']);
    Schema::drop('settings');

    newBridge()->apply(true);

    expect(config('mail.support_address'))->toBe('env@shop.test');
});

it('only declares bridges for config paths that exist', function () {
    foreach (app(SettingsRegistry::class)->bridged() as $definition) {
        foreach ($definition->configBridge as $path) {
            expect(config()->has($path))->toBeTrue("config path [{$path}] of {$definition->key}");
        }
    }
});

it('reaches the SEOTools defaults because the package builds its singletons lazily', function () {
    app(Settings::class)->setMany([
        'seo.default_description' => 'A custom default description',
        'seo.keywords' => ['alpha', 'beta'],
    ]);

    app()->forgetInstance('seotools.metatags');
    app()->forgetInstance('seotools.opengraph');

    expect(SEOMeta::getDescription())->toBe('A custom default description')
        ->and(SEOMeta::getKeywords())->toBe(['alpha', 'beta']);
});

it('does not overwrite non-empty baseline config with empty string for shipping sender settings', function () {
    config([
        'shipping.labels.sender.postal_code' => '09121',
        'shipping.labels.sender.city' => 'Cagliari',
    ]);

    $settings = app(Settings::class);
    $settings->setMany([
        'shipping.sender_postal_code' => '',
        'shipping.sender_city' => 'Sassari',
    ]);

    // postal_code has baseline '09121', DB has empty string -> should not overwrite baseline
    expect(config('shipping.labels.sender.postal_code'))->toBe('09121')
        // city was set to non-empty 'Sassari' -> should be updated
        ->and(config('shipping.labels.sender.city'))->toBe('Sassari');
});

<?php

use App\Models\Setting;
use App\Models\SettingChange;
use App\Models\User;
use App\Settings\Settings;
use App\Settings\SettingsRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

function freshSettings(): Settings
{
    return new Settings(app(SettingsRegistry::class));
}

it('returns typed defaults when no row exists', function () {
    $settings = app(Settings::class);

    expect($settings->get('tax.rate'))->toBe(22.0)
        ->and($settings->get('tax.prices_include_tax'))->toBeTrue()
        ->and($settings->get('orders.public_link_days'))->toBe(30)
        ->and($settings->get('shipping.destinations'))->toBe(['IT', 'DE', 'GB', 'FR', 'ES'])
        ->and($settings->get('store.currency'))->toBe('EUR')
        ->and($settings->source('tax.rate'))->toBe('default')
        ->and(settings('tax.rate'))->toBe(22.0)
        ->and(settings('nope.nope', 'fallback'))->toBe('fallback');
});

it('casts stored values by type, never to numeric strings', function () {
    $settings = app(Settings::class);
    $settings->set('tax.rate', '8.75');
    $settings->set('orders.max_qty_per_line', '5');
    $settings->set('tax.prices_include_tax', '0');
    $settings->set('shipping.destinations', ['it', 'de']);

    expect($settings->get('tax.rate'))->toBe(8.75)
        ->and($settings->get('orders.max_qty_per_line'))->toBe(5)
        ->and($settings->get('tax.prices_include_tax'))->toBeFalse()
        ->and($settings->get('shipping.destinations'))->toBe(['IT', 'DE'])
        ->and($settings->source('tax.rate'))->toBe('customised');
});

it('reads legacy plain-string rows and falls back to the default for unreadable ones', function () {
    Setting::create(['key' => 'tax.rate', 'value' => '19', 'type' => 'string']);
    Setting::create(['key' => 'orders.public_link_days', 'value' => 'abc', 'type' => 'string']);
    Setting::create(['key' => 'tax.prices_include_tax', 'value' => '', 'type' => 'string']);

    $settings = app(Settings::class);

    expect($settings->get('tax.rate'))->toBe(19.0)
        ->and($settings->get('orders.public_link_days'))->toBe(30)
        ->and($settings->get('tax.prices_include_tax'))->toBeFalse();
});

it('invalidates other instances when the version is bumped', function () {
    $a = freshSettings();
    $b = freshSettings();

    expect($a->get('catalog.per_page'))->toBe(12);

    $b->set('catalog.per_page', 24);

    expect($a->get('catalog.per_page'))->toBe(24);
});

it('never caches a missing row as its default', function () {
    $a = freshSettings();
    expect($a->get('catalog.per_page'))->toBe(12);

    // A row appears without going through the service (another process, a seeder using DB::table)
    DB::table('settings')->insert(['key' => 'catalog.per_page', 'value' => '30', 'type' => 'int', 'created_at' => now(), 'updated_at' => now()]);
    freshSettings()->flush();

    expect($a->get('catalog.per_page'))->toBe(30);
});

it('degrades to defaults when the database cannot be read', function () {
    $settings = freshSettings();
    Schema::drop('settings');

    expect($settings->get('tax.rate'))->toBe(22.0)
        ->and($settings->public())->toHaveKey('tax.rate');
});

it('degrades to direct database reads when the cache is broken', function () {
    config([
        'cache.stores.broken' => ['driver' => 'database', 'table' => 'no_such_cache_table', 'connection' => null],
        'settings.cache.store' => 'broken',
    ]);

    DB::table('settings')->insert(['key' => 'catalog.per_page', 'value' => '40', 'type' => 'int', 'created_at' => now(), 'updated_at' => now()]);

    $settings = freshSettings();

    expect($settings->get('catalog.per_page'))->toBe(40)
        ->and($settings->version())->toBeNull();
    $settings->flush(); // must not throw
});

it('writes audit rows with the user, skips unchanged values and resets to the default', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $settings = app(Settings::class);

    expect($settings->setMany(['tax.rate' => 10, 'catalog.per_page' => 12], $admin))->toBe(['tax.rate']);

    $change = SettingChange::where('key', 'tax.rate')->sole();
    expect($change->user_id)->toBe($admin->id)
        ->and(json_decode($change->old_value))->toEqual(22.0)
        ->and(json_decode($change->new_value))->toEqual(10.0)
        ->and(SettingChange::where('key', 'catalog.per_page')->exists())->toBeFalse();

    expect(DB::table('settings')->where('key', 'tax.rate')->value('updated_by'))->toBe($admin->id);

    $settings->reset('tax.rate', $admin);

    expect($settings->get('tax.rate'))->toBe(22.0)
        ->and($settings->source('tax.rate'))->toBe('default')
        ->and(DB::table('settings')->where('key', 'tax.rate')->exists())->toBeFalse()
        ->and(SettingChange::where('key', 'tax.rate')->count())->toBe(2);
});

it('validates on write with the definition rules and keeps the whole batch atomic', function () {
    $settings = app(Settings::class);

    try {
        $settings->setMany(['tax.rate' => 5, 'catalog.per_page' => 1000]);
        $this->fail('expected a ValidationException');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['catalog.per_page']);
    }

    expect($settings->get('tax.rate'))->toBe(22.0);

    expect(fn () => $settings->set('tax.rate', 'abc'))->toThrow(ValidationException::class);
    expect(fn () => $settings->set('tax.rate', 101))->toThrow(ValidationException::class);
    expect(fn () => $settings->set('contact.support_email', ''))->toThrow(ValidationException::class);
    expect(fn () => $settings->set('shipping.destinations', ['ITA']))->toThrow(ValidationException::class);
    expect(fn () => $settings->set('shipping.destinations', []))->toThrow(ValidationException::class);
});

it('enforces min <= max and the default country among the destinations', function () {
    $settings = app(Settings::class);

    expect(fn () => $settings->setMany(['shipping.dispatch_days_min' => 5, 'shipping.dispatch_days_max' => 2]))
        ->toThrow(ValidationException::class);
    expect(fn () => $settings->set('shipping.delivery_days_max', 1))->toThrow(ValidationException::class); // min is 3
    expect(fn () => $settings->set('shipping.default_country', 'US'))->toThrow(ValidationException::class);
    expect(fn () => $settings->set('shipping.destinations', ['DE', 'FR']))->toThrow(ValidationException::class); // default IT removed

    $settings->setMany(['shipping.destinations' => ['DE', 'FR'], 'shipping.default_country' => 'DE']);
    expect($settings->get('shipping.default_country'))->toBe('DE');
});

it('validates the carriers table', function () {
    $settings = app(Settings::class);
    $rows = [['code' => 'sda', 'name' => 'SDA', 'tracking_url' => 'https://t.example/track?n={number}']];

    $settings->set('shipping.carriers', $rows);
    expect($settings->get('shipping.carriers'))->toBe($rows);

    expect(fn () => $settings->set('shipping.carriers', [['code' => 'Bad Code', 'name' => 'X', 'tracking_url' => '']]))->toThrow(ValidationException::class);
    expect(fn () => $settings->set('shipping.carriers', [['code' => 'a', 'name' => 'X', 'tracking_url' => 'https://t.example/no-placeholder']]))->toThrow(ValidationException::class);
    expect(fn () => $settings->set('shipping.carriers', [['code' => 'a', 'name' => 'A', 'tracking_url' => ''], ['code' => 'a', 'name' => 'B', 'tracking_url' => '']]))->toThrow(ValidationException::class);
});

it('rejects unknown keys and read-only settings', function () {
    $settings = app(Settings::class);

    expect(fn () => $settings->get('nope.nothing'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $settings->set('nope.nothing', 1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $settings->set('store.currency', 'USD'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $settings->reset('store.currency'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $settings->source('nope.nothing'))->toThrow(InvalidArgumentException::class);
});

it('exposes only public settings to the frontend', function () {
    $public = app(Settings::class)->public();

    expect($public)->toHaveKeys(['store.name', 'tax.rate', 'store.currency'])
        ->and($public)->not->toHaveKeys(['store.company_iban', 'contact.admin_notification_email', 'contact.form_recipient', 'email.enabled']);
});

it('keeps legacy secret rows out of the cache', function () {
    Setting::set('stripe_secret_key', 'sk_test_x');
    app(Settings::class)->get('tax.rate');

    $cached = json_encode(Cache::get('settings:v'.app(Settings::class)->version()));

    expect($cached)->not->toContain('sk_test_x');
});

it('sees rows written through the model (seeders, factories) because the model flushes the cache', function () {
    $settings = app(Settings::class);
    expect($settings->get('catalog.per_page'))->toBe(12);

    Setting::factory()->forKey('catalog.per_page', 36, 'int')->create();

    expect($settings->get('catalog.per_page'))->toBe(36);
});

it('trusts its in-memory copy inside the recheck window and re-reads the shared version after refresh()', function () {
    config(['settings.cache.recheck_seconds' => 60]);

    $reader = freshSettings();
    expect($reader->get('tax.rate'))->toBe(22.0);

    // another process (a second instance) changes the value and bumps the shared version
    freshSettings()->set('tax.rate', 19);

    expect($reader->get('tax.rate'))->toBe(22.0); // still inside the window: no cache lookup at all

    $reader->refresh(); // what every queued job / scheduler tick does first

    expect($reader->get('tax.rate'))->toBe(19.0);
});

it('checks the shared version on every read when the recheck window is disabled', function () {
    config(['settings.cache.recheck_seconds' => 0]);

    $reader = freshSettings();
    $reader->get('tax.rate');

    freshSettings()->set('tax.rate', 17);

    expect($reader->get('tax.rate'))->toBe(17.0);
});

<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const LEGACY_MIGRATION = 'database/migrations/2026_10_14_100100_merge_legacy_settings.php';

beforeEach(function () {
    $this->file = tempnam(sys_get_temp_dir(), 'settings-migration-').'.sqlite';
    touch($this->file);

    config(['database.connections.legacyfile' => [
        'driver' => 'sqlite',
        'database' => $this->file,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);
    DB::purge('legacyfile');

    $this->migrate = fn () => Artisan::call('migrate', ['--database' => 'legacyfile', '--force' => true]);
    // Only the two settings migrations (later migrations, e.g. orders.tax_rate, must not matter here)
    $this->rollback = fn () => Artisan::call('migrate:rollback', [
        '--database' => 'legacyfile',
        '--path' => ['database/migrations/2026_10_14_100000_prepare_settings_for_registry.php', LEGACY_MIGRATION],
        '--force' => true,
    ]);
    $this->db = fn () => DB::connection('legacyfile');
});

afterEach(function () {
    DB::purge('legacyfile');
    @unlink($this->file);
});

function legacyFixtures(): array
{
    return [
        ['tax_rate', '19', 'string'],
        ['default_tax_rate', '22', 'string'],
        ['prices_include_tax', '0', 'boolean'],
        ['site_name', 'My Shop', 'string'],
        ['contact_email', '', 'string'],
        ['contact_phone', '+39 123 456', 'string'],
        ['company_iban', '', 'string'],
        ['email_notifications_enabled', '', 'boolean'],
        ['order_confirmation_enabled', '1', 'boolean'],
        ['order_shipped_enabled', '0', 'boolean'],
        ['order_cancelled_enabled', 'true', 'boolean'],
        ['minimum_order_amount', '25.50', 'string'],
        ['admin_notification_email', 'admin@rspecommerce.com', 'string'],
        ['tax_number', 'IT01234567890', 'string'],
        ['social_media', json_encode(['instagram' => 'https://instagram.com/shop', 'twitter' => 'https://x.com/shop']), 'json'],
        // dead keys
        ['free_shipping_threshold', '100.00', 'string'],
        ['shipping_zones', '[]', 'json'],
        ['stripe_webhook_secret', 'whsec_x', 'string'],
        ['paypal_client_secret', 'pp_x', 'string'],
        ['maintenance_mode', '0', 'boolean'],
        ['stripe_settings', '{"secret_key":"sk_test_..."}', 'json'],
        ['products_per_page', '12', 'string'],
        // kept as raw legacy rows
        ['stripe_secret_key', 'sk_test_keep', 'string'],
        ['stripe_public_key', 'pk_test_keep', 'string'],
    ];
}

function seedLegacyRows(callable $db): void
{
    foreach (legacyFixtures() as [$key, $value, $type]) {
        $db()->table('settings')->insert(['key' => $key, 'value' => $value, 'type' => $type, 'created_at' => now(), 'updated_at' => now()]);
    }
}

function storedValue(callable $db, string $key): mixed
{
    $raw = $db()->table('settings')->where('key', $key)->value('value');

    return $raw === null ? null : json_decode($raw, true);
}

it('converts the legacy rows, drops the dead keys, keeps the Stripe rows, and reverses', function () {
    // Everything but the two settings migrations, then the legacy data, then the two migrations
    ($this->migrate)();
    ($this->rollback)();
    expect(Schema::connection('legacyfile')->hasTable('setting_changes'))->toBeFalse();

    seedLegacyRows($this->db);
    ($this->migrate)();

    $db = $this->db;

    expect(storedValue($db, 'tax.rate'))->toBe(19.0)            // tax_rate wins over default_tax_rate
        ->and(storedValue($db, 'tax.prices_include_tax'))->toBeFalse()
        ->and(storedValue($db, 'store.name'))->toBe('My Shop')
        ->and(storedValue($db, 'contact.phone'))->toBe('+39 123 456')
        ->and(storedValue($db, 'email.enabled'))->toBeFalse()   // legacy '' meant off
        ->and(storedValue($db, 'email.order_confirmation'))->toBeTrue()
        ->and(storedValue($db, 'email.order_shipped'))->toBeFalse()
        ->and(storedValue($db, 'email.order_cancelled'))->toBeTrue()
        ->and(storedValue($db, 'orders.min_amount'))->toBe(25.5)
        ->and(storedValue($db, 'contact.admin_notification_email'))->toBe('admin@rspecommerce.com')
        ->and(storedValue($db, 'store.vat_number'))->toBe('IT01234567890')
        ->and(storedValue($db, 'social.instagram'))->toBe('https://instagram.com/shop')
        ->and($db()->table('settings')->where('key', 'contact.support_email')->exists())->toBeFalse() // '' = never set
        ->and($db()->table('settings')->where('key', 'store.company_iban')->exists())->toBeFalse()
        ->and($db()->table('settings')->where('key', 'tax.rate')->value('type'))->toBe('float');

    $remaining = $db()->table('settings')->pluck('key')->all();
    foreach (['tax_rate', 'default_tax_rate', 'site_name', 'contact_email', 'tax_number', 'social_media', 'free_shipping_threshold',
        'shipping_zones', 'maintenance_mode', 'stripe_settings', 'products_per_page'] as $gone) {
        expect($remaining)->not->toContain($gone);
    }
    // credential rows stay until `settings:purge-legacy-secrets` (the owner may still need to copy them to .env)
    expect($remaining)->toContain('stripe_secret_key')->toContain('stripe_public_key')
        ->toContain('stripe_webhook_secret')->toContain('paypal_client_secret')
        ->and($db()->table('settings')->where('key', 'stripe_secret_key')->value('value'))->toBe('sk_test_keep');

    // Idempotent: running the data migration again changes nothing
    $before = $db()->table('settings')->orderBy('key')->get(['key', 'value'])->toJson();
    DB::setDefaultConnection('legacyfile');
    try {
        (require base_path(LEGACY_MIGRATION))->up();
    } finally {
        DB::setDefaultConnection('sqlite');
    }
    expect($db()->table('settings')->orderBy('key')->get(['key', 'value'])->toJson())->toBe($before);

    // down(): legacy rows are back, catalogue rows are gone, schema reverted
    ($this->rollback)();

    expect($db()->table('settings')->where('key', 'tax_rate')->value('value'))->toBe('19')
        ->and($db()->table('settings')->where('key', 'prices_include_tax')->value('value'))->toBe('0')
        ->and($db()->table('settings')->where('key', 'order_shipped_enabled')->value('value'))->toBe('0')
        ->and($db()->table('settings')->where('key', 'tax_number')->value('value'))->toBe('IT01234567890')
        ->and(json_decode($db()->table('settings')->where('key', 'social_media')->value('value'), true))->toBe(['instagram' => 'https://instagram.com/shop'])
        ->and($db()->table('settings')->where('key', 'like', '%.%')->count())->toBe(0)
        ->and($db()->table('settings')->where('key', 'stripe_secret_key')->exists())->toBeTrue()
        ->and(Schema::connection('legacyfile')->hasTable('setting_changes'))->toBeFalse();

    // and up again
    ($this->migrate)();
    expect(storedValue($db, 'tax.rate'))->toBe(19.0)
        ->and(Schema::connection('legacyfile')->hasTable('setting_changes'))->toBeTrue();
});

it('lets the settings type column hold registry types after the schema migration', function () {
    ($this->migrate)();

    $this->db->__invoke()->table('settings')->insert(['key' => 'tax.rate', 'value' => '22.0', 'type' => 'float', 'created_at' => now(), 'updated_at' => now()]);

    expect($this->db->__invoke()->table('settings')->where('key', 'tax.rate')->value('type'))->toBe('float');
});

it('leaves a legacy value that is invalid for the new setting in place instead of guessing', function () {
    ($this->migrate)();
    ($this->rollback)();
    $db = $this->db;
    $db()->table('settings')->insert(['key' => 'tax_rate', 'value' => 'abc', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()]);

    ($this->migrate)();

    expect($db()->table('settings')->where('key', 'tax_rate')->value('value'))->toBe('abc')
        ->and($db()->table('settings')->where('key', 'tax.rate')->exists())->toBeFalse();
});

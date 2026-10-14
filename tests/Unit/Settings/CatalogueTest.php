<?php

use App\Settings\SettingsRegistry;
use App\Settings\SettingType;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

function catalogue(): SettingsRegistry
{
    return app(SettingsRegistry::class);
}

/** @return array<string, string> flat translations of a locale */
function settingsLang(string $locale): array
{
    return json_decode(file_get_contents(base_path("lang/{$locale}.json")), true);
}

it('declares unique, well-formed keys in declared groups', function () {
    $keys = array_column(config('settings.definitions'), 'key');

    expect($keys)->toBe(array_values(array_unique($keys)));

    foreach (catalogue()->all() as $key => $definition) {
        expect($key)->toBe("{$definition->group}.{$definition->name}")
            ->and(catalogue()->hasGroup($definition->group))->toBeTrue();
    }
});

it('has defaults that satisfy their own type and rules', function () {
    foreach (catalogue()->all() as $key => $definition) {
        expect($definition->type->accepts($definition->default))->toBeTrue("{$key} default has the wrong shape");

        $validator = Validator::make(['value' => $definition->default], $definition->validationRules('value'));
        expect($validator->passes())->toBeTrue("{$key} default fails its rules: ".json_encode($validator->errors()->all()));
    }
});

it('translates every label, help and group text in all supported locales', function () {
    foreach (config('app.supported_locales') as $locale) {
        $lang = settingsLang($locale);

        foreach (catalogue()->all() as $key => $definition) {
            expect($lang)->toHaveKey($definition->labelKey())->toHaveKey($definition->helpKey());
            expect(trim($lang[$definition->labelKey()]))->not->toBe('');
            expect(trim($lang[$definition->helpKey()]))->not->toBe('');
        }

        foreach (catalogue()->groupNames() as $group) {
            expect($lang)->toHaveKey("settings.group.{$group}.label")->toHaveKey("settings.group.{$group}.description");
        }
    }
});

it('keeps the same placeholders in every locale for all settings.* texts', function () {
    $placeholders = fn (string $text): array => tap(
        preg_match_all('/:[a-z_]+|\{[a-z_]+\}/i', $text, $m) ? $m[0] : [],
        fn (&$list) => sort($list)
    );

    $reference = array_filter(settingsLang('en'), fn ($v, $k) => str_starts_with($k, 'settings.'), ARRAY_FILTER_USE_BOTH);
    expect($reference)->not->toBeEmpty();

    foreach (config('app.supported_locales') as $locale) {
        $lang = settingsLang($locale);

        foreach ($reference as $key => $text) {
            expect($lang)->toHaveKey($key);
            expect($placeholders($lang[$key]))->toBe($placeholders($text), "{$locale}: {$key}");
        }

        $extra = array_diff(array_keys(array_filter($lang, fn ($v, $k) => str_starts_with($k, 'settings.'), ARRAY_FILTER_USE_BOTH)), array_keys($reference));
        expect($extra)->toBe([], "{$locale} has settings.* keys that en lacks");
    }
});

it('never declares anything that looks like a secret', function () {
    foreach (array_keys(catalogue()->all()) as $key) {
        expect($key)->not->toMatch('/(secret|password|passwd|token|api_?key|_key$|credential|private|webhook)/i');
    }
});

it('marks only safe settings as public', function () {
    $never = ['store.company_iban', 'store.account_holder', 'contact.admin_notification_email', 'contact.form_recipient'];

    foreach (catalogue()->public() as $key => $definition) {
        expect($never)->not->toContain($key)
            ->and($definition->sensitive)->toBeFalse()
            ->and($definition->group)->not->toBeIn(['email', 'system', 'tracking', 'reviews']);
    }

    foreach (catalogue()->all() as $definition) {
        if ($definition->sensitive) {
            expect($definition->public)->toBeFalse();
        }
    }
});

it('does not allow writing readonly or env settings', function () {
    foreach (catalogue()->all() as $key => $definition) {
        if ($definition->access !== 'admin') {
            expect($definition->isWritable())->toBeFalse();
            expect(fn () => app(App\Settings\Settings::class)->set($key, $definition->default))->toThrow(InvalidArgumentException::class);
        }
    }

    expect(catalogue()->get('store.currency')->isWritable())->toBeFalse();
});

it('has no free-shipping, flat-rate, zone or tax-switch settings', function () {
    foreach (array_keys(catalogue()->all()) as $key) {
        expect($key)->not->toMatch('/free_shipping|flat_rate|zone|shipping_enabled|tax_enabled|shipping_cost/');
    }
});

it('uses the documented ranges and defaults of the plan', function () {
    $r = catalogue();

    expect($r->get('tax.rate')->default)->toBe(22.0)
        ->and($r->get('tax.prices_include_tax')->default)->toBeTrue()
        ->and($r->get('catalog.per_page')->min)->toBe(6)
        ->and($r->get('catalog.per_page')->max)->toBe(60)
        ->and($r->get('orders.public_link_days')->max)->toBe(365)
        ->and($r->get('orders.order_notes_max')->default)->toBe(500)
        ->and($r->get('reviews.auto_approve')->default)->toBeTrue()
        ->and($r->get('shipping.dispatch_days_max')->atLeast)->toBe('shipping.dispatch_days_min')
        ->and($r->get('shipping.carriers')->type)->toBe(SettingType::Json);
});

<?php

use App\Settings\Settings;
use App\Settings\SettingsRegistry;
use App\Settings\SettingType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Legacy settings merge (.ai/rules/settings.md, "Legacy alias map").
 *
 * up():   rows saved under the old keys (tax_rate, site_name, order_shipped_enabled...) are rewritten under their
 *         catalogue keys with JSON values (mixed '' / '0' / '1' / numeric strings are parsed by the TARGET type),
 *         then the old rows and the dead keys (saved by the old admin page but never read) are deleted.
 *         stripe_secret_key / stripe_public_key are NOT touched: production may still depend on them
 *         (php artisan settings:purge-legacy-secrets removes them once the keys are in .env).
 *         Idempotent: a target that already exists is never overwritten.
 * down(): writes the old rows back from the new ones (first alias of each key) and deletes every catalogue row.
 *         Dead keys are gone for good, and values changed since only exist under the new keys.
 */
return new class extends Migration
{
    /** Saved by the old page, read by nothing. */
    private const DEAD_KEYS = [
        'site_description', 'default_currency', 'timezone', 'stripe_enabled', 'shipping_enabled', 'free_shipping_threshold',
        'default_shipping_cost', 'shipping_calculation_method', 'allow_international_shipping', 'processing_time_days',
        'shipping_zones', 'tax_enabled', 'tax_calculation_method', 'collect_tax_for_digital_products',
        'newsletter_enabled', 'from_email', 'from_name', 'stripe_settings', 'email_settings', 'maintenance_mode',
        'products_per_page', 'max_cart_items',
    ];

    private const SOCIAL_NETWORKS = ['instagram', 'tiktok', 'facebook'];

    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $registry = SettingsRegistry::fromConfig();
        $rows = DB::table('settings')->get()->keyBy('key');

        /** @var array<string, mixed> $converted new key => typed value */
        $converted = [];
        $remove = [];

        foreach ($registry->legacyAliases() as $old => $new) {
            $row = $rows->get($old);

            if ($row === null) {
                continue;
            }

            // The target already exists (earlier run, or tax_rate converted before default_tax_rate): the legacy row is a leftover
            if ($rows->has($new) || array_key_exists($new, $converted)) {
                $remove[] = $old;

                continue;
            }

            $parsed = $this->parse($registry, $new, (string) $row->value);

            if ($parsed['ok']) {
                $converted[$new] = $parsed['value'];
                $remove[] = $old;
            } elseif (trim((string) $row->value) === '') {
                $remove[] = $old;
            } else {
                Log::warning('Legacy setting left in place: its value is not valid for the new setting', ['legacy_key' => $old, 'new_key' => $new]);
            }
        }

        $vat = $rows->get('tax_number');
        if ($vat !== null) {
            $parsed = $this->parse($registry, 'store.vat_number', (string) $vat->value);
            if ($parsed['ok'] && ! $rows->has('store.vat_number') && ! array_key_exists('store.vat_number', $converted)) {
                $converted['store.vat_number'] = $parsed['value'];
            }
            $remove[] = 'tax_number';
        }

        $social = $rows->get('social_media');
        if ($social !== null) {
            foreach ($this->socialLinks((string) $social->value) as $network => $url) {
                $key = "social.{$network}";
                $parsed = $this->parse($registry, $key, $url);
                if ($parsed['ok'] && ! $rows->has($key) && ! array_key_exists($key, $converted)) {
                    $converted[$key] = $parsed['value'];
                }
            }
            $remove[] = 'social_media';
        }

        DB::transaction(function () use ($registry, $converted, $remove) {
            foreach ($converted as $key => $value) {
                $definition = $registry->get($key);
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => $definition->encode($value),
                    'type' => $definition->type->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('settings')->whereIn('key', [...self::DEAD_KEYS, ...$remove])->delete();
        });

        $this->clearCaches($rows->keys()->all());
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $registry = SettingsRegistry::fromConfig();
        $rows = DB::table('settings')->get()->keyBy('key');

        $reverse = [];
        foreach ($registry->legacyAliases() as $old => $new) {
            $reverse[$new] ??= $old;
        }
        $reverse['store.vat_number'] = 'tax_number';

        $legacy = [];
        $social = [];

        foreach ($rows as $key => $row) {
            $definition = $registry->find($key);

            if ($definition === null) {
                continue;
            }

            $value = $definition->decode($row->value);

            if (str_starts_with($key, 'social.')) {
                $social[substr($key, strlen('social.'))] = $value;
            } elseif (isset($reverse[$key])) {
                $legacy[$reverse[$key]] = [
                    'value' => is_bool($value) ? ($value ? '1' : '0') : (is_array($value) ? json_encode($value) : (string) $value),
                    'type' => is_bool($value) ? 'boolean' : 'string',
                ];
            }
        }

        if ($social !== []) {
            $legacy['social_media'] = ['value' => json_encode($social), 'type' => 'json'];
        }

        DB::transaction(function () use ($registry, $legacy) {
            foreach ($legacy as $key => $attributes) {
                if (! DB::table('settings')->where('key', $key)->exists()) {
                    DB::table('settings')->insert([...$attributes, 'key' => $key, 'created_at' => now(), 'updated_at' => now()]);
                }
            }

            DB::table('settings')->whereIn('key', array_keys($registry->all()))->delete();
        });

        $this->clearCaches(array_keys($legacy));
    }

    /**
     * @return array{ok: bool, value: mixed}
     */
    private function parse(SettingsRegistry $registry, string $key, string $raw): array
    {
        $definition = $registry->get($key);
        $raw = trim($raw);

        // A legacy boolean saved as '' meant "off"; for every other type an empty string means "never set"
        if ($raw === '' && $definition->type !== SettingType::Bool) {
            return ['ok' => false, 'value' => null];
        }

        $value = $definition->coerce($raw);

        if (! $definition->type->accepts($value)) {
            return ['ok' => false, 'value' => null];
        }

        $valid = Validator::make(['value' => $value], $definition->validationRules('value'))->passes();

        return ['ok' => $valid, 'value' => $value];
    }

    /**
     * @return array<string, string>
     */
    private function socialLinks(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        $links = [];
        foreach (self::SOCIAL_NETWORKS as $network) {
            if (isset($decoded[$network]) && is_string($decoded[$network])) {
                $links[$network] = $decoded[$network];
            }
        }

        return $links;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function clearCaches(array $keys): void
    {
        try {
            foreach ($keys as $key) {
                Cache::forget('setting_'.$key);
            }

            app(Settings::class)->flush();
        } catch (Throwable) {
            // no cache available (e.g. a fresh database): nothing to clear
        }
    }
};

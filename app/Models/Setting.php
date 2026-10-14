<?php

namespace App\Models;

use App\Settings\Settings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Storage row of the `settings` table (key, JSON value, informational type, updated_by).
 *
 * Reading and writing settings is the job of App\Settings\Settings (or the settings() helper). The static
 * get/set/getTaxRate/getPricesIncludeTax wrappers below are DEPRECATED: they keep old call sites working by
 * mapping legacy keys (tax_rate, site_name, order_shipped_enabled...) to the catalogue keys through the alias
 * map in config/settings.php. Keys that are not part of the catalogue (stripe_secret_key, stripe_public_key,
 * until `settings:purge-legacy-secrets` removes them) are read and written as raw rows, never cached.
 */
class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'updated_by',
    ];

    protected static function booted(): void
    {
        // Rows written outside the Settings service (seeders, factories, tinker) must not leave a stale cache behind
        $flush = fn () => app(Settings::class)->flush();

        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * @deprecated use settings('group.key') / App\Settings\Settings
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = app(Settings::class);
        $resolved = $settings->resolveKey($key);

        if ($resolved !== null) {
            return $settings->get($resolved);
        }

        $value = static::query()->where('key', $key)->value('value');

        return $value ?? $default;
    }

    /**
     * @deprecated use App\Settings\Settings::set()
     */
    public static function set(string $key, mixed $value, string $type = 'string'): ?self
    {
        $settings = app(Settings::class);
        $resolved = $settings->resolveKey($key);

        if ($resolved !== null) {
            $settings->set($resolved, $value);

            return static::query()->where('key', $resolved)->first();
        }

        // Legacy raw row (e.g. stripe_secret_key): plain string, as before
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (is_array($value) ? json_encode($value) : (string) $value), 'type' => $type]
        );
    }

    /**
     * @deprecated use settings('tax.rate')
     */
    public static function getTaxRate(): float
    {
        return (float) app(Settings::class)->get('tax.rate');
    }

    /**
     * @deprecated use settings('tax.prices_include_tax')
     */
    public static function getPricesIncludeTax(): bool
    {
        return (bool) app(Settings::class)->get('tax.prices_include_tax');
    }
}

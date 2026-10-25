<?php

namespace App\Models;

use App\Settings\Settings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

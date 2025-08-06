<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
    ];

    // Cache key prefix
    private const CACHE_PREFIX = 'setting_';
    private const CACHE_TTL = 3600; // 1 hour

    // Boot method to clear cache when settings change
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($setting) {
            Cache::forget(self::CACHE_PREFIX . $setting->key);
        });

        static::deleted(function ($setting) {
            Cache::forget(self::CACHE_PREFIX . $setting->key);
        });
    }

    // Get setting value with proper type casting
    public function getValueAttribute($value)
    {
        return match ($this->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    // Set setting value with proper type handling
    public function setValueAttribute($value)
    {
        $this->attributes['value'] = match ($this->type) {
            'boolean' => $value ? '1' : '0',
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };
    }

    // Static methods for easy setting management
    public static function get(string $key, $default = null)
    {
        return Cache::remember(
            self::CACHE_PREFIX . $key,
            self::CACHE_TTL,
            function () use ($key, $default) {
                $setting = static::where('key', $key)->first();
                return $setting ? $setting->value : $default;
            }
        );
    }

    public static function set(string $key, $value, string $type = 'string'): self
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );

        Cache::forget(self::CACHE_PREFIX . $key);
        
        return $setting;
    }

    public static function has(string $key): bool
    {
        return static::where('key', $key)->exists();
    }

    public static function forget(string $key): bool
    {
        Cache::forget(self::CACHE_PREFIX . $key);
        return static::where('key', $key)->delete() > 0;
    }

    // Get multiple settings
    public static function getMany(array $keys): array
    {
        $settings = [];
        
        foreach ($keys as $key) {
            $settings[$key] = static::get($key);
        }
        
        return $settings;
    }

    // Get all settings as key-value pairs
    public static function getAllSettings(): array
    {
        return static::pluck('value', 'key')->toArray();
    }

    // Common settings getters
    public static function getSiteName(): string
    {
        return static::get('site_name', 'Ecommerce Store');
    }

    public static function getSiteDescription(): string
    {
        return static::get('site_description', '');
    }

    public static function getDefaultCurrency(): string
    {
        return static::get('default_currency', 'EUR');
    }

    public static function getTaxRate(): float
    {
        return (float) static::get('tax_rate', 0);
    }

    public static function getPricesIncludeTax(): bool
    {
        return (bool) static::get('prices_include_tax', false);
    }

    public static function getShippingEnabled(): bool
    {
        return static::get('shipping_enabled', true);
    }
}

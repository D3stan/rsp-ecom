<?php

namespace App\Settings;

enum SettingType: string
{
    case String = 'string';
    case Text = 'text';
    case Email = 'email';
    case Url = 'url';
    case Int = 'int';
    case Float = 'float';
    case Bool = 'bool';
    case Enum = 'enum';
    case List = 'list';
    case Countries = 'countries';
    case Json = 'json';

    public function isText(): bool
    {
        return in_array($this, [self::String, self::Text, self::Email, self::Url, self::Enum], true);
    }

    public function isCollection(): bool
    {
        return in_array($this, [self::List, self::Countries, self::Json], true);
    }

    public function coerce(mixed $value): mixed
    {
        return match ($this) {
            self::String, self::Text, self::Url, self::Enum => $this->coerceString($value),
            self::Email => is_string($value) || $value === null ? mb_strtolower(trim((string) $value)) : $value,
            self::Int => $this->coerceInt($value),
            self::Float => $this->coerceFloat($value),
            self::Bool => $this->coerceBool($value),
            self::List => $this->coerceList($value, false),
            self::Countries => $this->coerceList($value, true),
            self::Json => is_string($value) ? (json_decode($value, true) ?? $value) : $value,
        };
    }

    public function accepts(mixed $value): bool
    {
        return match ($this) {
            self::String, self::Text, self::Email, self::Url, self::Enum => is_string($value),
            self::Int => is_int($value),
            self::Float => is_float($value) || is_int($value),
            self::Bool => is_bool($value),
            self::List, self::Countries => is_array($value) && array_is_list($value) && collect($value)->every(fn ($v) => is_string($v)),
            self::Json => is_array($value),
        };
    }

    private function coerceString(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }

        return is_scalar($value) && ! is_bool($value) ? trim((string) $value) : $value;
    }

    private function coerceInt(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if (is_int($value)) {
            return $value;
        }

        if ((is_string($value) || is_float($value)) && is_numeric($value) && (float) $value == (int) (float) $value && abs((float) $value) < PHP_INT_MAX) {
            return (int) (float) $value;
        }

        return $value;
    }

    private function coerceFloat(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim(str_replace(',', '.', $value));
        }

        return (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) ? (float) $value : $value;
    }

    private function coerceBool(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
        }

        return match (true) {
            in_array($value, [1, '1', 'true', 'on', 'yes'], true) => true,
            in_array($value, [0, '0', 'false', 'off', 'no', '', null], true) => false,
            default => $value,
        };
    }

    private function coerceList(mixed $value, bool $countries): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        $items = array_map(function ($item) use ($countries) {
            if (! is_string($item)) {
                return $item;
            }

            $item = trim($item);

            return $countries ? strtoupper($item) : $item;
        }, array_values($value));

        $items = array_values(array_filter($items, fn ($item) => $item !== ''));

        return $countries ? array_values(array_unique($items, SORT_REGULAR)) : $items;
    }
}

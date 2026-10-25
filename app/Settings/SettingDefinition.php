<?php

namespace App\Settings;

use InvalidArgumentException;

final readonly class SettingDefinition
{
    public const ACCESS_ADMIN = 'admin';

    public const ACCESS_READONLY = 'readonly';

    public const ACCESS_ENV = 'env';

    /**
     * @param  array<int, string>  $rules  extra validation rules (strings)
     * @param  array<int, string>  $options  enum values
     * @param  array<string, array<int, string>>  $schema  json: rules per row column
     * @param  array<int, string>  $configBridge  config paths overlaid with the stored value
     */
    public function __construct(
        public string $key,
        public string $group,
        public string $name,
        public SettingType $type,
        public mixed $default,
        public bool $required = false,
        public array $rules = [],
        public int|float|null $min = null,
        public int|float|null $max = null,
        public int $itemMax = 100,
        public array $options = [],
        public ?string $unit = null,
        public array $schema = [],
        public ?string $atLeast = null,
        public ?string $memberOf = null,
        public bool $public = false,
        public string $access = self::ACCESS_ADMIN,
        public array $configBridge = [],
        public ?string $bridgeFormat = null,
        public string $risk = 'normal',
        public ?string $dependsOn = null,
        public int $order = 0,
        public bool $seed = true,
        public bool $sensitive = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, int $order): self
    {
        $key = (string) ($data['key'] ?? '');

        if (! preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', $key)) {
            throw new InvalidArgumentException("Setting key [{$key}] must look like group.name (lowercase, underscores).");
        }

        [$group, $name] = explode('.', $key, 2);
        $type = SettingType::tryFrom((string) ($data['type'] ?? ''))
            ?? throw new InvalidArgumentException("Setting [{$key}] has an unknown type.");

        $access = (string) ($data['access'] ?? self::ACCESS_ADMIN);
        if (! in_array($access, [self::ACCESS_ADMIN, self::ACCESS_READONLY, self::ACCESS_ENV], true)) {
            throw new InvalidArgumentException("Setting [{$key}] has an unknown access level [{$access}].");
        }

        $risk = (string) ($data['risk'] ?? 'normal');
        if (! in_array($risk, ['normal', 'high'], true)) {
            throw new InvalidArgumentException("Setting [{$key}] has an unknown risk level [{$risk}].");
        }

        if (! array_key_exists('default', $data)) {
            throw new InvalidArgumentException("Setting [{$key}] has no default.");
        }

        $bridge = $data['config_bridge'] ?? [];

        $definition = new self(
            key: $key,
            group: $group,
            name: $name,
            type: $type,
            default: $type->coerce($data['default']),
            required: (bool) ($data['required'] ?? false),
            rules: array_values($data['rules'] ?? []),
            min: $data['min'] ?? null,
            max: $data['max'] ?? null,
            itemMax: (int) ($data['item_max'] ?? 100),
            options: array_values($data['options'] ?? []),
            unit: $data['unit'] ?? null,
            schema: $data['schema'] ?? [],
            atLeast: $data['at_least'] ?? null,
            memberOf: $data['member_of'] ?? null,
            public: (bool) ($data['public'] ?? false),
            access: $access,
            configBridge: array_values(is_array($bridge) ? $bridge : [$bridge]),
            bridgeFormat: $data['bridge_format'] ?? null,
            risk: $risk,
            dependsOn: $data['depends_on'] ?? null,
            order: $order,
            seed: (bool) ($data['seed'] ?? true),
            sensitive: (bool) ($data['sensitive'] ?? false),
        );

        if (! $type->accepts($definition->default)) {
            throw new InvalidArgumentException("The default of setting [{$key}] is not a valid {$type->value}.");
        }

        return $definition;
    }

    public function isWritable(): bool
    {
        return $this->access === self::ACCESS_ADMIN;
    }

    public function labelKey(): string
    {
        return "settings.{$this->key}.label";
    }

    public function helpKey(): string
    {
        return "settings.{$this->key}.help";
    }

    public function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    public function decode(?string $raw): mixed
    {
        if ($raw === null) {
            return $this->default;
        }

        $decoded = json_decode($raw, true);
        $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;

        if ($value === null) {
            return $this->default;
        }

        $value = $this->type->coerce($value);

        return $this->type->accepts($value) ? $value : $this->default;
    }

    public function coerce(mixed $value): mixed
    {
        return $this->type->coerce($value);
    }

    /**
     * Laravel validation rules for a value validated as attribute $attribute (the key itself contains a dot,
     * so callers pass a simple attribute name). Includes the wildcard rules of list/json items.
     *
     * @return array<string, array<int, string>>
     */
    public function validationRules(string $attribute = 'value'): array
    {
        $rules = [$this->required ? 'required' : 'nullable'];
        $items = [];

        switch ($this->type) {
            case SettingType::String:
                array_push($rules, 'string', 'max:'.($this->max ?? 255));
                $this->min !== null && $rules[] = 'min:'.$this->min;
                break;
            case SettingType::Text:
                array_push($rules, 'string', 'max:'.($this->max ?? 2000));
                break;
            case SettingType::Email:
                array_push($rules, 'email', 'max:'.($this->max ?? 255));
                break;
            case SettingType::Url:
                array_push($rules, 'url:http,https', 'max:'.($this->max ?? 500));
                break;
            case SettingType::Int:
                $rules[] = 'integer';
                $this->min !== null && $rules[] = 'min:'.$this->min;
                $this->max !== null && $rules[] = 'max:'.$this->max;
                break;
            case SettingType::Float:
                $rules[] = 'numeric';
                $this->min !== null && $rules[] = 'min:'.$this->min;
                $this->max !== null && $rules[] = 'max:'.$this->max;
                break;
            case SettingType::Bool:
                $rules[] = 'boolean';
                break;
            case SettingType::Enum:
                $rules[] = 'string';
                $this->options !== [] && $rules[] = 'in:'.implode(',', $this->options);
                break;
            case SettingType::List:
                $rules[] = 'array';
                $this->min !== null && $rules[] = 'min:'.$this->min;
                $this->max !== null && $rules[] = 'max:'.$this->max;
                $items = ['string', 'max:'.$this->itemMax];
                break;
            case SettingType::Countries:
                $rules[] = 'array';
                $this->min !== null && $rules[] = 'min:'.$this->min;
                $this->max !== null && $rules[] = 'max:'.$this->max;
                $items = ['string', 'regex:/^[A-Z]{2}$/'];
                break;
            case SettingType::Json:
                $rules[] = 'array';
                $this->min !== null && $rules[] = 'min:'.$this->min;
                $this->max !== null && $rules[] = 'max:'.$this->max;
                break;
        }

        $result = [$attribute => [...$rules, ...$this->rules]];

        if ($items !== []) {
            $result["{$attribute}.*"] = $items;
        }

        if ($this->type === SettingType::Json) {
            $result["{$attribute}.*"] = ['array'];
            foreach ($this->schema as $column => $columnRules) {
                $result["{$attribute}.*.{$column}"] = $columnRules;
            }
        }

        return $result;
    }

    /**
     * Plain-text rule summary for the admin page (rule strings, no objects).
     *
     * @return array<int, string>
     */
    public function ruleSummary(): array
    {
        return array_values(array_filter(
            $this->validationRules('value')['value'],
            fn ($rule) => is_string($rule)
        ));
    }
}

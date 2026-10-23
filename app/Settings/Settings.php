<?php

namespace App\Settings;

use App\Models\SettingChange;
use App\Models\User;
use App\Settings\Events\SettingsChanged;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Typed access to the admin settings declared in config/settings.php (docs/admin-settings.md).
 *
 * Precedence: stored row > definition default. Rows are JSON. The whole table (registry keys only, so legacy
 * secret rows never reach the cache) is cached as one array under `settings:v{version}`; every write bumps
 * `settings:version`, which invalidates every process at once. Missing rows are never cached as defaults. A cache
 * or database failure degrades to defaults and never throws.
 */
class Settings
{
    public const VERSION_KEY = 'settings:version';

    private const ROWS_KEY_PREFIX = 'settings:v';

    /** @var array<string, string>|null */
    private ?array $memo = null;

    private ?string $memoVersion = null;

    /** When the cache version was last read (microtime): bounds version lookups to one per recheck window. */
    private ?float $versionCheckedAt = null;

    public function __construct(private readonly SettingsRegistry $registry) {}

    public function registry(): SettingsRegistry
    {
        return $this->registry;
    }

    // ------------------------------------------------------------------ reading

    public function get(string $key): mixed
    {
        return $this->valueFor($this->registry->get($key), $this->rows());
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function many(array $keys): array
    {
        $rows = $this->rows();
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->valueFor($this->registry->get($key), $rows);
        }

        return $values;
    }

    /**
     * Every setting: typed value, default and source.
     *
     * @return array<string, array{value: mixed, default: mixed, source: string}>
     */
    public function all(): array
    {
        $rows = $this->rows();
        $all = [];

        foreach ($this->registry->all() as $key => $definition) {
            $all[$key] = [
                'value' => $this->valueFor($definition, $rows),
                'default' => $definition->default,
                'source' => $this->sourceOf($definition, $rows),
            ];
        }

        return $all;
    }

    /**
     * 'customised' when a stored value differs from the default, else 'default' (a row that merely repeats
     * the default, e.g. one inserted by the seeder, is not a customisation).
     */
    public function source(string $key): string
    {
        return $this->sourceOf($this->registry->get($key), $this->rows());
    }

    /**
     * @param  array<string, string>  $rows
     */
    private function sourceOf(SettingDefinition $definition, array $rows): string
    {
        if (! array_key_exists($definition->key, $rows)) {
            return 'default';
        }

        return $rows[$definition->key] === $definition->encode($definition->default) ? 'default' : 'customised';
    }

    public function definition(string $key): SettingDefinition
    {
        return $this->registry->get($key);
    }

    /**
     * Values of the settings marked public (shared with the frontend), keyed by setting key.
     *
     * @return array<string, mixed>
     */
    public function public(): array
    {
        $rows = $this->rows();
        $values = [];

        foreach ($this->registry->public() as $key => $definition) {
            $values[$key] = $this->valueFor($definition, $rows);
        }

        return $values;
    }

    /**
     * The registry key a legacy key stands for (null for keys that are not part of the catalogue).
     */
    public function resolveKey(string $key): ?string
    {
        return $this->registry->resolve($key);
    }

    /**
     * Stored values of the given keys that are customised, or null when storage cannot be read at all.
     * Used by the config bridge, which must not touch config while it does not know the truth.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>|null
     */
    public function customised(array $keys): ?array
    {
        $rows = $this->loadRows();

        if ($rows === null) {
            return null;
        }

        $values = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $rows)) {
                $values[$key] = $this->registry->get($key)->decode($rows[$key]);
            }
        }

        return $values;
    }

    // ------------------------------------------------------------------ writing

    public function set(string $key, mixed $value, ?User $by = null): void
    {
        $this->setMany([$key => $value], $by);
    }

    /**
     * Validate and store several settings in one transaction (all or nothing), with one audit row per change.
     *
     * @param  array<string, mixed>  $values  key => value
     * @return array<int, string> keys that actually changed
     *
     * @throws ValidationException
     * @throws InvalidArgumentException unknown or read-only key
     */
    public function setMany(array $values, ?User $by = null): array
    {
        $clean = $this->validate($values);

        if (($cross = $this->crossFieldErrors($clean)) !== []) {
            throw ValidationException::withMessages(array_map(fn (string $message) => [$message], $cross));
        }

        return $this->write($clean, $by);
    }

    /**
     * Drop the stored value so the default applies again.
     */
    public function reset(string $key, ?User $by = null): void
    {
        $this->writableDefinition($key);

        $this->write([$key => null], $by);
    }

    /**
     * Coerce and validate values against their definitions.
     *
     * @param  array<string, mixed>  $values  key => raw value
     * @return array<string, mixed> key => typed value
     *
     * @throws ValidationException
     */
    public function validate(array $values): array
    {
        $clean = [];
        $errors = [];

        foreach ($values as $key => $value) {
            $definition = $this->writableDefinition((string) $key);
            $value = $definition->coerce($value);

            $validator = Validator::make(
                ['value' => $value],
                $definition->validationRules('value'),
                [],
                ['value' => __($definition->labelKey())],
            );

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $attribute => $messages) {
                    $errors[$key.substr($attribute, strlen('value'))] = $messages;
                }

                continue;
            }

            $clean[$key] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }

    /**
     * Rules that involve two settings (min <= max, default country among the destinations).
     * Only checked for pairs where at least one side is in $values; the other side is read from storage.
     *
     * @param  array<string, mixed>  $values  key => typed value
     * @return array<string, string> key => message
     */
    public function crossFieldErrors(array $values): array
    {
        $errors = [];
        $current = fn (string $key) => array_key_exists($key, $values) ? $values[$key] : $this->get($key);

        foreach ($this->registry->all() as $key => $definition) {
            if ($definition->atLeast !== null && (array_key_exists($key, $values) || array_key_exists($definition->atLeast, $values))) {
                if ($current($key) < $current($definition->atLeast)) {
                    $errors[$key] = __('settings.ui.validation.at_least', ['other' => __($this->registry->get($definition->atLeast)->labelKey())]);
                }
            }

            if ($definition->memberOf !== null && (array_key_exists($key, $values) || array_key_exists($definition->memberOf, $values))) {
                $value = $current($key);
                $members = self::members($current($definition->memberOf));
                // a list value: every item must be a member
                $outside = is_array($value)
                    ? array_filter($value, fn ($item) => ! in_array($item, $members, true)) !== []
                    : ! in_array($value, $members, true);
                if ($outside) {
                    $errors[$key] = __('settings.ui.validation.member_of', ['other' => __($this->registry->get($definition->memberOf)->labelKey())]);
                }
            }
        }

        return $errors;
    }

    /**
     * The single write path: applies changes (value, or null = reset), writes the audit rows, bumps the version.
     *
     * @param  array<string, mixed>  $changes  key => typed value, or null to reset
     * @return array<int, string> keys that actually changed
     */
    private function write(array $changes, ?User $by): array
    {
        $changed = [];
        $ip = app()->runningInConsole() ? null : request()->ip();

        DB::transaction(function () use ($changes, $by, $ip, &$changed) {
            $existing = DB::table('settings')->whereIn('key', array_keys($changes))->get()->keyBy('key');

            foreach ($changes as $key => $value) {
                $definition = $this->registry->get($key);
                $row = $existing->get($key);
                $old = $row ? $definition->decode($row->value) : $definition->default;
                $new = $value === null ? $definition->default : $value;

                if ($value === null) {
                    if ($row === null) {
                        continue;
                    }

                    DB::table('settings')->where('key', $key)->delete();
                } else {
                    if ($definition->encode($old) === $definition->encode($new)) {
                        continue;
                    }

                    $attributes = [
                        'value' => $definition->encode($new),
                        'type' => $definition->type->value,
                        'updated_by' => $by?->getKey(),
                        'updated_at' => now(),
                    ];

                    $row !== null
                        ? DB::table('settings')->where('key', $key)->update($attributes)
                        : DB::table('settings')->insert([...$attributes, 'key' => $key, 'created_at' => now()]);
                }

                SettingChange::create([
                    'key' => $key,
                    'old_value' => $definition->sensitive ? '***' : $definition->encode($old),
                    'new_value' => $definition->sensitive ? '***' : $definition->encode($new),
                    'user_id' => $by?->getKey(),
                    'ip' => $ip,
                    'created_at' => now(),
                ]);

                $changed[] = $key;
            }
        });

        if ($changed !== []) {
            $this->memo = null;
            $this->memoVersion = null;

            DB::afterCommit(function () use ($changed, $by) {
                $this->flush();
                Log::info('Settings changed', ['keys' => $changed, 'user_id' => $by?->getKey()]);
                event(new SettingsChanged($changed));
            });
        }

        return $changed;
    }

    // ------------------------------------------------------------------ cache

    /**
     * Invalidate the cached table everywhere (bumps the shared version) and in this process.
     */
    public function flush(): void
    {
        $this->memo = null;
        $this->memoVersion = null;
        $this->versionCheckedAt = null;

        try {
            $this->store()->forever(self::VERSION_KEY, $this->newVersion());
        } catch (Throwable) {
            // no cache available: nothing is cached either
        }
    }

    /**
     * Forget when the version was last checked, so the next read looks at the shared version again
     * (called at the start of every queued job / scheduler tick / worker loop).
     */
    public function refresh(): void
    {
        $this->versionCheckedAt = null;
    }

    /**
     * Current cache version, or null when the cache store cannot be reached.
     */
    public function version(): ?string
    {
        try {
            $store = $this->store();
            $version = $store->get(self::VERSION_KEY);

            if ($version === null) {
                $store->add(self::VERSION_KEY, $this->newVersion());
                $version = $store->get(self::VERSION_KEY);
            }

            return $version === null ? null : (string) $version;
        } catch (Throwable) {
            return null;
        }
    }

    private function store(): Repository
    {
        return Cache::store(config('settings.cache.store'));
    }

    private function newVersion(): string
    {
        return (string) (int) (microtime(true) * 1_000_000);
    }

    /**
     * @return array<string, string>
     */
    private function rows(): array
    {
        return $this->loadRows() ?? [];
    }

    /**
     * Raw rows (key => stored JSON) of the registry keys; null when the database cannot be read.
     *
     * @return array<string, string>|null
     */
    private function loadRows(): ?array
    {
        // Within the recheck window the in-process copy is trusted, so a page that reads dozens of
        // settings does not ask the (possibly database-backed) cache for the version every time.
        $recheck = (float) config('settings.cache.recheck_seconds', 5);

        if ($this->memo !== null && $this->versionCheckedAt !== null && $recheck > 0
            && (microtime(true) - $this->versionCheckedAt) < $recheck) {
            return $this->memo;
        }

        $version = $this->version();
        $this->versionCheckedAt = microtime(true);

        if ($version !== null && $this->memo !== null && $this->memoVersion === $version) {
            return $this->memo;
        }

        if ($version !== null) {
            try {
                $cached = $this->store()->get(self::ROWS_KEY_PREFIX.$version);
            } catch (Throwable) {
                $cached = null;
            }

            if (is_array($cached)) {
                $this->memo = $cached;
                $this->memoVersion = $version;

                return $cached;
            }
        }

        $rows = $this->readDatabase();

        if ($rows === null) {
            return null;
        }

        if ($version !== null) {
            try {
                $this->store()->put(self::ROWS_KEY_PREFIX.$version, $rows, (int) config('settings.cache.ttl', 86400));
            } catch (Throwable) {
                // served from the database next time
            }

            $this->memo = $rows;
            $this->memoVersion = $version;
        }

        return $rows;
    }

    /**
     * @return array<string, string>|null
     */
    private function readDatabase(): ?array
    {
        try {
            return DB::table('settings')
                ->whereIn('key', array_keys($this->registry->all()))
                ->pluck('value', 'key')
                ->map(fn ($value) => (string) $value)
                ->all();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, string>  $rows
     */
    private function valueFor(SettingDefinition $definition, array $rows): mixed
    {
        return array_key_exists($definition->key, $rows)
            ? $definition->decode($rows[$definition->key])
            : $definition->default;
    }

    private function writableDefinition(string $key): SettingDefinition
    {
        $definition = $this->registry->get($key);

        if (! $definition->isWritable()) {
            throw new InvalidArgumentException("Setting [{$key}] is read-only (access: {$definition->access}).");
        }

        return $definition;
    }

    // ------------------------------------------------------------------ admin payload

    /**
     * Everything the admin page needs for one group (labels and help already translated).
     *
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        if (! $this->registry->hasGroup($group)) {
            throw new InvalidArgumentException("Unknown settings group [{$group}].");
        }

        $rows = $this->rows();
        $fields = [];

        foreach ($this->registry->group($group) as $key => $definition) {
            $fields[] = $this->field($definition, $rows);
        }

        return [
            'key' => $group,
            'labelKey' => "settings.group.{$group}.label",
            'label' => __("settings.group.{$group}.label"),
            'description' => __("settings.group.{$group}.description"),
            'icon' => $this->registry->groupIcon($group),
            'panel' => $this->registry->isPanelGroup($group),
            'fields' => $fields,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function groups(): array
    {
        return array_map(fn (string $group) => $this->group($group), $this->registry->groupNames());
    }

    /**
     * The values a member_of setting may take: the other list's items, or the `code` column of json rows (shipping.carriers).
     *
     * @return list<mixed>
     */
    private static function members(mixed $value): array
    {
        return array_values(array_map(fn ($member) => is_array($member) ? ($member['code'] ?? null) : $member, (array) $value));
    }

    /**
     * @param  array<string, string>  $rows
     * @return array<string, mixed>
     */
    private function field(SettingDefinition $definition, array $rows): array
    {
        $options = $definition->memberOf !== null
            ? self::members($this->valueFor($this->registry->get($definition->memberOf), $rows))
            : $definition->options;

        return [
            'key' => $definition->key,
            'name' => $definition->name,
            'type' => $definition->type->value,
            'value' => $this->valueFor($definition, $rows),
            'default' => $definition->default,
            'source' => $this->sourceOf($definition, $rows),
            'label' => __($definition->labelKey()),
            'help' => __($definition->helpKey()),
            'unit' => $definition->unit,
            'min' => $definition->min,
            'max' => $definition->max,
            'options' => array_values($options),
            'readonly' => ! $definition->isWritable(),
            'access' => $definition->access,
            'risk' => $definition->risk,
            'required' => $definition->required,
            'dependsOn' => $definition->dependsOn,
            'atLeast' => $definition->atLeast,
            'memberOf' => $definition->memberOf,
            'public' => $definition->public,
            'rules' => $definition->ruleSummary(),
            'columns' => $this->columns($definition),
        ];
    }

    /**
     * Columns of a json (table) setting, null for every other type.
     *
     * @return array<int, array{name: string, required: bool}>|null
     */
    private function columns(SettingDefinition $definition): ?array
    {
        if ($definition->type !== SettingType::Json) {
            return null;
        }

        $columns = [];

        foreach ($definition->schema as $name => $rules) {
            $columns[] = ['name' => $name, 'required' => in_array('required', $rules, true)];
        }

        return $columns;
    }

    /**
     * Most recent audit rows of a group, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $group, int $limit = 20): array
    {
        if (! $this->registry->hasGroup($group)) {
            throw new InvalidArgumentException("Unknown settings group [{$group}].");
        }

        return SettingChange::query()
            ->with('user:id,name')
            ->whereIn('key', array_keys($this->registry->group($group)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (SettingChange $change) {
                $definition = $this->registry->find($change->key);

                return [
                    'id' => $change->id,
                    'key' => $change->key,
                    'label' => $definition ? __($definition->labelKey()) : $change->key,
                    'old' => $this->decodeAuditValue($change->old_value),
                    'new' => $this->decodeAuditValue($change->new_value),
                    'user' => $change->user ? ['id' => $change->user->id, 'name' => $change->user->name] : null,
                    'created_at' => $change->created_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    private function decodeAuditValue(?string $value): mixed
    {
        if ($value === null || $value === '***') {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}

<?php

namespace App\Settings;

use InvalidArgumentException;

/**
 * The validated catalogue from config/settings.php: ordered groups and their definitions.
 */
final class SettingsRegistry
{
    /** @var array<string, SettingDefinition> */
    private array $definitions = [];

    /** @var array<string, array<string, mixed>> */
    private array $groups;

    /** @var array<string, string> */
    private array $legacyAliases;

    /**
     * @param  array<string, mixed>  $config  the contents of config/settings.php
     */
    public function __construct(array $config)
    {
        $this->groups = $config['groups'] ?? [];
        $this->legacyAliases = $config['legacy_aliases'] ?? [];

        foreach (array_values($config['definitions'] ?? []) as $order => $data) {
            $definition = SettingDefinition::fromArray($data, $order);

            if (isset($this->definitions[$definition->key])) {
                throw new InvalidArgumentException("Setting [{$definition->key}] is declared twice.");
            }

            if (! isset($this->groups[$definition->group])) {
                throw new InvalidArgumentException("Setting [{$definition->key}] belongs to the undeclared group [{$definition->group}].");
            }

            $this->definitions[$definition->key] = $definition;
        }

        foreach ($this->definitions as $definition) {
            foreach ([$definition->atLeast, $definition->memberOf, $definition->dependsOn] as $reference) {
                if ($reference !== null && ! isset($this->definitions[$reference])) {
                    throw new InvalidArgumentException("Setting [{$definition->key}] refers to the unknown setting [{$reference}].");
                }
            }
        }

        foreach ($this->legacyAliases as $old => $new) {
            if (! isset($this->definitions[$new])) {
                throw new InvalidArgumentException("Legacy key [{$old}] points to the unknown setting [{$new}].");
            }
        }
    }

    public static function fromConfig(): self
    {
        return new self((array) config('settings'));
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function find(string $key): ?SettingDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    public function get(string $key): SettingDefinition
    {
        return $this->definitions[$key]
            ?? throw new InvalidArgumentException("Unknown setting [{$key}].");
    }

    /**
     * @return array<string, SettingDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * Group names in display order.
     *
     * @return array<int, string>
     */
    public function groupNames(): array
    {
        return array_keys($this->groups);
    }

    public function hasGroup(string $group): bool
    {
        return isset($this->groups[$group]);
    }

    public function groupIcon(string $group): ?string
    {
        return $this->groups[$group]['icon'] ?? null;
    }

    /**
     * Groups that show derived, read-only data instead of editable fields (the system panel).
     */
    public function isPanelGroup(string $group): bool
    {
        return (bool) ($this->groups[$group]['readonly_panel'] ?? false);
    }

    /**
     * @return array<string, SettingDefinition> definitions of one group, in declaration order
     */
    public function group(string $group): array
    {
        return array_filter($this->definitions, fn (SettingDefinition $d) => $d->group === $group);
    }

    /**
     * @return array<string, SettingDefinition>
     */
    public function public(): array
    {
        return array_filter($this->definitions, fn (SettingDefinition $d) => $d->public);
    }

    /**
     * @return array<string, SettingDefinition>
     */
    public function bridged(): array
    {
        return array_filter($this->definitions, fn (SettingDefinition $d) => $d->configBridge !== []);
    }

    /**
     * The registry key a legacy key stands for (or the key itself when it is already a registry key).
     */
    public function resolve(string $key): ?string
    {
        if (isset($this->definitions[$key])) {
            return $key;
        }

        return $this->legacyAliases[$key] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function legacyAliases(): array
    {
        return $this->legacyAliases;
    }
}

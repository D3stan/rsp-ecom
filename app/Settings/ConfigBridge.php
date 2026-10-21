<?php

namespace App\Settings;

use Illuminate\Contracts\Config\Repository;
use Throwable;

/**
 * Overlays stored (customised) settings onto config() for the definitions that declare a `config_bridge`, so
 * code that reads config('mail.support_address') & co. follows the admin page without being rewritten.
 *
 * Only customised values are written; when a setting is reset (or its row disappears) the config value that was
 * there before the first overlay is restored. apply() is cheap when nothing changed (one cache read for the
 * version), which is what lets queue workers and long-running processes call it before every job.
 */
class ConfigBridge
{
    /** @var array<string, mixed> config path => value before the first overlay */
    private array $baseline = [];

    /** @var array<string, true> config paths currently holding an overlay */
    private array $overlaid = [];

    private ?string $appliedVersion = null;

    public function __construct(
        private readonly Settings $settings,
        private readonly Repository $config,
    ) {}

    /**
     * Never throws: with no database / table (artisan during install, package:discover) it simply does nothing.
     */
    public function apply(bool $force = false): void
    {
        try {
            $this->settings->refresh();
            $version = $this->settings->version();

            if (! $force && $version !== null && $version === $this->appliedVersion) {
                return;
            }

            $bridged = $this->settings->registry()->bridged();
            $values = $this->settings->customised(array_keys($bridged));

            if ($values === null) {
                return;
            }

            foreach ($bridged as $key => $definition) {
                foreach ($definition->configBridge as $path) {
                    if (array_key_exists($key, $values)) {
                        $val = $this->format($definition, $values[$key]);

                        if (str_starts_with($path, 'shipping.labels.sender.') && ($val === '' || $val === null)) {
                            $baselineVal = isset($this->overlaid[$path]) ? $this->baseline[$path] : $this->config->get($path);
                            if ($baselineVal !== '' && $baselineVal !== null) {
                                if (isset($this->overlaid[$path])) {
                                    $this->restore($path);
                                }
                                continue;
                            }
                        }

                        $this->overlay($path, $val);
                    } else {
                        $this->restore($path);
                    }
                }
            }

            $this->appliedVersion = $version;
        } catch (Throwable) {
            // config keeps its current values
        }
    }

    private function overlay(string $path, mixed $value): void
    {
        if (! isset($this->overlaid[$path])) {
            $this->baseline[$path] = $this->config->get($path);
            $this->overlaid[$path] = true;
        }

        $this->config->set($path, $value);
    }

    private function restore(string $path): void
    {
        if (! isset($this->overlaid[$path])) {
            return;
        }

        $this->config->set($path, $this->baseline[$path]);
        unset($this->overlaid[$path], $this->baseline[$path]);
    }

    private function format(SettingDefinition $definition, mixed $value): mixed
    {
        if ($definition->bridgeFormat === 'keyed_by_code') {
            $keyed = [];

            foreach ((array) $value as $row) {
                $keyed[(string) $row['code']] = array_filter(
                    ['name' => $row['name'] ?? $row['code'], 'tracking_url' => $row['tracking_url'] ?? null],
                    fn ($v) => $v !== null && $v !== ''
                );
            }

            return $keyed;
        }

        return $value;
    }
}

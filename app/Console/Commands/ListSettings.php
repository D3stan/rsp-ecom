<?php

namespace App\Console\Commands;

use App\Settings\Settings;
use Illuminate\Console\Command;

class ListSettings extends Command
{
    protected $signature = 'settings:list {--group= : Only this group (store, tax, shipping...)}';

    protected $description = 'List the admin settings with their effective value and whether it is the default or customised';

    public function handle(Settings $settings): int
    {
        $group = $this->option('group');

        if ($group !== null && ! $settings->registry()->hasGroup((string) $group)) {
            $this->error("Unknown group [{$group}]. Available: ".implode(', ', $settings->registry()->groupNames()));

            return self::FAILURE;
        }

        $rows = [];

        foreach ($settings->all() as $key => $entry) {
            if ($group !== null && $settings->definition($key)->group !== $group) {
                continue;
            }

            $definition = $settings->definition($key);
            $value = is_scalar($entry['value']) ? var_export($entry['value'], true) : json_encode($entry['value'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $rows[] = [
                $key,
                $definition->sensitive ? '***' : mb_strimwidth((string) $value, 0, 70, '...'),
                $entry['source'].($definition->isWritable() ? '' : ' ('.$definition->access.')'),
            ];
        }

        $this->table(['Key', 'Value', 'Source'], $rows);

        return self::SUCCESS;
    }
}

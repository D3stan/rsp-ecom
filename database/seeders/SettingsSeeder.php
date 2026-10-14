<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Settings\Settings;
use App\Settings\SettingsRegistry;
use Illuminate\Database\Seeder;

/**
 * Inserts the catalogue defaults that are missing from the settings table, and nothing else: existing rows are
 * never overwritten, so it is safe on a live database. Settings whose default comes from .env / config and
 * should keep following it (support address, store name, delivery days, carriers...) are skipped (`seed => false`
 * in config/settings.php).
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $registry = app(SettingsRegistry::class);
        $existing = Setting::query()->pluck('key')->all();

        foreach ($registry->all() as $key => $definition) {
            if (! $definition->seed || ! $definition->isWritable() || in_array($key, $existing, true)) {
                continue;
            }

            Setting::create([
                'key' => $key,
                'value' => $definition->encode($definition->default),
                'type' => $definition->type->value,
            ]);
        }

        app(Settings::class)->flush();
    }
}

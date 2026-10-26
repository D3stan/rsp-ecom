<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Storage rows of the settings table. Values are JSON, as App\Settings\Settings writes them.
 *
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'store.name',
            'value' => json_encode($this->faker->company()),
            'type' => 'string',
        ];
    }

    /**
     * A row for a catalogue key, e.g. Setting::factory()->forKey('tax.rate', 8.75)->create().
     */
    public function forKey(string $key, mixed $value, string $type = 'string'): static
    {
        return $this->state(fn () => [
            'key' => $key,
            'value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
            'type' => $type,
        ]);
    }
}

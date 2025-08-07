<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setting>
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            'value' => $this->faker->word(),
            'type' => $this->faker->randomElement(['string', 'boolean', 'json']),
        ];
    }

    /**
     * Create a string setting
     */
    public function string(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'string',
            'value' => $this->faker->sentence(),
        ]);
    }

    /**
     * Create a boolean setting
     */
    public function boolean(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'boolean',
            'value' => $this->faker->boolean() ? '1' : '0',
        ]);
    }

    /**
     * Create a JSON setting
     */
    public function json(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'json',
            'value' => json_encode(['key' => $this->faker->word()]),
        ]);
    }
}

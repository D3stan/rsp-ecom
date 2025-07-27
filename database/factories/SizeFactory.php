<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Size>
 */
class SizeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Small', 'Medium', 'Large', 'Extra Large']),
            'length' => $this->faker->randomFloat(2, 10, 50),
            'width' => $this->faker->randomFloat(2, 10, 50),
            'height' => $this->faker->randomFloat(2, 5, 30),
            'box_type' => $this->faker->randomElement(['box', 'non_rigid_box']),
            'shipping_cost' => $this->faker->randomFloat(2, 5, 25),
        ];
    }

    /**
     * Create a small shipping size
     */
    public function small(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Small',
            'length' => $this->faker->randomFloat(2, 10, 20),
            'width' => $this->faker->randomFloat(2, 10, 20),
            'height' => $this->faker->randomFloat(2, 5, 15),
            'box_type' => 'box',
            'shipping_cost' => $this->faker->randomFloat(2, 5, 15),
        ]);
    }

    /**
     * Create a medium shipping size
     */
    public function medium(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Medium',
            'length' => $this->faker->randomFloat(2, 20, 35),
            'width' => $this->faker->randomFloat(2, 20, 35),
            'height' => $this->faker->randomFloat(2, 15, 25),
            'box_type' => 'box',
            'shipping_cost' => $this->faker->randomFloat(2, 10, 20),
        ]);
    }

    /**
     * Create a large shipping size
     */
    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Large',
            'length' => $this->faker->randomFloat(2, 35, 50),
            'width' => $this->faker->randomFloat(2, 35, 50),
            'height' => $this->faker->randomFloat(2, 25, 30),
            'box_type' => 'box',
            'shipping_cost' => $this->faker->randomFloat(2, 15, 25),
        ]);
    }
}

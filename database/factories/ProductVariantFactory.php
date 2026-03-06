<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'sku_suffix' => '',
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'stock_quantity' => $this->faker->numberBetween(1, 100),
            'images' => [],
            'is_default' => true,
            'sort_order' => 0,
            'status' => 'active',
        ];
    }
}

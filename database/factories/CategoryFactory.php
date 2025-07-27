<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            'Electronics' => 'Latest electronic devices and gadgets',
            'Clothing' => 'Fashion and apparel for all occasions',
            'Home & Garden' => 'Everything for your home and garden',
            'Sports & Outdoors' => 'Sports equipment and outdoor gear',
            'Books' => 'Books across all genres and categories',
            'Beauty & Health' => 'Beauty products and health essentials',
            'Toys & Games' => 'Fun toys and games for all ages',
            'Automotive' => 'Car parts and automotive accessories',
            'Jewelry & Watches' => 'Fine jewelry and luxury watches',
            'Pet Supplies' => 'Everything your pets need',
        ];

        $categoryName = $this->faker->randomElement(array_keys($categories));
        $slug = strtolower(str_replace([' ', '&'], ['-', 'and'], $categoryName));

        return [
            'name' => $categoryName,
            'slug' => $slug,
            'description' => $categories[$categoryName],
            'image' => 'category.png', // Default category image
            'is_active' => $this->faker->boolean(95), // 95% active
            'sort_order' => $this->faker->numberBetween(1, 100),
        ];
    }

    /**
     * Create an active category
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive category
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a category with specific name
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'slug' => strtolower(str_replace([' ', '&'], ['-', 'and'], $name)),
        ]);
    }

    /**
     * Create an electronics category
     */
    public function electronics(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Electronics',
            'slug' => 'electronics',
            'description' => 'Latest electronic devices and gadgets',
        ]);
    }

    /**
     * Create a clothing category
     */
    public function clothing(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Clothing',
            'slug' => 'clothing',
            'description' => 'Fashion and apparel for all occasions',
        ]);
    }

    /**
     * Create a home & garden category
     */
    public function homeGarden(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Home & Garden',
            'slug' => 'home-and-garden',
            'description' => 'Everything for your home and garden',
        ]);
    }
}

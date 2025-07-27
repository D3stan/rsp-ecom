<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->create();
        $quantity = $this->faker->numberBetween(1, 3); // Most people buy 1-3 of same item
        $unitPrice = $product->price;
        $totalPrice = $unitPrice * $quantity;

        return [
            'order_id' => Order::factory(),
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'price' => $unitPrice,
            'total' => $totalPrice,
        ];
    }

    /**
     * Create order item for specific product
     */
    public function forProduct(Product $product): static
    {
        return $this->state(function (array $attributes) use ($product) {
            $quantity = $attributes['quantity'] ?? $this->faker->numberBetween(1, 3);
            $unitPrice = $product->price;
            
            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'price' => $unitPrice,
                'total' => $unitPrice * $quantity,
            ];
        });
    }

    /**
     * Create order item with specific quantity
     */
    public function quantity(int $quantity): static
    {
        return $this->state(function (array $attributes) use ($quantity) {
            $unitPrice = $attributes['price'] ?? $this->faker->randomFloat(2, 10, 200);
            
            return [
                'quantity' => $quantity,
                'total' => $unitPrice * $quantity,
            ];
        });
    }

    /**
     * Create order item without size (for non-clothing items)
     */
    public function withoutSize(): static
    {
        return $this->state(fn (array $attributes) => [
            // No size_id column in migration
        ]);
    }

    /**
     * Create high quantity order item (bulk purchase)
     */
    public function bulk(): static
    {
        return $this->state(function (array $attributes) {
            $quantity = $this->faker->numberBetween(5, 15);
            $unitPrice = $attributes['price'] ?? $this->faker->randomFloat(2, 10, 200);
            
            return [
                'quantity' => $quantity,
                'total' => $unitPrice * $quantity,
            ];
        });
    }

    /**
     * Create order item with specific unit price
     */
    public function withPrice(float $price): static
    {
        return $this->state(function (array $attributes) use ($price) {
            $quantity = $attributes['quantity'] ?? $this->faker->numberBetween(1, 3);
            
            return [
                'price' => $price,
                'total' => $price * $quantity,
            ];
        });
    }
}

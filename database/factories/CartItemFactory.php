<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->create();
        
        return [
            'cart_id' => Cart::factory(),
            'product_id' => $product->id,
            'size_id' => Size::factory(),
            'quantity' => $this->faker->numberBetween(1, 5),
            'price' => $product->price,
        ];
    }
}

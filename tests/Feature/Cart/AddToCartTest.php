<?php

use App\Models\CartItem;
use App\Models\User;
use Tests\Support\CatalogFixtures;

beforeEach(function () {
    $this->customer = User::factory()->create(['role' => 'customer']);
    $this->product = CatalogFixtures::product();
    $this->variantA = CatalogFixtures::variant($this->product, ['stock_quantity' => 10, 'price' => 10]);
    $this->variantB = CatalogFixtures::variant($this->product, ['stock_quantity' => 10, 'price' => 12, 'is_default' => false]);
});

function addVariantToCart($test, $variant, int $quantity)
{
    return $test->actingAs($test->customer)->postJson(route('cart.add'), [
        'product_id' => $test->product->id,
        'product_variant_id' => $variant->id,
        'quantity' => $quantity,
    ]);
}

it('keeps two variants of the same product as separate lines and sums repeats of one variant', function () {
    addVariantToCart($this, $this->variantA, 1)->assertOk();
    addVariantToCart($this, $this->variantB, 2)->assertOk();
    addVariantToCart($this, $this->variantA, 3)->assertOk()->assertJson(['cartCount' => 6]);

    expect(CartItem::pluck('quantity', 'product_variant_id')->map(fn ($quantity) => (int) $quantity)->all())
        ->toEqual([$this->variantA->id => 4, $this->variantB->id => 2]);
});

it('explains a stock rejection in a message the cart toast can show', function () {
    addVariantToCart($this, $this->variantA, 11)
        ->assertStatus(422)
        ->assertJson(['message' => 'Not enough stock: only 10 available.']);

    addVariantToCart($this, $this->variantA, 8)->assertOk();

    addVariantToCart($this, $this->variantA, 3)
        ->assertStatus(422)
        ->assertJson(['message' => 'Not enough stock: only 10 available.']);
});

<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Settings\Settings;
use Tests\Support\CatalogFixtures;

beforeEach(function () {
    $this->customer = User::factory()->create(['role' => 'customer']);
    $this->product = CatalogFixtures::product(['stock_quantity' => 40]);
    // The variant is the stock that counts; the product mirror is deliberately different.
    $this->variant = CatalogFixtures::variant($this->product, ['stock_quantity' => 20, 'price' => 10]);
});

function cartLimitSet(int $max): void
{
    app(Settings::class)->set('orders.max_qty_per_line', $max, User::factory()->create(['role' => 'admin']));
}

function cartLimitAdd($test, int $quantity)
{
    return $test->actingAs($test->customer)->postJson(route('cart.add'), [
        'product_id' => $test->product->id,
        'product_variant_id' => $test->variant->id,
        'quantity' => $quantity,
    ]);
}

it('adds up to the variant stock when the limit is 0 (unlimited)', function () {
    cartLimitAdd($this, 20)->assertOk()->assertJson(['success' => true, 'cartCount' => 20]);
});

it('rejects an add above orders.max_qty_per_line with a translated message', function () {
    cartLimitSet(3);

    cartLimitAdd($this, 4)
        ->assertStatus(422)
        ->assertJson(['success' => false, 'max' => 3, 'message' => 'Maximum quantity per item: 3.']);

    expect(CartItem::count())->toBe(0);
    cartLimitAdd($this, 3)->assertOk();
});

it('applies the limit to the accumulated line quantity as well', function () {
    cartLimitSet(5);

    cartLimitAdd($this, 3)->assertOk();
    cartLimitAdd($this, 3)->assertStatus(422)->assertJson(['max' => 5]);

    expect(CartItem::first()->quantity)->toBe(3);
});

it('still reports a stock problem before the limit', function () {
    cartLimitSet(50);

    cartLimitAdd($this, 21)->assertStatus(422)->assertJson(['error' => 'Not enough stock', 'available' => 20]);
});

it('enforces the limit when updating a line', function () {
    $cart = Cart::factory()->create(['user_id' => $this->customer->id]);
    $item = CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'price' => 10,
        'size_id' => $this->product->size_id,
    ]);
    cartLimitSet(4);

    $this->actingAs($this->customer)->putJson(route('cart.update', $item), ['quantity' => 5])
        ->assertStatus(422)->assertJson(['success' => false, 'max' => 4]);
    $this->actingAs($this->customer)->putJson(route('cart.update', $item), ['quantity' => 4])
        ->assertOk();

    expect($item->fresh()->quantity)->toBe(4);
});

it('checks the variant stock (not the product mirror) when updating a line', function () {
    $cart = Cart::factory()->create(['user_id' => $this->customer->id]);
    $item = CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'price' => 10,
        'size_id' => $this->product->size_id,
    ]);

    // product stock is 40, variant stock is 20
    $this->actingAs($this->customer)->putJson(route('cart.update', $item), ['quantity' => 21])
        ->assertStatus(400)->assertJson(['maxQuantity' => 20]);
    $this->actingAs($this->customer)->putJson(route('cart.update', $item), ['quantity' => 20])
        ->assertOk();
});

it('shares orders.max_qty_per_line with the product page', function () {
    cartLimitSet(6);

    $this->get(route('products.show', $this->product->slug))
        ->assertInertia(fn ($page) => $page->where('settings', fn ($shared) => collect($shared)->get('orders.max_qty_per_line') === 6));
});

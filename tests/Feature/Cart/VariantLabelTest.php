<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

/*
 * Customer views must name the variant that was chosen (e.g. "Smoke / XL"), not the shipping box (App\Models\Size)
 * that cart.tsx, Dashboard/Orders.tsx and Orders/GuestShow.tsx labelled "Size".
 */

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'customer']);
    $this->product = CatalogFixtures::product(['stock_quantity' => 10]);
    $this->variant = CatalogFixtures::variant($this->product, ['name' => 'Smoke / XL', 'stock_quantity' => 7, 'price' => 30]);
});

function orderWithVariantLine($test, ?User $user): Order
{
    $order = Order::factory()->create($user ? ['user_id' => $user->id] : ['user_id' => null, 'guest_email' => 'g@example.test']);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $test->product->id,
        'product_variant_id' => $test->variant->id,
        'product_name' => $test->product->name,
        'size_id' => $test->product->size_id,
        'quantity' => 1,
        'price' => 30,
        'total' => 30,
    ]);

    return $order;
}

it('gives the cart page the variant name and its stock limit', function () {
    $cart = Cart::create(['user_id' => $this->user->id]);
    $cart->cartItems()->create([
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'price' => 30,
        'size_id' => $this->product->size_id,
    ]);

    $this->actingAs($this->user)->get(route('cart'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('cartItems.0.variant.name', 'Smoke / XL')
            ->where('cartItems.0.variant.max_quantity', 7));
});

it('gives the account orders page the variant name of each line', function () {
    orderWithVariantLine($this, $this->user);

    $this->actingAs($this->user)->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('orders.0.order_items.0.variant_name', 'Smoke / XL'));
});

it('gives the dashboard latest orders the variant name of each line', function () {
    orderWithVariantLine($this, $this->user);

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('recentOrders.0.order_items.0.variant_name', 'Smoke / XL'));
});

it('gives the guest order page the variant name of each line', function () {
    $order = orderWithVariantLine($this, null);

    $this->get($order->publicUrl())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('order.items.0.variant_name', 'Smoke / XL'));
});

it('sends no variant name once the variant has been deleted', function () {
    $order = orderWithVariantLine($this, null);
    $this->variant->delete();

    $this->get($order->publicUrl())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('order.items.0.variant_name', null));
});

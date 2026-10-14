<?php

use App\Models\Order;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The header's "Orders" link pointed at /orders, which does not exist (only /dashboard/orders = orders.index and the
 * signed guest page /orders/{order}). The account orders page must identify orders by their order number, the
 * reference used in the emails and on the success / guest pages, not by the database id.
 */

it('has no /orders page: the account orders list lives at orders.index', function () {
    $user = User::factory()->create();

    // the catch-all fallback route renders the "not found" page (with status 200)
    $this->actingAs($user)->get('/orders')
        ->assertInertia(fn (Assert $page) => $page->component('Errors/NotFound'));
    $this->actingAs($user)->get(route('orders.index'))->assertOk();
    expect(route('orders.index', absolute: false))->toBe('/dashboard/orders');
});

it('gives the account orders page the order number of every order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Orders')
            ->where('orders.0.order_number', $order->order_number));

    expect($order->order_number)->toStartWith('ORD-')->not->toBe((string) $order->id);
});

it('gives the dashboard latest-orders card the order number as well', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('recentOrders.0.order_number', $order->order_number));
});

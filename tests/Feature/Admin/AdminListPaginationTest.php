<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Size;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The admin Orders and Products lists render their pager from the props: they must carry real page links.
 */
beforeEach(function () {
    Queue::fake();
    $this->admin = User::factory()->create(['role' => 'admin']);
});

/** 20 orders, newest first: $orders[0] is the newest. */
function ordersNewestFirst(int $count, array $attributes = []): array
{
    return collect(range(0, $count - 1))
        ->map(fn (int $i) => Order::factory()->create($attributes + ['created_at' => now()->subMinutes($i)]))
        ->all();
}

it('gives the admin orders list working page links', function () {
    ordersNewestFirst(20);

    $this->actingAs($this->admin)->get(route('admin.orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->where('orders.meta.last_page', 2)
            ->has('orders.data', 15)
            ->where('orders.links', fn ($links) => collect($links)->contains(
                fn ($link) => is_string($link['url'] ?? null) && str_ends_with($link['url'], 'page=2')
            ))
        );
});

it('serves the second page of the admin orders list', function () {
    $orders = ordersNewestFirst(20);

    $this->actingAs($this->admin)->get(route('admin.orders.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders.meta.current_page', 2)
            ->has('orders.data', 5)
            ->where('orders.data.0.id', $orders[15]->id)
        );
});

it('keeps the admin orders filters in the page links', function () {
    ordersNewestFirst(20, ['status' => 'shipped']);

    $this->actingAs($this->admin)->get(route('admin.orders.index', ['status' => 'shipped']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders.links', fn ($links) => collect($links)->contains(
                fn ($link) => is_string($link['url'] ?? null)
                    && str_contains($link['url'], 'status=shipped')
                    && str_contains($link['url'], 'page=2')
            ))
        );
});

it('exposes the products pagination at the top level, where the page reads it', function () {
    $category = Category::factory()->create();
    $size = Size::factory()->create();
    Product::factory()->count(25)->create(['category_id' => $category->id, 'size_id' => $size->id]);

    $this->actingAs($this->admin)->get(route('admin.products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Index')
            ->where('products.current_page', 1)
            ->where('products.last_page', 2)
            ->where('products.total', 25)
            ->where('products.from', 1)
            ->where('products.to', 20)
            ->has('products.data', 20)
            ->missing('products.meta')
            ->where('products.links', fn ($links) => collect($links)->contains(
                fn ($link) => is_string($link['url'] ?? null) && str_ends_with($link['url'], 'page=2')
            ))
        );
});

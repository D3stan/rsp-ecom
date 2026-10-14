<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Settings\Settings;
use Tests\Support\CatalogFixtures;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('renders the admin dashboard on SQLite, including top products', function () {
    $product = CatalogFixtures::product(['stock_quantity' => 50]);
    $order = Order::factory()->create(['payment_status' => 'succeeded']);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);
    CatalogFixtures::product(['stock_quantity' => 50]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->has('topProducts', 1)
            ->where('topProducts.0.id', $product->id));
});

it('lists low stock products from the inventory.low_stock_threshold setting', function () {
    $low = CatalogFixtures::product(['stock_quantity' => 4]);
    $edge = CatalogFixtures::product(['stock_quantity' => 5]);
    $high = CatalogFixtures::product(['stock_quantity' => 6]);
    $out = CatalogFixtures::product(['stock_quantity' => 0]);

    app(Settings::class)->set('inventory.low_stock_threshold', 5, $this->admin);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('lowStockProducts', 2)
            ->where('lowStockProducts.0.id', $low->id)
            ->where('lowStockProducts.1.id', $edge->id));

    app(Settings::class)->set('inventory.low_stock_threshold', 0, $this->admin);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->has('lowStockProducts', 0));
});

it('uses the same low stock definition in the admin product list stats and filters', function () {
    CatalogFixtures::product(['stock_quantity' => 3]);
    CatalogFixtures::product(['stock_quantity' => 8]);
    CatalogFixtures::product(['stock_quantity' => 9]);
    CatalogFixtures::product(['stock_quantity' => 0]);

    app(Settings::class)->set('inventory.low_stock_threshold', 8, $this->admin);

    $this->actingAs($this->admin)
        ->get(route('admin.products.index', ['stock_filter' => 'low-stock']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.low_stock_count', 2)
            ->has('products.data', 2));

    $this->actingAs($this->admin)
        ->get(route('admin.products.index', ['stock_filter' => 'in-stock']))
        ->assertInertia(fn ($page) => $page->has('products.data', 1));
});

it('shares the threshold with the frontend as a public setting', function () {
    app(Settings::class)->set('inventory.low_stock_threshold', 7, $this->admin);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('settings', fn ($shared) => collect($shared)->get('inventory.low_stock_threshold') === 7));
});

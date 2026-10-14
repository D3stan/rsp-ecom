<?php

use App\Models\Category;
use App\Models\Size;
use App\Models\User;
use App\Settings\Settings;
use Tests\Support\CatalogFixtures;

beforeEach(function () {
    $this->category = Category::factory()->create(['is_active' => true]);
    $this->size = Size::factory()->create();

    foreach (range(1, 70) as $i) {
        CatalogFixtures::product([
            'category_id' => $this->category->id,
            'size_id' => $this->size->id,
            'stock_quantity' => 5,
            'name' => sprintf('Item %03d', $i),
        ]);
    }
});

function storefrontListUrl(string $list, array $query = []): string
{
    return $list === 'category'
        ? route('categories.show', ['slug' => Category::first()->slug] + $query)
        : route('products', $query);
}

dataset('storefront lists', ['products', 'category']);

it('uses catalog.per_page (default 12) and echoes the effective value in filters and pagination', function (string $list) {
    $this->get(storefrontListUrl($list))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 12)
            ->where('filters.per_page', 12)
            ->where('pagination.per_page', 12));
})->with('storefront lists');

it('follows the admin setting without a restart', function (string $list) {
    app(Settings::class)->set('catalog.per_page', 24, User::factory()->create(['role' => 'admin']));

    $this->get(storefrontListUrl($list))
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 24)
            ->where('filters.per_page', 24));
})->with('storefront lists');

it('clamps a per_page query value to 6..60', function (string $list, mixed $requested, int $expected) {
    $this->get(storefrontListUrl($list, ['per_page' => $requested]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', $expected)
            ->where('filters.per_page', $expected)
            ->where('pagination.per_page', $expected));
})->with('storefront lists')->with([
    'too low' => [1, 6],
    'zero' => [0, 6],
    'negative' => [-20, 6],
    'inside the range' => [18, 18],
    'too high' => [500, 60],
    'huge' => [999999999, 60],
]);

it('ignores a non-numeric per_page and falls back to the setting', function (string $list, mixed $requested) {
    $this->get(storefrontListUrl($list, ['per_page' => $requested]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 12)
            ->where('filters.per_page', 12));
})->with('storefront lists')->with([
    'word' => ['all'],
    'decimal' => ['1e3'],
    'array-ish' => ['9999999999999999999'],
]);

it('ignores per_page sent as an array', function () {
    $this->get('/products?per_page[]=5')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.per_page', 12));
});

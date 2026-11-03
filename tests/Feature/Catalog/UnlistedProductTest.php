<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

/*
 * An unlisted product works like an active one by direct link but appears in no storefront list or count.
 */

beforeEach(function () {
    Queue::fake();
    $this->category = Category::factory()->active()->create();
    $this->unlisted = CatalogFixtures::product(['status' => 'unlisted', 'stock_quantity' => 10, 'category_id' => $this->category->id, 'name' => 'Hidden Headlight']);
});

it('opens the unlisted product page by direct link and keeps it out of search engines', function () {
    $response = $this->get(route('products.show', $this->unlisted->slug))->assertOk();

    expect($response->getContent())
        ->toContain('<meta name="robots" content="noindex,nofollow">')
        ->not->toContain('content="index,follow"');
});

it('still returns 404 for an inactive product', function () {
    $inactive = CatalogFixtures::product(['status' => 'inactive']);

    $this->get(route('products.show', $inactive->slug))->assertNotFound();
});

it('leaves the unlisted product out of the shop list and search', function (array $query) {
    $listed = CatalogFixtures::product(['stock_quantity' => 10, 'category_id' => $this->category->id, 'name' => 'Hidden Twin']);

    $this->get(route('products', $query))
        ->assertInertia(fn (Assert $page) => $page->where('products.data', fn ($products) => collect($products)->pluck('id')->all() === [$listed->id]));
})->with([
    'list' => [[]],
    'search' => [['search' => 'Hidden']],
]);

it('does not count the unlisted product in home categories', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('categories', fn ($categories) => collect($categories)->doesntContain('slug', $this->category->slug)));
});

it('leaves the unlisted product out of related products', function () {
    $other = CatalogFixtures::product(['stock_quantity' => 10, 'category_id' => $this->category->id]);

    $this->get(route('products.show', $other->slug))
        ->assertInertia(fn (Assert $page) => $page->where('relatedProducts', fn ($related) => collect($related)->doesntContain('id', $this->unlisted->id)));
});

it('lets the unlisted product be added to the cart', function () {
    expect($this->unlisted->canAddToCart())->toBeTrue()
        ->and(CatalogFixtures::product(['status' => 'inactive', 'stock_quantity' => 10])->canAddToCart())->toBeFalse();
});

it('lets an admin set a product to unlisted', function () {
    $product = CatalogFixtures::product();
    $variant = $product->defaultVariant;

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->put(route('admin.products.update', $product), [
            'name' => $product->name,
            'category_id' => (string) $product->category_id,
            'size_id' => (string) $product->size_id,
            'status' => 'unlisted',
            'featured' => '0',
            'base_sku' => $product->base_sku,
            'variants' => json_encode([[
                'id' => $variant->id,
                'name' => $variant->name,
                'sku_suffix' => $variant->sku_suffix,
                'price' => (string) $variant->price,
                'stock_quantity' => (string) $variant->stock_quantity,
                'stock_quantity_original' => $variant->stock_quantity,
                'is_default' => true,
                'sort_order' => $variant->sort_order,
                'existing_images' => [],
            ]]),
        ])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->status)->toBe('unlisted');
});

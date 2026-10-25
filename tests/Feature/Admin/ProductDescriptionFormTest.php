<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CatalogFixtures;

beforeEach(fn () => Queue::fake());

/** @return array{0: User, 1: Product, 2: ProductVariant} */
function descriptionFormFixture(): array
{
    $product = CatalogFixtures::product();
    $variant = $product->defaultVariant;
    $variant->update(['images' => [], 'stock_quantity' => 5]);

    return [User::factory()->create(['role' => 'admin']), $product->fresh(), $variant->fresh()];
}

/** Payload as Admin/Products/Edit.tsx builds it, with the description and SEO description under test. */
function descriptionFormPayload(Product $product, ProductVariant $variant, string $description, string $seoDescription = ''): array
{
    return [
        'name' => $product->name,
        'category_id' => (string) $product->category_id,
        'size_id' => (string) $product->size_id,
        'status' => $product->status,
        'featured' => '0',
        'base_sku' => $product->base_sku,
        'seo_title' => '',
        'seo_description' => $seoDescription,
        'variants' => json_encode([[
            'id' => $variant->id,
            'name' => $variant->name,
            'sku_suffix' => $variant->sku_suffix,
            'price' => (string) $variant->price,
            'stock_quantity' => '5',
            'stock_quantity_original' => 5,
            'description' => $description,
            'is_default' => true,
            'sort_order' => $variant->sort_order,
            'existing_images' => [],
        ]]),
    ];
}

it('saves a markdown description untouched', function () {
    [$admin, $product, $variant] = descriptionFormFixture();

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), descriptionFormPayload($product, $variant, "**Bold**\n\n- one\n- two"))
        ->assertSessionHasNoErrors();

    expect($variant->fresh()->description)->toBe("**Bold**\n\n- one\n- two");
});

it('rejects a description over the limit with a field error', function () {
    [$admin, $product, $variant] = descriptionFormFixture();

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), descriptionFormPayload($product, $variant, str_repeat('a', 10001)))
        ->assertSessionHasErrors('variants.0.description');
});

it('rejects an SEO description over 500 characters with a field error', function () {
    [$admin, $product, $variant] = descriptionFormFixture();

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), descriptionFormPayload($product, $variant, 'ok', str_repeat('a', 501)))
        ->assertSessionHasErrors('seo_description');
});

it('accepts a 500 character SEO description', function () {
    [$admin, $product, $variant] = descriptionFormFixture();

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), descriptionFormPayload($product, $variant, 'ok', str_repeat('a', 500)))
        ->assertSessionHasNoErrors();
});

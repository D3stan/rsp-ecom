<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;

/**
 * Minimal catalogue rows for feature tests (the product factory needs a category and a size to exist).
 */
final class CatalogFixtures
{
    public static function product(array $attributes = []): Product
    {
        $category = Category::query()->first() ?? Category::factory()->create();
        $size = Size::query()->first() ?? Size::factory()->create();

        return Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'size_id' => $size->id,
            'status' => 'active',
        ], $attributes));
    }

    public static function variant(Product $product, array $attributes = []): ProductVariant
    {
        return ProductVariant::factory()->create(array_merge([
            'product_id' => $product->id,
        ], $attributes));
    }
}

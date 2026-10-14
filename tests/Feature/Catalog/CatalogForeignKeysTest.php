<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Database\QueryException;

/**
 * Deleting a category or a shipping size must never delete its products (and, through them, variants,
 * reviews, wishlists and cart lines). The admin controller already refuses; the database must too.
 */
function productIn(Category $category, Size $size): Product
{
    return Product::factory()->create(['category_id' => $category->id, 'size_id' => $size->id]);
}

/** Runs the delete; true when the database refused it. */
function deleteIsRefused(Category|Size $model): bool
{
    try {
        $model->delete();
    } catch (QueryException) {
        return true;
    }

    return false;
}

test('a category with products cannot be deleted, even outside the admin controller', function () {
    $product = productIn($category = Category::factory()->create(), Size::factory()->create());

    $refused = deleteIsRefused($category);

    expect(Product::find($product->id))->not->toBeNull('the product was deleted by the category cascade')
        ->and($refused)->toBeTrue()
        ->and(Category::find($category->id))->not->toBeNull();
});

test('a size with products cannot be deleted, even outside the admin controller', function () {
    $product = productIn(Category::factory()->create(), $size = Size::factory()->create());

    $refused = deleteIsRefused($size);

    expect(Product::find($product->id))->not->toBeNull('the product was deleted by the size cascade')
        ->and($refused)->toBeTrue()
        ->and(Size::find($size->id))->not->toBeNull();
});

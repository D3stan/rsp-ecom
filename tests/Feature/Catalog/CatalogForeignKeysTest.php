<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Size;
use App\Models\User;
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

test('an empty category or size can still be deleted', function () {
    $category = Category::factory()->create();
    $size = Size::factory()->create();

    expect(deleteIsRefused($category))->toBeFalse()
        ->and(deleteIsRefused($size))->toBeFalse()
        ->and(Category::find($category->id))->toBeNull()
        ->and(Size::find($size->id))->toBeNull();
});

test('the restrict migration is reversible on sqlite', function () {
    $migration = require database_path('migrations/2026_10_15_100100_restrict_category_and_size_deletes.php');

    $migration->down();
    $product = productIn($category = Category::factory()->create(), Size::factory()->create());
    $category->delete();
    expect(Product::find($product->id))->toBeNull();

    $migration->up();
    $product = productIn($category = Category::factory()->create(), Size::factory()->create());
    expect(deleteIsRefused($category))->toBeTrue()
        ->and(Product::find($product->id))->not->toBeNull();
});

test('the admin controller still refuses with its message', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    productIn($category = Category::factory()->create(), $size = Size::factory()->create());

    $this->actingAs($admin)->delete(route('admin.categories.destroy', $category))->assertSessionHas('error');
    $this->actingAs($admin)->delete(route('admin.sizes.destroy', $size))->assertSessionHas('error');

    expect(Category::find($category->id))->not->toBeNull()
        ->and(Size::find($size->id))->not->toBeNull();
});

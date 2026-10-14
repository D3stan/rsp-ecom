<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CatalogFixtures;

/*
 * The product edit form posts the stock it loaded (stock_quantity_original) next to the value the admin typed; the
 * server applies only the difference to the current stock, so sales that happen while the form is open are kept.
 */

beforeEach(function () {
    Queue::fake();
});

/** Payload exactly as Admin/Products/Edit.tsx builds it (variants as a JSON string, numbers typed as strings). */
function stockEditPayload(Product $product, array $variants): array
{
    return [
        'name' => $product->name,
        'category_id' => (string) $product->category_id,
        'size_id' => (string) $product->size_id,
        'status' => $product->status,
        'featured' => '0',
        'base_sku' => $product->base_sku,
        'seo_title' => '',
        'seo_description' => '',
        'variants' => json_encode($variants),
    ];
}

/** One variant row of the form as loaded from the edit page, with the stock the admin typed. */
function stockEditVariant(ProductVariant $variant, int $loaded, int $typed): array
{
    return [
        'id' => $variant->id,
        'name' => $variant->name,
        'sku_suffix' => $variant->sku_suffix,
        'price' => (string) $variant->price,
        'stock_quantity' => (string) $typed,
        'stock_quantity_original' => $loaded,
        'description' => $variant->description ?? '',
        'is_default' => (bool) $variant->is_default,
        'sort_order' => $variant->sort_order,
        'existing_images' => [],
    ];
}

/** A paid order for $quantity units of $variant whose stock is deducted (a sale while the form is open). */
function stockEditSell(ProductVariant $variant, int $quantity): void
{
    $order = Order::factory()->create(['status' => 'processing']);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $variant->product_id,
        'product_variant_id' => $variant->id,
        'product_name' => 'Sold item',
        'quantity' => $quantity,
        'price' => 10,
        'total' => 10 * $quantity,
    ]);

    expect(app(StockService::class)->deductForOrder($order))->toBeTrue();
}

/** @return array{0: User, 1: Product, 2: ProductVariant} */
function stockEditFixture(int $stock): array
{
    $product = CatalogFixtures::product(['stock_quantity' => $stock]);
    $variant = $product->defaultVariant;
    // images: the product factory copies its raw JSON string into the variant; real variants hold an array
    $variant->update(['stock_quantity' => $stock, 'images' => []]);
    $product->syncStockMirror();

    return [User::factory()->create(['role' => 'admin']), $product->fresh(), $variant->fresh()];
}

it('keeps a sale made while the form was open when the stock field is left unchanged', function () {
    [$admin, $product, $variant] = stockEditFixture(10);
    $payload = stockEditPayload($product, [stockEditVariant($variant, 10, 10)]);

    stockEditSell($variant, 3);
    expect($variant->fresh()->stock_quantity)->toBe(7);

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.products.index'));

    expect($variant->fresh()->stock_quantity)->toBe(7)
        ->and($product->fresh()->stock_quantity)->toBe(7);
});

it('applies the admin change as a delta on top of a concurrent sale', function () {
    [$admin, $product, $variant] = stockEditFixture(10);
    $payload = stockEditPayload($product, [stockEditVariant($variant, 10, 15)]);

    stockEditSell($variant, 3);

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), $payload)
        ->assertSessionHasNoErrors();

    expect($variant->fresh()->stock_quantity)->toBe(12)
        ->and($product->fresh()->stock_quantity)->toBe(12);
});

it('clamps a decrease at zero and logs a warning', function () {
    [$admin, $product, $variant] = stockEditFixture(10);
    // The admin lowers 10 -> 2 (-8) while 9 units sell: 1 left, 1 - 8 would be -7
    $payload = stockEditPayload($product, [stockEditVariant($variant, 10, 2)]);

    stockEditSell($variant, 9);
    Log::spy();

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), $payload)
        ->assertSessionHasNoErrors();

    expect($variant->fresh()->stock_quantity)->toBe(0);
    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message, $context = []) => str_contains($message, 'clamped') && ($context['variant_id'] ?? null) === $variant->id)
        ->once();
});

it('tells the admin when the stock changed while the form was open', function () {
    [$admin, $product, $variant] = stockEditFixture(10);
    $payload = stockEditPayload($product, [stockEditVariant($variant, 10, 15)]);

    stockEditSell($variant, 3);

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), $payload)
        ->assertSessionHas('warning', fn (string $message) => str_contains($message, $variant->name) && str_contains($message, '12'));
});

it('does not warn when nobody else touched the stock', function () {
    [$admin, $product, $variant] = stockEditFixture(10);

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), stockEditPayload($product, [stockEditVariant($variant, 10, 4)]))
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('warning');

    expect($variant->fresh()->stock_quantity)->toBe(4);
});

it('creates a new variant with the typed stock as is', function () {
    [$admin, $product, $variant] = stockEditFixture(10);
    $new = [
        'name' => 'Brand new',
        'sku_suffix' => '-NEW',
        'price' => '30',
        'stock_quantity' => '6',
        'description' => '',
        'is_default' => false,
        'sort_order' => 1,
        'existing_images' => [],
    ];

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), stockEditPayload($product, [stockEditVariant($variant, 10, 10), $new]))
        ->assertSessionHasNoErrors();

    expect(ProductVariant::where('product_id', $product->id)->where('name', 'Brand new')->value('stock_quantity'))->toBe(6)
        ->and($variant->fresh()->stock_quantity)->toBe(10);
});

it('refuses a save of an existing variant without the loaded stock (out-of-date form)', function () {
    [$admin, $product, $variant] = stockEditFixture(10);
    $row = stockEditVariant($variant, 10, 4);
    unset($row['stock_quantity_original']);

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), stockEditPayload($product, [$row]))
        ->assertSessionHasErrors(['variants.0.stock_quantity_original' => 'This form is out of date: reload the page and try again.']);

    expect($variant->fresh()->stock_quantity)->toBe(10)
        ->and($product->fresh()->stock_quantity)->toBe(10);
});

<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Size;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->admin = User::factory()->create(['role' => 'admin']);
});

/**
 * A paid order in $status whose stock was deducted at checkout: 2 units of a product that has 10 left.
 * Cancelling it would give the 2 units back (OrderObserver -> StockService::restoreForOrder).
 */
function transitionsOrder(string $status): Order
{
    $product = Product::factory()->create([
        // one shared category/size: CategoryFactory draws from a short list of unique slugs
        'category_id' => Category::query()->value('id') ?? Category::factory()->create()->id,
        'size_id' => Size::query()->value('id') ?? Size::factory()->create()->id,
        'stock_quantity' => 10,
    ]);
    $product->defaultVariant->update(['stock_quantity' => 10]);

    $order = Order::factory()->create([
        'status' => $status,
        'payment_status' => 'succeeded',
        'user_id' => null,
        'guest_email' => 'buyer@example.test',
    ]);
    $order->orderItems()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 2,
        'price' => 10,
        'total' => 20,
    ]);
    Order::whereKey($order->id)->update(['stock_deducted_at' => now()]);

    return $order->fresh();
}

function transitionsStock(Order $order): int
{
    return (int) $order->orderItems()->first()->product->defaultVariant()->value('stock_quantity');
}

it('refuses to cancel a delivered order through the edit form', function () {
    $order = transitionsOrder('delivered');

    $response = $this->actingAs($this->admin)->put(route('admin.orders.update', $order), ['status' => 'cancelled']);

    expect($order->fresh()->status)->toBe('delivered')
        ->and(transitionsStock($order))->toBe(10);
    $response->assertSessionHas('error');
});

it('refuses to cancel a delivered order through the status endpoint', function () {
    $order = transitionsOrder('delivered');

    $response = $this->actingAs($this->admin)->patch(route('admin.orders.update-status', $order), ['status' => 'cancelled']);

    expect($order->fresh()->status)->toBe('delivered')
        ->and(transitionsStock($order))->toBe(10);
    $response->assertSessionHas('error');
});

it('refuses to move a shipped order back to pending', function () {
    $order = transitionsOrder('shipped');

    $response = $this->actingAs($this->admin)->patch(route('admin.orders.update-status', $order), ['status' => 'pending']);

    expect($order->fresh()->status)->toBe('shipped');
    $response->assertSessionHas('error');
});

it('refuses to undo a shipment without a reason', function () {
    $order = transitionsOrder('shipped');

    $response = $this->actingAs($this->admin)->patch(route('admin.orders.update-status', $order), ['status' => 'processing']);

    expect($order->fresh()->status)->toBe('shipped');
    $response->assertSessionHas('error');
});

it('bulk-cancels only the orders that may be cancelled and reports the skipped ones', function () {
    $processing = transitionsOrder('processing');
    $shipped = transitionsOrder('shipped');
    $delivered = transitionsOrder('delivered');

    $response = $this->actingAs($this->admin)->patch(route('admin.orders.bulk-update-status'), [
        'order_ids' => [$processing->id, $shipped->id, $delivered->id],
        'status' => 'cancelled',
    ]);

    expect($shipped->fresh()->status)->toBe('shipped')
        ->and($delivered->fresh()->status)->toBe('delivered')
        ->and(transitionsStock($shipped))->toBe(10)
        ->and(transitionsStock($delivered))->toBe(10)
        // the allowed one still goes through, and gets its stock back
        ->and($processing->fresh()->status)->toBe('cancelled')
        ->and(transitionsStock($processing))->toBe(12);

    $flash = (string) session('success');
    expect($flash)->toContain('1 updated')
        ->and($flash)->toContain('2 skipped')
        ->and($flash)->toContain($shipped->order_number)
        ->and($flash)->toContain($delivered->order_number);
});

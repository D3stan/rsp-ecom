<?php

use App\Exceptions\InvalidOrderTransition;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Size;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

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

dataset('every status move', function () {
    $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    $allowed = ['pending>processing', 'pending>cancelled', 'processing>shipped', 'processing>cancelled', 'shipped>delivered', 'shipped>processing'];

    foreach ($statuses as $from) {
        foreach ($statuses as $to) {
            if ($from !== $to) {
                yield "{$from} -> {$to}" => [$from, $to, in_array("{$from}>{$to}", $allowed, true)];
            }
        }
    }
});

it('allows exactly the moves of the lifecycle map', function (string $from, string $to, bool $allowed) {
    $order = Order::factory()->create(['status' => $from, 'payment_status' => 'succeeded']);

    try {
        $order->transitionTo($to, $this->admin, 'checked by the test');
        $moved = true;
    } catch (InvalidOrderTransition) {
        $moved = false;
    }

    expect($moved)->toBe($allowed)
        ->and($order->fresh()->status)->toBe($allowed ? $to : $from);
})->with('every status move');

it('undoes a shipment when a reason is given', function () {
    $order = transitionsOrder('shipped');

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.update-status', $order), ['status' => 'processing', 'reason' => 'Parcel came back to the warehouse'])
        ->assertSessionHas('success');

    expect($order->fresh()->status)->toBe('processing');
});

it('stamps delivered_at when the order enters delivered', function () {
    $order = transitionsOrder('shipped');

    $this->actingAs($this->admin)->patch(route('admin.orders.update-status', $order), ['status' => 'delivered']);

    expect($order->fresh()->delivered_at)->not->toBeNull();
});

it('decides on the stored status, not on a stale copy of the order', function () {
    $order = transitionsOrder('processing');
    $stale = Order::find($order->id);

    $order->transitionTo('shipped');

    expect(fn () => $stale->transitionTo('cancelled'))->toThrow(InvalidOrderTransition::class);
    expect($order->fresh()->status)->toBe('shipped')
        ->and(transitionsStock($order))->toBe(10);
});

it('treats a move to the current status as a no-op that still saves the other changes', function () {
    $order = transitionsOrder('delivered');

    $order->notes = 'checked';
    $order->transitionTo('delivered');

    expect($order->fresh()->notes)->toBe('checked')
        ->and($order->fresh()->delivered_at)->toBeNull(); // not re-entered
});

it('refuses to ship an unpaid pending order from the ship form', function () {
    $order = Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending']);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $order), [
        'sender_info' => ['company_name' => 'Shop', 'address' => 'Via Roma 1', 'phone' => '123', 'email' => 'shop@example.test'],
        'customer_info' => ['name' => 'Mario Rossi', 'email' => 'mario@example.test'],
        'shipping_address' => [
            'first_name' => 'Mario', 'last_name' => 'Rossi', 'address_line_1' => 'Via Verdi 2',
            'city' => 'Milano', 'state' => 'MI', 'postal_code' => '20100', 'country' => 'IT',
        ],
    ])->assertSessionHas('error');

    expect($order->fresh()->status)->toBe('pending');
});

it('passes the allowed next statuses to the admin order pages', function () {
    $order = transitionsOrder('shipped');

    $this->actingAs($this->admin)->get(route('admin.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('allowed_transitions', ['delivered', 'processing']));

    $this->actingAs($this->admin)->get(route('admin.orders.edit', $order))
        ->assertInertia(fn (Assert $page) => $page->where('allowed_transitions', ['delivered', 'processing']));

    $this->actingAs($this->admin)->get(route('admin.orders.index'))
        ->assertInertia(fn (Assert $page) => $page->where('orders.data.0.allowed_transitions', ['delivered', 'processing']));
});

<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->admin = User::factory()->create(['role' => 'admin']);
});

/**
 * Two lines (2 x 10.00 and 1 x 5.00 = 25.00), shipping 5.00, discount 3.00: total 27.00, VAT 22% included.
 */
function editGuardsOrder(string $paymentStatus, string $status = 'processing'): Order
{
    $order = Order::factory()->create([
        'status' => $status,
        'payment_status' => $paymentStatus,
        'user_id' => null,
        'guest_email' => 'buyer@example.test',
        'subtotal' => 25.00,
        'shipping_amount' => 5.00,
        'discount_amount' => 3.00,
        'total_amount' => 27.00,
        'tax_rate' => 22.00,
        'tax_amount' => 4.87,
    ]);
    $order->orderItems()->create(['product_id' => null, 'product_name' => 'Line A', 'quantity' => 2, 'price' => 10.00, 'total' => 20.00]);
    $order->orderItems()->create(['product_id' => null, 'product_name' => 'Line B', 'quantity' => 1, 'price' => 5.00, 'total' => 5.00]);

    return $order->fresh('orderItems');
}

/** The order_items field as the edit form sends it (a JSON string). */
function editGuardsLines(Order $order, array $changes = [], array $drop = []): string
{
    return json_encode($order->orderItems
        ->reject(fn ($item) => in_array($item->product_name, $drop, true))
        ->map(fn ($item) => array_merge(
            ['id' => $item->id, 'quantity' => $item->quantity, 'price' => (float) $item->price, 'total' => (float) $item->total],
            $changes[$item->product_name] ?? [],
        ))->values()->all());
}

it('refuses to change the payment status of a paid order', function () {
    $order = editGuardsOrder('succeeded');

    $response = $this->actingAs($this->admin)->patch(route('admin.orders.update', $order), ['payment_status' => 'pending']);

    expect($order->fresh()->payment_status)->toBe('succeeded');
    $response->assertSessionHas('error');
});

it('refuses a negative line quantity', function () {
    $order = editGuardsOrder('pending', 'pending');

    $response = $this->actingAs($this->admin)->patch(route('admin.orders.update', $order), [
        'order_items' => editGuardsLines($order, ['Line A' => ['quantity' => -3, 'total' => -30]]),
    ]);

    expect($order->orderItems()->where('product_name', 'Line A')->value('quantity'))->toBe(2);
    $response->assertSessionHas('error');
});

it('refuses to change the totals of a paid order', function () {
    $order = editGuardsOrder('succeeded');

    $response = $this->actingAs($this->admin)->patch(route('admin.orders.update', $order), ['subtotal' => 1.00, 'total_amount' => 1.00]);

    expect((float) $order->fresh()->total_amount)->toBe(27.0)
        ->and((float) $order->fresh()->subtotal)->toBe(25.0);
    $response->assertSessionHas('error');
});

it('refuses to change line quantities or prices of a paid order', function () {
    $order = editGuardsOrder('succeeded');

    $response = $this->actingAs($this->admin)->patch(route('admin.orders.update', $order), [
        'order_items' => editGuardsLines($order, ['Line A' => ['quantity' => 5, 'total' => 50], 'Line B' => ['price' => 1.00, 'total' => 1.00]]),
    ]);

    $lines = $order->orderItems()->pluck('quantity', 'product_name');
    expect($lines['Line A'])->toBe(2)
        ->and((float) $order->orderItems()->where('product_name', 'Line B')->value('price'))->toBe(5.0);
    $response->assertSessionHas('error');
});

it('recomputes the totals of an unpaid order from its lines, shipping and discount', function () {
    $order = editGuardsOrder('pending', 'pending');

    // the form also sends its own (stale) totals: the server derives them instead
    $this->actingAs($this->admin)->patch(route('admin.orders.update', $order), [
        'order_items' => editGuardsLines($order, ['Line A' => ['quantity' => 3, 'total' => 30]]),
        'shipping_amount' => 5.00,
        'subtotal' => 25.00,
        'total_amount' => 30.00,
    ])->assertSessionHas('success');

    $fresh = $order->fresh();
    expect((float) $fresh->subtotal)->toBe(35.0)              // 3 x 10 + 1 x 5
        ->and((float) $fresh->total_amount)->toBe(37.0)       // 35 + 5 shipping - 3 discount
        ->and((float) $fresh->tax_amount)->toBe(6.67)         // 22% contained in 37.00
        ->and((float) $fresh->orderItems()->where('product_name', 'Line A')->value('total'))->toBe(30.0);
});

it('deletes a line removed from an unpaid order', function () {
    $order = editGuardsOrder('pending', 'pending');

    $this->actingAs($this->admin)->patch(route('admin.orders.update', $order), [
        'order_items' => editGuardsLines($order, drop: ['Line A']),
    ])->assertSessionHas('success');

    expect($order->orderItems()->pluck('product_name')->all())->toBe(['Line B'])
        ->and((float) $order->fresh()->subtotal)->toBe(5.0)
        ->and((float) $order->fresh()->total_amount)->toBe(7.0);
});

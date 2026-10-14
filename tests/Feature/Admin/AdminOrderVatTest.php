<?php

use App\Models\Order;
use App\Models\User;
use App\Support\Vat;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('computes the VAT portion of a gross amount for a given rate', function () {
    expect(Vat::portion(122.00, 22.0))->toBe(22.0)
        ->and(Vat::portion(145.90, 22.0))->toBe(26.31)
        ->and(Vat::portion(-5.0, 22.0))->toBe(0.0)
        ->and(Vat::portion(100.0, 0.0))->toBe(0.0);
});

it('recomputes the stored VAT portion with the order rate when an admin edits an unpaid order', function () {
    // totals of a paid order are locked (OrderEditGuardsTest); an unpaid one is recomputed from lines + shipping
    $order = Order::factory()->create([
        'payment_status' => 'pending', 'status' => 'pending',
        'subtotal' => 117.00, 'shipping_amount' => 5.00, 'discount_amount' => 0,
        'total_amount' => 122.00, 'tax_amount' => 22.00, 'tax_rate' => 22.00,
    ]);
    $order->orderItems()->create(['product_id' => null, 'product_name' => 'Item', 'quantity' => 1, 'price' => 117.00, 'total' => 117.00]);

    // the shop rate changes later: the order keeps its own rate
    settings()->set('tax.rate', 10);

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), ['shipping_amount' => 127.00]);

    $order->refresh();
    expect((float) $order->total_amount)->toBe(244.0)
        ->and((float) $order->tax_amount)->toBe(44.0)
        ->and((float) $order->tax_rate)->toBe(22.0);
});

it('leaves the VAT portion alone when the total is not edited or the order has no rate snapshot', function () {
    $withRate = Order::factory()->create(['payment_status' => 'succeeded', 'status' => 'processing', 'total_amount' => 122.00, 'tax_amount' => 22.00, 'tax_rate' => 22.00]);
    $legacy = Order::factory()->create(['payment_status' => 'succeeded', 'status' => 'processing', 'total_amount' => 100.00, 'tax_amount' => 7.00, 'tax_rate' => null]);

    $this->actingAs($this->admin)->put(route('admin.orders.update', $withRate), ['notes' => 'x']);
    $this->actingAs($this->admin)->put(route('admin.orders.update', $legacy), ['total_amount' => 200.00]);

    expect((float) $withRate->fresh()->tax_amount)->toBe(22.0)
        ->and((float) $legacy->fresh()->tax_amount)->toBe(7.0);
});

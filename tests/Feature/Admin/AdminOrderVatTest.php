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

it('recomputes the stored VAT portion with the order rate when an admin edits the total', function () {
    $order = Order::factory()->create([
        'payment_status' => 'succeeded', 'status' => 'processing',
        'total_amount' => 122.00, 'tax_amount' => 22.00, 'tax_rate' => 22.00,
    ]);

    // the shop rate changes later: the order keeps its own rate
    settings()->set('tax.rate', 10);

    $this->actingAs($this->admin)->put(route('admin.orders.update', $order), ['total_amount' => 244.00]);

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

<?php

use App\Models\Order;
use App\Models\Shipment;

it('stores the pickup date, the deferred-email flag and the awaiting_payment status on a shipment', function () {
    $shipment = Order::factory()->create()->shipments()->create([
        'carrier_code' => 'pdb', 'driver' => 'spediamo', 'status' => Shipment::STATUS_AWAITING_PAYMENT,
        'pickup_date' => '2026-10-26', 'notify_on_pickup' => true,
    ]);

    expect($shipment->fresh()->pickup_date->toDateString())->toBe('2026-10-26')
        ->and($shipment->fresh()->notify_on_pickup)->toBeTrue()
        ->and($shipment->fresh()->status)->toBe('awaiting_payment');
});

<?php

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\Shipping\Support\TrackingFixtures as Fx;

it('does not queue an order whose shipment still waits for payment', function () {
    Bus::fake();
    Fx::useFake();

    $draft = Fx::shippedOrder(['tracking_number' => 'shp_draft_1']);
    $draft->shipments()->create([
        'carrier_code' => 'sda', 'driver' => 'spediamo', 'external_id' => 'shp_draft_1',
        'tracking_number' => 'shp_draft_1', 'status' => Shipment::STATUS_AWAITING_PAYMENT,
    ]);
    $paid = Fx::shippedOrder(['tracking_number' => 'TRK-2']);

    $this->artisan('tracking:sync')->assertSuccessful()->expectsOutputToContain('Queued 1');

    // a queued order is claimed with a lease (tracking_next_sync_at is set); the draft must stay untouched
    expect(Order::query()->whereKey($draft->id)->value('tracking_next_sync_at'))->toBeNull()
        ->and(Order::query()->whereKey($paid->id)->value('tracking_next_sync_at'))->not->toBeNull();
});

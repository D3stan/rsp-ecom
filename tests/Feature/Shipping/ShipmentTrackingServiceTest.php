<?php

use App\Jobs\SendOrderEmail;
use App\Models\OrderEmail;
use App\Models\ShipmentTrackingEvent;
use App\Shipping\Tracking\DTO\TrackingEvent;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\Enums\TrackingStatus as S;
use App\Shipping\Tracking\ShipmentTrackingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Shipping\Support\TrackingFixtures as Fx;

beforeEach(function () {
    Queue::fake();
    $this->service = app(ShipmentTrackingService::class);
});

function trkDeliveredEmails(): int
{
    return OrderEmail::where('type', OrderEmail::TYPE_DELIVERED)->count();
}

it('stores events once: applying the same answer twice changes nothing', function () {
    $order = Fx::shippedOrder();
    $result = Fx::result(S::InTransit, Fx::event(S::InfoReceived, '-3 hours', 'e1'), Fx::event(S::InTransit, '-1 hour', 'e2'));

    $this->service->apply($order, 'fake', $result);
    $this->service->apply($order, 'fake', $result);

    expect(ShipmentTrackingEvent::where('order_id', $order->id)->count())->toBe(2)
        ->and($order->tracking_status)->toBe('in_transit')
        ->and($order->tracking_last_synced_at)->not->toBeNull()
        ->and($order->tracking_started_at)->not->toBeNull()
        ->and($order->tracking_next_sync_at)->not->toBeNull()
        ->and($order->status)->toBe('shipped');
});

it('deduplicates the same event arriving with and without wording changes when it has a carrier id', function () {
    $order = Fx::shippedOrder();

    $this->service->apply($order, 'fake', Fx::result(S::InTransit, Fx::event(S::InTransit, '-2 hours', 'same', 'Departed')));
    $this->service->apply($order, 'fake', Fx::result(S::InTransit, Fx::event(S::InTransit, '-2 hours', 'same', 'Departed from hub')));

    expect(ShipmentTrackingEvent::count())->toBe(1);
});

it('keeps separate histories per provider', function () {
    $order = Fx::shippedOrder();
    $result = Fx::result(S::InTransit, Fx::event(S::InTransit, '-2 hours', 'e1'));

    $this->service->apply($order, 'one', $result);
    $this->service->apply($order, 'two', $result);

    expect(ShipmentTrackingEvent::count())->toBe(2);
});

it('stores timestamps in UTC whatever zone the carrier used', function () {
    $order = Fx::shippedOrder();
    $event = new TrackingEvent(S::InTransit, 'x', null, CarbonImmutable::parse('2026-03-01 10:00:00', 'Asia/Tokyo'), 'e1');

    $this->service->apply($order, 'fake', new TrackingResult(S::InTransit, [$event]));

    expect(ShipmentTrackingEvent::first()->occurred_at->utc()->format('Y-m-d H:i:s'))->toBe('2026-03-01 01:00:00');
});

it('never moves the tracking status backwards (out-of-order events)', function () {
    $order = Fx::shippedOrder();

    $this->service->apply($order, 'fake', Fx::result(S::OutForDelivery, Fx::event(S::OutForDelivery, '-1 hour', 'late')));
    $this->service->apply($order, 'fake', Fx::result(S::InTransit, Fx::event(S::InTransit, '-5 hours', 'early')));
    $this->service->apply($order, 'fake', Fx::result(S::Unknown));

    expect($order->fresh()->tracking_status)->toBe('out_for_delivery')
        ->and(ShipmentTrackingEvent::count())->toBe(2); // the late-arriving old scan is still recorded in the history
});

it('marks a shipped order delivered and sends exactly one delivered email, however often it is applied', function () {
    $order = Fx::shippedOrder();
    $result = Fx::result(S::Delivered, Fx::event(S::InTransit, '-5 hours', 'e1'), Fx::event(S::Delivered, '-1 hour', 'e2'));

    $this->service->apply($order, 'fake', $result);   // e.g. the webhook
    $this->service->apply($order, 'fake', $result);   // e.g. the poll right after

    expect($order->fresh()->status)->toBe('delivered')
        ->and($order->fresh()->delivered_at)->not->toBeNull()
        ->and($order->fresh()->tracking_status)->toBe('delivered')
        ->and($order->fresh()->tracking_next_sync_at)->toBeNull()
        ->and(trkDeliveredEmails())->toBe(1);

    Queue::assertPushed(SendOrderEmail::class, fn ($job) => $job->type === OrderEmail::TYPE_DELIVERED);
    Queue::assertPushed(SendOrderEmail::class, 1);
});

it('does not email an order marked delivered by the carrier when the customer was never notified', function () {
    $order = Fx::shippedOrder(['payment_status' => 'pending']);

    $this->service->apply($order, 'fake', Fx::result(S::Delivered, Fx::event(S::Delivered, '-1 hour', 'e2')));

    expect($order->fresh()->status)->toBe('delivered')->and(trkDeliveredEmails())->toBe(0);
});

it('leaves cancelled, delivered and not-yet-shipped orders completely untouched', function (string $status) {
    $order = Fx::shippedOrder(['status' => $status]);

    $this->service->apply($order, 'fake', Fx::result(S::Delivered, Fx::event(S::Delivered, '-1 hour', 'e1')));

    $fresh = $order->fresh();
    expect($fresh->status)->toBe($status)
        ->and($fresh->tracking_status)->toBeNull()
        ->and($fresh->tracking_last_synced_at)->toBeNull()
        ->and(ShipmentTrackingEvent::count())->toBe(0);
    Queue::assertNothingPushed();
})->with(['cancelled', 'delivered', 'processing', 'pending']);

it('ignores shipped orders without a tracking number', function () {
    $order = Fx::shippedOrder(['tracking_number' => null]);

    $this->service->apply($order, 'fake', Fx::result(S::InTransit, Fx::event(S::InTransit, '-1 hour', 'e1')));

    expect(ShipmentTrackingEvent::count())->toBe(0);
});

it('records Exception and Returned without changing the order or sending mail', function (S $status) {
    $order = Fx::shippedOrder();

    $this->service->apply($order, 'fake', Fx::result($status, Fx::event($status, '-1 hour', 'e1')));

    $fresh = $order->fresh();
    expect($fresh->status)->toBe('shipped')
        ->and($fresh->tracking_status)->toBe($status->value)
        ->and(ShipmentTrackingEvent::count())->toBe(1);
    Queue::assertNothingPushed();
})->with([S::Exception, S::Returned]);

it('can recover from an exception back to transit, but not after Returned', function () {
    $order = Fx::shippedOrder();

    $this->service->apply($order, 'fake', Fx::result(S::Exception, Fx::event(S::Exception, '-3 hours', 'e1')));
    $this->service->apply($order, 'fake', Fx::result(S::OutForDelivery, Fx::event(S::OutForDelivery, '-2 hours', 'e2')));
    expect($order->fresh()->tracking_status)->toBe('out_for_delivery');

    $this->service->apply($order, 'fake', Fx::result(S::Returned, Fx::event(S::Returned, '-1 hour', 'e3')));
    $this->service->apply($order, 'fake', Fx::result(S::Delivered, Fx::event(S::Delivered, '-30 minutes', 'e4')));

    // terminal Returned: no automatic delivery, polling stops (next sync cleared)
    expect($order->fresh()->tracking_status)->toBe('returned')
        ->and($order->fresh()->status)->toBe('shipped')
        ->and($order->fresh()->tracking_next_sync_at)->toBeNull();
});

it('drops events dated far in the future but keeps small clock skew', function () {
    $order = Fx::shippedOrder();

    $this->service->apply($order, 'fake', Fx::result(S::InTransit,
        Fx::event(S::InTransit, '+3 days', 'future'),
        Fx::event(S::InTransit, '+10 minutes', 'skew'),
    ));

    expect(ShipmentTrackingEvent::pluck('external_id')->all())->toBe(['skew']);
});

it('caps huge raw payloads and long text', function () {
    $order = Fx::shippedOrder();
    config(['shipping.tracking.max_raw_bytes' => 100]);
    $event = new TrackingEvent(S::InTransit, str_repeat('d', 5000), str_repeat('l', 600), CarbonImmutable::now()->subHour(), 'e1', ['blob' => str_repeat('x', 5000)]);
    $small = new TrackingEvent(S::InTransit, 'ok', null, CarbonImmutable::now()->subMinutes(30), 'e2', ['a' => 1]);

    $this->service->apply($order, 'fake', new TrackingResult(S::InTransit, [$event, $small]));

    $big = ShipmentTrackingEvent::where('external_id', 'e1')->first();
    expect($big->raw)->toHaveKey('_truncated')->and(strlen($big->description))->toBeLessThanOrEqual(1003)->and(strlen($big->location))->toBe(255)
        ->and(ShipmentTrackingEvent::where('external_id', 'e2')->first()->raw)->toBe(['a' => 1]);
});

it('hands the caller an up-to-date instance', function () {
    $order = Fx::shippedOrder();

    $this->service->apply($order, 'fake', Fx::result(S::InTransit, Fx::event(S::InTransit, '-1 hour', 'e1')));

    expect($order->tracking_status)->toBe('in_transit')->and($order->isDirty())->toBeFalse();
});

it('starts a fresh tracking cycle when the tracking number or carrier is corrected', function () {
    config(['shipping.carriers' => ['acme' => ['name' => 'Acme'], 'zeta' => ['name' => 'Zeta']]]);
    $order = Fx::shippedOrder(['tracking_carrier' => 'acme']);
    $this->service->apply($order, 'fake', Fx::result(S::InTransit, Fx::event(S::InTransit, '-1 hour', 'e1')));
    $this->service->disablePolling($order);

    // only case/spacing of the same number: nothing resets
    $order->update(['tracking_number' => 'trk-1001 ']);
    expect($order->fresh()->tracking_status)->toBe('in_transit');

    $order->update(['tracking_number' => 'NEW-2']);
    $fresh = $order->fresh();
    expect($fresh->tracking_status)->toBeNull()->and($fresh->tracking_next_sync_at)->toBeNull()->and($fresh->tracking_started_at)->toBeNull();

    $this->service->apply($order, 'fake', Fx::result(S::InTransit, Fx::event(S::InTransit, '-1 hour', 'e9')));
    $order->update(['tracking_carrier' => 'zeta']);
    expect($order->fresh()->tracking_status)->toBeNull();
});

it('does not reset tracking state on no-op tracking_driver or tracking_carrier edits, but resets when changed', function () {
    $order = Fx::shippedOrder(['tracking_driver' => null]);
    $this->service->apply($order, 'fake', Fx::result(S::InTransit, Fx::event(S::InTransit, '-1 hour', 'e1')));
    $order->update(['tracking_next_sync_at' => now()->addHour()]);
    expect($order->fresh()->tracking_status)->toBe('in_transit')
        ->and($order->fresh()->tracking_next_sync_at)->not->toBeNull();

    // 1. updating tracking_driver from null to '' does NOT wipe tracking_status or tracking_next_sync_at
    $order->update(['tracking_driver' => '']);
    $fresh = $order->fresh();
    expect($fresh->tracking_status)->toBe('in_transit')
        ->and($fresh->tracking_next_sync_at)->not->toBeNull();

    // 2. updating to same driver with whitespace / case does NOT wipe tracking_status
    $order->update(['tracking_driver' => 'fake']);
    $this->service->apply($order, 'fake', Fx::result(S::InTransit, Fx::event(S::InTransit, '-1 hour', 'e2')));
    $order->update(['tracking_next_sync_at' => now()->addHour()]);
    expect($order->fresh()->tracking_status)->toBe('in_transit');

    $order->update(['tracking_driver' => '  FAKE  ']);
    $fresh = $order->fresh();
    expect($fresh->tracking_status)->toBe('in_transit')
        ->and($fresh->tracking_next_sync_at)->not->toBeNull();

    // 3. changing tracking_driver to a different driver DOES reset tracking_status and tracking_next_sync_at
    $order->update(['tracking_driver' => 'manual']);
    $fresh = $order->fresh();
    expect($fresh->tracking_status)->toBeNull()
        ->and($fresh->tracking_next_sync_at)->toBeNull()
        ->and($fresh->tracking_started_at)->toBeNull();
});

it('finds shipped orders by tracking number ignoring case and spaces, honouring the carrier', function () {
    $a = Fx::shippedOrder(['tracking_number' => 'AB 123', 'tracking_carrier' => 'acme']);
    $b = Fx::shippedOrder(['tracking_number' => 'ab123']);                                  // same parcel, no carrier chosen
    Fx::shippedOrder(['tracking_number' => 'ab123', 'tracking_carrier' => 'zeta']);        // recycled number, other carrier
    Fx::shippedOrder(['tracking_number' => 'ab123', 'status' => 'delivered']);             // finished
    Fx::shippedOrder(['tracking_number' => 'OTHER']);

    expect($this->service->ordersForTrackingNumber(' Ab123 ', 'acme')->pluck('id')->sort()->values()->all())->toBe([$a->id, $b->id])
        ->and($this->service->ordersForTrackingNumber('AB123')->count())->toBe(3)
        ->and($this->service->ordersForTrackingNumber('  ')->count())->toBe(0)
        ->and($this->service->ordersForTrackingNumber('nope')->count())->toBe(0);
});

it('isolates orders by tracking_driver and default driver when matching tracking number with provider', function () {
    config(['shipping.tracking.driver' => 'fake']);
    $orderNullDriver = Fx::shippedOrder(['tracking_number' => 'AB999', 'tracking_driver' => null]);
    $orderManualDriver = Fx::shippedOrder(['tracking_number' => 'AB999', 'tracking_driver' => 'manual']);

    // Matches default driver ('fake') when order tracking_driver is null
    expect($this->service->ordersForTrackingNumber('AB999', null, 'fake')->pluck('id')->all())->toBe([$orderNullDriver->id]);

    // Does NOT match non-default provider when order tracking_driver is null
    expect($this->service->ordersForTrackingNumber('AB999', null, 'other')->count())->toBe(0);

    // Does NOT match when default driver is 'manual' and order tracking_driver is null
    config(['shipping.tracking.driver' => 'manual']);
    expect($this->service->ordersForTrackingNumber('AB999', null, 'fake')->count())->toBe(0);

    // Reset default driver to 'fake'
    config(['shipping.tracking.driver' => 'fake']);
    // Matches explicit driver, while order with tracking_driver null is isolated to 'fake'
    expect($this->service->ordersForTrackingNumber('AB999', null, 'manual')->pluck('id')->all())->toBe([$orderManualDriver->id]);
});

it('plans the next poll with the normal interval, then the slow one, and closes the window', function () {
    config(['shipping.tracking.poll' => ['interval_minutes' => 60, 'slow_after_days' => 7, 'slow_interval_minutes' => 600, 'max_age_days' => 30]]);
    $order = Fx::shippedOrder(['tracking_started_at' => now()->subDay()]);

    expect((int) round(now()->diffInMinutes($this->service->nextSyncAt($order), true)))->toBe(60)
        ->and($this->service->withinPollingWindow($order))->toBeTrue();

    $order->tracking_started_at = now()->subDays(8);
    expect((int) round(now()->diffInMinutes($this->service->nextSyncAt($order), true)))->toBe(600);

    $order->tracking_started_at = now()->subDays(31);
    expect($this->service->withinPollingWindow($order))->toBeFalse();
});

// ------------------------------------------------------------------ shipping.tracking.auto_deliver

it('marks the order delivered by default (auto_deliver is on)', function () {
    expect(config('shipping.tracking.auto_deliver'))->toBeTrue();

    $order = Fx::shippedOrder();
    $this->service->apply($order, 'fake', Fx::result(S::Delivered, Fx::event(S::Delivered, '-1 hour', 'e1')));

    expect($order->fresh()->status)->toBe('delivered')->and(trkDeliveredEmails())->toBe(1);
});

it('with auto_deliver off stores events and tracking status but leaves the order shipped and sends nothing', function () {
    config(['shipping.tracking.auto_deliver' => false]);
    $order = Fx::shippedOrder();
    $result = Fx::result(S::Delivered, Fx::event(S::InTransit, '-5 hours', 'e1'), Fx::event(S::Delivered, '-1 hour', 'e2'));

    $this->service->apply($order, 'fake', $result);
    $this->service->apply($order, 'fake', $result);

    $fresh = $order->fresh();
    expect($fresh->status)->toBe('shipped')
        ->and($fresh->tracking_status)->toBe('delivered')
        ->and($fresh->tracking_next_sync_at)->toBeNull() // terminal: polling stops, the admin decides
        ->and(ShipmentTrackingEvent::where('order_id', $order->id)->count())->toBe(2)
        ->and(trkDeliveredEmails())->toBe(0);

    Queue::assertNothingPushed();
});

it('with auto_deliver off the admin can still mark the order delivered, which sends the email once', function () {
    config(['shipping.tracking.auto_deliver' => false]);
    $order = Fx::shippedOrder();
    $this->service->apply($order, 'fake', Fx::result(S::Delivered, Fx::event(S::Delivered, '-1 hour', 'e1')));

    $order->fresh()->update(['status' => 'delivered']);

    expect(trkDeliveredEmails())->toBe(1);
});

it('does not touch other tracking behaviour when auto_deliver is off', function () {
    config(['shipping.tracking.auto_deliver' => false]);
    $order = Fx::shippedOrder();

    $this->service->apply($order, 'fake', Fx::result(S::InTransit, Fx::event(S::InTransit, '-1 hour', 'e1')));

    expect($order->fresh()->status)->toBe('shipped')->and($order->fresh()->tracking_status)->toBe('in_transit')
        ->and($order->fresh()->tracking_next_sync_at)->not->toBeNull();
});

it('follows the admin setting tracking.auto_deliver without a restart', function () {
    $admin = App\Models\User::factory()->create(['role' => 'admin']);

    // off in the admin: a delivered answer leaves the order shipped
    settings()->set('tracking.auto_deliver', false, $admin);
    $first = Fx::shippedOrder();
    $this->service->apply($first, 'fake', Fx::result(S::Delivered, Fx::event(S::Delivered, '-1 hour', 'e1')));
    expect($first->fresh()->status)->toBe('shipped')->and(trkDeliveredEmails())->toBe(0);

    // back on, same process, no restart: the next delivered answer marks the order delivered
    settings()->set('tracking.auto_deliver', true, $admin);
    $second = Fx::shippedOrder();
    $this->service->apply($second, 'fake', Fx::result(S::Delivered, Fx::event(S::Delivered, '-1 hour', 'e2')));
    expect($second->fresh()->status)->toBe('delivered')->and(trkDeliveredEmails())->toBe(1);

    // resetting restores the default (on)
    settings()->set('tracking.auto_deliver', false, $admin);
    settings()->reset('tracking.auto_deliver', $admin);
    $third = Fx::shippedOrder();
    $this->service->apply($third, 'fake', Fx::result(S::Delivered, Fx::event(S::Delivered, '-1 hour', 'e3')));
    expect($third->fresh()->status)->toBe('delivered');
});

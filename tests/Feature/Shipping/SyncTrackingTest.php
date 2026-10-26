<?php

use App\Jobs\SendOrderEmail;
use App\Models\Order;
use App\Models\ShipmentTrackingEvent;
use App\Shipping\Tracking\Enums\TrackingStatus as S;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use App\Shipping\Tracking\Exceptions\TrackingRejected;
use App\Shipping\Tracking\Jobs\SyncOrderTracking;
use App\Shipping\Tracking\ShipmentTrackingService;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Shipping\Support\TrackingFixtures as Fx;

function trkRunJob(int $orderId): void
{
    app()->call([new SyncOrderTracking($orderId), 'handle']);
}

// ------------------------------------------------------------------ tracking:sync command

it('is a successful no-op with the manual and null drivers', function (string $driver) {
    Bus::fake();
    config(['shipping.tracking.driver' => $driver]);
    Fx::shippedOrder();

    $this->artisan('tracking:sync')->assertSuccessful()->expectsOutputToContain('nothing to do');

    Bus::assertNothingDispatched();
    expect(Order::first()->tracking_next_sync_at)->toBeNull();
})->with(['manual', 'null']);

it('is a successful no-op when the driver cannot be resolved', function () {
    Bus::fake();
    config(['shipping.tracking.driver' => 'nope']);
    Fx::shippedOrder();

    $this->artisan('tracking:sync')->assertSuccessful();
    Bus::assertNothingDispatched();
});

it('queues one job per due shipped order with a tracking number and claims them', function () {
    Bus::fake();
    Fx::useFake();

    $due = Fx::shippedOrder();
    $scheduled = Fx::shippedOrder(['tracking_number' => 'TRK-2', 'tracking_next_sync_at' => now()->subMinute()]);
    Fx::shippedOrder(['tracking_number' => 'TRK-3', 'tracking_next_sync_at' => now()->addHour()]);   // not due
    Fx::shippedOrder(['tracking_number' => null]);                                                  // no number
    Fx::shippedOrder(['tracking_number' => '']);
    Fx::shippedOrder(['status' => 'processing', 'tracking_number' => 'TRK-4']);                      // not shipped
    Fx::shippedOrder(['status' => 'delivered', 'tracking_number' => 'TRK-5']);
    Fx::shippedOrder(['tracking_number' => 'TRK-6', 'tracking_status' => 'delivered']);             // terminal tracking
    Fx::shippedOrder(['tracking_number' => 'TRK-7', 'tracking_started_at' => now()->subDays(100)]); // outside the window

    $this->artisan('tracking:sync')->assertSuccessful()->expectsOutputToContain('Queued 2');

    Bus::assertDispatchedTimes(SyncOrderTracking::class, 2);
    Bus::assertDispatched(SyncOrderTracking::class, fn ($j) => $j->orderId === $due->id);
    Bus::assertDispatched(SyncOrderTracking::class, fn ($j) => $j->orderId === $scheduled->id);

    // claimed: an immediate second run queues nothing, and no duplicate jobs pile up
    expect($due->fresh()->tracking_next_sync_at->isFuture())->toBeTrue()->and($due->fresh()->tracking_started_at)->not->toBeNull();
    $this->artisan('tracking:sync')->expectsOutputToContain('Queued 0');
    Bus::assertDispatchedTimes(SyncOrderTracking::class, 2);
});

it('respects --limit and --order', function () {
    Bus::fake();
    Fx::useFake();
    $first = Fx::shippedOrder(['tracking_number' => 'A1']);
    Fx::shippedOrder(['tracking_number' => 'A2']);
    $later = Fx::shippedOrder(['tracking_number' => 'A3', 'tracking_next_sync_at' => now()->addDay(), 'tracking_started_at' => now()->subDays(100)]);

    $this->artisan('tracking:sync --limit=1')->expectsOutputToContain('Queued 1');
    Bus::assertDispatchedTimes(SyncOrderTracking::class, 1);
    Bus::assertDispatched(SyncOrderTracking::class, fn ($j) => $j->orderId === $first->id);

    // --order forces one order regardless of schedule and window
    $this->artisan("tracking:sync --order={$later->id}")->expectsOutputToContain('Queued 1');
    Bus::assertDispatched(SyncOrderTracking::class, fn ($j) => $j->orderId === $later->id);
});

it('runs the queued job end to end through the fake driver and delivers once', function () {
    Queue::fake([SendOrderEmail::class]);
    Fx::useFake();
    $order = Fx::shippedOrder(['tracking_number' => 'ZZ-DELIVERED']); // default queue is sync in tests: the job runs inline

    $this->artisan('tracking:sync')->assertSuccessful();
    $this->artisan('tracking:sync')->assertSuccessful();

    expect($order->fresh()->status)->toBe('delivered')->and(ShipmentTrackingEvent::count())->toBe(3);
    Queue::assertPushed(SendOrderEmail::class, 1);
});

// ------------------------------------------------------------------ SyncOrderTracking job

it('applies the carrier answer and schedules the next poll', function () {
    $fake = Fx::useFake();
    $order = Fx::shippedOrder(['tracking_number' => ' ab 12-TRANSIT ', 'tracking_carrier' => 'acme']);

    trkRunJob($order->id);

    expect($fake->requests)->toHaveCount(1)
        ->and($fake->requests[0]->trackingNumber)->toBe('ab12-TRANSIT')
        ->and($fake->requests[0]->carrierCode)->toBe('acme')
        ->and($fake->requests[0]->orderReference)->toBe($order->order_number);

    $fresh = $order->fresh();
    expect($fresh->tracking_status)->toBe('in_transit')->and($fresh->tracking_next_sync_at->isFuture())->toBeTrue()
        ->and(ShipmentTrackingEvent::count())->toBe(2);
});

it('skips orders that are no longer trackable or have a terminal tracking status', function () {
    $fake = Fx::useFake();

    foreach ([['status' => 'delivered'], ['status' => 'cancelled'], ['tracking_number' => null], ['tracking_status' => 'returned']] as $attrs) {
        trkRunJob(Fx::shippedOrder($attrs)->id);
    }
    trkRunJob(999999); // vanished

    expect($fake->requests)->toBe([]);
});

it('does nothing when the driver was switched to a passive one after queueing', function () {
    $order = Fx::shippedOrder();
    config(['shipping.tracking.driver' => 'manual']);
    Http::fake();

    trkRunJob($order->id);

    expect(ShipmentTrackingEvent::count())->toBe(0);
    Http::assertNothingSent();
});

it('rethrows a temporary carrier failure so the queue retries it, and keeps the order pollable', function () {
    Fx::useFake();
    $order = Fx::shippedOrder(['tracking_number' => 'ZZ-UNAVAILABLE']);

    expect(fn () => trkRunJob($order->id))->toThrow(TrackingProviderUnavailable::class);
    expect($order->fresh()->tracking_next_sync_at)->toBeNull(); // not parked: the retry/failed() path owns the schedule
});

it('switches polling off for an order the carrier rejects, with a clear log', function () {
    Fx::useFake();
    $order = Fx::shippedOrder(['tracking_number' => 'ZZ-REJECTED']);
    Log::spy();

    trkRunJob($order->id);

    Log::shouldHaveReceived('warning')->withArgs(fn ($m) => str_contains($m, 'polling disabled'))->once();
    expect($order->fresh()->tracking_next_sync_at->gt(now()->addYear()))->toBeTrue();

    // never picked up again by the scheduler ...
    Bus::fake();
    $this->artisan('tracking:sync')->expectsOutputToContain('Queued 0');

    // ... until the tracking number is corrected
    $order->update(['tracking_number' => 'ZZ-TRANSIT']);
    expect($order->fresh()->tracking_next_sync_at)->toBeNull();
    $this->artisan('tracking:sync')->expectsOutputToContain('Queued 1');
});

it('switches polling off when the driver does not support the order carrier', function () {
    $fake = Fx::useFake();
    $fake->unsupported = ['acme'];
    $order = Fx::shippedOrder(['tracking_carrier' => 'acme']);

    trkRunJob($order->id);

    expect($fake->requests)->toBe([])->and($order->fresh()->tracking_next_sync_at->gt(now()->addYear()))->toBeTrue();
});

it('postpones the next poll when all attempts failed', function () {
    $order = Fx::shippedOrder(['tracking_next_sync_at' => now()->subHour()]);

    (new SyncOrderTracking($order->id))->failed(new TrackingProviderUnavailable('down'));

    expect($order->fresh()->tracking_next_sync_at->isFuture())->toBeTrue();
});

it('is configured for retries with backoff, no overlap per order and a timeout below retry_after', function () {
    $job = new SyncOrderTracking(5);

    expect($job->tries)->toBe(5)->and($job->backoff())->toBe([60, 300, 900, 3600])->and($job->timeout)->toBeLessThan(90)
        ->and($job->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($job->middleware()[0]->key)->toBe('tracking-sync:5');
});

it('survives scripted failures then success across attempts', function () {
    $fake = Fx::useFake();
    $order = Fx::shippedOrder(['tracking_number' => 'SCRIPTED']);
    $fake->script('SCRIPTED', new TrackingProviderUnavailable('x'), new TrackingRejected('y'), Fx::result(S::InTransit, Fx::event(S::InTransit, '-1 hour', 'e1')));

    expect(fn () => trkRunJob($order->id))->toThrow(TrackingProviderUnavailable::class);
    trkRunJob($order->id); // rejected: parked, no exception
    expect($order->fresh()->tracking_status)->toBeNull();

    app(ShipmentTrackingService::class)->rescheduleAfterFailure($order);
    $order->update(['tracking_carrier' => null, 'tracking_number' => 'SCRIPTED2']); // new cycle
    $fake->script('SCRIPTED2', Fx::result(S::InTransit, Fx::event(S::InTransit, '-1 hour', 'e2')));
    trkRunJob($order->id);
    expect($order->fresh()->tracking_status)->toBe('in_transit');
});

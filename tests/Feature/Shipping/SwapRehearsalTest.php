<?php

/*
| Swap rehearsal: two completely different carrier APIs (Spediamo: bearer token, query string, ISO dates;
| RehearsalProvider in tests/Support: API-key header, path parameter, d/m/Y dates, other statuses) drive
| the same module. Only configuration changes between them; sync job, events, status and the delivered
| email come from the unchanged core.
*/

use App\Mail\OrderDelivered;
use App\Models\ShipmentTrackingEvent;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Shipping\Support\TrackingFixtures as Fx;
use Tests\Support\Shipping\RehearsalProvider;

beforeEach(function () {
    config([
        'shipping.tracking.drivers.spediamo' => ['base_url' => 'https://spediamo.test', 'token' => 'bearer-1', 'retries' => 0, 'retry_sleep_ms' => 0],
        'shipping.tracking.drivers.rehearsal' => ['base_url' => 'https://rehearsal.test', 'api_key' => 'key-1', 'retries' => 0, 'retry_sleep_ms' => 0],
    ]);

    app(TrackingManager::class)->extend('rehearsal', fn () => new RehearsalProvider(config('shipping.tracking.drivers.rehearsal')));
});

function swapFakeApis(bool $delivered): void
{
    Http::swap(new Illuminate\Http\Client\Factory); // earlier stubs would win over the new ones
    Http::fake([
        'spediamo.test/*' => Http::response([
            'status' => $delivered ? 'DELIVERED' : 'CURRENT',
            'tracking' => array_merge([
                ['date' => '2026-09-01', 'time' => ['hour' => 9, 'minute' => 0, 'second' => 0], 'description' => 'Picked up', 'site' => 'Milano', 'status' => 'OPEN'],
            ], $delivered ? [['date' => '2026-09-03', 'time' => ['hour' => 16, 'minute' => 0, 'second' => 0], 'description' => 'Delivered', 'site' => 'Roma', 'status' => 'DELIVERED']] : []),
        ]),
        'rehearsal.test/*' => Http::response([
            'state' => $delivered ? 'CLOSED' : 'MOVING',
            'scans' => array_merge([
                ['at' => '01/09/2026 07:00', 'what' => 'Accepted', 'where' => 'Milano', 'state' => 'ACCEPTED'],
            ], $delivered ? [['at' => '03/09/2026 14:00', 'what' => 'Closed', 'where' => 'Roma', 'state' => 'CLOSED']] : []),
        ]),
    ]);
}

it('delivers end to end with either API, selected by configuration only', function (string $driver, string $authHeader) {
    Mail::fake();
    config(['shipping.tracking.driver' => $driver]);
    swapFakeApis(delivered: true);
    $order = Fx::shippedOrder(['tracking_number' => 'SWAP-1']);

    $this->artisan('tracking:sync')->assertSuccessful();
    $this->artisan('tracking:sync')->assertSuccessful();

    $fresh = $order->fresh();
    expect($fresh->status)->toBe('delivered')
        ->and($fresh->tracking_status)->toBe('delivered')
        ->and(ShipmentTrackingEvent::where('order_id', $order->id)->pluck('provider')->unique()->all())->toBe([$driver])
        ->and(ShipmentTrackingEvent::where('order_id', $order->id)->count())->toBe(2);

    Mail::assertSent(OrderDelivered::class, 1);
    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => $r->hasHeader($authHeader));
})->with([
    'spediamo' => ['spediamo', 'Authorization'],
    'rehearsal' => ['rehearsal', 'X-Api-Key'],
]);

it('keeps an in-flight shipment correct when the driver is switched in the middle', function () {
    Mail::fake();
    $order = Fx::shippedOrder(['tracking_number' => 'SWAP-2']);

    config(['shipping.tracking.driver' => 'spediamo']);
    swapFakeApis(delivered: false);
    $this->artisan('tracking:sync')->assertSuccessful();
    expect($order->fresh()->tracking_status)->toBe('in_transit');

    // ops flips SHIPPING_TRACKING_DRIVER; nothing else changes
    config(['shipping.tracking.driver' => 'rehearsal']);
    swapFakeApis(delivered: true);
    $order->fresh()->forceFill(['tracking_next_sync_at' => now()->subMinute()])->saveQuietly();
    $this->artisan('tracking:sync')->assertSuccessful();

    $fresh = $order->fresh();
    expect($fresh->status)->toBe('delivered')
        ->and(ShipmentTrackingEvent::where('order_id', $order->id)->where('provider', 'spediamo')->count())->toBe(1)
        ->and(ShipmentTrackingEvent::where('order_id', $order->id)->where('provider', 'rehearsal')->count())->toBe(2);

    Mail::assertSent(OrderDelivered::class, 1);
});

it('converts the other API date format to UTC through the same pipeline', function () {
    config(['shipping.tracking.driver' => 'rehearsal']);
    swapFakeApis(delivered: false);

    $result = app(TrackingManager::class)->driver()->track(new App\Shipping\Tracking\DTO\TrackingRequest('SWAP-3'));

    expect($result->events[0]->occurredAt->format('Y-m-d H:i'))->toBe('2026-09-01 07:00')
        ->and($result->status)->toBe(App\Shipping\Tracking\Enums\TrackingStatus::InTransit);
});

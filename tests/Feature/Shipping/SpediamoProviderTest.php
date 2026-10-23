<?php

/*
| The Spediamo tracking adapter (app/Shipping/Tracking/Drivers/Spediamo). Http::fake only, never the network.
| Everything here is reached through the TrackingManager, so the registration in TrackingServiceProvider is covered too.
*/

use App\Mail\OrderDelivered;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use App\Shipping\Providers\Spediamo\SpediamoLabelProvider;
use App\Shipping\Providers\Spediamo\SpediamoProvider;
use App\Shipping\Tracking\Contracts\SupportsWebhooks;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\Enums\TrackingStatus as S;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Tests\Feature\Shipping\Support\TrackingFixtures as Fx;

const SPD_TOKEN = 'spd-secret-token-123';

beforeEach(function () {
    config([
        'shipping.tracking.driver' => 'manual',
        'shipping.tracking.drivers.spediamo' => [
            'base_url' => 'https://api.spediamo.test',
            'token' => SPD_TOKEN,
            'timeout' => 5,
            'retries' => 1,
            'retry_sleep_ms' => 0,
            'timezone' => 'Europe/Rome',
            'carrier_map' => [],
            'status_map' => [],
        ],
    ]);
});

function spdProvider(): SpediamoProvider
{
    return app(TrackingManager::class)->driver('spediamo');
}

function spdTrack(string $number = 'TRK-1001', ?string $carrier = null)
{
    return spdProvider()->track(new TrackingRequest($number, $carrier));
}

/** @return array<string, mixed> */
function spdAnswer(array $overrides = []): array
{
    return array_merge([
        'code' => 'TRK-1001',
        'trackingNumber' => 'TRK-1001',
        'courier' => 'SDA',
        'type' => 'DOMESTIC',
        'status' => 'CURRENT',
        'deliveryDate' => null,
        'expectedDeliveryDate' => '2026-09-05',
        'tracking' => [
            ['date' => '2026-09-01', 'time' => ['hour' => 9, 'minute' => 30, 'second' => 0, 'nano' => 0], 'description' => 'Picked up', 'longDescription' => 'Picked up from the sender', 'site' => 'Milano', 'status' => 'OPEN'],
            ['date' => '2026-09-02', 'time' => ['hour' => 14, 'minute' => 5, 'second' => 9, 'nano' => 0], 'description' => 'In transit', 'longDescription' => 'In transit', 'site' => 'Bologna', 'status' => 'CURRENT'],
        ],
    ], $overrides);
}

function spdCaptureLogs(): TestHandler
{
    $handler = new TestHandler;
    Log::swap(new Illuminate\Log\Logger(new Logger('test', [$handler])));

    return $handler;
}

/** @return list<string> */
function spdMessages(TestHandler $handler, Level $level): array
{
    return array_values(array_map(fn ($r) => $r->message, array_filter($handler->getRecords(), fn ($r) => $r->level === $level)));
}

// ------------------------------------------------------------------ registration and request

it('is registered as a pollable driver, not a passive or webhook one', function () {
    config(['shipping.tracking.driver' => 'spediamo']);
    $manager = app(TrackingManager::class);

    expect($manager->driver())->toBeInstanceOf(SpediamoProvider::class)
        ->and($manager->driverNames())->toContain('spediamo')
        ->and($manager->canPoll())->toBeTrue()
        ->and($manager->driver()->name())->toBe('spediamo')
        ->and($manager->driver())->not->toBeInstanceOf(SupportsWebhooks::class);
});

it('requests the tracking endpoint with the bearer token and no If-None-Match the first time', function () {
    Http::fake(['*' => Http::response(spdAnswer())]);

    spdTrack(' TRK 1001 ');

    Http::assertSentCount(1);
    Http::assertSent(fn (HttpRequest $r) => $r->method() === 'GET'
        && $r->url() === 'https://api.spediamo.test/v1/shipments/tracking?code=TRK1001'
        && $r->hasHeader('Authorization', 'Bearer '.SPD_TOKEN)
        && ! $r->hasHeader('If-None-Match'));
});

it('falls back to the public base url when none is configured', function () {
    config(['shipping.tracking.drivers.spediamo.base_url' => '']);
    Http::fake(['*' => Http::response(spdAnswer())]);

    spdTrack();

    Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->url(), 'https://api.spediamo.it/v1/shipments/tracking'));
});

// ------------------------------------------------------------------ 200 mapping

it('maps a full answer: events, UTC times, long descriptions, location, status and estimated delivery', function () {
    Http::fake(['*' => Http::response(spdAnswer())]);

    $result = spdTrack();

    expect($result->status)->toBe(S::InTransit)
        ->and($result->events)->toHaveCount(2)
        ->and($result->events[0]->status)->toBe(S::InfoReceived)
        ->and($result->events[0]->occurredAt->format('Y-m-d H:i:s'))->toBe('2026-09-01 07:30:00') // CEST => UTC
        ->and($result->events[0]->description)->toBe('Picked up - Picked up from the sender')
        ->and($result->events[0]->location)->toBe('Milano')
        ->and($result->events[0]->externalId)->toBeNull()
        ->and($result->events[1]->description)->toBe('In transit')
        ->and($result->estimatedDelivery)->not->toBeNull();
});

it('uses the configured timezone, and the default when it is invalid', function () {
    Http::fake(['*' => Http::response(spdAnswer())]);

    config(['shipping.tracking.drivers.spediamo.timezone' => 'UTC']);
    expect(spdTrack()->events[0]->occurredAt->format('H:i'))->toBe('09:30');

    app(TrackingManager::class)->extend('spediamo', fn () => new SpediamoProvider(['token' => 'x', 'timezone' => 'Mars/Olympus']));
    expect(spdTrack()->events[0]->occurredAt->format('H:i'))->toBe('07:30');
});

it('is Delivered by status, and also by a non-empty deliveryDate', function (array $overrides) {
    Http::fake(['*' => Http::response(spdAnswer($overrides))]);

    expect(spdTrack()->status)->toBe(S::Delivered);
})->with([
    'status' => [['status' => 'DELIVERED']],
    'deliveryDate' => [['status' => 'CURRENT', 'deliveryDate' => '2026-09-04']],
]);

it('lets config extend the status map', function () {
    config(['shipping.tracking.drivers.spediamo.status_map' => ['lost' => 'returned']]);
    Http::fake(['*' => Http::response(spdAnswer(['status' => 'LOST']))]);

    expect(spdTrack()->status)->toBe(S::Returned);
});

it('warns once per distinct unmapped status and treats it as unknown', function () {
    $logs = spdCaptureLogs();
    Http::fake(['*' => Http::response(spdAnswer(['status' => 'MYSTERY', 'tracking' => [
        ['date' => '2026-09-01', 'time' => ['hour' => 9, 'minute' => 0, 'second' => 0], 'description' => 'x', 'status' => 'WEIRD'],
        ['date' => '2026-09-01', 'time' => ['hour' => 10, 'minute' => 0, 'second' => 0], 'description' => 'y', 'status' => 'weird'],
    ]]))]);

    $result = spdTrack();
    spdTrack(); // same values again: no second warning

    expect($result->status)->toBe(S::Unknown)->and($result->events[0]->status)->toBe(S::Unknown);

    $warnings = spdMessages($logs, Level::Warning);
    expect($warnings)->toHaveCount(2); // MYSTERY and WEIRD, each once
    $statuses = collect($logs->getRecords())->pluck('context.status')->filter()->all();
    expect($statuses)->toEqualCanonicalizing(['MYSTERY', 'WEIRD']);
});

// ------------------------------------------------------------------ 304 / ETag

it('stores the ETag and reuses it: a 304 returns the previously parsed result', function () {
    Http::fake(['*' => Http::sequence()
        ->push(spdAnswer(), 200, ['ETag' => '"abc"'])
        ->push('', 304, ['ETag' => '"abc"'])]);

    $first = spdTrack();
    $second = spdTrack();

    expect($second->status)->toBe($first->status)
        ->and($second->events)->toHaveCount(2)
        ->and($second->events[1]->description)->toBe($first->events[1]->description)
        ->and($second->events[1]->occurredAt)->toEqual($first->events[1]->occurredAt)
        ->and($second->estimatedDelivery)->toEqual($first->estimatedDelivery);

    expect(Http::recorded()[1][0]->hasHeader('If-None-Match', '"abc"'))->toBeTrue();
});

it('treats a 304 without a cached result as "nothing new"', function () {
    Http::fake(['*' => Http::response('', 304)]);

    $result = spdTrack();

    expect($result->status)->toBe(S::Unknown)->and($result->events)->toBe([]);
});

// ------------------------------------------------------------------ 404 and errors

it('answers unknown for a 404 (not scanned yet) without logging an error', function () {
    $logs = spdCaptureLogs();
    Http::fake(['*' => Http::response(['message' => 'Shipment not found'], 404)]);

    $result = spdTrack();

    expect($result->status)->toBe(S::Unknown)->and($result->events)->toBe([])->and($logs->getRecords())->toBe([]);
});

it('treats 401/403 as unavailable and logs ONE critical line per hour without the token', function (int $status) {
    $logs = spdCaptureLogs();
    Http::fake(['*' => Http::response('{"error":"bad token '.SPD_TOKEN.'"}', $status)]);

    foreach ([1, 2] as $_) {
        try {
            spdTrack();
            $this->fail('expected exception');
        } catch (TrackingProviderUnavailable $e) {
            expect($e->httpStatus)->toBe($status)->and($e->getMessage())->not->toContain(SPD_TOKEN);
        }
    }

    $critical = spdMessages($logs, Level::Critical);
    expect($critical)->toHaveCount(1)
        ->and($critical[0])->toContain('missing, expired or wrong')->not->toContain(SPD_TOKEN);

    $this->travel(61)->minutes();
    expect(fn () => spdTrack())->toThrow(TrackingProviderUnavailable::class);
    expect(spdMessages($logs, Level::Critical))->toHaveCount(2);
})->with([401, 403]);

it('fails clearly, without a request, when the token is not configured', function () {
    $logs = spdCaptureLogs();
    config(['shipping.tracking.drivers.spediamo.token' => '  ']);
    Http::fake();

    expect(fn () => spdTrack())->toThrow(TrackingProviderUnavailable::class, 'API token is not configured');

    Http::assertNothingSent();
    expect(spdMessages($logs, Level::Critical))->toHaveCount(1);
});

it('treats 429 and 5xx as temporary, and timeouts too', function (int $status) {
    Http::fake(['*' => Http::response('slow down', $status)]);

    try {
        spdTrack();
        $this->fail('expected exception');
    } catch (TrackingProviderUnavailable $e) {
        expect($e->httpStatus)->toBe($status);
    }
})->with([429, 500, 502, 503]);

it('retries a transient 5xx once and succeeds', function () {
    Http::fake(['*' => Http::sequence()->push('boom', 503)->push(spdAnswer())]);

    expect(spdTrack()->status)->toBe(S::InTransit);
    Http::assertSentCount(2);
});

it('treats connection failures and timeouts as temporary', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

    expect(fn () => spdTrack())->toThrow(TrackingProviderUnavailable::class, 'connection failed');
});

it('treats an unreadable body as a temporary problem', function () {
    Http::fake(['*' => Http::response('<html>maintenance</html>', 200)]);

    expect(fn () => spdTrack())->toThrow(TrackingProviderUnavailable::class, 'unexpected response');
});

it('never puts the token, headers or bodies into any log line', function () {
    $logs = spdCaptureLogs();

    Http::fake(['*' => Http::sequence()
        ->push(spdAnswer(['status' => 'MYSTERY']), 200, ['ETag' => '"abc"'])
        ->push('', 304)
        ->push('nope '.SPD_TOKEN, 401)
        ->push('nope', 500)->push('nope', 500)
        ->push('', 404)]);

    foreach (range(1, 5) as $_) {
        try {
            spdTrack();
        } catch (TrackingProviderUnavailable) {
        }
    }

    expect($logs->getRecords())->not->toBeEmpty();

    foreach ($logs->getRecords() as $record) {
        $line = $record->message.' '.json_encode($record->context);
        expect($line)->not->toContain(SPD_TOKEN)->not->toContain('Bearer')->not->toContain('Authorization')->not->toContain('If-None-Match');
    }
});

// ------------------------------------------------------------------ carriers

it('accepts every carrier with an empty carrier_map, only mapped ones otherwise', function () {
    expect(spdProvider()->supports('sda'))->toBeTrue()->and(spdProvider()->supports('anything'))->toBeTrue();

    config(['shipping.tracking.drivers.spediamo.carrier_map' => ['sda' => 'SDA']]);
    app(TrackingManager::class)->extend('spediamo', fn () => new SpediamoProvider(config('shipping.tracking.drivers.spediamo')));

    expect(spdProvider()->supports('sda'))->toBeTrue()
        ->and(spdProvider()->supports('SDA'))->toBeTrue()
        ->and(spdProvider()->supports('gls'))->toBeFalse();
});

it('builds a tracking link only from a configured template', function () {
    expect(spdProvider()->trackingUrl('A 1', 'acme'))->toBeNull();

    config(['shipping.carriers' => ['acme' => ['name' => 'Acme', 'tracking_url' => 'https://track.example.test/?n={number}']]]);
    config(['shipping.tracking.driver' => 'spediamo']);

    expect(spdProvider()->trackingUrl('A 1', 'acme'))->toBe('https://track.example.test/?n=A%201')
        ->and(spdProvider()->trackingUrl('A 1', 'other'))->toBeNull()
        ->and(app(TrackingManager::class)->trackingUrl('A 1', 'acme'))->toBe('https://track.example.test/?n=A%201');
});

// ------------------------------------------------------------------ end to end

it('syncs a shipped order end to end: events stored once, order delivered once, one delivered email', function () {
    Mail::fake();
    config(['shipping.tracking.driver' => 'spediamo']);
    $order = Fx::shippedOrder(['tracking_number' => 'TRK-E2E', 'tracking_carrier' => 'sda']);

    Http::fake(['*' => Http::sequence()
        ->push(spdAnswer(['trackingNumber' => 'TRK-E2E', 'code' => 'TRK-E2E']), 200, ['ETag' => '"v1"'])   // in transit
        ->push('', 304)                                                                                       // unchanged
        ->push(spdAnswer(['status' => 'DELIVERED', 'deliveryDate' => '2026-09-04', 'tracking' => array_merge(spdAnswer()['tracking'], [
            ['date' => '2026-09-04', 'time' => ['hour' => 11, 'minute' => 0, 'second' => 0], 'description' => 'Delivered', 'longDescription' => '', 'site' => 'Roma', 'status' => 'DELIVERED'],
        ])]), 200, ['ETag' => '"v2"'])]);

    $this->artisan('tracking:sync')->assertSuccessful();
    expect($order->fresh()->tracking_status)->toBe('in_transit')->and($order->fresh()->status)->toBe('shipped')
        ->and(ShipmentTrackingEvent::where('order_id', $order->id)->count())->toBe(2);

    // not due yet: make it due, the poll gets the 304 and nothing changes
    $order->fresh()->forceFill(['tracking_next_sync_at' => now()->subMinute()])->saveQuietly();
    $this->artisan('tracking:sync')->assertSuccessful();
    expect(ShipmentTrackingEvent::where('order_id', $order->id)->count())->toBe(2)->and($order->fresh()->status)->toBe('shipped');

    $order->fresh()->forceFill(['tracking_next_sync_at' => now()->subMinute()])->saveQuietly();
    $this->artisan('tracking:sync')->assertSuccessful();
    $this->artisan('tracking:sync')->assertSuccessful(); // delivered orders are never polled again

    $fresh = $order->fresh();
    expect($fresh->status)->toBe('delivered')->and($fresh->tracking_status)->toBe('delivered')
        ->and(ShipmentTrackingEvent::where('order_id', $order->id)->where('provider', 'spediamo')->count())->toBe(3);

    Mail::assertSent(OrderDelivered::class, 1);
    Http::assertSentCount(3);
});

it('keeps polling an order whose shipment is not known yet (404)', function () {
    config(['shipping.tracking.driver' => 'spediamo']);
    $order = Fx::shippedOrder(['tracking_number' => 'NOT-YET']);
    Http::fake(['*' => Http::response('', 404)]);

    $this->artisan('tracking:sync')->assertSuccessful();

    $fresh = $order->fresh();
    expect($fresh->tracking_status)->toBeNull()
        ->and($fresh->tracking_last_synced_at)->not->toBeNull()
        ->and($fresh->tracking_next_sync_at->isFuture())->toBeTrue()
        ->and($fresh->tracking_next_sync_at->lt(now()->addDay()))->toBeTrue(); // not parked for years
});

it('promotes Spediamo code to carrier waybill on manual shipment, preserves tracking state, and uses providerCode on subsequent polls', function () {
    config(['shipping.tracking.driver' => 'spediamo']);

    $startedAt = now()->subHours(2);

    $order = Fx::shippedOrder([
        'tracking_number' => 'A422-098-162-296',
        'tracking_carrier' => 'sda',
        'tracking_started_at' => $startedAt,
    ]);
    $shipment = $order->shipments()->create([
        'driver' => 'manual',
        'carrier_code' => 'sda',
        'tracking_carrier' => 'sda',
        'tracking_number' => 'A422-098-162-296',
        'external_id' => null,
        'status' => Shipment::STATUS_CREATED,
    ]);

    Http::fake([
        'https://api.spediamo.test/v1/shipments/tracking?code=A422-098-162-296' => Http::sequence()
            ->push(spdAnswer([
                'code' => 'A422-098-162-296',
                'trackingNumber' => '288364I031083',
                'courier' => 'SDA',
                'status' => 'CURRENT',
            ]))
            ->push(spdAnswer([
                'code' => 'A422-098-162-296',
                'trackingNumber' => '288364I031083',
                'courier' => 'SDA',
                'status' => 'DELIVERED',
            ])),
    ]);

    // Poll 1: triggers promotion
    $this->artisan('tracking:sync')->assertSuccessful();

    $order1 = $order->fresh();
    $shipment1 = $shipment->fresh();

    expect($order1->tracking_number)->toBe('288364I031083')
        ->and($shipment1->tracking_number)->toBe('288364I031083')
        ->and($shipment1->external_id)->toBe('A422-098-162-296')
        ->and($order1->tracking_status)->toBe('in_transit')
        ->and($order1->tracking_started_at->toIso8601String())->toBe($startedAt->toIso8601String())
        ->and($order1->tracking_next_sync_at->isFuture())->toBeTrue();

    // Poll 2: uses providerCode (shipments.external_id), query must still go to ?code=A422-098-162-296, NOT the waybill!
    $order1->forceFill(['tracking_next_sync_at' => now()->subMinute()])->saveQuietly();
    $this->artisan('tracking:sync')->assertSuccessful();

    $order2 = $order->fresh();
    expect($order2->tracking_status)->toBe('delivered')
        ->and($order2->status)->toBe('delivered')
        ->and($order2->tracking_number)->toBe('288364I031083');

    // Verify all requests used code=A422-098-162-296 and never queried the carrier waybill
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'code=A422-098-162-296');
    });
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'code=288364I031083');
    });
});

it('can book the Poste Delivery Business courier that Spediamo quotes', function () {
    $labels = new SpediamoLabelProvider(config('shipping.providers.spediamo'));

    expect($labels->supports('pdb'))->toBeTrue();
});

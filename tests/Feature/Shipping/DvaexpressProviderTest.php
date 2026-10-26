<?php

use App\Models\Shipment;
use App\Shipping\Providers\Dvaexpress\DvaexpressProvider;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\Enums\TrackingStatus;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use App\Shipping\Tracking\Exceptions\TrackingRejected;
use App\Shipping\Tracking\Jobs\SyncOrderTracking;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Shipping\Support\TrackingFixtures;

function dvaexpressTrackingConfig(array $extra = []): array
{
    return array_merge([
        'base_url' => 'https://api.easyparcel.it',
        'api_key' => 'secret-dva-key-12345',
        'timezone' => 'Europe/Rome',
        'retries' => 1,
        'retry_sleep_ms' => 0,
    ], $extra);
}

it('parses golden tracking fixture into TrackingResult with Delivered status and chronologically ordered events in UTC', function () {
    $fixtureJson = (string) file_get_contents(base_path('tests/Fixtures/Shipping/dvaexpress/tracking.json'));
    $fixtureData = json_decode($fixtureJson, true);

    Http::fake([
        'https://api.easyparcel.it/tracking/secret-dva-key-12345*' => Http::response($fixtureData, 200),
    ]);

    $provider = new DvaexpressProvider(dvaexpressTrackingConfig());
    $result = $provider->track(new TrackingRequest('288016E049120', 'sda'));

    expect($result)->toBeInstanceOf(TrackingResult::class)
        ->and($result->status)->toBe(TrackingStatus::Delivered)
        ->and($result->events)->toHaveCount(7);

    // Verify chronological ordering and UTC timestamps
    // Original local times in Europe/Rome (UTC+2 in April):
    // 1: 2020-04-15 12:41:00 -> 2020-04-15 10:41:00 UTC (RIT -> InTransit)
    // 2: 2020-04-15 16:10:02 -> 2020-04-15 14:10:02 UTC (PPP -> InTransit)
    // 3: 2020-04-16 10:54:00 -> 2020-04-16 08:54:00 UTC (PPP -> InTransit)
    // 4: 2020-04-16 10:55:00 -> 2020-04-16 08:55:00 UTC (THE -> InTransit)
    // 5: 2020-04-17 04:18:00 -> 2020-04-17 02:18:00 UTC (PPP -> InTransit)
    // 6: 2020-04-17 04:18:00 -> 2020-04-17 02:18:00 UTC (THE -> InTransit)
    // 7: 2020-04-17 12:25:00 -> 2020-04-17 10:25:00 UTC (000 -> Delivered)

    $events = $result->events;

    expect($events[0]->status)->toBe(TrackingStatus::InTransit)
        ->and($events[0]->occurredAt->toIso8601ZuluString())->toBe('2020-04-15T10:41:00Z')
        ->and($events[0]->location)->toBe('Catanzaro')
        ->and($events[0]->externalId)->toBeNull();

    expect($events[1]->status)->toBe(TrackingStatus::InTransit)
        ->and($events[1]->occurredAt->toIso8601ZuluString())->toBe('2020-04-15T14:10:02Z')
        ->and($events[1]->location)->toBe('Catanzaro')
        ->and($events[1]->externalId)->toBeNull();

    expect($events[2]->status)->toBe(TrackingStatus::InTransit)
        ->and($events[2]->occurredAt->toIso8601ZuluString())->toBe('2020-04-16T08:54:00Z')
        ->and($events[2]->location)->toBe('Roma Hub Espresso')
        ->and($events[2]->externalId)->toBeNull();

    expect($events[3]->status)->toBe(TrackingStatus::InTransit)
        ->and($events[3]->occurredAt->toIso8601ZuluString())->toBe('2020-04-16T08:55:00Z')
        ->and($events[3]->location)->toBe('Roma Hub Espresso')
        ->and($events[3]->externalId)->toBeNull();

    expect($events[4]->status)->toBe(TrackingStatus::InTransit)
        ->and($events[4]->occurredAt->toIso8601ZuluString())->toBe('2020-04-17T02:18:00Z')
        ->and($events[4]->location)->toBe('Hub Espresso Piacenza')
        ->and($events[4]->externalId)->toBeNull();

    expect($events[5]->status)->toBe(TrackingStatus::InTransit)
        ->and($events[5]->occurredAt->toIso8601ZuluString())->toBe('2020-04-17T02:18:00Z')
        ->and($events[5]->location)->toBe('Hub Espresso Piacenza')
        ->and($events[5]->externalId)->toBeNull();

    expect($events[6]->status)->toBe(TrackingStatus::Delivered)
        ->and($events[6]->occurredAt->toIso8601ZuluString())->toBe('2020-04-17T10:25:00Z')
        ->and($events[6]->location)->toBe('Hub Espresso Piacenza')
        ->and($events[6]->externalId)->toBeNull();

    // Verify all timestamps are strictly in UTC
    foreach ($events as $event) {
        expect($event->occurredAt->timezoneName)->toBe('UTC')
            ->and($event->externalId)->toBeNull();
    }
});

it('classifies EasyParcel error codes 101, 102, 104, 120 as TrackingProviderUnavailable and logs rate-limited Log::critical on 101/120', function () {
    $provider = new DvaexpressProvider(dvaexpressTrackingConfig());

    // Error 101: Invalid API Key -> Unavailable + rate-limited critical alert
    Cache::flush();
    Log::spy();

    Http::fake([
        'https://api.easyparcel.it/tracking/secret-dva-key-12345*' => Http::response([
            'result' => 'KO',
            'errorcode' => 101,
            'errormessage' => 'API Key non valida o non attiva',
        ], 200),
    ]);

    expect(fn () => $provider->track(new TrackingRequest('TRK1', 'sda')))
        ->toThrow(TrackingProviderUnavailable::class, 'API Key non valida o non attiva');

    Log::shouldHaveReceived('critical')->once();

    // Second call with cached alert: still throws unavailable, but does not log critical again
    expect(fn () => $provider->track(new TrackingRequest('TRK1', 'sda')))
        ->toThrow(TrackingProviderUnavailable::class);

    Log::shouldHaveReceived('critical')->once();

    // Error 120: Account suspended -> Unavailable + rate-limited critical alert
    Cache::flush();
    Log::spy();

    Http::swap(new Factory);
    Http::fake([
        'https://api.easyparcel.it/tracking/secret-dva-key-12345*' => Http::response([
            'result' => 'KO',
            'errorcode' => 120,
            'errormessage' => 'Account cliente bloccato o sospeso',
        ], 200),
    ]);

    expect(fn () => $provider->track(new TrackingRequest('TRK2', 'sda')))
        ->toThrow(TrackingProviderUnavailable::class, 'Account cliente bloccato o sospeso');

    Log::shouldHaveReceived('critical')->twice();

    // Error 102: Invalid credentials -> Unavailable
    Http::swap(new Factory);
    Http::fake([
        'https://api.easyparcel.it/tracking/secret-dva-key-12345*' => Http::response([
            'result' => 'KO',
            'errorcode' => 102,
            'errormessage' => 'Credenziali non valide',
        ], 200),
    ]);

    expect(fn () => $provider->track(new TrackingRequest('TRK3', 'sda')))
        ->toThrow(TrackingProviderUnavailable::class, 'Credenziali non valide');

    // Error 104: Service maintenance -> Unavailable
    Http::swap(new Factory);
    Http::fake([
        'https://api.easyparcel.it/tracking/secret-dva-key-12345*' => Http::response([
            'result' => 'KO',
            'errorcode' => 104,
            'errormessage' => 'Servizio temporaneamente non disponibile per manutenzione',
        ], 200),
    ]);

    expect(fn () => $provider->track(new TrackingRequest('TRK4', 'sda')))
        ->toThrow(TrackingProviderUnavailable::class, 'Servizio temporaneamente non disponibile');
});

it('classifies EasyParcel error codes 105, 110 with flattened errordetails and unknown tracking number as TrackingRejected', function () {
    $provider = new DvaexpressProvider(dvaexpressTrackingConfig());

    // Error 105: Shipment not found / unknown tracking number -> TrackingRejected
    Http::fake([
        'https://api.easyparcel.it/tracking/secret-dva-key-12345*' => Http::response([
            'result' => 'KO',
            'errorcode' => 105,
            'errormessage' => 'Spedizione inesistente o non trovata',
        ], 200),
    ]);

    expect(fn () => $provider->track(new TrackingRequest('UNKNOWN-TRK', 'sda')))
        ->toThrow(TrackingRejected::class, 'Spedizione inesistente o non trovata');

    // Error 110: Validation error with nested errordetails -> TrackingRejected with flattened details
    Http::swap(new Factory);
    Http::fake([
        'https://api.easyparcel.it/tracking/secret-dva-key-12345*' => Http::response([
            'result' => 'KO',
            'errorcode' => 110,
            'errormessage' => 'Dati richiesta non corretti',
            'errordetails' => [
                'ldv' => 'Formato lettera di vettura non valido',
                'vettore' => 'Codice vettore sconosciuto',
            ],
        ], 200),
    ]);

    expect(fn () => $provider->track(new TrackingRequest('BAD-FORMAT', 'sda')))
        ->toThrow(function (TrackingRejected $e) {
            expect($e->getMessage())->toContain('Dati richiesta non corretti')
                ->and($e->getMessage())->toContain('Formato lettera di vettura non valido')
                ->and($e->getMessage())->toContain('Codice vettore sconosciuto');
        });

    // Blank / empty tracking number rejected immediately without network call
    Http::swap(new Factory);
    Http::fake();
    expect(fn () => $provider->fetch('   '))
        ->toThrow(TrackingRejected::class, 'Tracking number cannot be empty.');
    Http::assertNothingSent();
});

it('redacts the API key from exception messages when a network or HTTP error occurs', function () {
    $secretKey = 'super-secret-easyparcel-key-999';
    $provider = new DvaexpressProvider(dvaexpressTrackingConfig(['api_key' => $secretKey]));

    // 1. HTTP 500 error whose body mentions the URL and secret key
    Http::fake([
        '*' => Http::response("Internal server crash at https://api.easyparcel.it/tracking/{$secretKey}", 500),
    ]);

    try {
        $provider->track(new TrackingRequest('TRK-REDACT-1', 'sda'));
        test()->fail('Should have thrown TrackingProviderUnavailable');
    } catch (TrackingProviderUnavailable $e) {
        expect($e->getMessage())->not->toContain($secretKey)
            ->and($e->getMessage())->toContain('[REDACTED]');
    }

    // 2. ConnectionException mentioning the API key in the URL
    Http::swap(new Factory);
    Http::fake(function () use ($secretKey) {
        throw new ConnectionException("Failed to connect to https://api.easyparcel.it/tracking/{$secretKey}: Connection timed out");
    });

    try {
        $provider->track(new TrackingRequest('TRK-REDACT-2', 'sda'));
        test()->fail('Should have thrown TrackingProviderUnavailable');
    } catch (TrackingProviderUnavailable $e) {
        expect($e->getMessage())->not->toContain($secretKey)
            ->and($e->getMessage())->toContain('[REDACTED]');
    }
});

it('polls ldv ignoring providerCode and preserves tracking number without promotion on manual shipment', function () {
    $fixtureJson = (string) file_get_contents(base_path('tests/Fixtures/Shipping/dvaexpress/tracking.json'));
    $fixtureData = json_decode($fixtureJson, true);

    Http::fake([
        'https://api.easyparcel.it/tracking/secret-dva-key-12345*' => Http::response($fixtureData, 200),
    ]);

    config([
        'shipping.tracking.driver' => 'dvaexpress',
        'shipping.tracking.drivers.dvaexpress' => dvaexpressTrackingConfig(),
    ]);

    $order = TrackingFixtures::shippedOrder([
        'tracking_number' => '288016E049120',
        'tracking_carrier' => 'sda',
        'tracking_driver' => 'dvaexpress',
    ]);

    $shipment = $order->shipments()->create([
        'driver' => 'manual',
        'carrier_code' => 'sda',
        'tracking_carrier' => 'sda',
        'tracking_number' => '288016E049120',
        'external_id' => 'DVA-ORDER-MANUAL-999',
        'status' => Shipment::STATUS_CREATED,
    ]);

    app()->call([new SyncOrderTracking($order->id), 'handle']);

    // Verify EasyParcel received ldv = 288016E049120, NOT DVA-ORDER-MANUAL-999
    Http::assertSent(function ($request) {
        $body = $request->data();

        return str_contains($request->url(), '/tracking/secret-dva-key-12345')
            && ($body['dettagli']['ldv'] ?? null) === '288016E049120';
    });

    $freshOrder = $order->fresh();
    $freshShipment = $shipment->fresh();

    // No promotion occurred: tracking_number remains intact
    expect($freshOrder->tracking_number)->toBe('288016E049120')
        ->and($freshShipment->tracking_number)->toBe('288016E049120')
        ->and($freshShipment->external_id)->toBe('DVA-ORDER-MANUAL-999')
        ->and($freshOrder->tracking_status)->toBe('delivered');
});

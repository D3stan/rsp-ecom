<?php

/*
|--------------------------------------------------------------------------
| Contract every tracking adapter must satisfy
|--------------------------------------------------------------------------
|
| To certify a new adapter add ONE row to the 'tracking providers' dataset:
|   'name' => [fn () => new ExampleCarrierProvider([...]), $webhookRequestFactoryOrNull, $knownCarrierCode]
| Use Http::fake() inside the factory/arrange step for HTTP adapters (see the spediamo row, which uses
| trkContractFake() to script the numbers below): no real network is ever allowed.
| The test then checks supports(), trackingUrl() null-safety, track() output normalisation and, when the
| adapter implements SupportsWebhooks, that parse() is pure and applying its updates is idempotent.
*/

use App\Models\ShipmentTrackingEvent;
use App\Shipping\Tracking\Contracts\PassiveProvider;
use App\Shipping\Tracking\Contracts\SupportsWebhooks;
use App\Shipping\Tracking\Contracts\TrackingProvider;
use App\Shipping\Tracking\Drivers\FakeProvider;
use App\Shipping\Tracking\Drivers\ManualProvider;
use App\Shipping\Tracking\Drivers\NullProvider;
use App\Shipping\Tracking\DTO\TrackingEvent;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\DTO\TrackingUpdate;
use App\Shipping\Tracking\Enums\TrackingStatus;
use App\Shipping\Tracking\Exceptions\TrackingException;
use App\Shipping\Tracking\ShipmentTrackingService;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Shipping\Support\TrackingFixtures as Fx;
use Tests\Support\Shipping\RehearsalProvider;

/**
 * Fake HTTP API for the contract numbers: answers like a carrier would for each scripted tracking number.
 *
 * @param  Closure  $numberOf  extracts the tracking number from the outgoing request
 * @param  Closure  $success  builds the 200 answer from (number, kind: delivered|exception|transit, scan time)
 */
function trkContractFake(Closure $numberOf, Closure $success): void
{
    Http::fake(function ($request) use ($numberOf, $success) {
        $number = $numberOf($request);

        return match ($number) {
            'X-UNAVAILABLE' => Http::response('down', 503),
            'X-REJECTED' => Http::response('bad request', 400),
            'ZZ-DELIVERED' => $success($number, 'delivered', now()->subHour()->toImmutable()),
            'ZZ-EXCEPTION' => $success($number, 'exception', now()->subHour()->toImmutable()),
            default => $success($number, 'transit', now()->subHour()->toImmutable()),
        };
    });
}

dataset('tracking providers', function () {
    $signed = function (): Request {
        $body = json_encode(['updates' => [[
            'tracking_number' => 'TRK-1001',
            'status' => 'in_transit',
            'events' => [['id' => 'c1', 'status' => 'in_transit', 'description' => 'Scan', 'occurred_at' => now()->subHour()->toIso8601String()]],
        ]]]);

        return Request::create('/webhooks/tracking/fake', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_SIGNATURE' => hash_hmac('sha256', $body, 'contract-secret'),
        ], $body);
    };

    return [
        'manual' => [fn () => new ManualProvider, null, 'acme'],
        'null' => [fn () => new NullProvider, null, 'acme'],
        'fake' => [fn () => new FakeProvider(['webhook_secret' => 'contract-secret']), $signed, 'acme'],
        'spediamo' => [function () {
            config(['shipping.tracking.drivers.spediamo' => ['base_url' => 'https://spediamo.contract.test', 'token' => 'contract-token', 'retries' => 1, 'retry_sleep_ms' => 0]]);
            trkContractFake(
                fn ($request) => (string) ($request->data()['code'] ?? ''),
                function (string $number, string $kind, $at) {
                    $local = $at->setTimezone('Europe/Rome');
                    $status = ['delivered' => 'DELIVERED', 'exception' => 'ON_HOLD', 'transit' => 'CURRENT'][$kind];

                    return Http::response([
                        'code' => $number, 'trackingNumber' => $number, 'courier' => 'SDA', 'status' => $status,
                        'tracking' => [['date' => $local->format('Y-m-d'), 'time' => ['hour' => (int) $local->format('G'), 'minute' => (int) $local->format('i'), 'second' => 0],
                            'description' => 'Scan', 'longDescription' => 'Scan at hub', 'site' => 'Hub A', 'status' => $status]],
                    ], 200, ['ETag' => '"contract"']);
                },
            );

            return app(TrackingManager::class)->driver('spediamo');
        }, null, 'acme'],
        'dvaexpress' => [function () {
            config([
                'shipping.providers.dvaexpress' => [
                    'base_url' => 'https://api.easyparcel.it',
                    'api_key' => 'contract-dva-key',
                    'carrier_map' => [],
                    'retries' => 1,
                    'retry_sleep_ms' => 0,
                ],
                'shipping.tracking.drivers.dvaexpress' => [
                    'base_url' => 'https://api.easyparcel.it',
                    'api_key' => 'contract-dva-key',
                    'carrier_map' => [],
                    'retries' => 1,
                    'retry_sleep_ms' => 0,
                ],
            ]);
            trkContractFake(
                fn ($request) => (string) ($request->data()['dettagli']['ldv'] ?? ''),
                function (string $number, string $kind, $at) {
                    $local = $at->setTimezone('Europe/Rome');
                    $statusCode = ['delivered' => '000', 'exception' => 'GIAC', 'transit' => 'PPP'][$kind];
                    $desc = ['delivered' => 'CONSEGNATA', 'exception' => 'GIACENZA', 'transit' => 'PARTITA'][$kind];

                    return Http::response([
                        'result' => 'OK',
                        'errorcode' => 0,
                        'errormessage' => 'OK',
                        'tracking' => ['lettera_vettura' => $number],
                        'dettagli' => [
                            [
                                'id' => 1,
                                'data' => $local->format('Y-m-d'),
                                'ora' => $local->format('H:i:s'),
                                'status' => $statusCode,
                                'descrizione' => $desc,
                                'filiale' => 'Hub Roma',
                            ],
                        ],
                    ], 200);
                },
            );

            return app(TrackingManager::class)->driver('dvaexpress');
        }, null, 'acme'],
        'rehearsal' => [function () {
            trkContractFake(
                fn ($request) => rawurldecode(basename(parse_url($request->url(), PHP_URL_PATH))),
                function (string $number, string $kind, $at) {
                    $state = ['delivered' => 'CLOSED', 'exception' => 'DELAYED', 'transit' => 'MOVING'][$kind];

                    return Http::response(['state' => $state, 'scans' => [['at' => $at->utc()->format('d/m/Y H:i'), 'what' => 'Scan', 'where' => 'Hub A', 'state' => $state]]]);
                },
            );

            return new RehearsalProvider(['base_url' => 'https://rehearsal.contract.test', 'api_key' => 'contract-key', 'retries' => 1, 'retry_sleep_ms' => 0]);
        }, null, 'acme'],
    ];
});

beforeEach(function () {
    config(['shipping.carriers' => ['acme' => ['name' => 'Acme', 'tracking_url' => 'https://track.example.test/?n={number}']]]);
});

it('has a stable name and a consistent, side-effect free supports()', function (Closure $make, ?Closure $webhook, string $carrier) {
    Http::fake();
    $provider = $make();

    expect($provider)->toBeInstanceOf(TrackingProvider::class)
        ->and($provider->name())->toBeString()->not->toBeEmpty()
        ->and($provider->supports($carrier))->toBeBool()->toBe($provider->supports($carrier))
        ->and($provider->supports('definitely-not-a-carrier-code'))->toBeBool();

    Http::assertNothingSent();
})->with('tracking providers');

it('builds tracking urls null-safely, without a network call', function (Closure $make, ?Closure $webhook, string $carrier) {
    Http::fake();
    $provider = $make();

    foreach ([[' ', null], ['', $carrier], ['ABC', null], ['ABC', ''], ['ABC', 'definitely-not-a-carrier-code'], ["we ird/%&?#\u{e9}", $carrier]] as [$number, $code]) {
        $url = $provider->trackingUrl($number, $code);

        expect($url === null || (is_string($url) && preg_match('#^https?://[^\s]+$#', $url) === 1))->toBeTrue("bad url for [{$number}] [{$code}]");
    }

    // blank numbers never produce a link
    expect($provider->trackingUrl(' ', $carrier))->toBeNull();

    // a link implies the provider supports that carrier; numbers are URL-encoded
    $url = $provider->trackingUrl('A B/1', $carrier);
    if ($url !== null) {
        expect($provider->supports($carrier))->toBeTrue()->and($url)->not->toContain(' ')->not->toContain('A B/1');
    }

    Http::assertNothingSent();
})->with('tracking providers');

it('returns normalised, non-future track() results or throws only tracking exceptions', function (Closure $make, ?Closure $webhook, string $carrier) {
    $provider = $make();

    foreach (['TRK-1001', 'ZZ-DELIVERED', 'ZZ-TRANSIT', 'ZZ-EXCEPTION'] as $number) {
        $result = $provider->track(new TrackingRequest($number, $carrier, 'ORD-1'));

        expect($result)->toBeInstanceOf(TrackingResult::class)->and($result->status)->toBeInstanceOf(TrackingStatus::class);

        if ($provider instanceof PassiveProvider) {
            expect($result->status)->toBe(TrackingStatus::Unknown)->and($result->events)->toBe([]);
        }

        foreach ($result->events as $event) {
            expect($event)->toBeInstanceOf(TrackingEvent::class)
                ->and($event->status)->toBeInstanceOf(TrackingStatus::class)
                ->and($event->occurredAt->lessThanOrEqualTo(now()->addMinutes(5)))->toBeTrue()
                ->and($event->description)->toBeString();
        }
    }

    // failures must be the module's own exceptions
    foreach (['X-UNAVAILABLE', 'X-REJECTED'] as $number) {
        try {
            $provider->track(new TrackingRequest($number, $carrier));
        } catch (Throwable $e) {
            expect($e)->toBeInstanceOf(TrackingException::class);
        }
    }
})->with('tracking providers');

it('parses webhooks purely and applies them idempotently', function (Closure $make, ?Closure $webhook, string $carrier) {
    $provider = $make();

    if (! $provider instanceof SupportsWebhooks) {
        expect($webhook)->toBeNull(); // adapters without the capability must not ship a webhook sample

        return;
    }

    expect($webhook)->not->toBeNull();
    $request = $webhook();

    expect($provider->verifySignature($request))->toBeTrue();

    // wrong / missing signature never passes, never throws
    expect($provider->verifySignature(Request::create('/x', 'POST', [], [], [], [], $request->getContent())))->toBeFalse();

    $first = $provider->parse($request);
    $second = $provider->parse($request);

    expect($first)->not->toBeEmpty()->and($first)->toEqual($second);
    foreach ($first as $update) {
        expect($update)->toBeInstanceOf(TrackingUpdate::class)->and($update->trackingNumber)->not->toBeEmpty();
    }

    $order = Fx::shippedOrder(['tracking_number' => $first[0]->trackingNumber]);
    $service = app(ShipmentTrackingService::class);

    foreach ([1, 2] as $_) {
        foreach ($first as $update) {
            $service->apply($order, $provider->name(), $update->result);
        }
    }

    expect(ShipmentTrackingEvent::where('order_id', $order->id)->count())->toBe(count($first[0]->result->events));
})->with('tracking providers');

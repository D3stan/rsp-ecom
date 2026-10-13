<?php

/*
|--------------------------------------------------------------------------
| Contract every tracking adapter must satisfy
|--------------------------------------------------------------------------
|
| To certify a new adapter add ONE row to the 'tracking providers' dataset:
|   'name' => [fn () => new ExampleCarrierProvider([...]), $webhookRequestFactoryOrNull, $knownCarrierCode]
| Use Http::fake() inside the factory/arrange step for HTTP adapters: no real network is ever allowed.
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Shipping\Support\TrackingFixtures as Fx;

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

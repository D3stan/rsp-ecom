<?php

use App\Models\Order;
use App\Shipping\Tracking\Contracts\TrackingProvider;
use App\Shipping\Tracking\Drivers\FakeProvider;
use App\Shipping\Tracking\Drivers\ManualProvider;
use App\Shipping\Tracking\Drivers\NullProvider;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\Exceptions\UnknownTrackingDriver;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Support\Facades\Http;

function trkManager(): TrackingManager
{
    return app(TrackingManager::class);
}

function trkCarriers(): void
{
    config(['shipping.carriers' => [
        'acme' => ['name' => 'Acme Courier', 'tracking_url' => 'https://track.example.test/?n={number}'],
        'broken' => ['name' => 'Broken', 'tracking_url' => 'javascript:alert({number})'],
    ]]);
}

it('defaults to the manual driver, which cannot poll (no behaviour change)', function () {
    expect(config('shipping.tracking.driver'))->toBe('manual')
        ->and(trkManager()->driver())->toBeInstanceOf(ManualProvider::class)
        ->and(trkManager()->canPoll())->toBeFalse()
        ->and(app(TrackingProvider::class))->toBeInstanceOf(ManualProvider::class)
        ->and(trkManager())->toBe(app(TrackingManager::class)); // singleton
});

it('resolves the built-in drivers', function () {
    expect(trkManager()->driver('manual'))->toBeInstanceOf(ManualProvider::class)
        ->and(trkManager()->driver('null'))->toBeInstanceOf(NullProvider::class)
        ->and(trkManager()->driver('fake'))->toBeInstanceOf(FakeProvider::class);

    config(['shipping.tracking.driver' => 'null']);
    expect(trkManager()->driver())->toBeInstanceOf(NullProvider::class)->and(trkManager()->canPoll())->toBeFalse();

    config(['shipping.tracking.driver' => 'fake']);
    expect(trkManager()->canPoll())->toBeTrue();
});

it('registers a custom driver at runtime with extend(), replacing a cached one', function () {
    $custom = new class extends FakeProvider
    {
        public function name(): string
        {
            return 'custom';
        }
    };

    trkManager()->extend('custom', fn ($app) => $custom);
    config(['shipping.tracking.driver' => 'custom']);

    expect(trkManager()->driver())->toBe($custom)
        ->and(trkManager()->driverNames())->toContain('custom')
        ->and(trkManager()->canPoll())->toBeTrue();

    $other = new NullProvider;
    trkManager()->extend('custom', fn () => $other);
    expect(trkManager()->driver('custom'))->toBe($other);
});

it('rejects a custom driver that is not a TrackingProvider', function () {
    trkManager()->extend('bad', fn () => new stdClass);

    trkManager()->driver('bad');
})->throws(\App\Shipping\Tracking\Exceptions\TrackingException::class, 'must implement');

it('throws a clear exception for an unknown driver', function () {
    config(['shipping.tracking.driver' => 'nope']);

    expect(fn () => trkManager()->driver())->toThrow(UnknownTrackingDriver::class, 'Unknown shipping tracking driver [nope]. Available: manual, null, fake');
    expect(trkManager()->canPoll())->toBeFalse(); // never throws
});

it('refuses the fake driver in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => trkManager()->driver('fake'))->toThrow(UnknownTrackingDriver::class)
        ->and(trkManager()->driverNames())->not->toContain('fake');
});

it('reads per-driver settings from config', function () {
    config(['shipping.tracking.drivers.example' => ['base_url' => 'https://api.example.test']]);

    expect(trkManager()->driverConfig('example'))->toBe(['base_url' => 'https://api.example.test'])
        ->and(trkManager()->driverConfig('missing'))->toBe([]);
});

it('keeps the manual driver identical to the old Order::trackingUrl behaviour', function () {
    trkCarriers();
    $order = Order::factory()->make(['tracking_number' => 'AB 12/34', 'tracking_carrier' => 'acme']);

    expect($order->trackingUrl())->toBe('https://track.example.test/?n=AB%2012%2F34')
        ->and($order->trackingUrl('XYZ'))->toBe('https://track.example.test/?n=XYZ')
        ->and($order->trackingCarrierName())->toBe('Acme Courier');

    $order->tracking_carrier = 'unknown';
    expect($order->trackingUrl())->toBeNull()->and($order->trackingCarrierName())->toBe('unknown');

    $order->tracking_carrier = 'broken';
    expect($order->trackingUrl())->toBeNull();

    $order->tracking_carrier = null;
    expect($order->trackingUrl())->toBeNull()->and($order->trackingCarrierName())->toBeNull();

    $order->tracking_carrier = 'acme';
    $order->tracking_number = '  ';
    expect($order->trackingUrl())->toBeNull();
});

it('never makes a network call to build a tracking link', function () {
    Http::fake();
    trkCarriers();
    config(['shipping.tracking.driver' => 'fake']);

    trkManager()->trackingUrl('ABC', 'acme');
    Http::assertNothingSent();
});

it('disables links with the null driver and falls back to manual templates for adapters without a page', function () {
    trkCarriers();

    config(['shipping.tracking.driver' => 'null']);
    expect(trkManager()->trackingUrl('ABC', 'acme'))->toBeNull();

    // adapter that has no public page of its own -> manual template
    $noPage = new class extends FakeProvider
    {
        public function trackingUrl(string $trackingNumber, ?string $carrierCode): ?string
        {
            return null;
        }
    };
    trkManager()->extend('nopage', fn () => $noPage);
    config(['shipping.tracking.driver' => 'nopage']);
    expect(trkManager()->trackingUrl('ABC', 'acme'))->toBe('https://track.example.test/?n=ABC');

    // adapter that has one wins
    config(['shipping.tracking.driver' => 'fake']);
    expect(trkManager()->trackingUrl('ABC', 'acme'))->toBe('https://tracking.example.test/track/ABC');
});

it('falls back to the manual template when the configured driver is broken (emails must not fail)', function () {
    trkCarriers();
    config(['shipping.tracking.driver' => 'does-not-exist']);

    expect(trkManager()->trackingUrl('ABC', 'acme'))->toBe('https://track.example.test/?n=ABC');
});

it('lists the configured carriers for the admin UI', function () {
    trkCarriers();

    expect(trkManager()->carriers())->toBe([['code' => 'acme', 'name' => 'Acme Courier'], ['code' => 'broken', 'name' => 'Broken']])
        ->and(trkManager()->carrierCodes())->toBe(['acme', 'broken'])
        ->and(trkManager()->carrierName('acme'))->toBe('Acme Courier')
        ->and(trkManager()->carrierName('zzz'))->toBe('zzz');

    config(['shipping.carriers' => []]);
    expect(trkManager()->carriers())->toBe([]);
});

it('passive drivers answer unknown without a network call', function () {
    Http::fake();

    foreach (['manual', 'null'] as $name) {
        $result = trkManager()->driver($name)->track(new TrackingRequest('ABC', 'acme'));
        expect($result)->toBeInstanceOf(TrackingResult::class)->and($result->events)->toBe([]);
    }

    Http::assertNothingSent();
});

it('schedules tracking:sync every 15 minutes, gated by the driver capability', function () {
    $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'tracking:sync'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('*/15 * * * *')
        ->and($event->filtersPass(app()))->toBeFalse(); // manual driver

    config(['shipping.tracking.driver' => 'fake']);
    expect($event->filtersPass(app()))->toBeTrue();
});

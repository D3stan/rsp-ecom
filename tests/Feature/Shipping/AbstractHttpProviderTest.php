<?php

use App\Shipping\Tracking\Drivers\AbstractHttpProvider;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\Enums\TrackingStatus;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use App\Shipping\Tracking\Exceptions\TrackingRejected;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** A throwaway adapter for a fictitious API, only here to exercise the base class. */
function trkHttpProvider(array $config = []): AbstractHttpProvider
{
    return new class(array_merge(['base_url' => 'https://api.example.test', 'api_key' => 'secret-key', 'retries' => 1, 'retry_sleep_ms' => 0], $config)) extends AbstractHttpProvider
    {
        public function name(): string
        {
            return 'example';
        }

        public function supports(string $carrierCode): bool
        {
            return true;
        }

        public function trackingUrl(string $trackingNumber, ?string $carrierCode): ?string
        {
            return null;
        }

        protected function statusMap(): array
        {
            return ['PICKED_UP' => TrackingStatus::InTransit, 'DONE' => TrackingStatus::Delivered];
        }

        protected function authenticate(PendingRequest $request): PendingRequest
        {
            return $request->withToken((string) $this->config['api_key']);
        }

        public function track(TrackingRequest $request): TrackingResult
        {
            $json = $this->send(fn (PendingRequest $http) => $http->get('/track', ['code' => $request->trackingNumber]))->json();

            return new TrackingResult($this->mapStatus($json['status'] ?? null));
        }

        public function at(string $date, string $tz): \Carbon\CarbonImmutable
        {
            return $this->toUtc($date, $tz);
        }
    };
}

it('calls the configured base url with authentication and maps statuses', function () {
    Http::fake(['api.example.test/*' => Http::response(['status' => ' picked_up '])]);

    $result = trkHttpProvider()->track(new TrackingRequest('ABC'));

    expect($result->status)->toBe(TrackingStatus::InTransit);
    Http::assertSent(fn ($r) => $r->url() === 'https://api.example.test/track?code=ABC' && $r->hasHeader('Authorization', 'Bearer secret-key'));
});

it('maps unlisted carrier statuses to Unknown', function () {
    Http::fake(['*' => Http::response(['status' => 'WEIRD'])]);

    expect(trkHttpProvider()->track(new TrackingRequest('ABC'))->status)->toBe(TrackingStatus::Unknown);
});

it('turns temporary failures into TrackingProviderUnavailable', function (int $status) {
    Http::fake(['*' => Http::response('nope', $status)]);

    expect(fn () => trkHttpProvider()->track(new TrackingRequest('ABC')))->toThrow(TrackingProviderUnavailable::class, "HTTP {$status}");
})->with([401, 403, 408, 429, 500, 503]);

it('turns other client errors into TrackingRejected carrying the http status', function (int $status) {
    Http::fake(['*' => Http::response('nope', $status)]);

    try {
        trkHttpProvider()->track(new TrackingRequest('ABC'));
        $this->fail('expected exception');
    } catch (TrackingRejected $e) {
        expect($e->httpStatus)->toBe($status);
    }
})->with([400, 404, 422]);

it('turns connection errors into TrackingProviderUnavailable', function () {
    Http::fake(fn () => throw new ConnectionException('boom'));

    expect(fn () => trkHttpProvider()->track(new TrackingRequest('ABC')))->toThrow(TrackingProviderUnavailable::class, 'connection failed');
});

it('retries transient server errors once before giving up', function () {
    Http::fake(['*' => Http::sequence()->push('x', 500)->push(['status' => 'DONE'])]);

    expect(trkHttpProvider()->track(new TrackingRequest('ABC'))->status)->toBe(TrackingStatus::Delivered);
    Http::assertSentCount(2);
});

it('never puts credentials or response bodies into exception messages', function () {
    Http::fake(['*' => Http::response('{"error":"bad key secret-key"}', 500)]);

    try {
        trkHttpProvider()->track(new TrackingRequest('ABC'));
    } catch (TrackingProviderUnavailable $e) {
        expect($e->getMessage())->not->toContain('secret-key')->and($e->getMessage())->toBe('example: HTTP 500');
    }
});

it('converts carrier-local times to UTC', function () {
    expect(trkHttpProvider()->at('2026-07-01 12:00:00', 'Europe/Rome')->format('H:i'))->toBe('10:00')
        ->and(trkHttpProvider()->at('2026-07-01T12:00:00+05:00', 'UTC')->format('H:i'))->toBe('07:00');
});

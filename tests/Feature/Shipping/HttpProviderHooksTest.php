<?php

/*
| Provider-neutral building blocks for HTTP adapters: accepted statuses (304/404), Authenticators,
| refresh-on-401 and the ETag trait. Exercised through a throwaway adapter for a fictitious API.
*/

use App\Shipping\Auth\ApiKeyHeaderAuthenticator;
use App\Shipping\Auth\BearerTokenAuthenticator;
use App\Shipping\Auth\CachedClientCredentialsAuthenticator;
use App\Shipping\Contracts\Authenticator;
use App\Shipping\Tracking\Drivers\AbstractHttpProvider;
use App\Shipping\Tracking\Drivers\Concerns\CachesConditionalRequests;
use App\Shipping\Tracking\DTO\TrackingEvent;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\DTO\TrackingResult;
use App\Shipping\Tracking\Enums\TrackingStatus;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use App\Shipping\Tracking\Exceptions\TrackingRejected;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function trkHookProvider(?Authenticator $auth = null, array $config = []): AbstractHttpProvider
{
    return new class(array_merge(['base_url' => 'https://api.example.test', 'retries' => 0, 'retry_sleep_ms' => 0], $config), $auth) extends AbstractHttpProvider
    {
        use CachesConditionalRequests;

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
            return ['DONE' => TrackingStatus::Delivered];
        }

        public function track(TrackingRequest $request): TrackingResult
        {
            $code = $request->trackingNumber;
            $response = $this->send(
                fn (PendingRequest $http) => $http->withHeaders($this->conditionalHeaders($code))->get('/track', ['code' => $code]),
                [304, 404],
            );

            if ($response->status() === 304) {
                return $this->cachedResult($code) ?? TrackingResult::unknown();
            }

            if ($response->status() === 404) {
                return TrackingResult::unknown();
            }

            $result = new TrackingResult($this->mapStatus($response->json('status')), [
                new TrackingEvent(TrackingStatus::Delivered, 'Done', 'Hub', CarbonImmutable::parse('2026-07-01 10:00:00'), 'e1'),
            ], CarbonImmutable::parse('2026-07-02 00:00:00'));
            $this->rememberResult($code, $response, $result);

            return $result;
        }
    };
}

// ------------------------------------------------------------------ accepted statuses

it('hands accepted non-2xx statuses to the adapter instead of throwing', function (int $status) {
    Http::fake(['*' => Http::response('', $status)]);

    expect(trkHookProvider()->track(new TrackingRequest('A1'))->status)->toBe(TrackingStatus::Unknown);
})->with([304, 404]);

it('still throws for statuses the adapter did not accept', function () {
    Http::fake(['*' => Http::response('', 422)]);

    expect(fn () => trkHookProvider()->track(new TrackingRequest('A1')))->toThrow(TrackingRejected::class);
});

// ------------------------------------------------------------------ authenticators

it('sends a bearer token and refuses to run without one', function () {
    Http::fake(['*' => Http::response(['status' => 'DONE'])]);

    trkHookProvider(new BearerTokenAuthenticator(' tok-1 ', 'example'))->track(new TrackingRequest('A1'));
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer tok-1'));

    Http::fake();
    expect(fn () => trkHookProvider(new BearerTokenAuthenticator('  ', 'example'))->track(new TrackingRequest('A1')))
        ->toThrow(TrackingProviderUnavailable::class, 'example: API token is not configured');
    Http::assertNothingSent();
});

it('sends an API key in the configured header and refuses to run without one', function () {
    Http::fake(['*' => Http::response(['status' => 'DONE'])]);

    trkHookProvider(new ApiKeyHeaderAuthenticator('key-1', 'X-Custom-Key', 'example'))->track(new TrackingRequest('A1'));
    Http::assertSent(fn ($r) => $r->hasHeader('X-Custom-Key', 'key-1') && ! $r->hasHeader('Authorization'));

    expect(fn () => trkHookProvider(new ApiKeyHeaderAuthenticator('', 'X-Custom-Key', 'example'))->track(new TrackingRequest('A1')))
        ->toThrow(TrackingProviderUnavailable::class, 'API key is not configured');
});

it('works unauthenticated by default', function () {
    Http::fake(['*' => Http::response(['status' => 'DONE'])]);

    trkHookProvider()->track(new TrackingRequest('A1'));
    Http::assertSent(fn ($r) => ! $r->hasHeader('Authorization'));
});

function trkOauth(): CachedClientCredentialsAuthenticator
{
    return new CachedClientCredentialsAuthenticator('example', 'https://auth.example.test/token', 'client-id', 'client-secret', 'tracking');
}

it('fetches a client-credentials token once and caches it until it expires', function () {
    Http::fake([
        'auth.example.test/*' => Http::response(['access_token' => 'at-1', 'expires_in' => 3600]),
        'api.example.test/*' => Http::response(['status' => 'DONE']),
    ]);
    $provider = trkHookProvider(trkOauth());

    $provider->track(new TrackingRequest('A1'));
    $provider->track(new TrackingRequest('A2'));

    Http::assertSentCount(3); // 1 token request + 2 API calls
    Http::assertSent(fn ($r) => str_contains($r->url(), 'auth.example.test')
        && $r['grant_type'] === 'client_credentials' && $r['client_id'] === 'client-id' && $r['client_secret'] === 'client-secret' && $r['scope'] === 'tracking');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'api.example.test') && $r->hasHeader('Authorization', 'Bearer at-1'));

    // the token is stored encrypted, never in clear text
    $stored = Cache::get('tracking:auth:example:'.sha1('client-id|https://auth.example.test/token'));
    expect($stored)->toBeString()->not->toContain('at-1')->and(Crypt::decryptString($stored))->toBe('at-1');
});

it('requests a fresh token after the cached one expired', function () {
    Http::fake([
        'auth.example.test/*' => Http::response(['access_token' => 'at-1', 'expires_in' => 3600]),
        'api.example.test/*' => Http::response(['status' => 'DONE']),
    ]);
    $provider = trkHookProvider(trkOauth());

    $provider->track(new TrackingRequest('A1'));
    $this->travel(2)->hours();
    $provider->track(new TrackingRequest('A2'));

    Http::assertSentCount(4); // token, call, new token, call
});

it('refreshes the token once after a 401 and repeats the call', function () {
    Http::fake([
        'auth.example.test/*' => Http::sequence()->push(['access_token' => 'old', 'expires_in' => 3600])->push(['access_token' => 'new', 'expires_in' => 3600]),
        'api.example.test/*' => Http::sequence()->push('expired', 401)->push(['status' => 'DONE']),
    ]);

    expect(trkHookProvider(trkOauth())->track(new TrackingRequest('A1'))->status)->toBe(TrackingStatus::Delivered);

    Http::assertSentCount(4);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'api.example.test') && $r->hasHeader('Authorization', 'Bearer new'));
});

it('gives up after a single refresh when the second answer is a 401 too', function () {
    Http::fake([
        'auth.example.test/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        'api.example.test/*' => Http::response('no', 401),
    ]);

    expect(fn () => trkHookProvider(trkOauth())->track(new TrackingRequest('A1')))->toThrow(TrackingProviderUnavailable::class, 'HTTP 401');
    Http::assertSentCount(4); // token, call, token, call: never a third attempt
});

it('reports token endpoint problems as temporary, without leaking secrets', function () {
    Http::fake(['auth.example.test/*' => Http::response('{"error":"client-secret wrong"}', 400)]);

    try {
        trkHookProvider(trkOauth())->track(new TrackingRequest('A1'));
        $this->fail('expected exception');
    } catch (TrackingProviderUnavailable $e) {
        expect($e->getMessage())->not->toContain('client-secret')->and($e->getMessage())->toContain('token endpoint');
    }

    expect(fn () => trkHookProvider(new CachedClientCredentialsAuthenticator('example', '', 'id', 'secret'))->track(new TrackingRequest('A1')))
        ->toThrow(TrackingProviderUnavailable::class, 'client credentials are not configured');
});

it('does not cache a token that is about to expire', function () {
    Http::fake([
        'auth.example.test/*' => Http::response(['access_token' => 'short', 'expires_in' => 30]),
        'api.example.test/*' => Http::response(['status' => 'DONE']),
    ]);
    $provider = trkHookProvider(trkOauth());

    $provider->track(new TrackingRequest('A1'));
    $provider->track(new TrackingRequest('A2'));

    Http::assertSentCount(4); // a token request before each call
});

// ------------------------------------------------------------------ ETag trait

it('stores the ETag and the parsed result, sends If-None-Match next time and serves a 304 from the cache', function () {
    Http::fake(['*' => Http::sequence()
        ->push(['status' => 'DONE'], 200, ['ETag' => '"v1"'])
        ->push('', 304, ['ETag' => '"v1"'])]);
    $provider = trkHookProvider();

    $first = $provider->track(new TrackingRequest('A1'));
    $second = $provider->track(new TrackingRequest('A1'));

    expect($second->status)->toBe(TrackingStatus::Delivered)
        ->and($second->events)->toHaveCount(1)
        ->and($second->events[0]->description)->toBe('Done')
        ->and($second->events[0]->occurredAt)->toEqual($first->events[0]->occurredAt)
        ->and($second->estimatedDelivery)->toEqual($first->estimatedDelivery);

    $requests = Http::recorded();
    expect($requests[0][0]->hasHeader('If-None-Match'))->toBeFalse()
        ->and($requests[1][0]->hasHeader('If-None-Match', '"v1"'))->toBeTrue();
});

it('keys the cache by provider and tracking number, case-insensitively', function () {
    Http::fake(['*' => Http::response(['status' => 'DONE'], 200, ['ETag' => '"v1"'])]);

    trkHookProvider()->track(new TrackingRequest('Ab1'));
    trkHookProvider()->track(new TrackingRequest('ab1'));
    trkHookProvider()->track(new TrackingRequest('other'));

    $recorded = Http::recorded();
    expect($recorded[1][0]->hasHeader('If-None-Match', '"v1"'))->toBeTrue()
        ->and($recorded[2][0]->hasHeader('If-None-Match'))->toBeFalse();
});

it('answers unknown to a 304 whose cache entry is gone, and drops the entry when the answer has no ETag', function () {
    Http::fake(['*' => Http::sequence()->push('', 304)->push(['status' => 'DONE'], 200, ['ETag' => '"v1"'])->push(['status' => 'DONE'])->push('', 304)]);
    $provider = trkHookProvider();

    expect($provider->track(new TrackingRequest('A1'))->status)->toBe(TrackingStatus::Unknown);
    $provider->track(new TrackingRequest('A1')); // stores v1
    $provider->track(new TrackingRequest('A1')); // answer without ETag clears it
    expect($provider->track(new TrackingRequest('A1'))->status)->toBe(TrackingStatus::Unknown);
});

it('bounds the cache lifetime of conditional entries', function () {
    Http::fake(['*' => Http::response(['status' => 'DONE'], 200, ['ETag' => '"v1"'])]);
    $provider = trkHookProvider(config: ['etag_ttl_minutes' => 999999]);

    $provider->track(new TrackingRequest('A1'));
    $this->travel(15)->days();
    $provider->track(new TrackingRequest('A1'));

    $recorded = Http::recorded();
    expect($recorded[0][0]->hasHeader('If-None-Match'))->toBeFalse()
        ->and($recorded[1][0]->hasHeader('If-None-Match'))->toBeFalse(); // entries live 14 days at most
});

it('never logs anything while talking to the API', function () {
    Log::spy();
    Http::fake(['*' => Http::response(['status' => 'DONE'], 200, ['ETag' => '"v1"'])]);

    trkHookProvider(new BearerTokenAuthenticator('super-secret', 'example'))->track(new TrackingRequest('A1'));

    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('debug');
    Log::shouldNotHaveReceived('warning');
});

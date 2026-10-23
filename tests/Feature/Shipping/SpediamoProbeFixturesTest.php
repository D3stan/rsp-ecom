<?php

use App\Shipping\Auth\BearerTokenAuthenticator;
use App\Shipping\Providers\Spediamo\SpediamoRateProvider;
use App\Shipping\Rates\DTO\RateAddress;
use App\Shipping\Rates\DTO\RateParcel;
use App\Shipping\Rates\DTO\RateRequest;
use App\Shipping\Rates\Exceptions\RateRejected;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * Replays requests and responses recorded from the LIVE Spediamo API (tests/Fixtures/spediamo/probes/*.json, made with
 * the probe script: one scenario per file, billing data redacted). They pin the two things the mocks used to guess:
 * what we send, and how the provider reads what comes back.
 */

function spediamoProbes(): array
{
    $probes = [];
    foreach (glob(__DIR__.'/../../Fixtures/spediamo/probes/*.json') as $file) {
        $probes[basename($file, '.json')] = [json_decode(file_get_contents($file), true)];
    }

    return $probes;
}

function spediamoProvider(): SpediamoRateProvider
{
    return new SpediamoRateProvider(['base_url' => 'https://api.spediamo.it', 'cleanup_quote_drafts' => false], new BearerTokenAuthenticator('t'));
}

// the request every probe starts from (fake sender, Cesena to Cervia)
function spediamoProbeRequest(array $parcels): RateRequest
{
    return new RateRequest(
        sender: new RateAddress('47522', 'Cesena', 'FC', 'IT', 'Test Sender', 'Via Pola 2', '3331234567', 'sender@example.test'),
        recipient: new RateAddress('48015', 'Cervia', 'RA', 'IT', 'Test Customer', 'Via Roma 1'),
        parcels: $parcels,
    );
}

it('sends exactly the request the live API accepted', function () {
    Http::fake(['api.spediamo.it/v1/shipments' => Http::response(['id' => 'x', 'courier' => 'PDB', 'totalPrice' => ['value' => 1]], 201)]);

    spediamoProvider()->quote(spediamoProbeRequest([new RateParcel(weightKg: 1, lengthCm: 35, widthCm: 25, heightCm: 8)]));

    $live = spediamoProbes()['quote_ok_single_parcel'][0]['request'];
    Http::assertSent(function (Request $request) use ($live) {
        $sent = $request->data();
        ksort($sent);
        ksort($live);

        return $sent === $live;
    });
});

it('reads each recorded live response', function (array $probe) {
    Http::fake(['api.spediamo.it/v1/shipments' => Http::response($probe['response'], $probe['status'])]);

    $quote = fn () => spediamoProvider()->quote(spediamoProbeRequest([new RateParcel(weightKg: 1, lengthCm: 35, widthCm: 25, heightCm: 8)]));

    if (str_starts_with($probe['scenario'], 'quote_ok')) {
        $quotes = $quote();
        expect($quotes)->toHaveCount(1)
            ->and($quotes[0]->totalPrice)->toBe((float) $probe['response']['totalPrice']['value'])
            ->and($quotes[0]->carrierCode)->not->toBe('');

        return;
    }

    // the pickup date is only "missing" until booking, the provider rightly ignores it
    $fields = collect($probe['response']['errors'])->pluck('field')->reject(fn ($field) => str_starts_with($field, 'requestedPickup'))->all();
    try {
        $quote();
        $this->fail('expected a rejection');
    } catch (RateRejected $e) {
        foreach ($fields as $field) {
            expect($e->getMessage())->toContain($field);
        }
    }
})->with(spediamoProbes());

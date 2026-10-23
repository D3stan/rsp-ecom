<?php

use App\Shipping\Auth\BearerTokenAuthenticator;
use App\Shipping\Providers\Spediamo\SpediamoRateProvider;
use App\Shipping\Rates\DTO\RateAddress;
use App\Shipping\Rates\DTO\RateParcel;
use App\Shipping\Rates\DTO\RateRequest;
use App\Shipping\Rates\Exceptions\RateRejected;
use App\Shipping\Rates\RateManager;
use Illuminate\Support\Facades\Http;

function spediamoProbe(string $name): array
{
    return json_decode(file_get_contents(__DIR__."/../../Fixtures/spediamo/probes/{$name}.json"), true);
}

function spediamoIssuesFor(array $probe): array
{
    Http::fake(['api.spediamo.it/v1/shipments' => Http::response($probe['response'], $probe['status'])]);

    $provider = new SpediamoRateProvider(['base_url' => 'https://api.spediamo.it', 'cleanup_quote_drafts' => false], new BearerTokenAuthenticator('t'));
    $request = new RateRequest(
        sender: new RateAddress('47522', 'Cesena', 'FC', 'IT'),
        recipient: new RateAddress('48105', 'Cervia', 'RA', 'IT'),
        parcels: [new RateParcel(weightKg: 1)],
    );

    try {
        $provider->quote($request);
    } catch (RateRejected $e) {
        return [collect($e->issues)->map->toArray()->all(), $e->getMessage()];
    }

    test()->fail('expected a rejection');
}

it('maps a live recipient postal code rejection to an address issue', function () {
    [$issues, $message] = spediamoIssuesFor(spediamoProbe('reject_invalid_recipient_postal_code'));

    expect($issues)->toBe([
        ['provider' => 'spediamo', 'kind' => 'address', 'field' => 'recipient.postal_code', 'code' => 'invalid', 'message' => 'Non valido'],
    ])->and($message)->toContain('recipientPostalCode');
});

it('maps live parcel size rejections to parcel issues', function () {
    [$issues] = spediamoIssuesFor(spediamoProbe('reject_zero_size'));

    expect(collect($issues)->pluck('kind')->unique()->all())->toBe(['parcel'])
        ->and(collect($issues)->pluck('field')->all())->toBe(['packages[0].height', 'packages[0].length', 'packages[0].width']);
});

it('maps a live sender rejection to a settings issue', function () {
    [$issues] = spediamoIssuesFor(spediamoProbe('reject_invalid_sender_postal_code'));

    expect($issues)->toBe([
        ['provider' => 'spediamo', 'kind' => 'settings', 'field' => 'senderPostalCode', 'code' => 'invalid', 'message' => 'Non valido'],
    ]);
});

it('maps the errors of a priced draft but not its pickup date', function () {
    [$issues] = spediamoIssuesFor(spediamoProbe('reject_missing_recipient_town'));

    expect($issues)->toBe([
        ['provider' => 'spediamo', 'kind' => 'address', 'field' => 'recipient.city', 'code' => 'required', 'message' => 'Obbligatorio'],
    ]);
});

it('keeps unknown fields as other issues with the carrier text', function () {
    [$issues] = spediamoIssuesFor(['status' => 400, 'response' => ['errors' => [['field' => 'weird', 'code' => 'x', 'message' => 'Boh']]]]);

    expect($issues[0])->toMatchArray(['kind' => 'other', 'field' => 'weird', 'message' => 'Boh']);
});

it('reports a missing Dvaexpress recipient postal code as an address issue', function () {
    config(['shipping.providers.dvaexpress.api_key' => 'k']);
    $provider = app(RateManager::class)->driver('dvaexpress');

    $request = new RateRequest(
        sender: new RateAddress('47522', 'Cesena', 'FC', 'IT'),
        recipient: new RateAddress('', 'Cervia', 'RA', 'IT'),
        parcels: [new RateParcel(weightKg: 1)],
    );

    try {
        $provider->quote($request);
        $this->fail('expected a rejection');
    } catch (RateRejected $e) {
        expect($e->issues[0]->toArray())->toMatchArray(['kind' => 'address', 'field' => 'recipient.postal_code', 'code' => 'required']);
    }
});

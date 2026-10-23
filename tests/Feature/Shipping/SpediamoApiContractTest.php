<?php

use App\Shipping\Auth\BearerTokenAuthenticator;
use App\Shipping\Labels\DTO\Address;
use App\Shipping\Labels\DTO\Parcel;
use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Providers\Spediamo\SpediamoLabelRequestMapper;
use App\Shipping\Providers\Spediamo\SpediamoRateProvider;
use App\Shipping\Rates\DTO\RateAddress;
use App\Shipping\Rates\DTO\RateParcel;
use App\Shipping\Rates\DTO\RateRequest;
use App\Shipping\Rates\Exceptions\RateRejected;
use App\Shipping\Rates\RateManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Spediamo API Contract & Discrepancy Tests
|--------------------------------------------------------------------------
|
| These tests verify how SpediamoRateProvider and SpediamoLabelRequestMapper
| construct HTTP requests and demonstrate why the live Spediamo API returns:
| "Spediamo: field: type; code: required; message: Obbligatorio"
|
| The root causes identified against the live https://api.spediamo.it API:
| 1. HTTP Content-Type: Spediamo POST /v1/shipments requires query parameters
|    or application/x-www-form-urlencoded. When sent as application/json,
|    Spediamo ignores the body and fails validation on the first required field:
|    {"errors":[{"field":"type","code":"required","message":"Obbligatorio"}]}
|
| 2. Payload field "type": The code sends "type" => "packages".
|    Spediamo expects "type" to be the shipment destination scope (e.g. "DOMESTIC"
|    or "INTERNATIONAL"). If "type=packages" is sent, Spediamo returns:
|    {"errors":[{"field":"type","code":"invalid","message":"Non valido"}]}
|    Package dimensions belong in the "packages" parameter (e.g. "1x35x25x8").
|
*/

it('reproduces the exact Spediamo error mapping for required type field', function () {
    Http::fake([
        'https://api.spediamo.it/v1/shipments' => Http::response([
            'errors' => [
                [
                    'field' => 'type',
                    'code' => 'required',
                    'message' => 'Obbligatorio',
                ],
            ],
            'warnings' => null,
        ], 400),
    ]);

    $provider = new SpediamoRateProvider(
        ['base_url' => 'https://api.spediamo.it', 'token' => 'dummy-token'],
        new BearerTokenAuthenticator('dummy-token'),
    );

    $rateRequest = new RateRequest(
        sender: new RateAddress(postalCode: '20100', city: 'Milano', province: 'MI', country: 'IT'),
        recipient: new RateAddress(postalCode: '48105', city: 'Cervia', province: 'RA', country: 'IT'),
        parcels: [new RateParcel(weightKg: 1.0, lengthCm: 35, widthCm: 25, heightCm: 8)],
    );

    expect(fn () => $provider->quote($rateRequest))
        ->toThrow(RateRejected::class, 'field: type; code: required; message: Obbligatorio');
});

it('reproduces RateManager error message formatting as reported in the UI', function () {
    Http::fake([
        'https://api.spediamo.it/v1/shipments' => Http::response([
            'errors' => [
                [
                    'field' => 'type',
                    'code' => 'required',
                    'message' => 'Obbligatorio',
                ],
            ],
            'warnings' => null,
        ], 400),
    ]);

    config([
        'shipping.providers.spediamo.token' => 'dummy-token',
        'shipping.providers.spediamo.base_url' => 'https://api.spediamo.it',
    ]);

    $manager = app(RateManager::class);
    $rateRequest = new RateRequest(
        sender: new RateAddress(postalCode: '20100', city: 'Milano', province: 'MI', country: 'IT'),
        recipient: new RateAddress(postalCode: '48105', city: 'Cervia', province: 'RA', country: 'IT'),
        parcels: [new RateParcel(weightKg: 1.0, lengthCm: 35, widthCm: 25, heightCm: 8)],
    );

    $result = $manager->quoteAll($rateRequest, ['spediamo']);

    expect($result['errors'])->toHaveKey('spediamo')
        ->and($result['errors']['spediamo'])->toBe('field: type; code: required; message: Obbligatorio');
});

it('reproduces the Spediamo error mapping when type is invalid (e.g. packages)', function () {
    Http::fake([
        'https://api.spediamo.it/v1/shipments' => Http::response([
            'errors' => [
                [
                    'field' => 'type',
                    'code' => 'invalid',
                    'message' => 'Non valido',
                ],
            ],
            'warnings' => null,
        ], 400),
    ]);

    $provider = new SpediamoRateProvider(
        ['base_url' => 'https://api.spediamo.it', 'token' => 'dummy-token'],
        new BearerTokenAuthenticator('dummy-token'),
    );

    $rateRequest = new RateRequest(
        sender: new RateAddress(postalCode: '20100', city: 'Milano', province: 'MI', country: 'IT'),
        recipient: new RateAddress(postalCode: '48105', city: 'Cervia', province: 'RA', country: 'IT'),
        parcels: [new RateParcel(weightKg: 1.0, lengthCm: 35, widthCm: 25, heightCm: 8)],
    );

    expect(fn () => $provider->quote($rateRequest))
        ->toThrow(RateRejected::class, 'field: type; code: invalid; message: Non valido');
});

it('inspects the outgoing HTTP payload structure sent by SpediamoRateProvider', function () {
    Http::fake([
        'https://api.spediamo.it/v1/shipments*' => Http::response([
            'id' => 'spd-draft-123',
            'status' => 'DRAFT',
            'type' => 'DOMESTIC',
            'netPrice' => 6.50,
            'totalPrice' => 7.93,
        ], 200),
    ]);

    $provider = new SpediamoRateProvider(
        ['base_url' => 'https://api.spediamo.it', 'token' => 'dummy-token', 'cleanup_quote_drafts' => false],
        new BearerTokenAuthenticator('dummy-token'),
    );

    $rateRequest = new RateRequest(
        sender: new RateAddress(postalCode: '20100', city: 'Milano', province: 'MI', country: 'IT', name: 'Sender Store'),
        recipient: new RateAddress(postalCode: '48105', city: 'Cervia', province: 'RA', country: 'IT', name: 'John Doe'),
        parcels: [new RateParcel(weightKg: 1.0, lengthCm: 35, widthCm: 25, heightCm: 8)],
    );

    $provider->quote($rateRequest);

    Http::assertSent(function (Request $request) {
        $data = $request->data();

        // 1. Implementation sends type as 'DOMESTIC'
        expect($data['type'])->toBe('DOMESTIC')
            // 2. Outgoing packages string
            ->and($data['packages'])->toBe('1x35x25x8')
            // 3. Sender / Recipient fields
            ->and($data['senderPostalCode'])->toBe('20100')
            ->and($data['recipientPostalCode'])->toBe('48105');

        // 4. Content-Type is form-urlencoded
        expect($request->isForm())->toBeTrue();

        return true;
    });
});

it('inspects the outgoing payload structure mapped by SpediamoLabelRequestMapper', function () {
    $mapper = new SpediamoLabelRequestMapper;

    $shipmentRequest = new ShipmentRequest(
        carrierCode: 'sda',
        sender: new Address(name: 'Sender Store', street: 'Via Store 1', city: 'Milano', postalCode: '20100', province: 'MI', country: 'IT'),
        recipient: new Address(name: 'Customer', street: 'Via Roma 1', city: 'Cervia', postalCode: '48105', province: 'RA', country: 'IT'),
        parcels: [new Parcel(weightKg: 1.0, lengthCm: 35, widthCm: 25, heightCm: 8)],
    );

    $mapped = $mapper->map($shipmentRequest);

    // Shows that SpediamoLabelRequestMapper sets 'type' => 'DOMESTIC'
    expect($mapped['type'])->toBe('DOMESTIC')
        ->and($mapped['packages'])->toBe('1x35x25x8');
});

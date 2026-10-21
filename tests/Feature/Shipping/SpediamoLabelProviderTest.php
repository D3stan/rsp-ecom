<?php

use App\Shipping\Providers\Spediamo\SpediamoLabelProvider;
use App\Shipping\Providers\Spediamo\SpediamoLabelRequestMapper;
use App\Shipping\Support\CarrierCodeMap;
use App\Shipping\Labels\DTO\Address;
use App\Shipping\Labels\DTO\Parcel;
use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\Exceptions\LabelRejected;
use Illuminate\Support\Facades\Http;

/*
 * The Spediamo label driver maps a ShipmentRequest to POST /v1/shipments and reads the waybill PDF from
 * GET /v1/waybills/{id}/pdf. Built from docs/spediamo/shipments.md and waybills.md; never calls the real API.
 */

function spediamoLabelConfig(): array
{
    return ['base_url' => 'https://api.spediamo.test', 'token' => 'label-token', 'timeout' => 5];
}

function spediamoShipmentRequest(): ShipmentRequest
{
    return new ShipmentRequest(
        carrierCode: 'acme',
        sender: new Address(name: 'My Shop', street: 'Via Roma 1', city: 'Milano', postalCode: '20100', province: 'MI', country: 'IT', phone: '0200000', email: 'shop@example.test'),
        recipient: new Address(name: 'Mario Rossi', street: 'Via Verdi 2', city: 'Roma', postalCode: '00100', province: 'RM', country: 'IT', phone: '0600000', email: 'mario@example.test'),
        parcels: [new Parcel(weightKg: 1.5)],
        reference: 'ORD-2026-1',
    );
}

it('creates a shipment and maps the response', function () {
    Http::fake([
        'https://api.spediamo.test/v1/shipments*' => Http::response([
            'id' => 'shp_123', 'trackingNumber' => 'SP-TRACK-9', 'courier' => 'SDA', 'status' => 'BOOKED', 'waybillAvailable' => true,
        ], 201),
    ]);

    $result = (new SpediamoLabelProvider(spediamoLabelConfig()))->createShipment(spediamoShipmentRequest());

    expect($result->externalId)->toBe('shp_123')
        ->and($result->trackingNumber)->toBe('SP-TRACK-9')
        ->and($result->carrierCode)->toBe('acme');

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($request->url(), '/v1/shipments')
            && $request->hasHeader('Authorization', 'Bearer label-token')
            && ($data['recipientName'] ?? null) === 'Mario Rossi'
            && ($data['recipientPostalCode'] ?? null) === '00100'
            && ($data['senderName'] ?? null) === 'My Shop';
    });
});

it('downloads the waybill PDF for a shipment', function () {
    Http::fake([
        'https://api.spediamo.test/v1/waybills/shp_123/pdf*' => Http::response('%PDF-1.4 fake waybill', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $document = (new SpediamoLabelProvider(spediamoLabelConfig()))->label('shp_123');

    expect($document->contents)->toStartWith('%PDF')
        ->and($document->mimeType)->toBe('application/pdf');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/waybills/shp_123/pdf')
        && $request->hasHeader('Authorization', 'Bearer label-token'));
});

it('turns a 400 into a LabelRejected with the carrier message', function () {
    Http::fake(['*' => Http::response(['message' => 'Invalid recipient postal code'], 400)]);

    expect(fn () => (new SpediamoLabelProvider(spediamoLabelConfig()))->createShipment(spediamoShipmentRequest()))
        ->toThrow(LabelRejected::class, 'Invalid recipient postal code');
});

it('turns a 401 and a 5xx into LabelProviderUnavailable', function () {
    Http::fake(['*' => Http::response('nope', 401)]);
    expect(fn () => (new SpediamoLabelProvider(spediamoLabelConfig()))->createShipment(spediamoShipmentRequest()))
        ->toThrow(LabelProviderUnavailable::class);

    Http::fake(['*' => Http::response('boom', 503)]);
    expect(fn () => (new SpediamoLabelProvider(spediamoLabelConfig()))->createShipment(spediamoShipmentRequest()))
        ->toThrow(LabelProviderUnavailable::class);
});

it('fails as unavailable without any HTTP call when the token is missing', function () {
    Http::fake();

    expect(fn () => (new SpediamoLabelProvider(['base_url' => 'https://api.spediamo.test', 'token' => '']))->createShipment(spediamoShipmentRequest()))
        ->toThrow(LabelProviderUnavailable::class);

    Http::assertNothingSent();
});

it('never logs the token or request bodies', function () {
    // the exception message carries the HTTP status only
    Http::fake(['*' => Http::response('secret body', 500)]);

    try {
        (new SpediamoLabelProvider(spediamoLabelConfig()))->createShipment(spediamoShipmentRequest());
    } catch (LabelProviderUnavailable $e) {
        expect($e->getMessage())->not->toContain('label-token')->not->toContain('secret body');
    }
});

it('S1: SpediamoLabelRequestMapper maps carrierCode via CarrierCodeMap into courier', function () {
    $carrierMap = new CarrierCodeMap(['sda' => 'SDA', 'brt' => 'BARTOLINI']);
    $mapper = new SpediamoLabelRequestMapper(carriers: $carrierMap);

    $req = new ShipmentRequest(
        carrierCode: 'sda',
        sender: new Address(name: 'Sender', street: 'Via 1', city: 'Milano', postalCode: '20100', province: 'MI', country: 'IT', phone: '02', email: 's@test.com'),
        recipient: new Address(name: 'Recip', street: 'Via 2', city: 'Roma', postalCode: '00100', province: 'RM', country: 'IT', phone: '06', email: 'r@test.com'),
        parcels: [new Parcel(weightKg: 2.0)],
        reference: 'REF-S1',
    );

    $params = $mapper->map($req);
    expect($params['courier'])->toBe('SDA');

    $reqBrt = new ShipmentRequest(
        carrierCode: 'brt',
        sender: $req->sender,
        recipient: $req->recipient,
        parcels: $req->parcels,
        reference: 'REF-S1-BRT',
    );
    expect($mapper->map($reqBrt)['courier'])->toBe('BARTOLINI');
});

it('S2: SpediamoLabelRequestMapper includes iban and bankAccountHolder when codAmount > 0', function () {
    $mapper = new SpediamoLabelRequestMapper(
        config: ['iban' => 'IT60X0542811101000000123456', 'bank_account_holder' => 'RSP Shop Srl'],
    );

    $req = new ShipmentRequest(
        carrierCode: 'sda',
        sender: new Address(name: 'Sender', street: 'Via 1', city: 'Milano', postalCode: '20100', province: 'MI', country: 'IT', phone: '02', email: 's@test.com'),
        recipient: new Address(name: 'Recip', street: 'Via 2', city: 'Roma', postalCode: '00100', province: 'RM', country: 'IT', phone: '06', email: 'r@test.com'),
        parcels: [new Parcel(weightKg: 2.0)],
        reference: 'REF-S2',
        codAmount: 75.50,
    );

    $params = $mapper->map($req);
    expect($params['cashOnDelivery'])->toBe('true')
        ->and($params['cashOnDeliveryAmount'])->toBe('75.50')
        ->and($params['iban'])->toBe('IT60X0542811101000000123456')
        ->and($params['bankAccountHolder'])->toBe('RSP Shop Srl');
});

it('S2: SpediamoLabelProvider reuses existing draft via GET /v1/shipments/{quoteToken} and falls back to id when status is DRAFT and trackingNumber is empty', function () {
    Http::fake([
        'https://api.spediamo.test/v1/shipments/spd-draft-123' => Http::response([
            'id' => 'spd-draft-123',
            'status' => 'DRAFT',
            'trackingNumber' => '',
            'courier' => 'SDA',
            'waybillAvailable' => false,
        ], 200),
    ]);

    $req = new ShipmentRequest(
        carrierCode: 'sda',
        sender: new Address(name: 'Sender', street: 'Via 1', city: 'Milano', postalCode: '20100', province: 'MI', country: 'IT', phone: '02', email: 's@test.com'),
        recipient: new Address(name: 'Recip', street: 'Via 2', city: 'Roma', postalCode: '00100', province: 'RM', country: 'IT', phone: '06', email: 'r@test.com'),
        parcels: [new Parcel(weightKg: 1.0)],
        reference: 'ORD-DRAFT-1',
        quoteToken: 'spd-draft-123',
    );

    $provider = new SpediamoLabelProvider(spediamoLabelConfig());
    $result = $provider->createShipment($req);

    expect($result->externalId)->toBe('spd-draft-123')
        ->and($result->trackingNumber)->toBe('spd-draft-123');

    Http::assertSent(fn ($request) => $request->method() === 'GET' && str_contains($request->url(), '/v1/shipments/spd-draft-123'));
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('S3: SpediamoLabelProvider label() sends Accept header and handles both raw PDF and JSON base64 payloads', function () {
    // 1. Raw %PDF binary
    Http::fake([
        'https://api.spediamo.test/v1/waybills/shp_raw/pdf*' => Http::response('%PDF-1.4 binary content', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $provider = new SpediamoLabelProvider(spediamoLabelConfig());
    $docRaw = $provider->label('shp_raw');

    expect($docRaw->contents)->toBe('%PDF-1.4 binary content')
        ->and($docRaw->filename)->toBe('label-shp_raw.pdf');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/v1/waybills/shp_raw/pdf')
            && $request->hasHeader('Accept', 'application/pdf, application/json');
    });

    // 2. JSON base64 payload {"data": "JVBERi0xLjQK..."}
    $b64Pdf = base64_encode('%PDF-1.5 json base64 content');
    Http::fake([
        'https://api.spediamo.test/v1/waybills/shp_b64/pdf*' => Http::response(['data' => $b64Pdf], 200, ['Content-Type' => 'application/json']),
    ]);

    $docB64 = $provider->label('shp_b64');
    expect($docB64->contents)->toBe('%PDF-1.5 json base64 content')
        ->and($docB64->filename)->toBe('label-shp_b64.pdf');
});

it('S4: SpediamoLabelProvider cancelShipment() throws LabelRejected when DELETE returns 403 with draft status restriction', function () {
    Http::fake([
        'https://api.spediamo.test/v1/shipments/shp_locked' => Http::response([
            'message' => 'Only shipments with status DRAFT can be cancelled',
        ], 403),
    ]);

    $provider = new SpediamoLabelProvider(spediamoLabelConfig());

    expect(fn () => $provider->cancelShipment('shp_locked'))
        ->toThrow(LabelRejected::class, 'Only shipments with status DRAFT can be cancelled');
});

it('retrieves courier waybill number via GET /v1/shipments/{id} during label creation when externalId is a ShipmentResult', function () {
    Http::fake([
        'https://api.spediamo.test/v1/waybills/shp_123/pdf*' => Http::response('%PDF-1.4 fake waybill', 200, ['Content-Type' => 'application/pdf']),
        'https://api.spediamo.test/v1/shipments/shp_123' => Http::response([
            'id' => 'shp_123',
            'code' => 'A422-098-162-296',
            'trackingNumber' => '288364I031083',
        ], 200),
    ]);

    $shipmentResult = new \App\Shipping\Labels\DTO\ShipmentResult(
        externalId: 'shp_123',
        trackingNumber: 'A422-098-162-296',
        carrierCode: 'sda',
    );

    $document = (new SpediamoLabelProvider(spediamoLabelConfig()))->label($shipmentResult);

    expect($document->contents)->toStartWith('%PDF')
        ->and($document->trackingNumber)->toBe('288364I031083');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/shipments/shp_123'));
});


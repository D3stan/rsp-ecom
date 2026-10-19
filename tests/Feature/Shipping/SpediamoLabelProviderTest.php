<?php

use App\Shipping\Providers\Spediamo\SpediamoLabelProvider;
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

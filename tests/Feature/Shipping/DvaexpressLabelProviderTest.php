<?php

use App\Shipping\Labels\DTO\Address;
use App\Shipping\Labels\DTO\LabelDocument;
use App\Shipping\Labels\DTO\Parcel;
use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Labels\DTO\ShipmentResult;
use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\Exceptions\LabelRejected;
use App\Shipping\Providers\Dvaexpress\DvaexpressLabelProvider;
use Illuminate\Support\Facades\Http;

function dvaexpressLabelConfig(array $extra = []): array
{
    return array_merge([
        'base_url' => 'https://api.easyparcel.it',
        'api_key' => 'test-label-dva-key',
        'carrier_map' => [
            'sda' => 'SDAM',
            'brt' => 'BRT',
            'ups' => 'UPS',
        ],
        'sender_tax_code' => 'IT12345678901',
    ], $extra);
}

function dvaexpressShipmentRequest(array $overrides = []): ShipmentRequest
{
    return new ShipmentRequest(
        carrierCode: $overrides['carrierCode'] ?? 'sda',
        sender: $overrides['sender'] ?? new Address(
            name: 'Mittente Negozio',
            street: 'Via Roma 10',
            city: 'Milano',
            postalCode: '20100',
            province: 'MI',
            country: 'IT',
            phone: '0212345678',
            email: 'shop@example.test',
        ),
        recipient: $overrides['recipient'] ?? new Address(
            name: 'Destinatario Rossi',
            street: 'Via Napoli 20',
            city: 'Roma',
            postalCode: '00100',
            province: 'RM',
            country: 'IT',
            phone: '0687654321',
            email: 'rossi@example.test',
        ),
        parcels: $overrides['parcels'] ?? [
            new Parcel(weightKg: 2.5, lengthCm: 30.0, widthCm: 20.0, heightCm: 15.0),
        ],
        reference: $overrides['reference'] ?? 'ORD-FAST-101',
        notes: $overrides['notes'] ?? '',
        insuranceAmount: $overrides['insuranceAmount'] ?? null,
        codAmount: $overrides['codAmount'] ?? null,
        quoteToken: $overrides['quoteToken'] ?? null,
    );
}

it('handles order-fast flow mapping domestic and export shipments, vettore, accessories, decodes inline label without 2nd HTTP request, and strips ldv_base64 from raw', function () {
    $pdfContent = '%PDF-1.4 inline order-fast waybill test';
    $b64Pdf = base64_encode($pdfContent);

    Http::fake([
        'https://api.easyparcel.it/order-fast/test-label-dva-key*' => Http::response([
            'result' => 'OK',
            'id_ordine' => 160855,
            'lettera_vettura' => '2875561502819',
            'url_ldv' => 'https://www.easyparcel.it/ldv/160855.pdf',
            'ldv_base64' => $b64Pdf,
            'single_waybills' => [
                [
                    'lettera_vettura' => '2875561502819',
                    'ldv_base64' => $b64Pdf,
                ],
            ],
        ], 200),
    ]);

    $provider = new DvaexpressLabelProvider(dvaexpressLabelConfig());

    // 1. Domestic shipment with insurance and COD
    $reqDomestic = dvaexpressShipmentRequest([
        'carrierCode' => 'sda',
        'insuranceAmount' => 200.0,
        'codAmount' => 50.0,
    ]);

    $result = $provider->createShipment($reqDomestic);

    expect($result)->toBeInstanceOf(ShipmentResult::class)
        ->and($result->externalId)->toBe('160855')
        ->and($result->trackingNumber)->toBe('2875561502819')
        ->and($result->carrierCode)->toBe('sda');

    // ldv_base64 must be stripped from raw payload to save DB storage
    expect($result->raw)->not->toHaveKey('ldv_base64');
    if (isset($result->raw['single_waybills'][0])) {
        expect($result->raw['single_waybills'][0])->not->toHaveKey('ldv_base64');
    }

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($request->url(), '/order-fast/test-label-dva-key')
            && ($data['dettagli']['tipo_spedizione'] ?? null) === 'N'
            && ($data['dettagli']['vettore'] ?? null) === 'SDAM'
            && ($data['dettagli']['accessori']['assicurazione'] ?? null) === 'S'
            && ($data['dettagli']['accessori']['assicurazione_importo'] ?? null) == 200.0
            && ($data['dettagli']['accessori']['contrassegno'] ?? null) === 'S'
            && ($data['dettagli']['accessori']['contrassegno_importo'] ?? null) == 50.0
            && ($data['mittente']['codicefiscale'] ?? null) === 'IT12345678901';
    });

    // label() uses the in-memory decoded PDF without an additional HTTP request
    $doc = $provider->label($result);
    expect($doc)->toBeInstanceOf(LabelDocument::class)
        ->and($doc->contents)->toBe($pdfContent)
        ->and($doc->filename)->toBe('label-160855.pdf')
        ->and($doc->mimeType)->toBe('application/pdf');

    Http::assertSentCount(1);

    // 2. Export shipment mapping
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake([
        'https://api.easyparcel.it/order-fast/test-label-dva-key*' => Http::response([
            'result' => 'OK',
            'id_ordine' => 160856,
            'lettera_vettura' => '1Z99999999',
            'ldv_base64' => $b64Pdf,
        ], 200),
    ]);

    $reqExport = dvaexpressShipmentRequest([
        'carrierCode' => 'ups',
        'recipient' => new Address(
            name: 'Hans Muller',
            street: 'Berliner Str. 1',
            city: 'Berlin',
            postalCode: '10115',
            province: '--',
            country: 'DE',
            phone: '030123456',
            email: 'hans@example.de',
        ),
    ]);

    $resultExport = $provider->createShipment($reqExport);
    expect($resultExport->externalId)->toBe('160856');

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($request->url(), '/order-fast/test-label-dva-key')
            && ($data['dettagli']['tipo_spedizione'] ?? null) === 'E'
            && ($data['dettagli']['vettore'] ?? null) === 'UPS';
    });
});

it('handles two-step quote purchase flow when quoteToken is passed on ShipmentRequest', function () {
    $pdfBytes = '%PDF-1.4 two-step purchase waybill';
    $b64Pdf = base64_encode($pdfBytes);

    Http::fake([
        'https://api.easyparcel.it/order/test-label-dva-key*' => Http::response([
            'result' => 'OK',
            'id_ordine' => '160854',
            'codice_offerta' => '5dcc4a53b00fc',
            'custom' => 'ORD-1001',
            'ordine' => 'EAS-5dcc4a53b00fc',
            'importo' => '6.90',
        ], 200),
        'https://api.easyparcel.it/getwaybill/test-label-dva-key*' => Http::response([
            'result' => 'OK',
            'order_id' => '160854',
            'waybill_number' => ['2875561502819'],
            'waybill_base64' => $b64Pdf,
        ], 200),
    ]);

    $provider = new DvaexpressLabelProvider(dvaexpressLabelConfig());

    $request = dvaexpressShipmentRequest([
        'quoteToken' => '5dcc4a53b00fc',
        'carrierCode' => 'sda',
    ]);

    $result = $provider->createShipment($request);

    expect($result->externalId)->toBe('160854')
        ->and($result->trackingNumber)->toBe('2875561502819')
        ->and($result->carrierCode)->toBe('sda')
        ->and($result->raw)->not->toHaveKey('waybill_base64');

    // Verify order purchase payload
    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($request->url(), '/order/test-label-dva-key')
            && ($data['call'] ?? null) === 'order'
            && ($data['dettagli']['codice_offerta'] ?? null) === '5dcc4a53b00fc'
            && isset($data['mittente']['codicefiscale'])
            && isset($data['ritiro']['dettagli']);
    });

    // Verify getwaybill payload
    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($request->url(), '/getwaybill/test-label-dva-key')
            && ($data['call'] ?? null) === 'getwaybill'
            && ($data['details']['order_id'] ?? null) == 160854
            && ($data['details']['waybill_base64'] ?? null) === 'Y';
    });

    // Label is retrieved from memory without a 3rd HTTP call
    $doc = $provider->label($result);
    expect($doc->contents)->toBe($pdfBytes)
        ->and($doc->filename)->toBe('label-160854.pdf');

    Http::assertSentCount(2);
});

it('calls POST /getwaybill/{APIKEY} and returns LabelDocument on fallback label(string externalId)', function () {
    $pdfBytes = '%PDF-1.4 remote fallback waybill';
    $b64Pdf = base64_encode($pdfBytes);

    Http::fake([
        'https://api.easyparcel.it/getwaybill/test-label-dva-key*' => Http::response([
            'result' => 'OK',
            'order_id' => '160899',
            'waybill_number' => ['2875561502999'],
            'waybill_base64' => $b64Pdf,
        ], 200),
    ]);

    $provider = new DvaexpressLabelProvider(dvaexpressLabelConfig());
    $doc = $provider->label('160899');

    expect($doc)->toBeInstanceOf(LabelDocument::class)
        ->and($doc->contents)->toBe($pdfBytes)
        ->and($doc->filename)->toBe('label-160899.pdf')
        ->and($doc->mimeType)->toBe('application/pdf');

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($request->url(), '/getwaybill/test-label-dva-key')
            && ($data['details']['order_id'] ?? null) == 160899
            && ($data['details']['waybill_base64'] ?? null) === 'Y';
    });
});

it('cancels shipment via POST /richiesta-storno: succeeds on ammesso, throws LabelRejected on stato_non_ammesso/tracking_presente, throws LabelProviderUnavailable on tracking_non_verificabile', function () {
    $provider = new DvaexpressLabelProvider(dvaexpressLabelConfig());

    // 1. Success on esiti[0].ammesso === true
    Http::fake([
        'https://api.easyparcel.it/richiesta-storno/test-label-dva-key*' => Http::response([
            'result' => 'OK',
            'esiti' => [
                [
                    'id_ordine' => 160855,
                    'ammesso' => true,
                    'codice' => 'ammesso',
                    'messaggio' => 'Storno richiesto correttamente',
                ],
            ],
        ], 200),
    ]);

    $provider->cancelShipment('160855'); // does not throw
    expect(true)->toBeTrue();

    // 2. Throws LabelRejected on stato_non_ammesso
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake([
        'https://api.easyparcel.it/richiesta-storno/test-label-dva-key*' => Http::response([
            'result' => 'OK',
            'esiti' => [
                [
                    'id_ordine' => 160855,
                    'ammesso' => false,
                    'codice' => 'stato_non_ammesso',
                    'messaggio' => 'Stato dell\'ordine non valido per lo storno',
                ],
            ],
        ], 200),
    ]);

    expect(fn () => $provider->cancelShipment('160855'))
        ->toThrow(LabelRejected::class, 'Stato dell\'ordine non valido per lo storno');

    // 3. Throws LabelRejected on tracking_presente
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake([
        'https://api.easyparcel.it/richiesta-storno/test-label-dva-key*' => Http::response([
            'result' => 'OK',
            'esiti' => [
                [
                    'id_ordine' => 160855,
                    'ammesso' => false,
                    'codice' => 'tracking_presente',
                    'messaggio' => 'Spedizione gia affidata al vettore',
                ],
            ],
        ], 200),
    ]);

    expect(fn () => $provider->cancelShipment('160855'))
        ->toThrow(LabelRejected::class, 'Spedizione gia affidata al vettore');

    // 4. Throws LabelProviderUnavailable on tracking_non_verificabile
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake([
        'https://api.easyparcel.it/richiesta-storno/test-label-dva-key*' => Http::response([
            'result' => 'OK',
            'esiti' => [
                [
                    'id_ordine' => 160855,
                    'ammesso' => false,
                    'codice' => 'tracking_non_verificabile',
                    'messaggio' => 'Verifica tracking temporaneamente non disponibile',
                ],
            ],
        ], 200),
    ]);

    expect(fn () => $provider->cancelShipment('160855'))
        ->toThrow(LabelProviderUnavailable::class, 'Verifica tracking temporaneamente non disponibile');
});

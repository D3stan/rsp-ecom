<?php

/*
|--------------------------------------------------------------------------
| Contract every label adapter must satisfy
|--------------------------------------------------------------------------
|
| To certify a new label adapter add ONE row to the 'label providers' dataset:
|   'name' => [fn () => new ExampleLabelProvider([...]), $knownCarrierCode]
| Use Http::fake() inside the factory for HTTP adapters: no real network is ever allowed.
*/

use App\Shipping\Labels\Contracts\CancelsShipments;
use App\Shipping\Labels\Contracts\LabelProvider;
use App\Shipping\Labels\Drivers\FakeLabelProvider;
use App\Shipping\Labels\DTO\Address;
use App\Shipping\Labels\DTO\LabelDocument;
use App\Shipping\Labels\DTO\Parcel;
use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Labels\DTO\ShipmentResult;
use App\Shipping\Labels\Exceptions\LabelProviderUnavailable;
use App\Shipping\Labels\Exceptions\LabelRejected;
use App\Shipping\Providers\Dvaexpress\DvaexpressLabelProvider;
use App\Shipping\Providers\Spediamo\SpediamoLabelProvider;
use Illuminate\Support\Facades\Http;

function contractShipmentRequest(string $carrier): ShipmentRequest
{
    return new ShipmentRequest(
        carrierCode: $carrier,
        sender: new Address(name: 'Shop', street: 'Via Roma 1', city: 'Milano', postalCode: '20100', province: 'MI', country: 'IT', phone: '02', email: 'shop@example.test'),
        recipient: new Address(name: 'Mario Rossi', street: 'Via Verdi 2', city: 'Roma', postalCode: '00100', province: 'RM', country: 'IT', phone: '06', email: 'mario@example.test'),
        parcels: [new Parcel(weightKg: 1.0)],
        reference: 'ORD-1',
    );
}

dataset('label providers', function () {
    return [
        'fake' => [fn () => new FakeLabelProvider, 'acme'],
        'spediamo' => [function () {
            Http::fake([
                '*/v1/shipments*' => Http::response(['id' => 'shp_1', 'trackingNumber' => 'SP-1', 'status' => 'BOOKED'], 201),
                '*/v1/waybills/*' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']),
            ]);

            return new SpediamoLabelProvider(['base_url' => 'https://spediamo.contract.test', 'token' => 'contract-token', 'carrier_map' => []]);
        }, 'acme'],
        'dvaexpress' => [function () {
            Http::fake([
                '*/order-fast/*' => Http::response([
                    'result' => 'OK',
                    'id_ordine' => '160855',
                    'lettera_vettura' => '2875561502819',
                    'ldv_base64' => base64_encode('%PDF-1.4 fake dva waybill'),
                ], 200),
                '*/getwaybill/*' => Http::response([
                    'result' => 'OK',
                    'order_id' => '160855',
                    'waybill_number' => ['2875561502819'],
                    'waybill_base64' => base64_encode('%PDF-1.4 fake dva waybill'),
                ], 200),
                '*/richiesta-storno/*' => Http::response([
                    'result' => 'OK',
                    'esiti' => [['ammesso' => true, 'codice' => 'ammesso']],
                ], 200),
            ]);

            return new DvaexpressLabelProvider([
                'base_url' => 'https://api.easyparcel.it',
                'api_key' => 'contract-dva-key',
                'carrier_map' => [],
            ]);
        }, 'acme'],
    ];
});

it('has a stable name and a boolean supports()', function (Closure $make, string $carrier) {
    $provider = $make();

    expect($provider)->toBeInstanceOf(LabelProvider::class)
        ->and($provider->name())->toBeString()->not->toBeEmpty()
        ->and($provider->supports($carrier))->toBeBool()
        ->and($provider->supports('definitely-not-a-carrier'))->toBeBool();
})->with('label providers');

it('creates a shipment that carries an external id and a tracking number, then a PDF label', function (Closure $make, string $carrier) {
    $provider = $make();

    $result = $provider->createShipment(contractShipmentRequest($carrier));

    expect($result)->toBeInstanceOf(ShipmentResult::class)
        ->and($result->externalId)->toBeString()->not->toBeEmpty()
        ->and($result->trackingNumber)->toBeString()->not->toBeEmpty()
        ->and($result->carrierCode)->toBe($carrier);

    $document = $provider->label($result);

    expect($document)->toBeInstanceOf(LabelDocument::class)
        ->and($document->contents)->toStartWith('%PDF')
        ->and($document->mimeType)->toBe('application/pdf')
        ->and($document->filename)->toBeString()->not->toBeEmpty();
})->with('label providers');

it('cancels a shipment when it advertises the capability', function (Closure $make, string $carrier) {
    $provider = $make();

    if (! $provider instanceof CancelsShipments) {
        expect(true)->toBeTrue();

        return;
    }

    Http::fake([
        '*/richiesta-storno*' => Http::response([
            'result' => 'OK',
            'esiti' => [['ammesso' => true, 'codice' => 'ammesso']],
        ], 200),
        '*' => Http::response('', 200),
    ]);
    $provider->cancelShipment('shp_1');
    expect(true)->toBeTrue(); // no exception
})->with('label providers');

it('throws LabelRejected when carrier rejects shipment creation', function (Closure $make, string $carrier) {
    $provider = $make();

    if ($provider instanceof FakeLabelProvider) {
        $req = new ShipmentRequest(
            carrierCode: $carrier,
            sender: new Address(name: 'Shop', street: 'Via Roma 1', city: 'Milano', postalCode: '20100', province: 'MI', country: 'IT', phone: '02', email: 'shop@example.test'),
            recipient: new Address(name: 'Mario Rossi', street: 'Via Verdi 2', city: 'Roma', postalCode: '00100', province: 'RM', country: 'IT', phone: '06', email: 'mario@example.test'),
            parcels: [new Parcel(weightKg: 150.0)],
            reference: 'REJ-1',
        );
        expect(fn () => $provider->createShipment($req))->toThrow(LabelRejected::class);

        return;
    }

    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake([
        '*' => Http::response([
            'result' => 'KO',
            'errorcode' => 105,
            'errormessage' => 'Invalid destination',
            'message' => 'Invalid destination',
        ], 400),
    ]);

    expect(fn () => $provider->createShipment(contractShipmentRequest($carrier)))->toThrow(LabelRejected::class);
})->with('label providers');

it('throws LabelProviderUnavailable when provider is down or unreachable', function (Closure $make, string $carrier) {
    $provider = $make();

    if ($provider instanceof FakeLabelProvider) {
        $req = new ShipmentRequest(
            carrierCode: $carrier,
            sender: new Address(name: 'Shop', street: 'Via Roma 1', city: 'Milano', postalCode: '20100', province: 'MI', country: 'IT', phone: '02', email: 'shop@example.test'),
            recipient: new Address(name: 'Mario Rossi', street: 'Via Verdi 2', city: 'Roma', postalCode: '00100', province: 'RM', country: 'IT', phone: '06', email: 'mario@example.test'),
            parcels: [new Parcel(weightKg: 0.005)],
            reference: 'UNAVAIL-1',
        );
        expect(fn () => $provider->createShipment($req))->toThrow(LabelProviderUnavailable::class);

        return;
    }

    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake([
        '*' => Http::response('Server Error', 503),
    ]);

    expect(fn () => $provider->createShipment(contractShipmentRequest($carrier)))->toThrow(LabelProviderUnavailable::class);
})->with('label providers');

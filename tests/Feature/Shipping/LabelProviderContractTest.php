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
use App\Shipping\Providers\Spediamo\SpediamoLabelProvider;
use App\Shipping\Labels\DTO\Address;
use App\Shipping\Labels\DTO\LabelDocument;
use App\Shipping\Labels\DTO\Parcel;
use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Labels\DTO\ShipmentResult;
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

            return new SpediamoLabelProvider(['base_url' => 'https://spediamo.contract.test', 'token' => 'contract-token']);
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

    Http::fake(['*' => Http::response('', 200)]);
    $provider->cancelShipment('shp_1');
    expect(true)->toBeTrue(); // no exception
})->with('label providers');

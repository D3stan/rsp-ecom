<?php

use App\Shipping\Labels\DTO\Address;
use App\Shipping\Labels\DTO\Parcel;
use App\Shipping\Labels\DTO\ShipmentRequest;
use App\Shipping\Labels\Exceptions\LabelRejected;
use App\Shipping\Providers\Dvaexpress\DvaexpressClient;
use App\Shipping\Providers\Dvaexpress\DvaexpressLabelProvider;
use App\Shipping\Providers\Dvaexpress\DvaexpressRateProvider;
use App\Shipping\Support\ShippingAlerts;
use Illuminate\Support\Facades\Http;

/*
 * A DVA Express API key in test mode ("live": "false" in apikeyinfo) accepts orders but only simulates them: placeholder
 * waybill 123456789000, a blank label, nothing charged, nothing picked up. Production must refuse to buy with it and
 * tell the admin; other environments may buy test labels.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    $this->config = ['base_url' => 'https://api.easyparcel.it', 'api_key' => 'dva-key', 'carrier_map' => ['sda' => 'SDAM']];
});

function fakeDvaAccount(string $live, array $extra = []): void
{
    Http::fake([
        'https://api.easyparcel.it/apikeyinfo/dva-key*' => Http::response(['response' => ['dettagli' => ['live' => $live, 'credito_prepagato' => '25.11', 'apikey' => 'dva-key']]], 200),
        ...$extra,
    ]);
}

function dvaFastOrderResponse(): array
{
    return [
        'https://api.easyparcel.it/order-fast/dva-key*' => Http::response([
            'result' => 'OK',
            'id_ordine' => 1052,
            'lettera_vettura' => '123456789000',
            'ldv_base64' => base64_encode('%PDF-1.4 test label'),
        ], 200),
    ];
}

function dvaShipmentRequest(): ShipmentRequest
{
    $address = fn (string $city, string $cap) => new Address(
        name: 'Name', street: 'Via Roma 1', city: $city, postalCode: $cap, province: 'MI', country: 'IT', phone: '021234567', email: 'a@example.test',
    );

    return new ShipmentRequest(
        carrierCode: 'sda',
        sender: $address('Milano', '20100'),
        recipient: $address('Roma', '00100'),
        parcels: [new Parcel(weightKg: 1.0, lengthCm: 20, widthCm: 20, heightCm: 10)],
        reference: 'ORD-1',
    );
}

it('refuses to buy a label in production while the account is in test mode, and raises the admin alert', function () {
    app()->detectEnvironment(fn () => 'production');
    fakeDvaAccount('false', dvaFastOrderResponse());

    expect(fn () => (new DvaexpressLabelProvider($this->config))->createShipment(dvaShipmentRequest()))
        ->toThrow(LabelRejected::class, 'TEST mode');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/order'));
    expect(app(ShippingAlerts::class)->all())->sequence(
        fn ($alert) => $alert->kind->toBe(ShippingAlerts::TEST_MODE)->message->toBe(DvaexpressClient::TEST_MODE_MESSAGE),
    );
});

it('still buys test labels outside production', function () {
    fakeDvaAccount('false', dvaFastOrderResponse());

    $result = (new DvaexpressLabelProvider($this->config))->createShipment(dvaShipmentRequest());

    expect($result->trackingNumber)->toBe('123456789000');
});

it('buys in production once the account is live, and clears the test-mode alert', function () {
    app()->detectEnvironment(fn () => 'production');
    app(ShippingAlerts::class)->raise('dvaexpress', ShippingAlerts::TEST_MODE, DvaexpressClient::TEST_MODE_MESSAGE);
    fakeDvaAccount('true', dvaFastOrderResponse());

    (new DvaexpressLabelProvider($this->config))->createShipment(dvaShipmentRequest());

    Http::assertSent(fn ($request) => str_contains($request->url(), '/order-fast/'));
    expect(app(ShippingAlerts::class)->all())->toBe([]);
});

it('cancels a test-mode shipment locally only: there is no real order at DVA', function () {
    fakeDvaAccount('false');

    (new DvaexpressLabelProvider($this->config))->cancelShipment('1052');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/richiesta-storno/'));
});

it('reports the prepaid credit and the test mode as the balance', function () {
    fakeDvaAccount('false');

    $balance = (new DvaexpressRateProvider($this->config))->balance();

    expect($balance->balance)->toBe(25.11)
        ->and($balance->live)->toBeFalse()
        ->and($balance->raw)->not->toHaveKey('apikey');
});

<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('runs shipping:test-rates command with fake driver successfully', function () {
    $this->artisan('shipping:test-rates', ['--driver' => 'fake'])
        ->expectsOutputToContain('Shipping Rate API Local Test Harness')
        ->expectsOutputToContain('Returned Quotes')
        ->expectsOutputToContain('fake')
        ->assertSuccessful();
});

it('runs shipping:test-rates with order option', function () {
    $order = Order::factory()->create([
        'order_number' => 'ORD-TEST-HARNESS',
    ]);
    $order->shippingAddress->update([
        'postal_code' => '48105',
        'city' => 'Cervia',
        'state' => 'RA',
        'country' => 'IT',
    ]);

    $this->artisan('shipping:test-rates', [
        '--driver' => 'fake',
        '--order' => 'ORD-TEST-HARNESS',
    ])
        ->expectsOutputToContain('Loaded order: #'.$order->id)
        ->assertSuccessful();
});

it('validates sender and recipient address presence for spediamo and dvaexpress', function () {
    // When sender is empty, both providers reject with clear actionable messages
    $this->artisan('shipping:test-rates', ['--driver' => 'spediamo'])
        ->expectsOutputToContain('Sender address requires at least postal code and city')
        ->assertFailed();

    $this->artisan('shipping:test-rates', ['--driver' => 'dvaexpress'])
        ->expectsOutputToContain('Sender address requires at least postal code and city')
        ->assertFailed();
});

it('tests mock HTTP request payloads for spediamo and dvaexpress with --raw', function () {
    Http::fake([
        'https://api.spediamo.it/v1/shipments*' => Http::response(
            json_decode(file_get_contents(base_path('tests/Fixtures/Shipping/spediamo/shipment_draft.json')), true),
            200,
        ),
        'https://api.easyparcel.it/quotation/*' => Http::response(
            json_decode(file_get_contents(base_path('tests/Fixtures/Shipping/dvaexpress/quotation.json')), true),
            200,
        ),
        'https://api.easyparcel.it/balance/*' => Http::response(
            json_decode(file_get_contents(base_path('tests/Fixtures/Shipping/dvaexpress/balance.json')), true),
            200,
        ),
    ]);

    $this->artisan('shipping:test-rates', [
        '--driver' => 'spediamo',
        '--token' => 'mock-token',
        '--sender-cap' => '20100',
        '--sender-city' => 'Milano',
        '--sender-prov' => 'MI',
        '--raw' => true,
    ])
        ->expectsOutputToContain('>>>> POST https://api.spediamo.it/v1/shipments')
        ->expectsOutputToContain('"type": "packages"')
        ->assertSuccessful();

    $this->artisan('shipping:test-rates', [
        '--driver' => 'dvaexpress',
        '--api-key' => 'mock-key',
        '--sender-cap' => '20100',
        '--sender-city' => 'Milano',
        '--sender-prov' => 'MI',
        '--raw' => true,
    ])
        ->expectsOutputToContain('>>>> POST https://api.easyparcel.it/quotation/mock-key')
        ->assertSuccessful();
});

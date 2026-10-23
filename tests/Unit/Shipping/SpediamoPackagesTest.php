<?php

use App\Shipping\Labels\DTO\Parcel;
use App\Shipping\Providers\Spediamo\SpediamoPackages;
use App\Shipping\Rates\DTO\RateParcel;

// Format confirmed live (tests/Fixtures/spediamo/probes): quantity:weight:height:length:width, parcels joined by ",".

it('encodes a parcel as quantity:weight:height:length:width', function () {
    expect(SpediamoPackages::fields([new Parcel(weightKg: 1.0, lengthCm: 35, widthCm: 25, heightCm: 8)]))
        ->toBe(['packages' => '1:1:8:35:25']);
});

it('keeps weight decimals, rounds sizes up to whole cm and floors quantity at one', function () {
    expect(SpediamoPackages::fields([new RateParcel(weightKg: 2.504, lengthCm: 30.5, widthCm: 25.0, heightCm: 20, quantity: 0)]))
        ->toBe(['packages' => '1:2.5:20:31:25'])
        ->and(SpediamoPackages::fields([new RateParcel(weightKg: 1, lengthCm: 10, widthCm: 10, heightCm: 10, quantity: 3)]))
        ->toBe(['packages' => '3:1:10:10:10']);
});

it('assumes 10 cm for an unknown size because the carrier rejects 0', function () {
    expect(SpediamoPackages::fields([new RateParcel(weightKg: 1)]))->toBe(['packages' => '1:1:10:10:10']);
});

it('joins several parcels with commas', function () {
    expect(SpediamoPackages::fields([
        new Parcel(weightKg: 1, lengthCm: 35, widthCm: 25, heightCm: 8),
        new Parcel(weightKg: 0.5, lengthCm: 15, widthCm: 10, heightCm: 5),
    ]))->toBe(['packages' => '1:1:8:35:25,1:0.5:5:15:10']);
});

it('falls back to the default parcel, then to a 1 kg 10x10x10 parcel', function () {
    expect(SpediamoPackages::fields([], ['weight' => 3, 'length' => 20, 'width' => 15, 'height' => 5]))
        ->toBe(['packages' => '1:3:5:20:15'])
        ->and(SpediamoPackages::fields([]))->toBe(['packages' => '1:1:10:10:10']);
});

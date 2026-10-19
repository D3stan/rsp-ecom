<?php

use App\Shipping\Support\CarrierCodeMap;

it('translates both ways, case-insensitively', function () {
    $map = new CarrierCodeMap(['sda' => 'SDA', 'Brt' => ' Bartolini ']);

    expect($map->toProvider('sda'))->toBe('SDA')
        ->and($map->toProvider(' SDA '))->toBe('SDA')
        ->and($map->toProvider('BRT'))->toBe('Bartolini')
        ->and($map->fromProvider('sda'))->toBe('sda')
        ->and($map->fromProvider('BARTOLINI'))->toBe('Brt')
        ->and($map->isEmpty())->toBeFalse();
});

it('answers null for anything unknown, never a guess', function () {
    $map = new CarrierCodeMap(['sda' => 'SDA']);

    expect($map->toProvider('gls'))->toBeNull()
        ->and($map->fromProvider('GLS'))->toBeNull()
        ->and($map->toProvider(null))->toBeNull()
        ->and($map->fromProvider(''))->toBeNull();
});

it('is empty without usable entries and builds from driver config', function () {
    expect((new CarrierCodeMap)->isEmpty())->toBeTrue()
        ->and((new CarrierCodeMap(['' => 'X', 'a' => '', 'b' => ['no']]))->isEmpty())->toBeTrue()
        ->and(CarrierCodeMap::fromDriverConfig([])->isEmpty())->toBeTrue()
        ->and(CarrierCodeMap::fromDriverConfig(['carrier_map' => ['sda' => 'SDA']])->toProvider('sda'))->toBe('SDA');
});

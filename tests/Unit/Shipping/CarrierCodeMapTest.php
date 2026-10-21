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

it('resolves reverse aliases and honors preferred codes', function () {
    $map = new CarrierCodeMap([
        'sda' => 'SDA',
        'poste' => 'SDA',
        'poste_italiane' => 'SDA',
        'brt' => 'BRT',
        'bartolini' => 'BRT',
    ]);

    // Without preferredCodes, returns default reverse (first defined)
    expect($map->fromProvider('SDA'))->toBe('sda')
        ->and($map->fromProvider('BRT'))->toBe('brt');

    // With preferredCodes matching an alias
    expect($map->fromProvider('SDA', ['poste_italiane']))->toBe('poste_italiane')
        ->and($map->fromProvider('SDA', ['POSTE']))->toBe('poste')
        ->and($map->fromProvider('SDA', ['unknown', 'poste_italiane']))->toBe('poste_italiane')
        ->and($map->fromProvider('BRT', ['bartolini']))->toBe('bartolini');

    // If preferredCodes does not match any alias, fallback to default reverse
    expect($map->fromProvider('SDA', ['dhl', 'ups']))->toBe('sda')
        ->and($map->fromProvider('UNKNOWN', ['sda']))->toBeNull();
});

it('compares whether two codes represent the same courier', function () {
    $map = new CarrierCodeMap([
        'sda' => 'SDA',
        'poste' => 'SDA',
        'poste_italiane' => 'SDA',
        'brt' => 'BRT',
        'gls' => 'GLS',
    ]);

    // Same string (case-insensitive / trimmed)
    expect($map->areSameCourier('sda', 'sda'))->toBeTrue()
        ->and($map->areSameCourier('sda', 'SDA'))->toBeTrue()
        ->and($map->areSameCourier('  brt ', 'BRT'))->toBeTrue();

    // Aliases mapping to the same provider courier
    expect($map->areSameCourier('sda', 'poste'))->toBeTrue()
        ->and($map->areSameCourier('poste', 'poste_italiane'))->toBeTrue()
        ->and($map->areSameCourier('sda', 'poste_italiane'))->toBeTrue();

    // Different couriers
    expect($map->areSameCourier('sda', 'brt'))->toBeFalse()
        ->and($map->areSameCourier('brt', 'gls'))->toBeFalse();

    // Null and empty handling
    expect($map->areSameCourier(null, 'sda'))->toBeFalse()
        ->and($map->areSameCourier('sda', null))->toBeFalse()
        ->and($map->areSameCourier(null, null))->toBeFalse()
        ->and($map->areSameCourier('', 'sda'))->toBeFalse()
        ->and($map->areSameCourier('   ', 'sda'))->toBeFalse();

    // Unmapped codes
    expect($map->areSameCourier('unmapped1', 'unmapped2'))->toBeFalse();
});

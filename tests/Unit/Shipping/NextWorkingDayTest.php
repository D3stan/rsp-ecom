<?php

use App\Shipping\Support\NextWorkingDay;
use Carbon\CarbonImmutable;

it('skips weekends and Italian national holidays', function (string $from, string $expected) {
    expect(NextWorkingDay::after(CarbonImmutable::parse($from))->toDateString())->toBe($expected);
})->with([
    'friday to monday' => ['2026-10-23', '2026-10-26'],
    'all saints on a sunday' => ['2026-10-30', '2026-11-02'],
    'christmas and boxing day' => ['2026-12-24', '2026-12-28'],
    'easter monday 2027' => ['2027-03-26', '2027-03-30'],
    'easter monday 2026' => ['2026-04-03', '2026-04-07'],
    'liberation day on a saturday' => ['2026-04-24', '2026-04-27'],
]);

it('knows which days are working days', function () {
    expect(NextWorkingDay::isWorkingDay(CarbonImmutable::parse('2026-08-15')))->toBeFalse()
        ->and(NextWorkingDay::isWorkingDay(CarbonImmutable::parse('2026-10-26')))->toBeTrue();
});

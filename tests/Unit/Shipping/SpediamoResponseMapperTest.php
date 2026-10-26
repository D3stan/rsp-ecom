<?php

use App\Shipping\Providers\Spediamo\SpediamoResponseMapper;
use App\Shipping\Providers\Spediamo\SpediamoStatusMap;
use App\Shipping\Tracking\Enums\TrackingStatus as S;

function spdScan(string $date, array $time, string $status = 'CURRENT', string $description = 'Scan', string $long = '', string $site = 'Hub'): array
{
    return [
        'date' => $date,
        'time' => ['hour' => $time[0], 'minute' => $time[1], 'second' => $time[2] ?? 0, 'nano' => 0],
        'description' => $description,
        'longDescription' => $long,
        'site' => $site,
        'status' => $status,
    ];
}

function spdMap(array $data, array $overrides = [], ?Closure $onUnmapped = null, string $tz = 'Europe/Rome')
{
    return (new SpediamoResponseMapper(new SpediamoStatusMap($overrides), $tz, $onUnmapped))->map($data);
}

it('maps the documented fields of a full answer', function () {
    $result = spdMap([
        'code' => 'ABC123', 'trackingNumber' => 'ABC123', 'courier' => 'SDA', 'status' => 'CURRENT',
        'expectedDeliveryDate' => '2026-07-03', 'deliveryDate' => null,
        'tracking' => [
            spdScan('2026-07-01', [9, 5, 7], 'OPEN', 'Created', '', 'Milano'),
            spdScan('2026-07-02', [14, 30], 'CURRENT', 'In transit', 'Parcel in transit to the destination hub', 'Bologna'),
        ],
    ]);

    expect($result->status)->toBe(S::InTransit)
        ->and($result->events)->toHaveCount(2)
        ->and($result->estimatedDelivery->format('Y-m-d'))->toBe('2026-07-02') // 2026-07-03 00:00 Rome, in UTC
        ->and($result->events[0]->status)->toBe(S::InfoReceived)
        ->and($result->events[0]->occurredAt->format('Y-m-d H:i:s'))->toBe('2026-07-01 07:05:07') // Rome summer time is UTC+2
        ->and($result->events[0]->location)->toBe('Milano')
        ->and($result->events[0]->externalId)->toBeNull()
        ->and($result->events[1]->description)->toBe('In transit - Parcel in transit to the destination hub');
});

it('does not repeat a long description that equals the short one, and falls back to it when the short one is empty', function () {
    $result = spdMap(['status' => 'CURRENT', 'tracking' => [
        spdScan('2026-07-01', [9, 0], 'CURRENT', 'Departed', ' departed '),
        spdScan('2026-07-01', [10, 0], 'CURRENT', '', 'Only the long text', ''),
    ]]);

    expect($result->events[0]->description)->toBe('Departed')
        ->and($result->events[1]->description)->toBe('Only the long text')
        ->and($result->events[1]->location)->toBeNull();
});

it('converts Europe/Rome wall-clock times to UTC across the DST edges', function (string $date, array $time, string $utc) {
    $result = spdMap(['status' => 'CURRENT', 'tracking' => [spdScan($date, $time)]]);

    expect($result->events[0]->occurredAt->format('Y-m-d H:i:s'))->toBe($utc)
        ->and($result->events[0]->occurredAt->timezoneName)->toBe('UTC');
})->with([
    'winter' => ['2026-01-15', [12, 0], '2026-01-15 11:00:00'],
    'summer' => ['2026-07-15', [12, 0], '2026-07-15 10:00:00'],
    'just before the spring jump' => ['2026-03-29', [1, 59, 59], '2026-03-29 00:59:59'],
    'time skipped by the spring jump moves forward' => ['2026-03-29', [2, 30], '2026-03-29 01:30:00'],
    'just after the spring jump' => ['2026-03-29', [3, 0], '2026-03-29 01:00:00'],
    'ambiguous autumn hour' => ['2026-10-25', [2, 30], '2026-10-25 01:30:00'],
    'after the autumn jump' => ['2026-10-25', [3, 0], '2026-10-25 02:00:00'],
]);

it('honours the configured timezone and ignores an event without a usable date', function () {
    $result = spdMap(['status' => 'CURRENT', 'tracking' => [
        spdScan('2026-07-15', [12, 0]),
        ['description' => 'no date', 'status' => 'CURRENT'],
        ['date' => 'garbage', 'status' => 'CURRENT'],
        'not an array',
    ]], tz: 'UTC');

    expect($result->events)->toHaveCount(1)->and($result->events[0]->occurredAt->format('H:i'))->toBe('12:00');
});

it('orders events oldest first whatever order the API uses', function () {
    $result = spdMap(['status' => 'CURRENT', 'tracking' => [
        spdScan('2026-07-02', [9, 0], 'CURRENT', 'second'),
        spdScan('2026-07-01', [9, 0], 'OPEN', 'first'),
    ]]);

    expect(array_map(fn ($e) => $e->description, $result->events))->toBe(['first', 'second']);
});

it('maps the default status table case-insensitively', function (string $api, S $expected) {
    expect((new SpediamoStatusMap)->map($api))->toBe($expected)
        ->and((new SpediamoStatusMap)->map('  '.strtolower($api).' '))->toBe($expected);
})->with([
    ['DRAFT', S::InfoReceived], ['OPEN', S::InfoReceived], ['BOOKED', S::InfoReceived], ['READY', S::InfoReceived],
    ['CURRENT', S::InTransit], ['DELIVERED', S::Delivered],
    ['ON_HOLD', S::Exception], ['FAILED', S::Exception], ['UNDERPAID', S::Exception],
]);

it('reports unmapped statuses to the callback and treats them as unknown, but not blanks', function () {
    $seen = [];
    $result = spdMap(['status' => 'MYSTERY', 'tracking' => [spdScan('2026-07-01', [9, 0], 'weird'), spdScan('2026-07-01', [10, 0], '')]], onUnmapped: function (string $v) use (&$seen) {
        $seen[] = $v;
    });

    expect($result->status)->toBe(S::Unknown)->and($seen)->toEqualCanonicalizing(['MYSTERY', 'weird']);
});

it('lets config override and extend the status map', function () {
    $result = spdMap(['status' => 'LOST', 'tracking' => [spdScan('2026-07-01', [9, 0], 'SORTING')]], ['lost' => 'returned', 'SORTING' => 'in_transit', 'BAD' => 'nonsense']);

    expect($result->status)->toBe(S::Returned)->and($result->events[0]->status)->toBe(S::InTransit)
        ->and((new SpediamoStatusMap(['bad' => 'nonsense']))->isMapped('BAD'))->toBeFalse();
});

it('is Delivered when the top-level status, the latest event or a deliveryDate says so', function (array $data) {
    expect(spdMap($data)->status)->toBe(S::Delivered);
})->with([
    'top-level status' => [['status' => 'DELIVERED', 'tracking' => []]],
    'latest event' => [['status' => 'CURRENT', 'tracking' => [spdScan('2026-07-01', [9, 0], 'CURRENT'), spdScan('2026-07-02', [9, 0], 'DELIVERED')]]],
    'deliveryDate only' => [['status' => 'CURRENT', 'deliveryDate' => '2026-07-02', 'tracking' => [spdScan('2026-07-01', [9, 0], 'CURRENT')]]],
]);

it('is not Delivered for an empty deliveryDate or an old delivered scan followed by nothing else delivered', function () {
    expect(spdMap(['status' => 'CURRENT', 'deliveryDate' => '', 'tracking' => []])->status)->toBe(S::InTransit)
        ->and(spdMap(['status' => 'CURRENT', 'deliveryDate' => null, 'tracking' => []])->status)->toBe(S::InTransit);
});

it('otherwise picks the most advanced mapped status, the top level winning ties', function () {
    $override = ['OUT' => 'out_for_delivery'];

    expect(spdMap(['status' => 'OPEN', 'tracking' => [spdScan('2026-07-01', [9, 0], 'CURRENT')]])->status)->toBe(S::InTransit)
        ->and(spdMap(['status' => 'OPEN', 'tracking' => [spdScan('2026-07-01', [9, 0], 'OUT')]], $override)->status)->toBe(S::OutForDelivery)
        ->and(spdMap(['status' => 'ON_HOLD', 'tracking' => [spdScan('2026-07-01', [9, 0], 'CURRENT')]])->status)->toBe(S::Exception)
        ->and(spdMap(['status' => 'CURRENT', 'tracking' => [spdScan('2026-07-01', [9, 0], 'ON_HOLD')]])->status)->toBe(S::InTransit)
        ->and(spdMap(['tracking' => []])->status)->toBe(S::Unknown)
        ->and(spdMap([])->events)->toBe([]);
});

it('handles array dates from Jackson/Spring serialized LocalDates without throwing array to string conversion', function () {
    // 1. Indexed array [2026, 10, 21]
    $res1 = spdMap([
        'status' => 'CURRENT',
        'tracking' => [
            [
                'date' => [2026, 10, 21],
                'time' => ['hour' => 14, 'minute' => 30, 'second' => 0],
                'description' => 'In transit',
                'status' => 'CURRENT',
            ],
        ],
    ]);

    expect($res1->events)->toHaveCount(1)
        ->and($res1->events[0]->occurredAt->format('Y-m-d H:i:s'))->toBe('2026-10-21 12:30:00');

    // 2. Jackson object ['year' => 2026, 'month' => 10, 'day' => 21]
    $res2 = spdMap([
        'status' => 'DELIVERED',
        'deliveryDate' => ['year' => 2026, 'month' => 10, 'day' => 21],
        'tracking' => [
            [
                'date' => ['year' => 2026, 'month' => 10, 'day' => 21],
                'time' => ['hour' => 16, 'minute' => 45, 'second' => 0],
                'description' => 'Delivered',
                'status' => 'DELIVERED',
            ],
        ],
    ]);

    expect($res2->status)->toBe(S::Delivered)
        ->and($res2->events)->toHaveCount(1)
        ->and($res2->events[0]->occurredAt->format('Y-m-d H:i:s'))->toBe('2026-10-21 14:45:00');

    // 3. Jackson object with monthValue and dayOfMonth
    $res3 = spdMap([
        'status' => 'CURRENT',
        'tracking' => [
            [
                'date' => ['year' => 2026, 'monthValue' => 10, 'dayOfMonth' => 21],
                'time' => ['hour' => 10, 'minute' => 0, 'second' => 0],
                'description' => 'Out for delivery',
                'status' => 'CURRENT',
            ],
        ],
    ]);

    expect($res3->events)->toHaveCount(1)
        ->and($res3->events[0]->occurredAt->format('Y-m-d H:i:s'))->toBe('2026-10-21 08:00:00');
});

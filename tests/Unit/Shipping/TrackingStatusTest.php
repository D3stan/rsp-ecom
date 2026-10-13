<?php

use App\Shipping\Tracking\DTO\TrackingEvent;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\Enums\TrackingStatus as S;
use Carbon\CarbonImmutable;

it('knows which statuses end a shipment', function () {
    expect(array_map(fn (S $s) => $s->isTerminal(), S::cases()))
        ->toBe([false, false, false, false, true, false, true]);
});

it('ranks the happy path in order', function () {
    expect(S::Unknown->rank())->toBeLessThan(S::InfoReceived->rank())
        ->and(S::InfoReceived->rank())->toBeLessThan(S::InTransit->rank())
        ->and(S::InTransit->rank())->toBeLessThan(S::OutForDelivery->rank())
        ->and(S::OutForDelivery->rank())->toBeLessThan(S::Delivered->rank());
});

it('moves forward but never backwards', function () {
    expect(S::InTransit->canReplace(null))->toBeTrue()
        ->and(S::InTransit->canReplace(S::InfoReceived))->toBeTrue()
        ->and(S::InTransit->canReplace(S::InTransit))->toBeTrue()
        ->and(S::InfoReceived->canReplace(S::InTransit))->toBeFalse()
        ->and(S::InTransit->canReplace(S::OutForDelivery))->toBeFalse()
        ->and(S::Delivered->canReplace(S::OutForDelivery))->toBeTrue();
});

it('lets Exception and Returned interrupt the journey and Exception be resolved', function () {
    expect(S::Exception->canReplace(S::OutForDelivery))->toBeTrue()
        ->and(S::Returned->canReplace(S::InTransit))->toBeTrue()
        ->and(S::OutForDelivery->canReplace(S::Exception))->toBeTrue()
        ->and(S::InTransit->canReplace(S::Exception))->toBeTrue()
        ->and(S::InfoReceived->canReplace(S::Exception))->toBeFalse();
});

it('never leaves a terminal status and never adopts Unknown', function () {
    foreach ([S::Delivered, S::Returned] as $terminal) {
        foreach (S::cases() as $next) {
            expect($next->canReplace($terminal))->toBeFalse();
        }
    }

    expect(S::Unknown->canReplace(null))->toBeFalse()
        ->and(S::Unknown->canReplace(S::InTransit))->toBeFalse()
        ->and(S::InfoReceived->canReplace(S::Unknown))->toBeTrue();
});

it('parses stored values leniently', function () {
    expect(S::fromValue('in_transit'))->toBe(S::InTransit)
        ->and(S::fromValue(' DELIVERED '))->toBe(S::Delivered)
        ->and(S::fromValue('lost in space'))->toBe(S::Unknown)
        ->and(S::fromValue(null))->toBe(S::Unknown);
});

it('normalises tracking numbers by removing whitespace only', function () {
    expect(TrackingRequest::normalizeNumber("  ab 12\t34 \n"))->toBe('ab1234')
        ->and(TrackingRequest::normalizeNumber(null))->toBe('')
        ->and(TrackingRequest::normalizeNumber('Ab-9'))->toBe('Ab-9');
});

it('hashes the same event identically and different events differently', function () {
    $at = CarbonImmutable::parse('2026-03-01 10:00:00', 'Europe/Rome');
    $make = fn (?string $id = null, string $desc = 'Departed hub', ?CarbonImmutable $when = null) => new TrackingEvent(S::InTransit, $desc, 'Milan', $when ?? $at, $id);

    // time zone of the carrier does not matter, case and spacing of the number do not matter
    $sameInstant = $at->setTimezone('UTC');
    expect($make()->hash('p', 'AB 12'))->toBe($make(when: $sameInstant)->hash('p', 'ab12'))
        ->and($make()->hash('p', 'AB12'))->not->toBe($make(desc: 'Arrived hub')->hash('p', 'AB12'))
        ->and($make()->hash('p', 'AB12'))->not->toBe($make()->hash('other', 'AB12'));

    // with a carrier id the id alone identifies the event (wording or time corrections do not duplicate it)
    expect($make('e-1')->hash('p', 'AB12'))->toBe($make('e-1', 'Corrected wording', $at->addMinute())->hash('p', 'AB12'))
        ->and($make('e-1')->hash('p', 'AB12'))->not->toBe($make('e-2')->hash('p', 'AB12'));
});

<?php

use App\Support\SentryEventFilter;
use Sentry\Event;
use Sentry\EventHint;

function sentryEventFor(?Throwable $exception = null, ?string $message = null): array
{
    $event = Event::createEvent();
    $event->setMessage($message ?? '');

    return [$event, $exception ? EventHint::fromArray(['exception' => $exception]) : null];
}

it('sends the first occurrence of an exception', function () {
    [$event, $hint] = sentryEventFor(new RuntimeException('boom'));

    expect(SentryEventFilter::beforeSend($event, $hint))->toBe($event);
});

it('drops a repeat of the same exception raised at the same place', function () {
    $raise = fn () => new RuntimeException('boom');

    [$first, $firstHint] = sentryEventFor($raise());
    [$second, $secondHint] = sentryEventFor($raise());

    expect(SentryEventFilter::beforeSend($first, $firstHint))->toBe($first)
        ->and(SentryEventFilter::beforeSend($second, $secondHint))->toBeNull();
});

it('sends an exception raised at a different place', function () {
    [$first, $firstHint] = sentryEventFor(new RuntimeException('boom'));
    [$second, $secondHint] = sentryEventFor(new RuntimeException('boom'));

    SentryEventFilter::beforeSend($first, $firstHint);

    expect(SentryEventFilter::beforeSend($second, $secondHint))->toBe($second);
});

it('sends the same exception again once the window has passed', function () {
    $raise = fn () => new RuntimeException('boom');
    [$first, $firstHint] = sentryEventFor($raise());
    [$second, $secondHint] = sentryEventFor($raise());

    SentryEventFilter::beforeSend($first, $firstHint);
    $this->travel(SentryEventFilter::WINDOW_MINUTES + 1)->minutes();

    expect(SentryEventFilter::beforeSend($second, $secondHint))->toBe($second);
});

it('deduplicates messages that carry no exception by their text', function () {
    [$first] = sentryEventFor(message: 'Carrier rates failed');
    [$second] = sentryEventFor(message: 'Carrier rates failed');
    [$other] = sentryEventFor(message: 'Stripe webhook failed');

    expect(SentryEventFilter::beforeSend($first))->toBe($first)
        ->and(SentryEventFilter::beforeSend($second))->toBeNull()
        ->and(SentryEventFilter::beforeSend($other))->toBe($other);
});

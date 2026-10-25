<?php

use App\Services\Ops\TelegramNotifier;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

const TELEGRAM_SEND_URL = 'https://api.telegram.org/botsecret-token/sendMessage';

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.telegram.token' => 'secret-token', 'services.telegram.chat_id' => '4242']);
});

it('sends the message to the configured chat, prefixed with the environment', function () {
    Http::fake([TELEGRAM_SEND_URL => Http::response(['ok' => true])]);

    expect(app(TelegramNotifier::class)->send('Queue stuck'))->toBeTrue();

    Http::assertSent(fn (Request $request) => $request['chat_id'] === '4242'
        && $request['text'] === '['.config('app.env').'] Queue stuck');
});

it('does nothing while no bot is configured', function () {
    config(['services.telegram.token' => null]);
    Http::fake();

    expect(app(TelegramNotifier::class)->send('Queue stuck'))->toBeFalse();

    Http::assertNothingSent();
});

it('returns false and keeps the bot token out of the logs when Telegram is unreachable', function () {
    Http::fake([TELEGRAM_SEND_URL => fn () => throw new ConnectionException('cURL error for '.TELEGRAM_SEND_URL)]);
    Log::spy();

    expect(app(TelegramNotifier::class)->send('Queue stuck'))->toBeFalse();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []) => ! str_contains(json_encode($context), 'secret-token'))->once();
});

it('sends the same key only once inside the window', function () {
    Http::fake([TELEGRAM_SEND_URL => Http::response(['ok' => true])]);
    $notifier = app(TelegramNotifier::class);

    expect($notifier->sendOnce('stock-negative', 'Negative stock'))->toBeTrue()
        ->and($notifier->sendOnce('stock-negative', 'Negative stock'))->toBeFalse()
        ->and($notifier->sendOnce('other-key', 'Something else'))->toBeTrue();

    Http::assertSentCount(2);
});

it('sends the same key again once the window has passed', function () {
    Http::fake([TELEGRAM_SEND_URL => Http::response(['ok' => true])]);
    $notifier = app(TelegramNotifier::class);

    $notifier->sendOnce('stock-negative', 'Negative stock', 15);
    $this->travel(16)->minutes();

    expect($notifier->sendOnce('stock-negative', 'Negative stock', 15))->toBeTrue();
});

describe('exception alerts', function () {
    it('alerts once for a repeating uncaught exception', function () {
        Http::fake([TELEGRAM_SEND_URL => Http::response(['ok' => true])]);
        $failing = fn () => report(new RuntimeException('Stripe exploded'));

        $failing();
        $failing();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'RuntimeException')
            && str_contains($request['text'], 'Stripe exploded'));
    });

    it('alerts again for a different exception', function () {
        Http::fake([TELEGRAM_SEND_URL => Http::response(['ok' => true])]);

        report(new RuntimeException('first'));
        report(new LogicException('second'));

        Http::assertSentCount(2);
    });

    it('does not alert for exceptions Laravel does not report', function () {
        Http::fake();

        report(new AuthenticationException);
        report(new NotFoundHttpException);

        Http::assertNothingSent();
    });

    it('can be switched off', function () {
        config(['ops.telegram.exception_alerts' => false]);
        Http::fake();

        report(new RuntimeException('Stripe exploded'));

        Http::assertNothingSent();
    });
});

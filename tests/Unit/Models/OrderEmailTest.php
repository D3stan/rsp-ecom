<?php

use App\Jobs\SendOrderEmail;
use App\Models\OrderEmail;
use Tests\TestCase;

uses(TestCase::class);

it('derives the shipped fingerprint from the tracking number only', function () {
    expect(OrderEmail::shippedFingerprint('ABC'))->toBe(OrderEmail::shippedFingerprint(' ABC '))
        ->and(OrderEmail::shippedFingerprint('ABC'))->not->toBe(OrderEmail::shippedFingerprint('ABD'))
        ->and(OrderEmail::shippedFingerprint(null))->toBe(OrderEmail::shippedFingerprint(''))
        ->and(OrderEmail::shippedFingerprint(null))->not->toBe(OrderEmail::shippedFingerprint('ABC'));

    // fits the fingerprint column (varchar 64)
    expect(strlen(OrderEmail::shippedFingerprint(str_repeat('x', 100))))->toBeLessThanOrEqual(64);
});

it('adds the carrier to the shipped fingerprint but keeps the old hash without one', function () {
    expect(OrderEmail::shippedFingerprint('ABC', null))->toBe(OrderEmail::shippedFingerprint('ABC'))
        ->and(OrderEmail::shippedFingerprint('ABC', ' '))->toBe(OrderEmail::shippedFingerprint('ABC'))
        ->and(OrderEmail::shippedFingerprint('ABC'))->toBe(substr(sha1('shipped|ABC'), 0, 40))
        ->and(OrderEmail::shippedFingerprint('ABC', 'Acme'))->toBe(OrderEmail::shippedFingerprint(' ABC ', 'acme'))
        ->and(OrderEmail::shippedFingerprint('ABC', 'acme'))->not->toBe(OrderEmail::shippedFingerprint('ABC'))
        ->and(OrderEmail::shippedFingerprint('ABC', 'acme'))->not->toBe(OrderEmail::shippedFingerprint('ABC', 'zeta'));
});

it('tells sent, failed and in-flight rows apart', function () {
    $queued = new OrderEmail(['queued_at' => now()]);
    $sent = new OrderEmail(['queued_at' => now(), 'sent_at' => now()]);
    $failed = new OrderEmail(['queued_at' => now(), 'failed_at' => now()]);

    expect($queued->isSent())->toBeFalse()->and($queued->isFailed())->toBeFalse();
    expect($sent->isSent())->toBeTrue()->and($sent->isFailed())->toBeFalse();
    expect($failed->isSent())->toBeFalse()->and($failed->isFailed())->toBeTrue();
});

it('configures the email job for retries with growing backoff and a timeout below retry_after', function () {
    $job = new SendOrderEmail(1, OrderEmail::TYPE_CONFIRMATION, 'default');

    expect($job->tries)->toBe(5)
        ->and($job->backoff())->toBe([60, 300, 900, 3600])
        ->and($job->timeout)->toBeLessThan(config('queue.connections.database.retry_after'))
        ->and($job->afterCommit)->toBeTrue()
        ->and($job->middleware())->toHaveCount(1);
});

<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Sentry\Event;
use Sentry\EventHint;
use Throwable;

/**
 * Protects the Sentry quota: the same failure is sent once per window, whatever number of times it repeats. It also
 * absorbs the duplicate that the `sentry` log channel produces for an exception the exception handler already sent.
 */
class SentryEventFilter
{
    public const WINDOW_MINUTES = 10;

    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        $exception = $hint?->exception;

        $fingerprint = $exception
            ? $exception::class.'|'.$exception->getFile().'|'.$exception->getLine()
            : (string) $event->getMessage();

        if ($fingerprint === '') {
            return $event;
        }

        try {
            $isFirst = Cache::add('sentry:dedupe:'.sha1($fingerprint), true, now()->addMinutes(self::WINDOW_MINUTES));
        } catch (Throwable) {
            return $event;
        }

        return $isFirst ? $event : null;
    }
}

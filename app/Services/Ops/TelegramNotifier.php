<?php

namespace App\Services\Ops;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pushes operational alerts to a Telegram chat. It never throws: an alert that cannot be delivered must not break
 * the request, command or exception report that raised it.
 */
class TelegramNotifier
{
    private const MAX_LENGTH = 3500;

    public function isConfigured(): bool
    {
        return filled(config('services.telegram.token')) && filled(config('services.telegram.chat_id'));
    }

    public function send(string $text): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $text = sprintf('[%s] %s', config('app.env'), $text);

        try {
            $response = Http::connectTimeout(2)
                ->timeout(3)
                ->post('https://api.telegram.org/bot'.config('services.telegram.token').'/sendMessage', [
                    'chat_id' => config('services.telegram.chat_id'),
                    'text' => Str::limit($text, self::MAX_LENGTH),
                    'disable_web_page_preview' => true,
                ]);

            if ($response->failed()) {
                Log::warning('Telegram alert was rejected', ['status' => $response->status()]);
            }

            return $response->successful();
        } catch (Throwable $e) {
            // Not $e->getMessage(): a connection error message contains the request URL, which holds the bot token.
            Log::warning('Telegram alert could not be sent', ['exception' => $e::class]);

            return false;
        }
    }

    /**
     * Sends at most one message per key within the window, so a repeating failure does not flood the chat.
     */
    public function sendOnce(string $key, string $text, int $windowMinutes = 15): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $isFirst = Cache::add('ops:telegram:'.sha1($key), true, now()->addMinutes($windowMinutes));
        } catch (Throwable) {
            // Cache down: better a duplicate alert than none.
            $isFirst = true;
        }

        return $isFirst && $this->send($text);
    }

    public function notifyException(Throwable $e): void
    {
        if (! config('ops.telegram.exception_alerts') || ! $this->isConfigured()) {
            return;
        }

        $request = request();
        // The route pattern, never the real URL: paths can carry tokens (unsubscribe, verification links).
        $where = app()->runningInConsole()
            ? 'console'
            : $request->method().' /'.ltrim((string) $request->route()?->uri(), '/');

        $this->sendOnce(
            $e::class.'|'.$e->getFile().'|'.$e->getLine(),
            implode("\n", array_filter([
                '🔥 '.class_basename($e).' on '.$where,
                Str::limit($e->getMessage(), 200),
                basename($e->getFile()).':'.$e->getLine(),
                $request->attributes->get('request_id') ? 'ref: '.$request->attributes->get('request_id') : null,
            ])),
            (int) config('ops.telegram.exception_throttle_minutes', 15),
        );
    }
}

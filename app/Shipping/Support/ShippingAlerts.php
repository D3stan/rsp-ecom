<?php

namespace App\Shipping\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Shipping problems the admin must see on every admin page until they are solved, not only in a toast: a carrier
 * account in test mode, the last failed label purchase. Providers raise and clear their own alerts; the admin layout
 * shows them as a banner (shared as `shippingAlerts` by HandleInertiaRequests, admins only).
 *
 * Stored in the shared cache so web, queue and scheduler see the same list. A cache outage loses the banner, never
 * the purchase: every method swallows cache errors.
 */
class ShippingAlerts
{
    /** The carrier account only simulates orders: labels are not real and nothing is picked up. */
    public const TEST_MODE = 'test_mode';

    /** The last label purchase failed; cleared by the next successful purchase with the same provider. */
    public const LABEL_FAILED = 'label_failed';

    private const CACHE_KEY = 'shipping:alerts';

    private const TTL_DAYS = 14;

    public function raise(string $provider, string $kind, string $message, array $context = []): void
    {
        $this->mutate(function (array $alerts) use ($provider, $kind, $message, $context) {
            $key = "{$provider}:{$kind}";
            $unchanged = ($alerts[$key]['message'] ?? null) === $message && ($alerts[$key]['context'] ?? null) === $context;

            // a repeated alert keeps the time it was first seen, so polling the same state rewrites nothing
            $alerts[$key] = [
                'provider' => $provider,
                'kind' => $kind,
                'message' => $message,
                'context' => $context,
                'at' => $unchanged ? $alerts[$key]['at'] : now()->toIso8601String(),
            ];

            return $alerts;
        });
    }

    public function clear(string $provider, string $kind): void
    {
        $this->mutate(function (array $alerts) use ($provider, $kind) {
            unset($alerts["{$provider}:{$kind}"]);

            return $alerts;
        });
    }

    /**
     * @return list<array{provider: string, kind: string, message: string, context: array<string, mixed>, at: string}>
     */
    public function all(): array
    {
        try {
            return array_values((array) Cache::get(self::CACHE_KEY, []));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  callable(array<string, array<string, mixed>>): array<string, array<string, mixed>>  $change
     */
    private function mutate(callable $change): void
    {
        try {
            $current = (array) Cache::get(self::CACHE_KEY, []);
            $next = $change($current);

            if ($next === $current) {
                return;
            }

            $next === []
                ? Cache::forget(self::CACHE_KEY)
                : Cache::put(self::CACHE_KEY, $next, now()->addDays(self::TTL_DAYS));
        } catch (Throwable) {
            // the banner is a convenience; the shipping flow must never fail because of it
        }
    }
}

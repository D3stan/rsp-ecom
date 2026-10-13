<?php

namespace App\Shipping\Tracking\Drivers\Concerns;

use Illuminate\Http\Request;

/**
 * Helper for webhook adapters whose carrier signs the raw body with a shared secret.
 */
trait VerifiesHmacSignature
{
    /**
     * Constant-time check of "HMAC(raw body, secret)" against a request header, which may carry the
     * hex digest alone or prefixed ("sha256=<hex>"). An empty secret never matches (fail closed).
     * Does not look at timestamps: adapters whose carrier sends one should also reject stale requests.
     */
    protected function hmacMatches(Request $request, string $secret, string $header, string $algo = 'sha256'): bool
    {
        if ($secret === '') {
            return false;
        }

        $provided = trim((string) $request->header($header));

        if (str_contains($provided, '=')) {
            $provided = substr($provided, strpos($provided, '=') + 1);
        }

        if ($provided === '') {
            return false;
        }

        return hash_equals(hash_hmac($algo, $request->getContent(), $secret), strtolower($provided));
    }
}

<?php

namespace App\Shipping\Tracking\Drivers\Concerns;

use Illuminate\Http\Request;

trait VerifiesHmacSignature
{
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

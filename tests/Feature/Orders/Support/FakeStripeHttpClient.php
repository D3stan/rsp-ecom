<?php

namespace Tests\Feature\Orders\Support;

use Stripe\HttpClient\ClientInterface;

/**
 * Replaces Stripe's HTTP transport in tests. Any request without a canned response fails loudly,
 * so a test can never reach the real Stripe API.
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var array<int, array{0: string, 1: string, 2: array|null}> method, URL, request parameters */
    public array $requests = [];

    /** @var array<int, array<int, string>> request headers, same order as (e.g. "Idempotency-Key: ...") */
    public array $headers = [];

    /** @var array<string, array> URL fragment => JSON body; a "__status" key sets the HTTP status (default 200) */
    public array $responses = [];

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1')
    {
        $this->requests[] = [$method, $absUrl, $params];
        $this->headers[] = $headers;

        foreach ($this->responses as $fragment => $body) {
            if (str_contains($absUrl, $fragment)) {
                $status = $body['__status'] ?? 200;
                unset($body['__status']);

                return [json_encode($body), $status, []];
            }
        }

        throw new \RuntimeException("Unexpected Stripe request in test: {$method} {$absUrl}");
    }
}

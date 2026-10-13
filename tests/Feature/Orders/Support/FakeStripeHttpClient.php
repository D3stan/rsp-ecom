<?php

namespace Tests\Feature\Orders\Support;

use Stripe\HttpClient\ClientInterface;

/**
 * Replaces Stripe's HTTP transport in tests. Any request without a canned response fails loudly,
 * so a test can never reach the real Stripe API.
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var array<int, array{0: string, 1: string}> */
    public array $requests = [];

    /** @var array<string, array> URL fragment => JSON body */
    public array $responses = [];

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1')
    {
        $this->requests[] = [$method, $absUrl];

        foreach ($this->responses as $fragment => $body) {
            if (str_contains($absUrl, $fragment)) {
                return [json_encode($body), 200, []];
            }
        }

        throw new \RuntimeException("Unexpected Stripe request in test: {$method} {$absUrl}");
    }
}

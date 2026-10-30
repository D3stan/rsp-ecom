<?php

namespace Tests\Feature\Orders\Support;

use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;

/**
 * Replaces Stripe's HTTP transport in tests. Any request without a canned response fails loudly,
 * so a test can never reach the real Stripe API.
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var array<int, array{0: string, 1: string, 2: array|null}> method, URL, request parameters */
    public array $requests = [];

    /** @var array<int, array<int, string>> request headers, same order as the requests (e.g. "Idempotency-Key: ...") */
    public array $headers = [];

    /**
     * URL fragment => JSON body. A fragment may start with the HTTP method ("GET /v1/refunds") to answer only that
     * method. In the body, "__status" sets the HTTP status (default 200) and "__throw" => 'connection' simulates a
     * network failure (Stripe\Exception\ApiConnectionException, as the real transport throws on a timeout).
     *
     * @var array<string, array>
     */
    public array $responses = [];

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = [$method, $absUrl, $params];
        $this->headers[] = $headers;

        foreach ($this->responses as $fragment => $body) {
            if (preg_match('/^(GET|POST|DELETE) (.+)$/', $fragment, $match)) {
                if (strcasecmp($match[1], $method) !== 0) {
                    continue;
                }

                $fragment = $match[2];
            }

            if (str_contains($absUrl, $fragment)) {
                if (($body['__throw'] ?? null) === 'connection') {
                    throw new ApiConnectionException('Request timed out (simulated)');
                }

                $status = $body['__status'] ?? 200;
                unset($body['__status']);

                return [json_encode($body), $status, []];
            }
        }

        throw new \RuntimeException("Unexpected Stripe request in test: {$method} {$absUrl}");
    }
}

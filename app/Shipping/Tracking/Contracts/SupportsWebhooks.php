<?php

namespace App\Shipping\Tracking\Contracts;

use App\Shipping\Tracking\DTO\TrackingUpdate;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use App\Shipping\Tracking\Exceptions\TrackingRejected;
use Illuminate\Http\Request;

/**
 * Optional capability: the carrier pushes updates to POST /webhooks/tracking/{driver}.
 *
 * The route only answers for drivers that implement this interface. The controller adds the
 * generic safeguards (rate limit, size limit, fail closed in production when
 * shipping.tracking.drivers.<name>.webhook_secret is empty); the adapter owns the carrier-specific
 * authentication and payload format.
 */
interface SupportsWebhooks
{
    /**
     * Authenticate the request (HMAC of the raw body, shared token, ...). Must compare in constant time
     * and return false, never throw, when a secret is missing or anything is off.
     */
    public function verifySignature(Request $request): bool;

    /**
     * Translate the payload into updates. Must be a pure function of the request: parsing the same
     * request twice yields equal updates.
     *
     * @return list<TrackingUpdate>
     *
     * @throws TrackingRejected the payload is malformed (answered with 422)
     * @throws TrackingProviderUnavailable temporary problem (answered with 503 so the carrier retries)
     */
    public function parse(Request $request): array;
}

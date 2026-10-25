<?php

namespace App\Shipping\Tracking\Contracts;

use App\Shipping\Tracking\DTO\TrackingUpdate;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use App\Shipping\Tracking\Exceptions\TrackingRejected;
use Illuminate\Http\Request;

interface SupportsWebhooks
{
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

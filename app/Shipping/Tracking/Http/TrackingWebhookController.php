<?php

namespace App\Shipping\Tracking\Http;

use App\Http\Controllers\Controller;
use App\Shipping\Tracking\Contracts\SupportsWebhooks;
use App\Shipping\Tracking\Exceptions\TrackingException;
use App\Shipping\Tracking\Exceptions\TrackingProviderUnavailable;
use App\Shipping\Tracking\Exceptions\TrackingRejected;
use App\Shipping\Tracking\ShipmentTrackingService;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POST /webhooks/tracking/{driver}: carrier pushes. Generic part only; the adapter authenticates
 * and parses its own payload (SupportsWebhooks).
 *
 *   404  unknown driver, or one that does not implement SupportsWebhooks
 *   413  body larger than 1 MB
 *   403  production without webhook_secret, or the adapter rejects the signature (fail closed)
 *   422  payload cannot be parsed (the carrier should not retry)
 *   503  the adapter reports a temporary problem (the carrier should retry)
 *   500  persistence failed (the carrier should retry; applying is idempotent)
 *   200  everything was stored; unknown tracking numbers are not an error
 *
 * Payloads, headers and tracking numbers are never logged.
 */
class TrackingWebhookController extends Controller
{
    private const MAX_BODY_BYTES = 1_048_576;

    public function __invoke(Request $request, string $driver, TrackingManager $manager, ShipmentTrackingService $service): JsonResponse
    {
        try {
            $provider = $manager->driver($driver);
        } catch (TrackingException) {
            return $this->reply('not found', 404);
        }

        if (! $provider instanceof SupportsWebhooks) {
            return $this->reply('not found', 404);
        }

        if (strlen($request->getContent()) > self::MAX_BODY_BYTES) {
            return $this->reply('payload too large', 413);
        }

        if (app()->isProduction() && trim((string) ($manager->driverConfig($driver)['webhook_secret'] ?? '')) === '') {
            Log::error('Tracking webhook refused: no webhook_secret configured for this driver', ['driver' => $driver]);

            return $this->reply('forbidden', 403);
        }

        if (! $provider->verifySignature($request)) {
            Log::warning('Tracking webhook refused: invalid signature', ['driver' => $driver, 'ip' => $request->ip()]);

            return $this->reply('forbidden', 403);
        }

        try {
            $updates = $provider->parse($request);
        } catch (TrackingRejected) {
            return $this->reply('invalid payload', 422);
        } catch (TrackingProviderUnavailable) {
            return $this->reply('temporarily unavailable', 503);
        }

        try {
            $applied = 0;

            foreach ($updates as $update) {
                foreach ($service->ordersForTrackingNumber($update->trackingNumber, $update->carrierCode) as $order) {
                    $service->apply($order, $provider->name(), $update->result);
                    $applied++;
                }
            }
        } catch (Throwable $e) {
            Log::error('Tracking webhook could not be stored', ['driver' => $driver, 'error_type' => get_class($e), 'error' => $e->getMessage()]);

            return $this->reply('could not be stored', 500);
        }

        return response()->json(['status' => 'ok', 'received' => count($updates), 'applied' => $applied]);
    }

    private function reply(string $error, int $status): JsonResponse
    {
        return response()->json(['error' => $error], $status);
    }
}

<?php

namespace App\Services\Ops;

use App\Enums\OpsEventType;
use App\Models\OpsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records the purchase funnel on the server, so a step that silently stops working shows up as a drop in the numbers
 * (see InvariantChecker). Best effort: it must never be the reason a cart or a checkout fails.
 */
class FunnelTracker
{
    public function record(OpsEventType $type, ?Request $request = null): void
    {
        $this->insert($type, $this->visitorKey($request ?? request()));
    }

    /**
     * A Stripe webhook arrived (any event type).
     */
    public function recordWebhook(): void
    {
        $this->insert(OpsEventType::StripeWebhook, null);
    }

    private function insert(OpsEventType $type, ?string $visitor): void
    {
        try {
            OpsEvent::query()->insertOrIgnore([
                'type' => $type->value,
                'visitor' => $visitor,
                'occurred_on' => now()->toDateString(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Ops event could not be recorded', ['type' => $type->value, 'exception' => $e::class]);
        }
    }

    /**
     * shortcut: a visitor who signs in half way through the funnel counts as two visitors (the session id changes at
     * login), so the step ratios are approximate. Fine for spotting a collapse, not for conversion reporting.
     */
    private function visitorKey(Request $request): ?string
    {
        $identity = $request->user()?->id
            ? 'user:'.$request->user()->id
            : ($request->hasSession() ? 'session:'.$request->session()->getId() : null);

        return $identity ? hash_hmac('sha1', $identity, (string) config('app.key')) : null;
    }
}

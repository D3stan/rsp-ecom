<?php

namespace App\Services;

use App\Exceptions\RefundFailed;
use App\Exceptions\RefundRefused;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\StripeObject;
use Throwable;

/**
 * Returns money to the customer through Stripe and keeps the order in step (refunds table, payment_status, audit trail,
 * optional restock, refund email). Also records refunds made directly in the Stripe dashboard (charge.refunded).
 *
 * refund():
 *   1. under a lock on the order: checks, then a pending refunds row that reserves the amount (a concurrent second
 *      refund sees less refundable);
 *   2. Stripe refunds.create with idempotency key "refund:{id}" (a retried call never refunds twice);
 *   3. success: row succeeded, payment_status refunded / partially_refunded, order_events "refund", restock, email;
 *      failure: row failed with Stripe's message, order_events "refund_failed", no email, RefundFailed thrown.
 */
class RefundService
{
    /** Money captured for the order (its total), in cents; 0 while nothing was captured. */
    public function capturedCents(Order $order): int
    {
        return $order->hasCapturedPayment() ? self::cents($order->total_amount) : 0;
    }

    /** What can still be refunded: captured minus succeeded and pending refunds (never negative). */
    public function refundableCents(Order $order): int
    {
        $reserved = (int) Refund::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [Refund::STATUS_SUCCEEDED, Refund::STATUS_PENDING])
            ->sum('amount_cents');

        return max(0, $this->capturedCents($order) - $reserved);
    }

    /**
     * @param  array<int, int>  $restock  order item id => units to put back in stock (StockService::restoreItems)
     *
     * @throws RefundRefused nothing was attempted
     * @throws RefundFailed Stripe did not execute it (the row is marked failed)
     */
    public function refund(Order $order, int $amountCents, ?string $reason, ?User $actor, string $source = Refund::SOURCE_ADMIN, array $restock = []): Refund
    {
        $reason = trim((string) $reason) !== '' ? Str::limit(trim((string) $reason), 500, '') : null;

        $refund = DB::transaction(function () use ($order, $amountCents, $reason, $actor, $source, $restock) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $locked->hasCapturedPayment()) {
                throw RefundRefused::notCaptured((string) $locked->order_number);
            }

            if (empty($locked->stripe_payment_intent_id)) {
                throw RefundRefused::noPaymentIntent((string) $locked->order_number);
            }

            $refundable = $this->refundableCents($locked);

            if ($amountCents <= 0 || $amountCents > $refundable) {
                throw RefundRefused::invalidAmount((string) $locked->order_number, $refundable);
            }

            $this->assertRestockable($locked, $restock);

            return Refund::create([
                'order_id' => $locked->id,
                'amount_cents' => $amountCents,
                'currency' => strtoupper((string) ($locked->currency ?: 'EUR')),
                'reason' => $reason,
                'status' => Refund::STATUS_PENDING,
                'source' => $source,
                'created_by' => $actor?->getKey(),
            ]);
        });

        try {
            $client = StripeService::getClient();

            if (! $client) {
                throw new \RuntimeException('Stripe is not configured.');
            }

            $stripeRefund = $client->refunds->create([
                'payment_intent' => $order->stripe_payment_intent_id,
                'amount' => $amountCents,
                'metadata' => ['order_id' => (string) $order->id, 'refund_id' => (string) $refund->id],
            ], ['idempotency_key' => 'refund:'.$refund->id]);

            if (in_array($stripeRefund->status ?? null, ['failed', 'canceled'], true)) {
                throw new \RuntimeException("Stripe reported the refund as {$stripeRefund->status}.");
            }
        } catch (Throwable $e) {
            $this->markFailed($refund, $order, $actor, $e->getMessage());

            throw new RefundFailed($refund->fresh(), $e->getMessage(), $e);
        }

        $this->complete($refund, $order, (string) $stripeRefund->id, $actor, $restock);

        return $refund->fresh();
    }

    /**
     * charge.refunded: record every succeeded refund of the charge that is not known yet (made in the Stripe dashboard),
     * and complete our own refunds whose outcome we never stored (process died, timeout). Idempotent across webhook
     * retries and concurrent deliveries (stripe_refund_id is unique).
     *
     * @param  array<string, mixed>  $charge  the Stripe charge of the event
     * @return int refunds newly recorded or completed
     */
    public function reconcileCharge(array $charge): int
    {
        $paymentIntent = is_string($charge['payment_intent'] ?? null) ? $charge['payment_intent'] : null;
        $order = $paymentIntent ? Order::query()->where('stripe_payment_intent_id', $paymentIntent)->first() : null;

        if (! $order) {
            Log::info('charge.refunded for a payment without an order here; ignored', ['charge' => $charge['id'] ?? null]);

            return 0;
        }

        $applied = 0;

        foreach ($this->refundsOfCharge($charge, $paymentIntent) as $stripeRefund) {
            if (($stripeRefund['status'] ?? null) !== 'succeeded' || empty($stripeRefund['id'])) {
                continue; // pending/failed at Stripe: a later event tells the outcome
            }

            if (Refund::query()->where('stripe_refund_id', $stripeRefund['id'])->exists()) {
                continue;
            }

            $ownId = $stripeRefund['metadata']['refund_id'] ?? null;
            $own = $ownId ? Refund::query()->where('order_id', $order->id)->find($ownId) : null;

            if ($own) {
                $applied += $this->complete($own, $order, (string) $stripeRefund['id'], null, []) ? 1 : 0;

                continue;
            }

            $applied += $this->recordDashboardRefund($order, $stripeRefund) ? 1 : 0;
        }

        return $applied;
    }

    /**
     * Pending (or wrongly failed) refund -> succeeded, once: whoever claims the row (refund() or the webhook) updates the
     * payment status, writes the event and sends the email. The restock is idempotent on its own key, so it runs anyway.
     *
     * @param  array<int, int>  $restock
     */
    private function complete(Refund $refund, Order $order, string $stripeRefundId, ?User $actor, array $restock): bool
    {
        $restocked = $restock !== [] ? app(StockService::class)->restoreItems($order, $restock, 'refund:'.$refund->id) : [];

        $claimed = DB::transaction(function () use ($refund, $order, $stripeRefundId, $actor, $restocked) {
            $claimed = Refund::query()
                ->whereKey($refund->id)
                ->whereIn('status', [Refund::STATUS_PENDING, Refund::STATUS_FAILED])
                ->update(['status' => Refund::STATUS_SUCCEEDED, 'stripe_refund_id' => $stripeRefundId, 'failure_message' => null, 'updated_at' => now()]);

            if (! $claimed) {
                return false;
            }

            $this->syncPaymentStatus($order);
            $this->event($order, OrderEvent::TYPE_REFUND, $actor ?? $refund->creator, [
                'refund_id' => $refund->id,
                'amount_cents' => $refund->amount_cents,
                'currency' => $refund->currency,
                'reason' => $refund->reason,
                'meta' => ['source' => $refund->source],
                'restocked' => $restocked ?: null,
            ]);

            return true;
        });

        if ($claimed) {
            $this->email($order, $refund->fresh());
        }

        return $claimed;
    }

    /**
     * @param  array<string, mixed>  $stripeRefund
     */
    private function recordDashboardRefund(Order $order, array $stripeRefund): bool
    {
        try {
            $refund = DB::transaction(function () use ($order, $stripeRefund) {
                Order::query()->lockForUpdate()->find($order->id);

                $refund = Refund::create([
                    'order_id' => $order->id,
                    'amount_cents' => (int) ($stripeRefund['amount'] ?? 0),
                    'currency' => strtoupper((string) ($stripeRefund['currency'] ?? $order->currency ?? 'EUR')),
                    'reason' => is_string($stripeRefund['reason'] ?? null) ? $stripeRefund['reason'] : null,
                    'status' => Refund::STATUS_SUCCEEDED,
                    'stripe_refund_id' => (string) $stripeRefund['id'],
                    'source' => Refund::SOURCE_STRIPE_DASHBOARD,
                ]);

                $this->syncPaymentStatus($order);
                $this->event($order, OrderEvent::TYPE_REFUND, null, [
                    'refund_id' => $refund->id,
                    'amount_cents' => $refund->amount_cents,
                    'currency' => $refund->currency,
                    'reason' => $refund->reason,
                    'meta' => ['source' => Refund::SOURCE_STRIPE_DASHBOARD],
                ]);

                return $refund;
            });
        } catch (UniqueConstraintViolationException) {
            return false; // a concurrent delivery of the same event recorded it
        }

        $this->email($order, $refund);

        return true;
    }

    private function markFailed(Refund $refund, Order $order, ?User $actor, string $message): void
    {
        $message = Str::limit($message, 1000);

        DB::transaction(function () use ($refund, $order, $actor, $message) {
            Refund::query()->whereKey($refund->id)->where('status', Refund::STATUS_PENDING)
                ->update(['status' => Refund::STATUS_FAILED, 'failure_message' => $message, 'updated_at' => now()]);

            $this->event($order, OrderEvent::TYPE_REFUND_FAILED, $actor, [
                'refund_id' => $refund->id,
                'amount_cents' => $refund->amount_cents,
                'currency' => $refund->currency,
                'reason' => $refund->reason,
                'error' => $message,
            ]);
        });

        Log::warning('Stripe refund failed', ['order_id' => $order->id, 'refund_id' => $refund->id, 'error' => $message]);
    }

    /**
     * payment_status from the succeeded refunds: none -> succeeded, all captured money -> refunded, else partially.
     */
    private function syncPaymentStatus(Order $order): void
    {
        $locked = Order::query()->lockForUpdate()->find($order->id);

        if (! $locked || ! $locked->hasCapturedPayment()) {
            return;
        }

        $refunded = (int) Refund::query()->where('order_id', $locked->id)->where('status', Refund::STATUS_SUCCEEDED)->sum('amount_cents');
        $captured = $this->capturedCents($locked);

        $locked->payment_status = match (true) {
            $refunded <= 0 => 'succeeded',
            $refunded >= $captured => 'refunded',
            default => 'partially_refunded',
        };
        $locked->save();

        $order->setAttribute('payment_status', $locked->payment_status);
        $order->syncOriginalAttribute('payment_status');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function event(Order $order, string $type, ?User $actor, array $payload): void
    {
        OrderEvent::create([
            'order_id' => $order->id,
            'actor_id' => $actor?->getKey(),
            'type' => $type,
            'payload' => array_filter($payload, fn ($value) => $value !== null),
        ]);
    }

    /** The refund email, deduplicated per refund (OrderEmail fingerprint); a mail problem never undoes a refund. */
    private function email(Order $order, Refund $refund): void
    {
        try {
            app(EmailService::class)->sendOrderRefunded($order->fresh(), $refund->amount(), $refund->id);
        } catch (Throwable $e) {
            Log::error('Refund email could not be requested', ['order_id' => $order->id, 'refund_id' => $refund->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<int, int>  $restock
     */
    private function assertRestockable(Order $order, array $restock): void
    {
        $lines = $order->orderItems()->get()->keyBy('id');

        foreach ($restock as $itemId => $quantity) {
            $line = $lines->get((int) $itemId);

            if (! $line || ! is_numeric($quantity) || (int) $quantity < 0 || (int) $quantity > (int) $line->quantity) {
                throw RefundRefused::invalidRestock((string) $order->order_number);
            }
        }
    }

    /**
     * The charge's refunds as carried by the event, else listed from Stripe (recent API versions do not expand
     * charge.refunds in webhooks).
     *
     * @return array<int, array<string, mixed>>
     */
    private function refundsOfCharge(array $charge, string $paymentIntent): array
    {
        $list = $charge['refunds'] ?? null;

        if (is_array($list) && isset($list['data']) && empty($list['has_more'])) {
            return $list['data'];
        }

        $client = StripeService::getClient();

        if (! $client) {
            throw new \RuntimeException('Stripe is not configured: cannot list the refunds of a charge.');
        }

        $result = $client->refunds->all(['payment_intent' => $paymentIntent, 'limit' => 100]);

        return array_map(fn ($refund) => $refund instanceof StripeObject ? $refund->toArray() : (array) $refund, $result->data);
    }

    private static function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}

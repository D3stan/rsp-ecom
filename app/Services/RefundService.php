<?php

namespace App\Services;

use App\Exceptions\RefundFailed;
use App\Exceptions\RefundRefused;
use App\Exceptions\RefundUnconfirmed;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\IdempotencyException;
use Stripe\Exception\RateLimitException;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Throwable;

/**
 * Returns money to the customer through Stripe and keeps the order in step (refunds table, payment_status, audit trail,
 * optional restock, refund email). Also records refunds made directly in the Stripe dashboard and refunds that fail
 * after the fact (charge.refunded, charge.refund.updated).
 *
 * refund():
 *   1. under a lock on the order: checks, then a pending refunds row that reserves the amount (a concurrent second
 *      refund sees less refundable);
 *   2. Stripe refunds.create with idempotency key "refund:{id}" (a retried call never refunds twice);
 *   3. the outcome:
 *      - confirmed (Stripe status succeeded or pending): row succeeded, payment_status refunded / partially_refunded,
 *        order_events "refund", restock, email;
 *      - definitive failure (Stripe 4xx, or status failed/canceled): row failed with the message, "refund_failed",
 *        no email, the amount is free again, RefundFailed;
 *      - unknown (timeout, connection error, rate limit, 5xx: Stripe may have refunded): row stays pending with the
 *        amount reserved, "refund_unconfirmed", RefundUnconfirmed. The webhook or retry() (same key) settles it.
 *
 * A Stripe refund in status "pending" counts as done everywhere (refund(), retry(), webhooks); if Stripe later reports
 * it failed or canceled, failAfterTheFact() takes it back.
 */
class RefundService
{
    /** Stripe refund statuses that mean the money is (being) returned. */
    private const CONFIRMED = ['succeeded', 'pending'];

    /** Stripe refund statuses that mean no money was returned. */
    private const DECLINED = ['failed', 'canceled'];

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
     * @param  array<int, int>  $restock  order item id => units to put back in stock once refunded
     *
     * @throws RefundRefused nothing was attempted
     * @throws RefundFailed Stripe did not refund (the row is failed, the amount free again)
     * @throws RefundUnconfirmed Stripe's answer is unknown (the row stays pending, the amount reserved)
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
                'restock' => $restock !== [] ? array_map('intval', $restock) : null,
            ]);
        });

        $client = StripeService::getClient();

        if (! $client) {
            // Nothing was sent: a definitive failure
            $this->markFailed($refund, $order, $actor, 'Stripe is not configured.');

            throw new RefundFailed($refund->fresh(), 'Stripe is not configured.');
        }

        $this->send($client, $refund, $order, $actor);

        return $refund->fresh();
    }

    /**
     * Settle a pending (unconfirmed) refund. First look for the refund Stripe may already have made for it (matched by
     * metadata.refund_id; idempotency keys expire after 24 hours), else send it again under the same key "refund:{id}",
     * which Stripe answers with the original result if the first request did get through.
     *
     * @throws RefundRefused the refund is not pending
     * @throws RefundFailed|RefundUnconfirmed as refund()
     */
    public function retry(Refund $refund, ?User $actor): Refund
    {
        if ($refund->status !== Refund::STATUS_PENDING) {
            throw RefundRefused::notPending($refund->id);
        }

        $order = $refund->order;
        $client = StripeService::getClient();

        if (! $client) {
            throw new RefundUnconfirmed($refund, 'Stripe is not configured.');
        }

        try {
            $existing = collect($this->listRefunds($client, (string) $order->stripe_payment_intent_id))
                ->first(fn (array $stripeRefund) => (string) ($stripeRefund['metadata']['refund_id'] ?? '') === (string) $refund->id);
        } catch (Throwable $e) {
            $this->markUnconfirmed($refund, $order, $actor, $e->getMessage());

            throw new RefundUnconfirmed($refund->fresh(), $e->getMessage(), $e);
        }

        if ($existing) {
            $this->applyOutcome($refund, $order, $existing, $actor);
        } else {
            $this->send($client, $refund, $order, $actor);
        }

        return $refund->fresh();
    }

    /**
     * charge.refunded: record every refund of the charge that is not known yet (made in the Stripe dashboard), and
     * complete our own refunds whose outcome we never stored (process died, timeout). Idempotent across webhook retries
     * and concurrent deliveries (stripe_refund_id is unique).
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
            $applied += $this->applyStripeRefund($order, $stripeRefund) ? 1 : 0;
        }

        return $applied;
    }

    /**
     * charge.refund.updated: a refund changed at Stripe. Failed or canceled -> take back a refund we recorded; succeeded
     * or pending -> same as charge.refunded for this one refund (record it if unknown). Idempotent.
     *
     * @param  array<string, mixed>  $stripeRefund  the Stripe refund of the event
     */
    public function reconcileRefundUpdate(array $stripeRefund): int
    {
        $row = $this->findRow($stripeRefund);
        $status = $stripeRefund['status'] ?? null;

        if (in_array($status, self::DECLINED, true)) {
            if (! $row) {
                Log::info('Stripe refund failed that was never recorded here; ignored', ['refund' => $stripeRefund['id'] ?? null]);

                return 0;
            }

            return $this->failAfterTheFact($row, $stripeRefund) ? 1 : 0;
        }

        $paymentIntent = is_string($stripeRefund['payment_intent'] ?? null) ? $stripeRefund['payment_intent'] : null;
        $order = $row?->order ?? ($paymentIntent ? Order::query()->where('stripe_payment_intent_id', $paymentIntent)->first() : null);

        return $order ? ($this->applyStripeRefund($order, $stripeRefund) ? 1 : 0) : 0;
    }

    /**
     * Call Stripe for a pending refund (first attempt or retry: always the same idempotency key) and apply the answer.
     */
    private function send(StripeClient $client, Refund $refund, Order $order, ?User $actor): void
    {
        try {
            $stripeRefund = $client->refunds->create([
                'payment_intent' => $order->stripe_payment_intent_id,
                'amount' => $refund->amount_cents,
                'metadata' => ['order_id' => (string) $order->id, 'refund_id' => (string) $refund->id],
            ], ['idempotency_key' => 'refund:'.$refund->id]);
        } catch (Throwable $e) {
            if ($this->isDefinitiveFailure($e)) {
                $this->markFailed($refund, $order, $actor, $e->getMessage());

                throw new RefundFailed($refund->fresh(), $e->getMessage(), $e);
            }

            $this->markUnconfirmed($refund, $order, $actor, $e->getMessage());

            throw new RefundUnconfirmed($refund->fresh(), $e->getMessage(), $e);
        }

        $this->applyOutcome($refund, $order, $stripeRefund->toArray(), $actor);
    }

    /**
     * Stripe answered for this row: confirmed -> complete, declined -> failed (RefundFailed).
     *
     * @param  array<string, mixed>  $stripeRefund
     */
    private function applyOutcome(Refund $refund, Order $order, array $stripeRefund, ?User $actor): void
    {
        $status = $stripeRefund['status'] ?? null;

        if (in_array($status, self::DECLINED, true)) {
            $message = "Stripe reported the refund as {$status}.";
            $this->markFailed($refund, $order, $actor, $message);

            throw new RefundFailed($refund->fresh(), $message);
        }

        $this->complete($refund, $order, (string) $stripeRefund['id'], $actor);
    }

    /**
     * One refund seen at Stripe (webhooks): record it if unknown, complete our own row if it matches one.
     *
     * @param  array<string, mixed>  $stripeRefund
     */
    private function applyStripeRefund(Order $order, array $stripeRefund): bool
    {
        if (! in_array($stripeRefund['status'] ?? null, self::CONFIRMED, true) || empty($stripeRefund['id'])) {
            return false; // failed/canceled at Stripe: nothing was returned
        }

        if (Refund::query()->where('stripe_refund_id', $stripeRefund['id'])->exists()) {
            return false;
        }

        $ownId = $stripeRefund['metadata']['refund_id'] ?? null;
        $own = $ownId ? Refund::query()->where('order_id', $order->id)->find($ownId) : null;

        if ($own) {
            return $this->complete($own, $order, (string) $stripeRefund['id'], null);
        }

        return $this->recordDashboardRefund($order, $stripeRefund);
    }

    /**
     * Pending (or wrongly failed) refund -> succeeded, once: whoever claims the row (refund(), retry() or a webhook)
     * updates the payment status, restocks, writes the event and sends the email.
     */
    private function complete(Refund $refund, Order $order, string $stripeRefundId, ?User $actor): bool
    {
        $claimed = DB::transaction(function () use ($refund, $order, $stripeRefundId, $actor) {
            $claimed = Refund::query()
                ->whereKey($refund->id)
                ->whereIn('status', [Refund::STATUS_PENDING, Refund::STATUS_FAILED])
                ->update(['status' => Refund::STATUS_SUCCEEDED, 'stripe_refund_id' => $stripeRefundId, 'failure_message' => null, 'updated_at' => now()]);

            if (! $claimed) {
                return false;
            }

            $refund->refresh();
            $restocked = $refund->restock ? app(StockService::class)->restoreItems($order, $refund->restock, 'refund:'.$refund->id) : [];

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

    /**
     * Stripe declined a refund we had counted (it was pending at Stripe, then failed or was canceled): the row becomes
     * failed, the payment status follows the remaining refunds, and the shop is alerted (the customer may already have
     * the refund email).
     *
     * @param  array<string, mixed>  $stripeRefund
     */
    private function failAfterTheFact(Refund $refund, array $stripeRefund): bool
    {
        $status = (string) ($stripeRefund['status'] ?? 'failed');
        $message = trim("Stripe reported the refund as {$status}. ".($stripeRefund['failure_reason'] ?? ''));
        $order = $refund->order;

        $claimed = DB::transaction(function () use ($refund, $order, $stripeRefund, $message) {
            $claimed = Refund::query()
                ->whereKey($refund->id)
                ->whereIn('status', [Refund::STATUS_SUCCEEDED, Refund::STATUS_PENDING])
                ->update([
                    'status' => Refund::STATUS_FAILED,
                    'failure_message' => Str::limit($message, 1000),
                    'stripe_refund_id' => $refund->stripe_refund_id ?? ($stripeRefund['id'] ?? null),
                    'updated_at' => now(),
                ]);

            if (! $claimed) {
                return false;
            }

            $this->syncPaymentStatus($order);
            $this->event($order, OrderEvent::TYPE_REFUND_FAILED, null, [
                'refund_id' => $refund->id,
                'amount_cents' => $refund->amount_cents,
                'currency' => $refund->currency,
                'error' => $message,
                'after_the_fact' => true,
            ]);

            return true;
        });

        if ($claimed) {
            Log::critical('Stripe refund failed after it was recorded: the customer did not get this money back', [
                'order_id' => $order->id,
                'refund_id' => $refund->id,
                'stripe_refund_id' => $stripeRefund['id'] ?? null,
                'status' => $status,
            ]);
        }

        return $claimed;
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

    /** The row stays pending (amount reserved); only the unknown outcome is recorded. */
    private function markUnconfirmed(Refund $refund, Order $order, ?User $actor, string $message): void
    {
        $message = Str::limit($message, 1000);

        $this->event($order, OrderEvent::TYPE_REFUND_UNCONFIRMED, $actor, [
            'refund_id' => $refund->id,
            'amount_cents' => $refund->amount_cents,
            'currency' => $refund->currency,
            'error' => $message,
        ]);

        Log::warning('Stripe did not confirm a refund; it stays pending until the webhook or a retry settles it', [
            'order_id' => $order->id,
            'refund_id' => $refund->id,
            'error' => $message,
        ]);
    }

    /**
     * A failure Stripe answered with certainty: a 4xx (invalid request, card, authentication, permission). Connection
     * errors, rate limits, idempotency conflicts, 5xx and anything unknown may hide a refund that went through.
     */
    private function isDefinitiveFailure(Throwable $e): bool
    {
        if ($e instanceof ApiConnectionException || $e instanceof RateLimitException || $e instanceof IdempotencyException) {
            return false;
        }

        if ($e instanceof ApiErrorException) {
            $status = $e->getHttpStatus();

            return $status !== null && $status >= 400 && $status < 500 && $status !== 409;
        }

        return false;
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
     * Our row for a Stripe refund: by its Stripe id, else by the refund_id we put in its metadata.
     *
     * @param  array<string, mixed>  $stripeRefund
     */
    private function findRow(array $stripeRefund): ?Refund
    {
        if (! empty($stripeRefund['id'])) {
            $row = Refund::query()->where('stripe_refund_id', $stripeRefund['id'])->first();

            if ($row) {
                return $row;
            }
        }

        $ownId = $stripeRefund['metadata']['refund_id'] ?? null;

        return $ownId ? Refund::query()->find($ownId) : null;
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

        return $this->listRefunds($client, $paymentIntent);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listRefunds(StripeClient $client, string $paymentIntent): array
    {
        $result = $client->refunds->all(['payment_intent' => $paymentIntent, 'limit' => 100]);

        return array_map(fn ($refund) => $refund instanceof StripeObject ? $refund->toArray() : (array) $refund, $result->data);
    }

    private static function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}

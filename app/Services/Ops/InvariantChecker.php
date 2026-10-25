<?php

namespace App\Services\Ops;

use App\Enums\OpsEventType;
use App\Models\OpsEvent;
use App\Models\Order;
use App\Models\OrderEmail;

/**
 * Things that must hold in a healthy shop and that no exception would ever announce: they only show up as numbers
 * that stopped adding up. Run by `ops:check-invariants`; thresholds live in config/ops.php.
 */
class InvariantChecker
{
    /**
     * @return array<string, string> problem key => what is wrong, empty when everything holds
     */
    public function problems(): array
    {
        return array_filter([
            'payment-pending' => $this->stalePaymentPending(),
            'paid-waiting' => $this->paidOrdersWaiting(),
            'failed-emails' => $this->failedEmails(),
            'webhook-silence' => $this->webhookSilence(),
            'payments-without-orders' => $this->paymentsWithoutOrders(),
            'no-orders' => $this->noRecentOrders(),
            ...$this->funnelDrops(),
        ]);
    }

    private function stalePaymentPending(): ?string
    {
        $hours = (int) config('ops.invariants.payment_pending_hours');
        $count = Order::query()->paymentPending()->where('created_at', '<', now()->subHours($hours))->count();

        return $count > 0
            ? "{$count} order(s) still awaiting payment after {$hours}h: Stripe's expiry webhook may not be arriving."
            : null;
    }

    private function paidOrdersWaiting(): ?string
    {
        $days = (int) config('ops.invariants.paid_waiting_days');
        $count = Order::query()
            ->processing()
            ->paymentSucceeded()
            ->whereDoesntHave('shipments')
            ->whereNull('tracking_number')
            ->where('created_at', '<', now()->subDays($days))
            ->count();

        return $count > 0 ? "{$count} paid order(s) waiting to ship for more than {$days} days." : null;
    }

    private function failedEmails(): ?string
    {
        $hours = (int) config('ops.invariants.failed_email_hours');
        $count = OrderEmail::query()
            ->whereNotNull('failed_at')
            ->whereNull('sent_at')
            ->where('failed_at', '>', now()->subHours($hours))
            ->count();

        return $count > 0 ? "{$count} order email(s) failed to send in the last {$hours}h." : null;
    }

    /**
     * A paid order exists, yet no Stripe webhook has arrived since it was created: the order came through the
     * success page and the webhook endpoint is not being reached (wrong URL, secret or firewall).
     */
    private function webhookSilence(): ?string
    {
        $firstRecorded = OpsEvent::query()->min('created_at');

        if ($firstRecorded === null) {
            return null;
        }

        $order = Order::query()
            ->paymentSucceeded()
            ->whereNotNull('stripe_checkout_session_id')
            ->where('created_at', '<', now()->subMinutes((int) config('ops.invariants.webhook_grace_minutes')))
            ->where('created_at', '>', now()->subDay())
            // Orders paid before this tracking existed cannot have a webhook on record
            ->where('created_at', '>', $firstRecorded)
            ->latest()
            ->first();

        if ($order === null) {
            return null;
        }

        $webhookSince = OpsEvent::query()
            ->where('type', OpsEventType::StripeWebhook->value)
            ->where('created_at', '>=', $order->created_at->copy()->subMinutes(5))
            ->exists();

        return $webhookSince
            ? null
            : "Order {$order->order_number} was paid but no Stripe webhook has arrived since: check the webhook endpoint in Stripe.";
    }

    private function paymentsWithoutOrders(): ?string
    {
        $threshold = (int) config('ops.invariants.payments_without_orders');
        $payers = OpsEvent::query()
            ->where('type', OpsEventType::PaymentStarted->value)
            ->where('created_at', '>', now()->subDay())
            ->count();

        if ($threshold <= 0 || $payers < $threshold) {
            return null;
        }

        $paid = Order::query()->paymentSucceeded()->where('created_at', '>', now()->subDay())->count();

        return $paid === 0
            ? "{$payers} visitors went to Stripe in the last 24h and not one paid order exists: checkout, payment or order creation may be broken."
            : null;
    }

    private function noRecentOrders(): ?string
    {
        $hours = (int) config('ops.invariants.no_orders_hours');

        if ($hours <= 0) {
            return null;
        }

        return Order::query()->paymentSucceeded()->where('created_at', '>', now()->subHours($hours))->exists()
            ? null
            : "No paid order in the last {$hours}h.";
    }

    /**
     * The share of visitors moving from one funnel step to the next collapsed against the previous week.
     *
     * @return array<string, string>
     */
    private function funnelDrops(): array
    {
        $minBaseline = (int) config('ops.invariants.funnel.min_baseline');
        $minRecent = (int) config('ops.invariants.funnel.min_recent');
        $dropRatio = (float) config('ops.invariants.funnel.drop_ratio');

        $problems = [];

        foreach ([
            [OpsEventType::AddToCart, OpsEventType::CheckoutStarted],
            [OpsEventType::CheckoutStarted, OpsEventType::PaymentStarted],
        ] as [$from, $to]) {
            $baselineFrom = $this->count($from, now()->subDays(8), now()->subDay());
            $recentFrom = $this->count($from, now()->subDay(), now());

            if ($baselineFrom < $minBaseline || $recentFrom < $minRecent) {
                continue;
            }

            $baselineRate = $this->count($to, now()->subDays(8), now()->subDay()) / $baselineFrom;
            $recentRate = $this->count($to, now()->subDay(), now()) / $recentFrom;

            if ($baselineRate > 0 && $recentRate < $baselineRate * $dropRatio) {
                $problems["funnel-{$from->value}-{$to->value}"] = sprintf(
                    'Funnel drop: %d%% of visitors went from %s to %s in the last 24h, against %d%% over the previous week.',
                    round($recentRate * 100),
                    $from->value,
                    $to->value,
                    round($baselineRate * 100),
                );
            }
        }

        return $problems;
    }

    private function count(OpsEventType $type, \DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return OpsEvent::query()
            ->where('type', $type->value)
            ->where('created_at', '>', $from)
            ->where('created_at', '<=', $to)
            ->count();
    }
}

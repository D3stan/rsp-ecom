<?php

namespace App\Services\Ops;

use App\Models\Order;
use App\Services\OrderCreationService;
use App\Services\StripeService;
use Stripe\Exception\ApiErrorException;

/**
 * Compares what Stripe says was paid with what the shop recorded, for the checkout sessions of the last two days.
 * The webhook is how an order normally appears; this is the net under it for the day the webhook, the order creation
 * or the success page fails without a trace.
 */
class StripeReconciler
{
    private const LISTED_IDS = 5;

    /**
     * @return array<string, string> problem key => what is wrong, empty when Stripe and the shop agree
     */
    public function problems(int $hours = 48): array
    {
        $client = StripeService::getClient();

        if ($client === null) {
            return ['reconciliation-unavailable' => 'Stripe reconciliation could not run: no Stripe secret key is configured.'];
        }

        $paid = [];

        try {
            $sessions = $client->checkout->sessions->all([
                'created' => ['gte' => now()->subHours($hours)->timestamp],
                'limit' => 100,
            ]);

            foreach ($sessions->autoPagingIterator() as $session) {
                if (($session->metadata['source'] ?? null) === OrderCreationService::SESSION_SOURCE && $session->status === 'complete') {
                    $paid[$session->id] = $session;
                }
            }
        } catch (ApiErrorException $e) {
            return ['reconciliation-unavailable' => 'Stripe reconciliation could not reach Stripe ('.class_basename($e).').'];
        }

        $orders = Order::query()->whereIn('stripe_checkout_session_id', array_keys($paid))->get()->keyBy('stripe_checkout_session_id');

        $missing = $notCaptured = $wrongAmount = $paidLocallyOnly = [];

        foreach ($paid as $id => $session) {
            $order = $orders->get($id);
            $isPaidAtStripe = $session->payment_status === 'paid';

            if ($isPaidAtStripe && $order === null) {
                $missing[] = $id;
            } elseif ($isPaidAtStripe && ! in_array($order->payment_status, Order::CAPTURED_PAYMENT_STATUSES, true)) {
                $notCaptured[] = $order->order_number;
            } elseif (! $isPaidAtStripe && $order?->payment_status === 'succeeded') {
                $paidLocallyOnly[] = $order->order_number;
            }

            if ($isPaidAtStripe && $order !== null && abs((int) round($order->total_amount * 100) - (int) $session->amount_total) > 1) {
                $wrongAmount[] = $order->order_number;
            }
        }

        return array_filter([
            'reconciliation-missing-orders' => $this->describe($missing, 'paid Stripe session(s) have no order'),
            'reconciliation-not-captured' => $this->describe($notCaptured, 'order(s) paid at Stripe are not marked paid here'),
            'reconciliation-paid-locally-only' => $this->describe($paidLocallyOnly, 'order(s) marked paid here are not paid at Stripe'),
            'reconciliation-amount' => $this->describe($wrongAmount, 'order(s) differ in amount from what Stripe charged'),
        ]);
    }

    /**
     * @param  array<int, string>  $subjects
     */
    private function describe(array $subjects, string $what): ?string
    {
        if ($subjects === []) {
            return null;
        }

        $shown = implode(', ', array_slice($subjects, 0, self::LISTED_IDS));
        $more = count($subjects) > self::LISTED_IDS ? ', …' : '';

        return count($subjects)." {$what}: {$shown}{$more}";
    }
}

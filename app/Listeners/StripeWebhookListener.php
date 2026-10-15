<?php

namespace App\Listeners;

use App\Services\EmailService;
use App\Services\OrderCreationService;
use App\Services\RefundService;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class StripeWebhookListener
{
    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        // Cashier only verifies the Stripe signature when a webhook secret is configured; without one
        // anyone could post a forged "paid" event. Production fails closed instead of running unverified.
        if (app()->isProduction() && ! config('cashier.webhook.secret')) {
            Log::critical('Stripe webhook refused: STRIPE_WEBHOOK_SECRET is not set, so the request cannot be verified');

            throw new AccessDeniedHttpException('Stripe webhook secret is not configured');
        }

        $payload = $event->payload;

        Log::info('Processing Stripe webhook event', [
            'type' => $payload['type'],
            'id' => $payload['id'],
        ]);

        switch ($payload['type']) {
            case 'checkout.session.completed':
                $this->handleCheckoutSessionCompleted($payload);
                break;
            case 'checkout.session.async_payment_succeeded':
                // A delayed payment method (SEPA debit, bank transfer...) finally paid: upgrades the pending order
                $this->createOrderFromSession($payload['id'], $payload['data']['object']);
                break;
            case 'checkout.session.async_payment_failed':
                $this->closeUnpaidSession($payload, 'failed');
                break;
            case 'checkout.session.expired':
                $this->closeUnpaidSession($payload, 'expired');
                break;
            case 'charge.refunded':
                $this->reconcileRefunds($payload);
                break;
            case 'charge.refund.updated':
            case 'refund.updated': // the same refund object; newer API versions send this name
                $this->reconcileRefunds($payload, update: true);
                break;
            default:
                Log::info('Unhandled webhook event type: '.$payload['type']);
                break;
        }
    }

    /**
     * Handle a completed checkout session: paid, or (delayed payment methods) completed but not yet paid. An unpaid
     * session creates the order as pending; the async_payment_succeeded / _failed event decides its fate later.
     */
    private function handleCheckoutSessionCompleted(array $payload): void
    {
        $session = $payload['data']['object'];

        Log::info('Processing checkout.session.completed webhook', ['session_id' => $session['id']]);

        if (in_array($session['payment_status'] ?? null, ['paid', 'unpaid'], true)) {
            $this->createOrderFromSession($payload['id'], $session);
        }
    }

    /**
     * Create (or find, or upgrade) the order for a Stripe checkout session.
     *
     * Failures are logged and rethrown so the webhook answers with a non-2xx status and Stripe
     * retries the delivery. This is safe because order creation is idempotent.
     */
    private function createOrderFromSession(string $eventId, array $session): void
    {
        $sessionId = $session['id'];

        try {
            $order = app(OrderCreationService::class)->createFromStripeSession($session);

            // Not a session of this shop (the service already logged it): acknowledge with 200.
            if (! $order) {
                return;
            }

            Log::info('Order processed from webhook', [
                'event_id' => $eventId,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'session_id' => $sessionId,
            ]);
        } catch (Throwable $e) {
            Log::critical('Stripe payment succeeded but the order could not be created', [
                'event_id' => $eventId,
                'session_id' => $sessionId,
                'payment_intent_id' => $session['payment_intent'] ?? null,
                'amount_total' => $session['amount_total'] ?? null,
                'currency' => $session['currency'] ?? null,
                'cart_id' => $session['metadata']['cart_id'] ?? null,
                'guest_session_id' => $session['metadata']['guest_session_id'] ?? null,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ]);

            $this->alertAdmin($sessionId, $session, $e);

            throw $e;
        }
    }

    /**
     * charge.refunded: record refunds made in the Stripe dashboard (and complete our own whose outcome was never
     * stored). charge.refund.updated / refund.updated (the object is a refund): a refund that failed or was canceled after the fact is
     * taken back. Idempotent, so a failure is logged critical and rethrown: Stripe retries the delivery.
     */
    private function reconcileRefunds(array $payload, bool $update = false): void
    {
        $object = $payload['data']['object'];

        try {
            $service = app(RefundService::class);
            $applied = $update ? $service->reconcileRefundUpdate($object) : $service->reconcileCharge($object);

            Log::info($payload['type'].' processed', ['event_id' => $payload['id'], 'object' => $object['id'] ?? null, 'applied' => $applied]);
        } catch (Throwable $e) {
            Log::critical('Stripe refund could not be recorded', [
                'event_id' => $payload['id'],
                'object' => $object['id'] ?? null,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * checkout.session.async_payment_failed / checkout.session.expired: close the order of a checkout that did not end
     * in a payment (only while its payment is still pending; a paid order is never touched). Same failure semantics:
     * log critical and rethrow so Stripe retries.
     */
    private function closeUnpaidSession(array $payload, string $outcome): void
    {
        $session = $payload['data']['object'];

        try {
            $order = app(OrderCreationService::class)->closeUnpaidSession($session, $outcome);

            Log::info('Unpaid checkout session processed from webhook', [
                'event_id' => $payload['id'],
                'session_id' => $session['id'],
                'outcome' => $outcome,
                'order_id' => $order?->id,
            ]);
        } catch (Throwable $e) {
            Log::critical('Stripe checkout session could not be closed', [
                'event_id' => $payload['id'],
                'session_id' => $session['id'],
                'outcome' => $outcome,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Tell the shop owner that a customer paid but no order exists. Deduped per session by EmailService;
     * must never mask the original exception, so any failure here is only logged.
     */
    private function alertAdmin(string $sessionId, ?array $session, Throwable $e): void
    {
        try {
            $amount = isset($session['amount_total'])
                ? number_format($session['amount_total'] / 100, 2, '.', '').' '.strtoupper($session['currency'] ?? '')
                : null;

            app(EmailService::class)->sendPaidOrderMissingAlert($sessionId, [
                'payment_intent_id' => $session['payment_intent'] ?? null,
                'amount' => $amount,
                'customer_email' => $session['customer_details']['email'] ?? $session['metadata']['guest_email'] ?? $session['metadata']['user_email'] ?? null,
                'cart_id' => $session['metadata']['cart_id'] ?? null,
                'guest_session_id' => $session['metadata']['guest_session_id'] ?? null,
                'error' => mb_substr(get_class($e).': '.$e->getMessage(), 0, 500),
            ]);
        } catch (Throwable $alertError) {
            Log::error('Could not alert the admin about a paid order without an order', [
                'session_id' => $sessionId,
                'error' => $alertError->getMessage(),
            ]);
        }
    }
}

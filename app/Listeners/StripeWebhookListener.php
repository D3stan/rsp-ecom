<?php

namespace App\Listeners;

use App\Services\EmailService;
use App\Services\OrderCreationService;
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
            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($payload);
                break;
            default:
                Log::info('Unhandled webhook event type: '.$payload['type']);
                break;
        }
    }

    /**
     * Handle a successful checkout session completion.
     */
    private function handleCheckoutSessionCompleted(array $payload): void
    {
        Log::info('Processing checkout.session.completed webhook', ['session_id' => $payload['data']['object']['id']]);

        $session = $payload['data']['object'];

        // Only process if payment was successful
        if ($session['payment_status'] === 'paid') {
            $this->createOrderFromSession($payload['id'], $session['id'], null, $session);
        }
    }

    /**
     * Handle a successful payment intent.
     */
    private function handlePaymentIntentSucceeded(array $payload): void
    {
        Log::info('Processing payment_intent.succeeded webhook', ['payment_intent_id' => $payload['data']['object']['id']]);

        $paymentIntent = $payload['data']['object'];
        $sessionId = $paymentIntent['metadata']['checkout_session_id'] ?? null;

        if ($sessionId) {
            $this->createOrderFromSession($payload['id'], $sessionId, $paymentIntent);
        } else {
            Log::warning('No checkout session ID found in payment intent metadata', [
                'payment_intent_id' => $paymentIntent['id'],
            ]);
        }
    }

    /**
     * Create (or find) the order for a paid Stripe checkout session.
     *
     * Failures are logged and rethrown so the webhook answers with a non-2xx status and Stripe
     * retries the delivery. This is safe because order creation is idempotent.
     */
    private function createOrderFromSession(string $eventId, string $sessionId, ?array $paymentIntent = null, ?array $session = null): void
    {
        try {
            $orders = app(OrderCreationService::class);

            // Retrieve session from Stripe if not provided
            $session ??= $orders->retrieveSession($sessionId);

            $order = $orders->createFromStripeSession($session, $paymentIntent);

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
                'payment_intent_id' => $paymentIntent['id'] ?? $session['payment_intent'] ?? null,
                'amount_total' => $session['amount_total'] ?? null,
                'currency' => $session['currency'] ?? null,
                'cart_id' => $session['metadata']['cart_id'] ?? null,
                'guest_session_id' => $session['metadata']['guest_session_id'] ?? null,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ]);

            $this->alertAdmin($sessionId, $paymentIntent, $session, $e);

            throw $e;
        }
    }

    /**
     * Tell the shop owner that a customer paid but no order exists. Deduped per session by EmailService;
     * must never mask the original exception, so any failure here is only logged.
     */
    private function alertAdmin(string $sessionId, ?array $paymentIntent, ?array $session, Throwable $e): void
    {
        try {
            $amount = isset($session['amount_total'])
                ? number_format($session['amount_total'] / 100, 2, '.', '').' '.strtoupper($session['currency'] ?? '')
                : null;

            app(EmailService::class)->sendPaidOrderMissingAlert($sessionId, [
                'payment_intent_id' => $paymentIntent['id'] ?? $session['payment_intent'] ?? null,
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

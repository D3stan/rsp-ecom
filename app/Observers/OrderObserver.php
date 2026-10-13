<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\EmailService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the lifecycle emails that follow a status change, whoever made it (admin form, bulk update,
 * model helpers, future tracking sync). Only `updated` is observed: orders created by factories or
 * seeders with a delivered/cancelled status must never email anyone.
 *
 * Idempotency lives in EmailService/order_emails, so saving the same status again never re-sends.
 */
class OrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        try {
            // Resolved per event (not injected): the observer instance lives as long as the application.
            $emails = app(EmailService::class);

            match ($order->status) {
                'delivered' => $this->customerWasNotified($order) && $emails->sendOrderDelivered($order),
                'cancelled' => $this->customerWasNotified($order) && $emails->sendOrderCancelled($order),
                default => null,
            };
        } catch (Throwable $e) {
            // A mail problem must never break the status change that triggered it.
            Log::error('Order lifecycle email could not be requested', [
                'order_id' => $order->id,
                'status' => $order->status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Only customers who know about the order (paid, or already sent the confirmation) get status
     * emails. An abandoned, never-paid checkout that gets cancelled/closed must stay silent.
     */
    private function customerWasNotified(Order $order): bool
    {
        return $order->isPaymentCompleted() || $order->hasEmailBeenSent();
    }
}

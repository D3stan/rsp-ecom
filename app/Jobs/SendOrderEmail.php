<?php

namespace App\Jobs;

use App\Mail\NewOrderAlert;
use App\Mail\OrderCancelled;
use App\Mail\OrderConfirmation;
use App\Mail\OrderDelivered;
use App\Mail\OrderRefunded;
use App\Mail\OrderShipped;
use App\Mail\OversoldOrderAlert;
use App\Mail\ReviewRequest;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Services\EmailService;
use App\Services\Newsletter\MarketingConsent;
use App\Support\Locales;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one transactional order email, identified by its order_emails row
 * (order_id + type + fingerprint).
 *
 * Only scalar identifiers are serialized: the order and its relations are loaded fresh
 * when the job runs, so a deleted product/user or a stale model can't break the send.
 *
 * Duplicate protection: the order_emails row is inserted before the job is dispatched,
 * handle() skips rows that already have sent_at, and WithoutOverlapping stops two workers
 * from sending the same row at once. sent_at is written only AFTER the mail was accepted,
 * so a worker killed mid-send retries instead of losing the mail. The only duplicate window
 * is a crash between the SMTP hand-off and the sent_at write.
 */
class SendOrderEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    // Must stay below the queue connection's retry_after (default 90s) or a slow send is picked up twice.
    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $orderId,
        public string $type,
        public string $fingerprint,
        public ?string $trackingNumber = null,
        public ?string $amount = null, // refund amount (OrderEmail::formatAmount), refund emails only
    ) {
        $this->afterCommit();
    }

    /**
     * Seconds to wait between attempts (1m, 5m, 15m, 1h).
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            // If another worker is already sending this exact email, retry later: by then it is either
            // marked sent (this job becomes a no-op) or it died. The lock must expire before the queue's
            // retry_after (90s) hands a dead worker's job to someone else, but outlive the 60s job timeout.
            (new WithoutOverlapping("order-email:{$this->orderId}:{$this->type}:{$this->fingerprint}"))
                ->releaseAfter(30)
                ->expireAfter(75),
        ];
    }

    public function handle(): void
    {
        $record = $this->record();

        if (! $record || $record->sent_at !== null) {
            return;
        }

        $order = Order::with([
            'user',
            'billingAddress',
            'shippingAddress',
            'orderItems.product.defaultVariant',
            'orderItems.size',
        ])->find($this->orderId);

        if (! $order) {
            return;
        }

        $recipient = app(EmailService::class)->recipientFor($order, $this->type);

        if (! $recipient) {
            // Nothing to retry: no address will appear by waiting.
            $record->recordFailure('No recipient email address for this order', final: true);
            Log::warning('Order email skipped: no recipient', $this->context());
            $this->delete();

            return;
        }

        // Marketing email: re-check consent at send time (it may have been withdrawn between dispatch and now).
        // Stop permanently rather than retry; a later re-subscribe does not revive this one-per-order request.
        if ($this->type === OrderEmail::TYPE_REVIEW_REQUEST && ! app(MarketingConsent::class)->allows($recipient)) {
            $record->recordFailure('Marketing consent withdrawn before the review request was sent', final: true);
            Log::info('Review request skipped: no marketing consent', $this->context());
            $this->delete();

            return;
        }

        try {
            Mail::to($recipient)
                ->locale($this->locale($order))
                ->send($this->mailable($order));

            // Only now is the mail considered sent. If the worker dies between send() and this write,
            // the retry sends once more (the one documented duplicate window).
            $record->markSent();
        } catch (Throwable $e) {
            $record->recordFailure(get_class($e).': '.$e->getMessage(), final: false);

            throw $e;
        }

        if ($this->type === OrderEmail::TYPE_CONFIRMATION) {
            try {
                $order->markEmailAsSent(); // legacy flag, kept for compatibility
            } catch (Throwable $e) {
                Log::warning('Could not update legacy confirmation flag', $this->context() + ['error' => $e->getMessage()]);
            }
        }

        Log::info('Order email sent', $this->context());
    }

    /**
     * Called once all attempts are exhausted (or on a non-retryable failure).
     */
    public function failed(?Throwable $e): void
    {
        $record = $this->record();

        if ($record && $record->sent_at === null) {
            $record->recordFailure($e ? get_class($e).': '.$e->getMessage() : 'Job failed', final: true);
        }

        Log::error('Order email permanently failed', $this->context() + [
            'error' => $e?->getMessage(),
        ]);
    }

    private function record(): ?OrderEmail
    {
        return OrderEmail::query()
            ->where('order_id', $this->orderId)
            ->where('type', $this->type)
            ->where('fingerprint', $this->fingerprint)
            ->first();
    }

    private function mailable(Order $order): Mailable
    {
        return match ($this->type) {
            OrderEmail::TYPE_CONFIRMATION => new OrderConfirmation($order),
            OrderEmail::TYPE_SHIPPED => new OrderShipped($order, $this->trackingNumber),
            OrderEmail::TYPE_DELIVERED => new OrderDelivered($order),
            OrderEmail::TYPE_CANCELLED => new OrderCancelled($order),
            OrderEmail::TYPE_REFUNDED => new OrderRefunded($order, (float) $this->amount),
            OrderEmail::TYPE_ADMIN_NEW_ORDER => new NewOrderAlert($order),
            OrderEmail::TYPE_ADMIN_OVERSOLD => new OversoldOrderAlert($order),
            OrderEmail::TYPE_REVIEW_REQUEST => new ReviewRequest($order),
            default => throw new \InvalidArgumentException("Unknown order email type [{$this->type}]"),
        };
    }

    /**
     * Recipient locale: orders.locale when present (added by the order-flow work), else the app locale.
     * Internal alerts always use the app locale (the shop owner's language, not the customer's).
     */
    private function locale(Order $order): string
    {
        if (OrderEmail::isInternal($this->type)) {
            return config('app.locale');
        }

        $locale = $order->getAttribute('locale');

        return Locales::isSupported($locale) ? $locale : Locales::fallback();
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [
            'order_id' => $this->orderId,
            'type' => $this->type,
            'fingerprint' => $this->fingerprint,
        ];
    }
}

<?php

namespace App\Jobs;

use App\Mail\BackInStock;
use App\Models\Order;
use App\Models\RestockNotification;
use App\Support\Locales;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one back-in-stock email (a restock_notifications row written by QueueRestockNotifications).
 * Everything is re-checked when the job runs: the user may have opted out, the variant may be sold out again.
 */
class SendRestockEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public int $notificationId)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(): void
    {
        $notification = RestockNotification::with(['user', 'productVariant.product'])->find($this->notificationId);

        if (! $notification || $notification->sent_at !== null) {
            return;
        }

        $user = $notification->user;
        $variant = $notification->productVariant;

        if (! $user || ! $user->email || ! $user->notify_back_in_stock || ! $variant || ! $variant->isInStock()
            || ! settings('email.enabled', true) || ! settings('email.back_in_stock', true)) {
            return;
        }

        Mail::to($user->email)->locale($this->localeFor($user->id))->send(new BackInStock($notification));

        $notification->forceFill(['sent_at' => now()])->save();
    }

    /**
     * Users have no language of their own: use the one of their last order (orders.locale, the language they paid in),
     * else the shop default.
     */
    private function localeFor(int $userId): string
    {
        $locale = Order::query()->where('user_id', $userId)->latest('id')->value('locale');

        return Locales::isSupported($locale) ? $locale : Locales::fallback();
    }

    public function failed(?Throwable $e): void
    {
        Log::error('Back-in-stock email permanently failed', [
            'restock_notification_id' => $this->notificationId,
            'error' => $e?->getMessage(),
        ]);
    }
}

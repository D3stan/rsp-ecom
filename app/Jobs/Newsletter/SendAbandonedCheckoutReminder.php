<?php

namespace App\Jobs\Newsletter;

use App\Mail\AbandonedCheckoutReminder;
use App\Models\CheckoutSnapshot;
use App\Services\Newsletter\AbandonedCheckoutReminders;
use App\Support\Locales;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAbandonedCheckoutReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public string $snapshotId)
    {
        $this->afterCommit();
    }

    public function handle(AbandonedCheckoutReminders $reminders): void
    {
        $snapshot = CheckoutSnapshot::find($this->snapshotId);

        if (! $snapshot || ! $reminders->shouldRemind($snapshot)) {
            return;
        }

        $claimed = CheckoutSnapshot::query()
            ->whereKey($snapshot->id)
            ->whereNull('reminder_sent_at')
            ->update(['reminder_sent_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        $subscriber = $reminders->subscriberFor($snapshot);

        try {
            Mail::to($snapshot->email)
                ->locale(Locales::isSupported($snapshot->locale) ? $snapshot->locale : Locales::fallback())
                ->send(new AbandonedCheckoutReminder($snapshot->fresh(), $subscriber));

            Log::info('Abandoned-checkout reminder sent', ['snapshot_id' => $snapshot->id]);
        } catch (Throwable $e) {
            Log::error('Abandoned-checkout reminder failed: '.$e->getMessage(), ['snapshot_id' => $snapshot->id]);
        }
    }
}

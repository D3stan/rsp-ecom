<?php

namespace App\Jobs\Newsletter;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignSend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

class DispatchCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $campaignId)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("newsletter-dispatch:{$this->campaignId}"))->releaseAfter(60)->expireAfter(360)];
    }

    public function handle(): void
    {
        $campaign = NewsletterCampaign::find($this->campaignId);

        if (! $campaign || $campaign->status !== NewsletterCampaign::STATUS_SENDING) {
            return;
        }

        if (! $campaign->sends()->exists()) {
            $campaign->audience()->select('id')->chunkById(500, function ($subscribers) use ($campaign) {
                NewsletterCampaignSend::insertOrIgnore($subscribers->map(fn ($subscriber) => [
                    'campaign_id' => $campaign->id,
                    'subscriber_id' => $subscriber->id,
                ])->all());
            });
        }

        $campaign->forceFill(['recipients_count' => $campaign->sends()->count()])->save();

        $rate = max(1, (int) settings('newsletter.send_rate_per_minute', 60));
        $position = 0;

        $campaign->sends()
            ->whereNull('sent_at')
            ->whereNull('failed_at')
            ->whereNull('skipped_at')
            ->select('id')
            ->chunkById(500, function ($sends) use ($rate, &$position) {
                foreach ($sends as $send) {
                    SendCampaignEmail::dispatch($send->id)->delay(now()->addMinutes(intdiv($position, $rate)));
                    $position++;
                }
            });

        Log::info('Newsletter campaign dispatched', ['campaign_id' => $campaign->id, 'queued' => $position]);

        $campaign->finishIfDone();
    }
}

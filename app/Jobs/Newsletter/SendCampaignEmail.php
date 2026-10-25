<?php

namespace App\Jobs\Newsletter;

use App\Mail\NewsletterCampaignMail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignSend;
use App\Support\Locales;
use Illuminate\Bus\Queueable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class SendCampaignEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const RATE_LIMITER = 'newsletter-send';

    public int $maxExceptions = 3;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public int $sendId)
    {
        $this->afterCommit();
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDay();
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        RateLimiter::for(self::RATE_LIMITER, fn () => Limit::perMinute(max(1, (int) settings('newsletter.send_rate_per_minute', 60))));

        return [
            (new WithoutOverlapping("newsletter-send:{$this->sendId}"))->releaseAfter(30)->expireAfter(75),
            new RateLimited(self::RATE_LIMITER),
        ];
    }

    public function handle(): void
    {
        $send = NewsletterCampaignSend::with(['campaign', 'subscriber'])->find($this->sendId);

        if (! $send || $send->isDone()) {
            return;
        }

        $campaign = $send->campaign;

        if (! $campaign || $campaign->status !== NewsletterCampaign::STATUS_SENDING) {
            return;
        }

        $subscriber = $send->subscriber;

        if (! $subscriber || ! $subscriber->isConfirmed()) {
            $send->forceFill(['skipped_at' => now(), 'error' => 'Subscriber no longer confirmed'])->save();
            $campaign->finishIfDone();

            return;
        }

        $locale = $campaign->locale ?? $subscriber->locale;

        try {
            Mail::to($subscriber->email)
                ->locale(Locales::isSupported($locale) ? $locale : Locales::fallback())
                ->send(new NewsletterCampaignMail($campaign, $subscriber));
        } catch (Throwable $e) {
            $send->forceFill(['error' => mb_substr(get_class($e).': '.$e->getMessage(), 0, 2000)])->save();

            throw $e;
        }

        $send->forceFill(['sent_at' => now(), 'error' => null])->save();
        NewsletterCampaign::query()->whereKey($campaign->id)->increment('sent_count');

        $campaign->finishIfDone();
    }

    public function failed(?Throwable $e): void
    {
        $send = NewsletterCampaignSend::find($this->sendId);

        if (! $send || $send->isDone()) {
            return;
        }

        $send->forceFill([
            'failed_at' => now(),
            'error' => mb_substr($e ? get_class($e).': '.$e->getMessage() : 'Job failed', 0, 2000),
        ])->save();
        NewsletterCampaign::query()->whereKey($send->campaign_id)->increment('failed_count');

        Log::error('Newsletter email permanently failed', ['campaign_id' => $send->campaign_id, 'send_id' => $send->id]);

        $send->campaign?->finishIfDone();
    }
}

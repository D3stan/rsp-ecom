<?php

namespace App\Console\Commands;

use App\Jobs\Newsletter\DispatchCampaign;
use App\Models\NewsletterCampaign;
use Illuminate\Console\Command;

class DispatchScheduledNewsletters extends Command
{
    /**
     * @var string
     */
    protected $signature = 'newsletter:dispatch-scheduled';

    /**
     * @var string
     */
    protected $description = 'Start every scheduled newsletter campaign whose time has come';

    public function handle(): int
    {
        $started = 0;

        NewsletterCampaign::query()
            ->where('status', NewsletterCampaign::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->pluck('id')
            ->each(function (int $id) use (&$started) {
                // Conditional update: a campaign cancelled meanwhile, or claimed by an overlapping run, is left alone
                $claimed = NewsletterCampaign::query()
                    ->whereKey($id)
                    ->where('status', NewsletterCampaign::STATUS_SCHEDULED)
                    ->update(['status' => NewsletterCampaign::STATUS_SENDING, 'started_at' => now(), 'updated_at' => now()]);

                if ($claimed === 1) {
                    DispatchCampaign::dispatch($id);
                    $started++;
                }
            });

        $this->info("Started {$started} campaign(s).");

        return self::SUCCESS;
    }
}

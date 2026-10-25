<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendHeartbeat extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ops:heartbeat';

    /**
     * @var string
     */
    protected $description = 'Ping the external dead-man\'s switch (OPS_HEARTBEAT_URL) to prove the scheduler is alive';

    public function handle(): int
    {
        $url = config('ops.heartbeat_url');

        if (blank($url)) {
            $this->info('OPS_HEARTBEAT_URL is not set: nothing to ping.');

            return self::SUCCESS;
        }

        try {
            Http::connectTimeout(3)->timeout(5)->get($url)->throw();
        } catch (Throwable) {
            // Not reported: a failed ping is what the external service exists to notice. Reporting it here would
            // alert twice, and an outage of that service would flood Sentry.
            $this->warn('Heartbeat ping failed.');

            return self::FAILURE;
        }

        $this->info('Heartbeat sent.');

        return self::SUCCESS;
    }
}

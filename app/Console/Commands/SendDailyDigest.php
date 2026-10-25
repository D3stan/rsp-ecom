<?php

namespace App\Console\Commands;

use App\Services\Ops\DailyDigest;
use App\Services\Ops\TelegramNotifier;
use Illuminate\Console\Command;

class SendDailyDigest extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ops:daily-digest';

    /**
     * @var string
     */
    protected $description = 'Send yesterday\'s orders, funnel and check results to Telegram';

    public function handle(DailyDigest $digest, TelegramNotifier $telegram): int
    {
        $message = $digest->build();

        $this->line($message);

        if (! $telegram->isConfigured()) {
            $this->warn('Telegram is not configured: the digest was not sent.');

            return self::SUCCESS;
        }

        return $telegram->send($message) ? self::SUCCESS : self::FAILURE;
    }
}

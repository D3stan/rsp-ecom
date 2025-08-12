<?php

namespace App\Console\Commands;

use App\Models\PendingUserVerification;
use Illuminate\Console\Command;

class CleanupExpiredPendingVerifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:cleanup-pending {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired pending user verifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredCount = PendingUserVerification::where('token_expires_at', '<', now())->count();

        if ($expiredCount === 0) {
            $this->info('No expired pending verifications found.');
            return;
        }

        if (!$this->option('force')) {
            if (!$this->confirm("Found {$expiredCount} expired pending verification(s). Do you want to delete them?")) {
                $this->info('Cleanup cancelled.');
                return;
            }
        }

        $deleted = PendingUserVerification::where('token_expires_at', '<', now())->delete();

        $this->info("Successfully deleted {$deleted} expired pending verification(s).");
    }
}

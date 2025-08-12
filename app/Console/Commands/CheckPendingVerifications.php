<?php

namespace App\Console\Commands;

use App\Models\PendingUserVerification;
use Illuminate\Console\Command;

class CheckPendingVerifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:pending-status {--email= : Check specific email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the status of pending user verifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->option('email');

        if ($email) {
            $this->checkSpecificEmail($email);
        } else {
            $this->showOverview();
        }
    }

    private function checkSpecificEmail(string $email): void
    {
        $pendingUser = PendingUserVerification::where('email', $email)->first();

        if (!$pendingUser) {
            $this->info("No pending verification found for: {$email}");
            return;
        }

        $this->info("Pending verification details for: {$email}");
        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $pendingUser->name],
                ['Email', $pendingUser->email],
                ['Created', $pendingUser->created_at->format('Y-m-d H:i:s')],
                ['Expires', $pendingUser->token_expires_at->format('Y-m-d H:i:s')],
                ['Status', $pendingUser->isExpired() ? 'EXPIRED' : 'ACTIVE'],
                ['Time left', $pendingUser->isExpired() ? 'Expired' : $pendingUser->token_expires_at->diffForHumans()],
            ]
        );
    }

    private function showOverview(): void
    {
        $total = PendingUserVerification::count();
        $expired = PendingUserVerification::where('token_expires_at', '<', now())->count();
        $active = $total - $expired;

        $this->info('Pending User Verifications Overview');
        $this->info('================================');
        $this->info("Total pending: {$total}");
        $this->info("Active: {$active}");
        $this->info("Expired: {$expired}");

        if ($total > 0) {
            $this->newLine();
            
            if ($active > 0) {
                $this->info('Recent active pending verifications:');
                $activePending = PendingUserVerification::where('token_expires_at', '>=', now())
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get(['name', 'email', 'created_at', 'token_expires_at']);

                $this->table(
                    ['Name', 'Email', 'Created', 'Expires'],
                    $activePending->map(function ($pending) {
                        return [
                            $pending->name,
                            $pending->email,
                            $pending->created_at->format('Y-m-d H:i'),
                            $pending->token_expires_at->format('Y-m-d H:i'),
                        ];
                    })->toArray()
                );
            }

            if ($expired > 0) {
                $this->newLine();
                $this->warn("There are {$expired} expired pending verification(s).");
                $this->info('Run "php artisan auth:cleanup-pending" to remove them.');
            }
        }
    }
}

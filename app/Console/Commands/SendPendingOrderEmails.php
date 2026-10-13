<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderEmail;
use App\Services\EmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPendingOrderEmails extends Command
{
    /**
     * Pending orders above this count trigger a warning line (and log entry) before queueing.
     */
    private const BACKLOG_WARNING_THRESHOLD = 25;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:send-pending-emails
                            {--dry-run : Preview what would be queued without queueing anything}
                            {--force : Do not ask for confirmation (used by the scheduler)}
                            {--retry-failed : Also retry confirmations that failed permanently}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue confirmation emails for orders that haven\'t received them yet';

    /**
     * Execute the console command.
     */
    public function handle(EmailService $emailService)
    {
        $dryRun = $this->option('dry-run');
        $retryFailed = $this->option('retry-failed');

        if (! $emailService->isEnabled(OrderEmail::TYPE_CONFIRMATION)) {
            $this->info('Order confirmation emails are disabled in the settings: skipping (orders of the last 7 days will be picked up if re-enabled).');

            return 0;
        }

        $this->info('Looking for orders that need confirmation emails...');

        // Find orders that:
        // 1. Have succeeded payment status
        // 2. Haven't had a confirmation email sent
        // 3. Were created in the last 7 days (to avoid sending very old emails)
        // 4. Have no confirmation that is already sent, still in flight, or permanently failed
        $orders = Order::with('user')
            ->where('payment_status', 'succeeded')
            ->where('confirmation_email_sent', false)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereDoesntHave('emails', function ($query) use ($retryFailed) {
                $query->where('type', OrderEmail::TYPE_CONFIRMATION)
                    ->where(function ($query) use ($retryFailed) {
                        $query->whereNotNull('sent_at')
                            ->orWhere('queued_at', '>=', now()->subMinutes(EmailService::STALE_AFTER_MINUTES));

                        if (! $retryFailed) {
                            $query->orWhereNotNull('failed_at');
                        }
                    });
            })
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No orders found that need confirmation emails.');

            return 0;
        }

        $this->info("Found {$orders->count()} orders that need confirmation emails:");

        // A large batch usually means mail was switched off (or the queue/worker was down) for a while:
        // the 7-day window would now send all of it. Make that visible before it happens.
        if ($orders->count() > self::BACKLOG_WARNING_THRESHOLD) {
            $this->warn("Large backlog: {$orders->count()} orders (> ".self::BACKLOG_WARNING_THRESHOLD.') will receive their confirmation now.');
            Log::warning('Large pending confirmation email backlog', ['orders' => $orders->count()]);
        }

        $headers = ['Order Number', 'Created', 'Customer Email', 'Total', 'Status'];
        $tableData = $orders->map(function (Order $order) {
            $email = $order->user ? $order->user->email : $order->guest_email;

            return [
                $order->order_number,
                $order->created_at->format('Y-m-d H:i'),
                $email ? $this->maskEmail($email) : 'No email',
                '€'.number_format($order->total_amount, 2),
                $order->status,
            ];
        })->toArray();

        $this->table($headers, $tableData);

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No emails will be queued.');

            return 0;
        }

        if (! $this->option('force') && $this->input->isInteractive()
            && ! $this->confirm('Queue confirmation emails for these orders?')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        $successCount = 0;
        $failureCount = 0;

        foreach ($orders as $order) {
            $email = $order->user ? $order->user->email : $order->guest_email;

            if (! $email) {
                $this->warn("Skipping order {$order->order_number} - no email address");

                continue;
            }

            try {
                if ($emailService->sendOrderConfirmation($order, $retryFailed)) {
                    $this->info("Order {$order->order_number}: queued");
                    $successCount++;
                } else {
                    $this->error("Order {$order->order_number}: could not be queued (check logs)");
                    $failureCount++;
                }
            } catch (\Exception $e) {
                $this->error("Order {$order->order_number}: exception - ".$e->getMessage());
                $failureCount++;
                Log::error('Failed to queue pending order email', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Summary: queued {$successCount}, failed {$failureCount}");

        return $failureCount > 0 ? 1 : 0;
    }

    /**
     * j***@example.com: the scheduler appends this output to a log file, so keep addresses out of it.
     */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\EmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPendingOrderEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:send-pending-emails {--dry-run : Preview what would be sent without actually sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send confirmation emails for orders that haven\'t received them yet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info("Looking for orders that need confirmation emails...");
        
        // Find orders that:
        // 1. Have succeeded payment status
        // 2. Haven't had confirmation email sent
        // 3. Were created in the last 7 days (to avoid sending very old emails)
        $orders = Order::where('payment_status', 'succeeded')
            ->where('confirmation_email_sent', false)
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        if ($orders->isEmpty()) {
            $this->info("✅ No orders found that need confirmation emails.");
            return 0;
        }

        $this->info("Found {$orders->count()} orders that need confirmation emails:");
        
        $headers = ['Order Number', 'Created', 'Customer Email', 'Total', 'Status'];
        $tableData = $orders->map(function (Order $order) {
            $email = $order->user ? $order->user->email : $order->guest_email;
            return [
                $order->order_number,
                $order->created_at->format('Y-m-d H:i'),
                $email ?: 'No email',
                '€' . number_format($order->total_amount, 2),
                $order->status
            ];
        })->toArray();

        $this->table($headers, $tableData);

        if ($dryRun) {
            $this->warn("DRY RUN MODE - No emails will be sent.");
            return 0;
        }

        if (!$this->confirm('Send confirmation emails to these orders?')) {
            $this->info("Operation cancelled.");
            return 0;
        }

        $emailService = app(EmailService::class);
        $successCount = 0;
        $failureCount = 0;

        foreach ($orders as $order) {
            $email = $order->user ? $order->user->email : $order->guest_email;
            
            if (!$email) {
                $this->warn("Skipping order {$order->order_number} - no email address");
                $failureCount++;
                continue;
            }

            $this->info("Sending email for order {$order->order_number} to {$email}...");
            
            try {
                $success = $emailService->sendOrderConfirmation($order);
                
                if ($success) {
                    $this->info("✅ Email sent successfully");
                    $successCount++;
                } else {
                    $this->error("❌ Failed to send email (check logs)");
                    $failureCount++;
                }
            } catch (\Exception $e) {
                $this->error("❌ Exception: " . $e->getMessage());
                $failureCount++;
                Log::error("Failed to send pending order email", [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("\n📊 Summary:");
        $this->info("✅ Successfully sent: $successCount emails");
        if ($failureCount > 0) {
            $this->warn("❌ Failed to send: $failureCount emails");
        }

        return $failureCount > 0 ? 1 : 0;
    }
}

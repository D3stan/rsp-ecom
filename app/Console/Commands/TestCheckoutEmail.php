<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestCheckoutEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:checkout-email {email} {--order-id= : Use specific order ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the checkout email functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $orderId = $this->option('order-id');

        $this->info("Testing checkout email functionality for: $email");

        try {
            // Find or create a test order
            if ($orderId) {
                $order = Order::find($orderId);
                if (!$order) {
                    $this->error("Order with ID $orderId not found.");
                    return 1;
                }
            } else {
                // Find the most recent order
                $order = Order::latest()->first();
                if (!$order) {
                    $this->error("No orders found in database. Please create an order first.");
                    return 1;
                }
            }

            $this->info("Using order: {$order->order_number} (ID: {$order->id})");
            $this->info("Order status: {$order->status}");
            $this->info("Payment status: {$order->payment_status}");
            $this->info("Email already sent: " . ($order->hasEmailBeenSent() ? 'Yes' : 'No'));

            // Override the email for testing
            if ($order->user) {
                $originalEmail = $order->user->email;
                $order->user->email = $email;
                $this->info("Temporarily changed user email from $originalEmail to $email");
            } else {
                $originalEmail = $order->guest_email;
                $order->guest_email = $email;
                $order->save();
                $this->info("Changed guest email from $originalEmail to $email");
            }

            // Reset email sent status for testing
            $order->update([
                'confirmation_email_sent' => false,
                'confirmation_email_sent_at' => null,
            ]);

            // Test email sending
            $emailService = app(EmailService::class);
            $this->info("Attempting to send confirmation email...");
            
            $success = $emailService->sendOrderConfirmation($order);

            if ($success) {
                $this->info("✅ Email sent successfully!");
                $this->info("Check your inbox at: $email");
            } else {
                $this->error("❌ Failed to send email. Check logs for details.");
                return 1;
            }

            // Restore original email
            if ($order->user) {
                $order->user->email = $originalEmail;
                $order->user->save();
            } else {
                $order->guest_email = $originalEmail;
                $order->save();
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error("TestCheckoutEmail command failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}

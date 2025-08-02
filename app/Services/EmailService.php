<?php

namespace App\Services;

use App\Mail\OrderConfirmation;
use App\Mail\OrderShipped;
use App\Mail\WelcomeEmail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    /**
     * Send order confirmation email to customer
     */
    public function sendOrderConfirmation(Order $order): bool
    {
        try {
            $recipient = $order->user ? $order->user->email : $order->guest_email;
            
            if (!$recipient) {
                Log::warning('No email address found for order: ' . $order->order_number);
                return false;
            }

            Mail::to($recipient)->send(new OrderConfirmation($order));
            
            Log::info('Order confirmation email sent for order: ' . $order->order_number);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'order_number' => $order->order_number
            ]);
            return false;
        }
    }

    /**
     * Send order shipped notification to customer
     */
    public function sendOrderShipped(Order $order, string $trackingNumber = null): bool
    {
        try {
            $recipient = $order->user ? $order->user->email : $order->guest_email;
            
            if (!$recipient) {
                Log::warning('No email address found for order: ' . $order->order_number);
                return false;
            }

            Mail::to($recipient)->send(new OrderShipped($order, $trackingNumber));
            
            Log::info('Order shipped email sent for order: ' . $order->order_number);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send order shipped email: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'order_number' => $order->order_number
            ]);
            return false;
        }
    }

    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail(User $user): bool
    {
        try {
            Mail::to($user->email)->send(new WelcomeEmail($user));
            
            Log::info('Welcome email sent to user: ' . $user->email);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            return false;
        }
    }

    /**
     * Send custom email to customer
     */
    public function sendCustomEmail(string $to, string $subject, string $message): bool
    {
        try {
            Mail::raw($message, function ($mail) use ($to, $subject) {
                $mail->to($to)
                     ->subject($subject)
                     ->from(config('mail.from.address'), config('mail.from.name'));
            });
            
            Log::info('Custom email sent to: ' . $to);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send custom email: ' . $e->getMessage(), [
                'recipient' => $to,
                'subject' => $subject
            ]);
            return false;
        }
    }

    /**
     * Test email configuration
     */
    public function testEmailConfiguration(string $testEmail): bool
    {
        try {
            Mail::raw('Test email from ' . config('app.name'), function ($mail) use ($testEmail) {
                $mail->to($testEmail)
                     ->subject('Test Email Configuration')
                     ->from(config('mail.from.address'), config('mail.from.name'));
            });
            
            Log::info('Test email sent successfully to: ' . $testEmail);
            return true;
        } catch (\Exception $e) {
            Log::error('Email configuration test failed: ' . $e->getMessage());
            return false;
        }
    }
}
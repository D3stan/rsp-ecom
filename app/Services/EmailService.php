<?php

namespace App\Services;

use App\Jobs\SendOrderEmail;
use App\Jobs\SendWelcomeEmail;
use App\Mail\PaidOrderMissingAlert;
use App\Mail\TestEmail;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\Setting;
use App\Models\User;
use App\Support\Privacy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailService
{
    /**
     * An unsent "queued" row older than this has certainly lost its job (the whole retry
     * schedule of SendOrderEmail spans ~81 minutes), so it may be dispatched again.
     */
    public const STALE_AFTER_MINUTES = 180;

    /**
     * Setting that switches each order email type on/off (all default to enabled).
     */
    private const TYPE_SETTINGS = [
        OrderEmail::TYPE_CONFIRMATION => 'order_confirmation_enabled',
        OrderEmail::TYPE_SHIPPED => 'order_shipped_enabled',
        OrderEmail::TYPE_DELIVERED => 'order_delivered_enabled',
        OrderEmail::TYPE_CANCELLED => 'order_cancelled_enabled',
        OrderEmail::TYPE_REFUNDED => 'order_refunded_enabled',
    ];

    /**
     * How long a "paid but no order" alert for one Stripe session is suppressed (Stripe keeps retrying the webhook).
     */
    private const MISSING_ORDER_ALERT_HOURS = 6;

    /**
     * Queue the order confirmation email for delivery.
     *
     * Returns true when the mail is accepted for delivery: queued now, already queued,
     * already sent, or deliberately switched off in the admin settings. False means
     * it could not be queued (no recipient, queue failure, earlier permanent failure).
     * Safe to call any number of times: at most one mail is ever sent per order.
     */
    public function sendOrderConfirmation(Order $order, bool $retryFailed = false): bool
    {
        if ($order->hasEmailBeenSent()) {
            Log::info('Order confirmation email already sent', ['order_id' => $order->id]);

            return true;
        }

        return $this->dispatchOrderEmail(
            $order,
            OrderEmail::TYPE_CONFIRMATION,
            OrderEmail::FINGERPRINT_DEFAULT,
            retryFailed: $retryFailed,
        );
    }

    /**
     * Queue the order shipped email. Sent once per distinct tracking number
     * (first shipment, then again only if the tracking number changes).
     */
    public function sendOrderShipped(Order $order, ?string $trackingNumber = null): bool
    {
        $trackingNumber = $trackingNumber !== null && trim($trackingNumber) !== '' ? trim($trackingNumber) : null;

        return $this->dispatchOrderEmail(
            $order,
            OrderEmail::TYPE_SHIPPED,
            OrderEmail::shippedFingerprint($trackingNumber),
            $trackingNumber,
        );
    }

    /**
     * Queue the order cancelled email. At most one per order.
     */
    public function sendOrderCancelled(Order $order): bool
    {
        return $this->dispatchOrderEmail($order, OrderEmail::TYPE_CANCELLED, OrderEmail::FINGERPRINT_DEFAULT);
    }

    /**
     * Queue the order delivered email. At most one per order.
     */
    public function sendOrderDelivered(Order $order): bool
    {
        return $this->dispatchOrderEmail($order, OrderEmail::TYPE_DELIVERED, OrderEmail::FINGERPRINT_DEFAULT);
    }

    /**
     * Queue the refund notification. One mail per distinct refunded amount.
     *
     * Call this only once the money has really been returned (Stripe refund executed).
     */
    public function sendOrderRefunded(Order $order, float $amount): bool
    {
        if (round($amount, 2) <= 0 || round($amount, 2) > round((float) $order->total_amount, 2)) {
            Log::warning('Refund email skipped: invalid amount', ['order_id' => $order->id]);

            return false;
        }

        return $this->dispatchOrderEmail(
            $order,
            OrderEmail::TYPE_REFUNDED,
            OrderEmail::refundedFingerprint($amount),
            amount: OrderEmail::formatAmount($amount),
        );
    }

    /**
     * Queue the internal "new paid order" alert for the shop owner. At most one per order.
     */
    public function sendNewOrderAlert(Order $order): bool
    {
        return $this->dispatchOrderEmail($order, OrderEmail::TYPE_ADMIN_NEW_ORDER, OrderEmail::FINGERPRINT_DEFAULT);
    }

    /**
     * Queue the critical internal alert "a payment succeeded but no order exists".
     *
     * Not tied to an order and not subject to the admin switches (it is an incident, not a notification).
     * Deduped per Stripe session so a webhook that keeps failing does not flood the inbox.
     * Never throws: it is called from error handlers.
     *
     * @param  array<string, mixed>  $details  scalar facts about the payment (see PaidOrderMissingAlert)
     */
    public function sendPaidOrderMissingAlert(string $sessionId, array $details = []): bool
    {
        $claimed = false;

        try {
            $recipient = $this->adminAddress();

            if (! $recipient) {
                Log::error('Paid-order-missing alert not sent: no admin address configured', ['session_id' => $sessionId]);

                return false;
            }

            try {
                if (! Cache::add('paid-order-missing:'.$sessionId, true, now()->addHours(self::MISSING_ORDER_ALERT_HOURS))) {
                    return true;
                }
                $claimed = true;
            } catch (Throwable) {
                // Cache unavailable: better a repeated alert than none.
            }

            Mail::to($recipient)->queue(new PaidOrderMissingAlert($sessionId, $details));

            return true;
        } catch (Throwable $e) {
            if ($claimed) {
                try {
                    Cache::forget('paid-order-missing:'.$sessionId);
                } catch (Throwable) {
                    // ignore
                }
            }

            Log::error('Failed to queue paid-order-missing alert: '.$e->getMessage(), ['session_id' => $sessionId]);

            return false;
        }
    }

    /**
     * Address of the shop owner for internal alerts: the admin setting, else the contact address.
     */
    public function adminAddress(): ?string
    {
        try {
            $configured = trim((string) Setting::get('admin_notification_email', ''));
        } catch (Throwable) {
            $configured = '';
        }

        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        $fallback = trim((string) config('mail.contact_email'));

        return $fallback !== '' ? $fallback : null;
    }

    /**
     * Who an order email of this type is addressed to (null = nobody to send to).
     */
    public function recipientFor(Order $order, string $type): ?string
    {
        if (OrderEmail::isInternal($type)) {
            return $this->adminAddress();
        }

        return $order->user?->email ?: $order->guest_email ?: null;
    }

    /**
     * Queue the welcome email for a new user
     */
    public function sendWelcomeEmail(User $user): bool
    {
        if (! $this->notificationsEnabled()) {
            return true;
        }

        try {
            SendWelcomeEmail::dispatch($user->id);

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to queue welcome email: '.$e->getMessage(), ['user_id' => $user->id]);

            return false;
        }
    }

    /**
     * Whether the admin settings allow this order email type (master switch + per-type switch).
     */
    public function isEnabled(string $type): bool
    {
        if (! $this->notificationsEnabled()) {
            return false;
        }

        $setting = self::TYPE_SETTINGS[$type] ?? null;

        return $setting === null || $this->settingEnabled($setting);
    }

    /**
     * Insert-first idempotency: the unique (order, type, fingerprint) row decides who
     * dispatches; everyone else sees the existing row and does nothing.
     */
    private function dispatchOrderEmail(
        Order $order,
        string $type,
        string $fingerprint,
        ?string $trackingNumber = null,
        bool $retryFailed = false,
        ?string $amount = null,
    ): bool {
        if (! $this->isEnabled($type)) {
            Log::info('Order email disabled in settings, not sending', ['order_id' => $order->id, 'type' => $type]);

            return true;
        }

        $recipient = $this->recipientFor($order, $type);

        if (! $recipient) {
            Log::warning('No email address found for order', ['order_id' => $order->id, 'type' => $type]);

            return false;
        }

        try {
            $now = now();

            // insertOrIgnore is portable across MySQL/Postgres/SQLite and, unlike catching a
            // unique-violation exception, never aborts a surrounding transaction.
            $inserted = OrderEmail::query()->insertOrIgnore([
                'order_id' => $order->id,
                'type' => $type,
                'fingerprint' => $fingerprint,
                'queued_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $record = OrderEmail::query()
                ->where('order_id', $order->id)
                ->where('type', $type)
                ->where('fingerprint', $fingerprint)
                ->first();

            if (! $record) {
                // Ignored insert but no row: the order vanished (FK) - nothing to send.
                Log::warning('Order email row could not be recorded', ['order_id' => $order->id, 'type' => $type]);

                return false;
            }

            if (! $inserted && ! $this->shouldRedispatch($record, $retryFailed)) {
                return $record->isSent() || ! $record->isFailed();
            }

            if (! $inserted) {
                $record->forceFill(['queued_at' => $now, 'failed_at' => null, 'error' => null])->save();
            }

            try {
                SendOrderEmail::dispatch($order->id, $type, $fingerprint, $trackingNumber, $amount);
            } catch (Throwable $e) {
                // Queue backend unavailable: forget the claim so a later call can try again.
                if ($inserted) {
                    $record->delete();
                }

                throw $e;
            }

            Log::info('Order email queued', ['order_id' => $order->id, 'type' => $type]);

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to queue order email: '.$e->getMessage(), [
                'order_id' => $order->id,
                'type' => $type,
                'error_type' => get_class($e),
            ]);

            return false;
        }
    }

    /**
     * An existing row is dispatched again only when its job is certainly lost (stale, never
     * sent, not failed) or when a retry of a permanent failure was explicitly requested.
     */
    private function shouldRedispatch(OrderEmail $record, bool $retryFailed): bool
    {
        if ($record->isSent()) {
            return false;
        }

        if ($record->isFailed()) {
            return $retryFailed;
        }

        return $record->queued_at === null
            || $record->queued_at->lt(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    private function notificationsEnabled(): bool
    {
        return $this->settingEnabled('email_notifications_enabled');
    }

    private function settingEnabled(string $key): bool
    {
        try {
            $value = Setting::get($key, true);
        } catch (Throwable) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
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

            Log::info('Custom email sent to: '.Privacy::maskEmail($to));

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send custom email: '.$e->getMessage(), [
                'recipient' => Privacy::maskEmail($to),
                'subject' => $subject,
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
            // Send the comprehensive test email with logo
            Mail::to($testEmail)->send(new TestEmail);

            Log::info('Test email with logo sent successfully to: '.Privacy::maskEmail($testEmail));

            return true;
        } catch (\Exception $e) {
            Log::error('Email configuration test failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Send simple test email (without logo styling)
     */
    public function sendSimpleTestEmail(string $testEmail): bool
    {
        try {
            Mail::raw('Simple test email from '.config('app.name'), function ($mail) use ($testEmail) {
                $mail->to($testEmail)
                    ->subject('Simple Test Email Configuration')
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            Log::info('Simple test email sent successfully to: '.Privacy::maskEmail($testEmail));

            return true;
        } catch (\Exception $e) {
            Log::error('Simple email test failed: '.$e->getMessage());

            return false;
        }
    }
}

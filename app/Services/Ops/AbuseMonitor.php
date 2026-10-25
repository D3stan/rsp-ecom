<?php

namespace App\Services\Ops;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Counts the signals of abuse (password guessing, scraping or flooding, card testing, forged webhooks) and tells the
 * owner on Telegram when one crosses its threshold (config/ops.php, `abuse`), once per window. It only watches: the
 * blocking is left to the rate limiters, Cloudflare and Stripe Radar.
 */
class AbuseMonitor
{
    public function __construct(private TelegramNotifier $telegram) {}

    public function failedLogin(?string $ip, ?string $email, bool $targetsAdmin): void
    {
        if ($ip) {
            $this->signal('failed-login-ip', $ip, 'failed_login_per_ip', "Repeated failed logins from {$this->mask($ip)}.");
        }

        if ($targetsAdmin && $email) {
            $this->signal('failed-login-admin', strtolower($email), 'failed_login_admin_account', 'Repeated failed logins against an admin account.');
        }
    }

    public function rateLimited(?string $ip): void
    {
        if ($ip) {
            $this->signal('rate-limited', $ip, 'rate_limited_per_ip', "{$this->mask($ip)} keeps hitting rate limits (scraping or flooding?).");
        }
    }

    /**
     * Stripe reported a failed payment (payment_intent.payment_failed). A burst is the signature of card testing.
     */
    public function paymentFailed(): void
    {
        $this->signal('payment-failed', 'all', 'payment_failed', 'A burst of failed payments: possible card testing.');
    }

    /**
     * A webhook was refused (bad signature, or the secret is not configured).
     */
    public function webhookRejected(string $source): void
    {
        $this->signal('webhook-rejected', $source, 'webhook_rejected', "A {$source} webhook was rejected: forged request or a wrong signing secret.");
    }

    private function signal(string $name, string $subject, string $thresholdKey, string $message): void
    {
        $threshold = (int) config("ops.abuse.{$thresholdKey}.threshold");
        $window = (int) config("ops.abuse.{$thresholdKey}.window_minutes");
        $key = "abuse:{$name}:".sha1($subject);

        RateLimiter::hit($key, $window * 60);

        if (RateLimiter::attempts($key) < $threshold) {
            return;
        }

        Log::warning($message, ['signal' => $name]);

        $this->telegram->sendOnce($key, '🛡️ '.$message, $window);
    }

    /**
     * The owner can find the full address in Cloudflare; Telegram only gets enough to tell sources apart.
     */
    private function mask(string $ip): string
    {
        return str_contains($ip, ':')
            ? implode(':', array_slice(explode(':', $ip), 0, 3)).':…'
            : preg_replace('/\.\d+$/', '.x', $ip);
    }
}

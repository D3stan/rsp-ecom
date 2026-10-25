<?php

namespace App\Services\Newsletter;

use App\Jobs\Newsletter\SendAbandonedCheckoutReminder;
use App\Models\CheckoutSnapshot;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AbandonedCheckoutReminders
{
    public const RECOVERY_LINK_DAYS = 7;

    public const CONVERSION_WINDOW_DAYS = 7;

    public function __construct(private readonly MarketingConsent $consent) {}

    public function enabled(): bool
    {
        return (bool) settings('newsletter.abandoned_checkout_enabled', false);
    }

    public function sessionExpiresAt(): int
    {
        $minutes = (int) settings('orders.checkout_expiry_minutes', 60);
        $seconds = min(max($minutes * 60, 30 * 60 + 60), 24 * 3600 - 60);

        return now()->addSeconds($seconds)->timestamp;
    }

    public function rememberContact(CheckoutSnapshot $snapshot, Request $request, string $email, ?User $user = null): void
    {
        try {
            $consent = $request->boolean('marketing_consent') && (bool) settings('newsletter.enabled', false);

            $snapshot->forceFill([
                'email' => $consent ? NewsletterSubscriber::normalizeEmail($email) : null,
                'locale' => app()->getLocale(),
                'marketing_consent' => $consent,
                'consent_ip' => $consent ? $request->ip() : null,
            ])->save();

            if ($consent) {
                $owner = $user !== null && NewsletterSubscriber::normalizeEmail($user->email) === NewsletterSubscriber::normalizeEmail($email) ? $user : null;
                $this->consent->subscribe($email, 'checkout', app()->getLocale(), $owner, $request->ip(), $request->userAgent());
            }
        } catch (Throwable $e) {
            Log::warning('Could not record the checkout marketing consent', ['snapshot_id' => $snapshot->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * checkout.session.expired (after the order/snapshot were closed): queue the reminder when it may be sent.
     * Never throws, so a problem here cannot make Stripe retry the webhook.
     *
     * @param  array<string, mixed>  $session
     */
    public function sessionExpired(array $session): void
    {
        try {
            if (! $this->enabled()) {
                return;
            }

            $snapshot = $this->snapshotForSession($session);

            if ($snapshot === null || ! $snapshot->marketing_consent || ! $snapshot->email || $snapshot->reminder_sent_at !== null) {
                return;
            }

            SendAbandonedCheckoutReminder::dispatch($snapshot->id)
                ->delay(now()->addMinutes((int) settings('newsletter.abandoned_checkout_delay_minutes', 60)));
        } catch (Throwable $e) {
            Log::warning('Could not queue the abandoned-checkout reminder', ['session_id' => $session['id'] ?? null, 'error' => $e->getMessage()]);
        }
    }

    public function shouldRemind(CheckoutSnapshot $snapshot): bool
    {
        if (! $this->enabled() || ! $snapshot->marketing_consent || ! $snapshot->email || $snapshot->reminder_sent_at !== null) {
            return false;
        }

        if (empty($snapshot->lines)) {
            return false;
        }

        $subscriber = $this->subscriberFor($snapshot);

        if ($subscriber === null || $subscriber->status === NewsletterSubscriber::STATUS_UNSUBSCRIBED) {
            return false;
        }

        return ! $this->paidOrderSince($snapshot, $snapshot->created_at);
    }

    public function subscriberFor(CheckoutSnapshot $snapshot): ?NewsletterSubscriber
    {
        return $snapshot->email
            ? NewsletterSubscriber::query()->where('email', NewsletterSubscriber::normalizeEmail($snapshot->email))->first()
            : null;
    }

    public function paidOrderSince(CheckoutSnapshot $snapshot, $since, $until = null): bool
    {
        $email = $snapshot->email ? NewsletterSubscriber::normalizeEmail($snapshot->email) : null;

        return Order::query()
            ->where('payment_status', 'succeeded')
            ->where('created_at', '>=', $since)
            ->when($until !== null, fn ($query) => $query->where('created_at', '<=', $until))
            ->where(function ($query) use ($snapshot, $email) {
                $matched = false;

                if ($snapshot->user_id !== null) {
                    $query->where('user_id', $snapshot->user_id);
                    $matched = true;
                }

                if ($email !== null) {
                    $query->orWhereRaw('LOWER(guest_email) = ?', [$email])
                        ->orWhereHas('user', fn ($users) => $users->whereRaw('LOWER(email) = ?', [$email]));
                    $matched = true;
                }

                if (! $matched) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->exists();
    }

    /**
     * Admin stats of the last $days days: reminders sent, carts recovered through them, and conversions (a paid order
     * within CONVERSION_WINDOW_DAYS of the reminder).
     *
     * @return array{enabled: bool, days: int, sent: int, recovered: int, converted: int}
     */
    public function stats(int $days = 30): array
    {
        $sent = CheckoutSnapshot::query()->where('reminder_sent_at', '>=', now()->subDays($days))->get();

        return [
            'enabled' => $this->enabled(),
            'days' => $days,
            'sent' => $sent->count(),
            'recovered' => $sent->whereNotNull('recovered_at')->count(),
            'converted' => $sent->filter(fn (CheckoutSnapshot $snapshot) => $this->paidOrderSince(
                $snapshot,
                $snapshot->reminder_sent_at,
                $snapshot->reminder_sent_at->copy()->addDays(self::CONVERSION_WINDOW_DAYS),
            ))->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function snapshotForSession(array $session): ?CheckoutSnapshot
    {
        $sessionId = $session['id'] ?? null;
        $snapshotId = $session['metadata']['snapshot_id'] ?? null;

        return CheckoutSnapshot::query()
            ->where(function ($query) use ($sessionId, $snapshotId) {
                $query->where('stripe_session_id', $sessionId);

                if (is_string($snapshotId) && $snapshotId !== '') {
                    $query->orWhere('id', $snapshotId);
                }
            })
            ->first();
    }
}

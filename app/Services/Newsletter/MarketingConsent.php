<?php

namespace App\Services\Newsletter;

use App\Mail\NewsletterConfirm;
use App\Models\ConsentEvent;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Marketing (newsletter) consent: the only place that changes a subscriber's status, and it writes the consent
 * trail (consent_events) for every change.
 *
 * Double opt-in: subscribe() leaves the subscriber `pending`; only confirm() with the emailed token makes them
 * `confirmed`, and only confirmed subscribers may receive marketing email (allows()). Unsubscribing is immediate.
 */
class MarketingConsent
{
    /**
     * Version of the consent text the person agreed to. It is also the lang key of that text (the sign-up checkbox
     * label), so the stored proof says exactly what was shown. A new wording gets a new key (consent_v2...).
     */
    public const CONSENT_TEXT_VERSION = 'newsletter.consent_v1';

    /** How long the double opt-in link stays valid. */
    public const CONFIRM_TOKEN_TTL_DAYS = 7;

    /** A pending subscriber who asks again gets a new confirmation mail at most this often. */
    public const RESEND_AFTER_MINUTES = 10;

    /** Source of the event written when the emailed confirmation link is used. */
    public const SOURCE_CONFIRM_LINK = 'confirm_link';

    /**
     * Whether marketing email may be sent to this address: true only for a confirmed subscriber.
     */
    public function allows(string $email): bool
    {
        return NewsletterSubscriber::query()
            ->where('email', NewsletterSubscriber::normalizeEmail($email))
            ->where('status', NewsletterSubscriber::STATUS_CONFIRMED)
            ->exists();
    }

    /**
     * Record a subscription request and queue the double opt-in mail. Idempotent: a pending subscriber stays pending
     * (the mail is sent again at most every RESEND_AFTER_MINUTES) and a confirmed one stays confirmed (no mail, no
     * event); an unsubscribed one becomes pending again with a `resubscribed` event and a new mail.
     * Callers give the same answer whatever happened, so the response never reveals whether an address is known.
     *
     * @param  string  $source  one of NewsletterSubscriber::SOURCES
     *
     * @throws \Illuminate\Validation\ValidationException invalid email
     * @throws InvalidArgumentException unknown source
     */
    public function subscribe(
        string $email,
        string $source,
        ?string $locale = null,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): NewsletterSubscriber {
        $email = NewsletterSubscriber::normalizeEmail($email);

        Validator::make(['email' => $email], ['email' => ['required', 'string', 'email', 'max:255']])->validate();

        if (! in_array($source, NewsletterSubscriber::SOURCES, true)) {
            throw new InvalidArgumentException("Unknown newsletter source [{$source}].");
        }

        $locale = Locales::isSupported($locale) ? $locale : Locales::fallback();

        [$subscriber, $token] = DB::transaction(function () use ($email, $source, $locale, $user, $ip, $userAgent) {
            $subscriber = NewsletterSubscriber::query()->createOrFirst(['email' => $email], [
                'user_id' => $user?->id,
                'locale' => $locale,
                'status' => NewsletterSubscriber::STATUS_PENDING,
                'source' => $source,
            ]);

            if ($subscriber->wasRecentlyCreated) {
                $this->record($subscriber, ConsentEvent::EVENT_REQUESTED, $source, $ip, $userAgent);

                return [$subscriber, $this->issueConfirmationToken($subscriber)];
            }

            $subscriber = NewsletterSubscriber::query()->lockForUpdate()->findOrFail($subscriber->id);

            if ($user !== null && $subscriber->user_id === null) {
                $subscriber->user_id = $user->id;
            }

            if ($subscriber->status === NewsletterSubscriber::STATUS_UNSUBSCRIBED) {
                $subscriber->forceFill([
                    'status' => NewsletterSubscriber::STATUS_PENDING,
                    'locale' => $locale,
                    'source' => $source,
                    'confirm_token_hash' => null,
                    'confirmation_sent_at' => null,
                    'confirmed_at' => null,
                    'unsubscribed_at' => null,
                ])->save();

                $this->record($subscriber, ConsentEvent::EVENT_RESUBSCRIBED, $source, $ip, $userAgent);

                return [$subscriber, $this->issueConfirmationToken($subscriber)];
            }

            $subscriber->save();

            $resend = $subscriber->status === NewsletterSubscriber::STATUS_PENDING
                && ($subscriber->confirmation_sent_at === null
                    || $subscriber->confirmation_sent_at->lte(now()->subMinutes(self::RESEND_AFTER_MINUTES)));

            return [$subscriber, $resend ? $this->issueConfirmationToken($subscriber) : null];
        });

        if ($token !== null) {
            $this->sendConfirmation($subscriber, $token);
        }

        return $subscriber;
    }

    /**
     * New double opt-in token for a pending subscriber. Only its hash is stored and any earlier token stops working;
     * confirmation_sent_at starts the validity window, so call this right before mailing the link.
     */
    public function issueConfirmationToken(NewsletterSubscriber $subscriber): string
    {
        if ($subscriber->status !== NewsletterSubscriber::STATUS_PENDING) {
            throw new LogicException('Only a pending subscriber can receive a confirmation token.');
        }

        $token = Str::random(64);

        $subscriber->forceFill([
            'confirm_token_hash' => self::hashToken($token),
            'confirmation_sent_at' => now(),
        ])->save();

        return $token;
    }

    /**
     * Confirm the subscription the token was issued for. Single use; null when the token is unknown, already used
     * or older than CONFIRM_TOKEN_TTL_DAYS.
     */
    public function confirm(string $token, ?string $ip = null, ?string $userAgent = null): ?NewsletterSubscriber
    {
        if ($token === '') {
            return null;
        }

        return DB::transaction(function () use ($token, $ip, $userAgent) {
            $subscriber = NewsletterSubscriber::query()
                ->where('confirm_token_hash', self::hashToken($token))
                ->lockForUpdate()
                ->first();

            if ($subscriber === null
                || $subscriber->status !== NewsletterSubscriber::STATUS_PENDING
                || $subscriber->confirmation_sent_at === null
                || $subscriber->confirmation_sent_at->lte(now()->subDays(self::CONFIRM_TOKEN_TTL_DAYS))) {
                return null;
            }

            $subscriber->forceFill([
                'status' => NewsletterSubscriber::STATUS_CONFIRMED,
                'confirmed_at' => now(),
                'confirm_token_hash' => null,
            ])->save();

            $this->record($subscriber, ConsentEvent::EVENT_CONFIRMED, self::SOURCE_CONFIRM_LINK, $ip, $userAgent);

            return $subscriber;
        });
    }

    /**
     * Withdraw consent, effective immediately. Idempotent: an already unsubscribed subscriber is left as is.
     *
     * @param  string  $source  where it happened (link, one_click, account, admin...)
     */
    public function unsubscribe(
        NewsletterSubscriber $subscriber,
        string $source,
        ?string $ip = null,
        ?string $userAgent = null,
    ): NewsletterSubscriber {
        return DB::transaction(function () use ($subscriber, $source, $ip, $userAgent) {
            $subscriber = NewsletterSubscriber::query()->lockForUpdate()->findOrFail($subscriber->id);

            if ($subscriber->status === NewsletterSubscriber::STATUS_UNSUBSCRIBED) {
                return $subscriber;
            }

            $subscriber->forceFill([
                'status' => NewsletterSubscriber::STATUS_UNSUBSCRIBED,
                'unsubscribed_at' => now(),
                'confirm_token_hash' => null,
            ])->save();

            $this->record($subscriber, ConsentEvent::EVENT_UNSUBSCRIBED, $source, $ip, $userAgent);

            return $subscriber;
        });
    }

    /**
     * Sender name of marketing email: the newsletter setting, or the store name while it is empty.
     */
    public function senderName(): string
    {
        $name = trim((string) settings('newsletter.sender_name', ''));

        return $name !== '' ? $name : (string) settings('store.name', config('app.name'));
    }

    /**
     * Queue the double opt-in mail. A failure is logged and clears confirmation_sent_at, so the next request
     * can send it again straight away instead of waiting out the resend interval.
     */
    private function sendConfirmation(NewsletterSubscriber $subscriber, string $token): void
    {
        try {
            Mail::to($subscriber->email)
                ->locale($subscriber->locale ?: Locales::fallback())
                ->queue(new NewsletterConfirm($subscriber, $token));
        } catch (Throwable $e) {
            Log::error('Failed to queue newsletter confirmation: '.$e->getMessage(), ['subscriber_id' => $subscriber->id]);

            $subscriber->forceFill(['confirmation_sent_at' => null])->save();
        }
    }

    private static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function record(NewsletterSubscriber $subscriber, string $event, string $source, ?string $ip, ?string $userAgent): void
    {
        ConsentEvent::create([
            'subscriber_id' => $subscriber->id,
            'event' => $event,
            'source' => mb_substr($source, 0, 40),
            'consent_text_version' => self::CONSENT_TEXT_VERSION,
            'ip' => $ip !== null ? mb_substr($ip, 0, 45) : null,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 512) : null,
        ]);
    }
}

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
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Throwable;

class MarketingConsent
{
    public const CONSENT_TEXT_VERSION = 'newsletter.consent_v1';

    public const CONFIRM_TOKEN_TTL_DAYS = 7;

    public const RESEND_AFTER_MINUTES = 10;

    public const SOURCE_CONFIRM_LINK = 'confirm_link';

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
     * @throws ValidationException invalid email
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

    public function pendingForToken(string $token): ?NewsletterSubscriber
    {
        if ($token === '') {
            return null;
        }

        $subscriber = NewsletterSubscriber::query()->where('confirm_token_hash', self::hashToken($token))->first();

        return $subscriber !== null && $this->tokenIsUsable($subscriber) ? $subscriber : null;
    }

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

            if ($subscriber === null || ! $this->tokenIsUsable($subscriber)) {
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

    public function erase(NewsletterSubscriber $subscriber, string $source = 'admin'): void
    {
        DB::transaction(function () use ($subscriber, $source) {
            ConsentEvent::query()->where('subscriber_id', $subscriber->id)->delete();
            NewsletterSubscriber::query()->whereKey($subscriber->id)->delete();

            ConsentEvent::create([
                'subscriber_id' => null,
                'event' => ConsentEvent::EVENT_ERASED,
                'source' => mb_substr($source, 0, 40),
                'consent_text_version' => self::CONSENT_TEXT_VERSION,
            ]);
        });
    }

    public function senderName(): string
    {
        $name = trim((string) settings('newsletter.sender_name', ''));

        return $name !== '' ? $name : (string) settings('store.name', config('app.name'));
    }

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

    private function tokenIsUsable(NewsletterSubscriber $subscriber): bool
    {
        return $subscriber->status === NewsletterSubscriber::STATUS_PENDING
            && $subscriber->confirmation_sent_at !== null
            && $subscriber->confirmation_sent_at->gt(now()->subDays(self::CONFIRM_TOKEN_TTL_DAYS));
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

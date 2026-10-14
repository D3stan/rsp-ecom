<?php

use App\Models\ConsentEvent;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Services\Newsletter\MarketingConsent;
use Illuminate\Validation\ValidationException;

function consent(): MarketingConsent
{
    return app(MarketingConsent::class);
}

function subscribeEmail(string $email = 'Rider@Example.com', string $source = 'footer', ?User $user = null): NewsletterSubscriber
{
    return consent()->subscribe($email, $source, 'it', $user, '203.0.113.7', 'Mozilla/5.0 Test');
}

// ------------------------------------------------------------------ subscribe

it('creates a pending subscriber with a lowercased email and logs the request with IP, user agent and text version', function () {
    $subscriber = subscribeEmail();

    expect($subscriber->email)->toBe('rider@example.com')
        ->and($subscriber->status)->toBe(NewsletterSubscriber::STATUS_PENDING)
        ->and($subscriber->source)->toBe('footer')
        ->and($subscriber->locale)->toBe('it')
        ->and($subscriber->confirmed_at)->toBeNull();

    $events = ConsentEvent::where('subscriber_id', $subscriber->id)->get();
    expect($events)->toHaveCount(1);

    $event = $events->first();
    expect($event->event)->toBe(ConsentEvent::EVENT_REQUESTED)
        ->and($event->source)->toBe('footer')
        ->and($event->consent_text_version)->toBe(MarketingConsent::CONSENT_TEXT_VERSION)
        ->and($event->ip)->toBe('203.0.113.7')
        ->and($event->user_agent)->toBe('Mozilla/5.0 Test')
        ->and($event->created_at)->not->toBeNull();
});

it('is idempotent: subscribing the same email twice keeps one pending row and one request event', function () {
    $first = subscribeEmail('rider@example.com');
    $second = subscribeEmail('  RIDER@example.com ');

    expect($second->id)->toBe($first->id)
        ->and(NewsletterSubscriber::count())->toBe(1)
        ->and($second->status)->toBe(NewsletterSubscriber::STATUS_PENDING)
        ->and(ConsentEvent::where('event', ConsentEvent::EVENT_REQUESTED)->count())->toBe(1);
});

it('keeps a confirmed subscriber confirmed when they subscribe again', function () {
    $subscriber = subscribeEmail();
    $token = consent()->issueConfirmationToken($subscriber);
    consent()->confirm($token);

    $again = subscribeEmail();

    expect($again->id)->toBe($subscriber->id)
        ->and($again->status)->toBe(NewsletterSubscriber::STATUS_CONFIRMED)
        ->and($again->confirmed_at)->not->toBeNull()
        ->and(ConsentEvent::where('subscriber_id', $subscriber->id)->pluck('event')->all())
        ->toBe([ConsentEvent::EVENT_REQUESTED, ConsentEvent::EVENT_CONFIRMED]);
});

it('moves an unsubscribed subscriber back to pending with a resubscribed event', function () {
    $subscriber = subscribeEmail();
    consent()->confirm(consent()->issueConfirmationToken($subscriber));
    consent()->unsubscribe($subscriber->fresh(), 'link');

    $again = consent()->subscribe('rider@example.com', 'checkout', 'de', null, '198.51.100.1', 'Other UA');

    expect($again->id)->toBe($subscriber->id)
        ->and($again->status)->toBe(NewsletterSubscriber::STATUS_PENDING)
        ->and($again->confirmed_at)->toBeNull()
        ->and($again->unsubscribed_at)->toBeNull()
        ->and(consent()->allows('rider@example.com'))->toBeFalse();

    $last = ConsentEvent::where('subscriber_id', $subscriber->id)->orderByDesc('id')->first();
    expect($last->event)->toBe(ConsentEvent::EVENT_RESUBSCRIBED)
        ->and($last->source)->toBe('checkout')
        ->and($last->ip)->toBe('198.51.100.1')
        ->and($last->user_agent)->toBe('Other UA');
});

it('links the subscriber to the user who subscribed', function () {
    $user = User::factory()->create(['email' => 'rider@example.com']);

    $subscriber = subscribeEmail('rider@example.com', 'register', $user);

    expect($subscriber->user_id)->toBe($user->id)
        ->and($subscriber->user->is($user))->toBeTrue();
});

it('rejects an unknown source and an invalid email', function () {
    expect(fn () => subscribeEmail('rider@example.com', 'popup'))->toThrow(InvalidArgumentException::class);
    expect(fn () => subscribeEmail('not-an-email'))->toThrow(ValidationException::class);
    expect(NewsletterSubscriber::count())->toBe(0);
});

// -------------------------------------------------------------------- allows

it('allows marketing email only for confirmed subscribers', function () {
    expect(consent()->allows('rider@example.com'))->toBeFalse();

    $subscriber = subscribeEmail();
    expect(consent()->allows('rider@example.com'))->toBeFalse();

    consent()->confirm(consent()->issueConfirmationToken($subscriber));
    expect(consent()->allows('rider@example.com'))->toBeTrue()
        ->and(consent()->allows(' RIDER@EXAMPLE.COM '))->toBeTrue();

    consent()->unsubscribe($subscriber->fresh(), 'account');
    expect(consent()->allows('rider@example.com'))->toBeFalse();
});

// ------------------------------------------------------------------- confirm

it('confirms with a valid token once, storing only its hash, and logs the confirmation', function () {
    $subscriber = subscribeEmail();
    $token = consent()->issueConfirmationToken($subscriber);

    $subscriber->refresh();
    expect($subscriber->confirm_token_hash)->not->toBeNull()
        ->and($subscriber->confirm_token_hash)->not->toBe($token)
        ->and($subscriber->confirmation_sent_at)->not->toBeNull();

    $confirmed = consent()->confirm($token, '203.0.113.9', 'Confirm UA');

    expect($confirmed)->not->toBeNull()
        ->and($confirmed->id)->toBe($subscriber->id)
        ->and($confirmed->status)->toBe(NewsletterSubscriber::STATUS_CONFIRMED)
        ->and($confirmed->confirmed_at)->not->toBeNull()
        ->and($confirmed->confirm_token_hash)->toBeNull();

    $event = ConsentEvent::where('event', ConsentEvent::EVENT_CONFIRMED)->sole();
    expect($event->subscriber_id)->toBe($subscriber->id)
        ->and($event->ip)->toBe('203.0.113.9')
        ->and($event->user_agent)->toBe('Confirm UA')
        ->and($event->consent_text_version)->toBe(MarketingConsent::CONSENT_TEXT_VERSION);

    // Single use
    expect(consent()->confirm($token))->toBeNull()
        ->and(ConsentEvent::where('event', ConsentEvent::EVENT_CONFIRMED)->count())->toBe(1);
});

it('refuses an unknown or expired confirmation token', function () {
    $subscriber = subscribeEmail();
    $token = consent()->issueConfirmationToken($subscriber);

    expect(consent()->confirm('nope'))->toBeNull();

    $this->travel(MarketingConsent::CONFIRM_TOKEN_TTL_DAYS)->days();
    $this->travel(1)->minutes();

    expect(consent()->confirm($token))->toBeNull()
        ->and($subscriber->fresh()->status)->toBe(NewsletterSubscriber::STATUS_PENDING);
});

it('invalidates the previous token when a new one is issued', function () {
    $subscriber = subscribeEmail();
    $old = consent()->issueConfirmationToken($subscriber);
    $new = consent()->issueConfirmationToken($subscriber->fresh());

    expect(consent()->confirm($old))->toBeNull()
        ->and(consent()->confirm($new))->not->toBeNull();
});

// --------------------------------------------------------------- unsubscribe

it('unsubscribes immediately, logs it once and clears any pending token', function () {
    $subscriber = subscribeEmail();
    $token = consent()->issueConfirmationToken($subscriber);

    consent()->unsubscribe($subscriber->fresh(), 'one_click', '203.0.113.10', 'Mail client');
    consent()->unsubscribe($subscriber->fresh(), 'one_click');

    $subscriber->refresh();
    expect($subscriber->status)->toBe(NewsletterSubscriber::STATUS_UNSUBSCRIBED)
        ->and($subscriber->unsubscribed_at)->not->toBeNull()
        ->and($subscriber->confirm_token_hash)->toBeNull()
        ->and(consent()->confirm($token))->toBeNull();

    $events = ConsentEvent::where('event', ConsentEvent::EVENT_UNSUBSCRIBED)->get();
    expect($events)->toHaveCount(1)
        ->and($events->first()->source)->toBe('one_click')
        ->and($events->first()->ip)->toBe('203.0.113.10')
        ->and($events->first()->user_agent)->toBe('Mail client');
});

// --------------------------------------------------------------------- model

it('keeps the subscriber when the linked user is deleted, and deletes the events with the subscriber', function () {
    $user = User::factory()->create();
    $subscriber = subscribeEmail('rider@example.com', 'account', $user);

    $user->delete();
    expect($subscriber->fresh())->not->toBeNull()
        ->and($subscriber->fresh()->user_id)->toBeNull();

    $subscriber->delete();
    expect(ConsentEvent::count())->toBe(0);
});

it('has the consent text version as a translated key in every locale', function () {
    foreach (config('app.supported_locales') as $locale) {
        $lang = json_decode(file_get_contents(lang_path("{$locale}.json")), true);

        expect($lang)->toHaveKey(MarketingConsent::CONSENT_TEXT_VERSION);
        expect(trim($lang[MarketingConsent::CONSENT_TEXT_VERSION]))->not->toBe('');
    }

    // Real translations, not English copies
    $en = json_decode(file_get_contents(lang_path('en.json')), true)[MarketingConsent::CONSENT_TEXT_VERSION];
    foreach (['it', 'de', 'fr', 'es'] as $locale) {
        expect(json_decode(file_get_contents(lang_path("{$locale}.json")), true)[MarketingConsent::CONSENT_TEXT_VERSION])->not->toBe($en);
    }
});

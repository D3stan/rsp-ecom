<?php

use App\Mail\NewsletterConfirm;
use App\Models\ConsentEvent;
use App\Models\NewsletterSubscriber;
use App\Models\PendingUserVerification;
use App\Models\User;
use App\Services\Newsletter\MarketingConsent;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
    settings()->set('newsletter.enabled', true);
});

/** URL of the confirmation link in the (only) DOI mail queued for this address. */
function queuedConfirmUrl(string $email): string
{
    $url = null;

    Mail::assertQueued(NewsletterConfirm::class, function (NewsletterConfirm $mail) use ($email, &$url) {
        if ($mail->hasTo($email)) {
            $url = $mail->confirmUrl;

            return true;
        }

        return false;
    });

    return $url;
}

function confirmedSubscriber(string $email = 'known@example.com'): NewsletterSubscriber
{
    $consent = app(MarketingConsent::class);
    $subscriber = $consent->subscribe($email, 'footer');

    return $consent->confirm($consent->issueConfirmationToken($subscriber->fresh()));
}

// ------------------------------------------------------------------ subscribe

it('creates a pending subscriber and queues one double opt-in mail in the visitor locale', function () {
    $this->withSession(['locale' => 'de'])->from('/')->post('/newsletter/subscribe', ['email' => 'Rider@Example.com', 'source' => 'footer'])
        ->assertRedirect('/')
        ->assertSessionHas('success', __('newsletter.check_inbox'));

    $subscriber = NewsletterSubscriber::sole();
    expect($subscriber->email)->toBe('rider@example.com')
        ->and($subscriber->status)->toBe(NewsletterSubscriber::STATUS_PENDING)
        ->and($subscriber->source)->toBe('footer')
        ->and($subscriber->confirmation_sent_at)->not->toBeNull();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(NewsletterConfirm::class, fn (NewsletterConfirm $mail) => $mail->hasTo('rider@example.com') && $mail->locale === 'de');

    $event = ConsentEvent::sole();
    expect($event->event)->toBe(ConsentEvent::EVENT_REQUESTED)
        ->and($event->ip)->toBe('127.0.0.1')
        ->and($event->user_agent)->not->toBeNull();
});

it('does not send a second mail for the same email within 10 minutes, and resends after', function () {
    $this->from('/')->post('/newsletter/subscribe', ['email' => 'rider@example.com']);
    $this->from('/')->post('/newsletter/subscribe', ['email' => 'rider@example.com'])
        ->assertSessionHas('success', __('newsletter.check_inbox'));

    expect(NewsletterSubscriber::count())->toBe(1)
        ->and(NewsletterSubscriber::sole()->status)->toBe(NewsletterSubscriber::STATUS_PENDING);
    Mail::assertQueuedCount(1);

    $this->travel(11)->minutes();
    $this->from('/')->post('/newsletter/subscribe', ['email' => 'rider@example.com']);

    Mail::assertQueuedCount(2);
});

it('gives identical responses for a known and an unknown email', function () {
    confirmedSubscriber('known@example.com');
    Mail::fake();

    $known = $this->from('/products')->post('/newsletter/subscribe', ['email' => 'known@example.com']);
    $unknown = $this->from('/products')->post('/newsletter/subscribe', ['email' => 'unknown@example.com']);

    expect($known->getStatusCode())->toBe($unknown->getStatusCode())
        ->and($known->headers->get('Location'))->toBe($unknown->headers->get('Location'));
    $known->assertSessionHas('success', __('newsletter.check_inbox'))->assertSessionHasNoErrors();
    $unknown->assertSessionHas('success', __('newsletter.check_inbox'))->assertSessionHasNoErrors();

    // The confirmed subscriber is not mailed again and stays confirmed
    expect(NewsletterSubscriber::where('email', 'known@example.com')->value('status'))->toBe(NewsletterSubscriber::STATUS_CONFIRMED);
    Mail::assertQueuedCount(1);
    Mail::assertQueued(NewsletterConfirm::class, fn ($mail) => $mail->hasTo('unknown@example.com'));
});

it('silently drops submissions with the honeypot filled', function () {
    $this->from('/')->post('/newsletter/subscribe', ['email' => 'bot@example.com', 'website' => 'http://spam.example'])
        ->assertRedirect('/')
        ->assertSessionHas('success', __('newsletter.check_inbox'));

    expect(NewsletterSubscriber::count())->toBe(0);
    Mail::assertNothingQueued();
});

it('validates the email', function () {
    $this->from('/')->post('/newsletter/subscribe', ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');

    expect(NewsletterSubscriber::count())->toBe(0);
});

it('throttles the subscribe endpoint to 5 requests a minute', function () {
    foreach (range(1, 5) as $i) {
        $this->from('/')->post('/newsletter/subscribe', ['email' => "rider{$i}@example.com"])->assertRedirect('/');
    }

    $this->from('/')->post('/newsletter/subscribe', ['email' => 'rider6@example.com'])->assertStatus(429);
});

// -------------------------------------------------------------------- confirm

it('shows a confirm button on the emailed link without confirming (link scanners prefetch GETs)', function () {
    $this->from('/')->post('/newsletter/subscribe', ['email' => 'rider@example.com']);
    $url = queuedConfirmUrl('rider@example.com');

    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Newsletter/Confirm')
        ->where('status', 'pending')
        ->where('action', $url));
    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('status', 'pending'));

    expect(NewsletterSubscriber::sole()->status)->toBe(NewsletterSubscriber::STATUS_PENDING)
        ->and(ConsentEvent::where('event', ConsentEvent::EVENT_CONFIRMED)->count())->toBe(0);
});

it('confirms with the POST of the button exactly once, logging the IP and user agent of that POST', function () {
    $this->from('/')->post('/newsletter/subscribe', ['email' => 'rider@example.com']);
    $url = queuedConfirmUrl('rider@example.com');

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50', 'HTTP_USER_AGENT' => 'Confirming Browser'])
        ->post($url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Newsletter/Confirm')
        ->where('status', 'confirmed'));

    expect(NewsletterSubscriber::sole()->status)->toBe(NewsletterSubscriber::STATUS_CONFIRMED)
        ->and(app(MarketingConsent::class)->allows('rider@example.com'))->toBeTrue();

    $event = ConsentEvent::where('event', ConsentEvent::EVENT_CONFIRMED)->sole();
    expect($event->ip)->toBe('203.0.113.50')
        ->and($event->user_agent)->toBe('Confirming Browser');

    $this->post($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('status', 'invalid'));
    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('status', 'invalid'));
});

it('protects the confirm POST with CSRF and throttles it', function () {
    $middleware = new class(app(), app('encrypter')) extends ValidateCsrfToken
    {
        public function excludes(Request $request): bool
        {
            return $this->inExceptArray($request);
        }
    };

    expect($middleware->excludes(Request::create('/newsletter/confirm/abc', 'POST')))->toBeFalse();

    foreach (range(1, 10) as $i) {
        $this->post('/newsletter/confirm/nope')->assertOk();
    }
    $this->post('/newsletter/confirm/nope')->assertStatus(429);
});

it('refuses a confirmation link older than 7 days and offers to resend', function () {
    $this->from('/')->post('/newsletter/subscribe', ['email' => 'rider@example.com']);
    $url = queuedConfirmUrl('rider@example.com');

    $this->travel(7)->days();
    $this->travel(1)->minutes();

    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Newsletter/Confirm')
        ->where('status', 'invalid'));
    $this->post($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('status', 'invalid'));

    expect(NewsletterSubscriber::sole()->status)->toBe(NewsletterSubscriber::STATUS_PENDING);
});

it('builds a locale-aware confirmation mail with button, plain link and validity note', function () {
    settings()->set('store.name', 'Moto Luci');
    $subscriber = app(MarketingConsent::class)->subscribe('rider@example.com', 'footer', 'it');
    $mail = (new NewsletterConfirm($subscriber, 'plain-token'))->locale('it');

    $html = $mail->render();

    expect($mail->confirmUrl)->toBe(route('newsletter.confirm', 'plain-token'))
        ->and($html)->toContain(e($mail->confirmUrl))
        ->and($html)->toContain(e(__('newsletter.confirm_email.button', [], 'it')))
        ->and($html)->toContain(e(__('newsletter.confirm_email.validity', ['days' => 7], 'it')))
        ->and(__('newsletter.confirm_email.button', [], 'it'))->not->toBe(__('newsletter.confirm_email.button', [], 'en'));

    $mail->assertHasSubject(__('newsletter.confirm_email.subject', ['store' => 'Moto Luci'], 'it'));
});

// ---------------------------------------------------------------- unsubscribe

it('shows the unsubscribe page from a signed link without login and refuses an unsigned one', function () {
    $subscriber = confirmedSubscriber();

    $this->get($subscriber->unsubscribeUrl())->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Newsletter/Unsubscribe')
        ->where('done', false));

    $this->get('/newsletter/unsubscribe/'.$subscriber->id)->assertForbidden();
    $this->post('/newsletter/unsubscribe/'.$subscriber->id)->assertForbidden();

    expect($subscriber->fresh()->status)->toBe(NewsletterSubscriber::STATUS_CONFIRMED);
});

it('unsubscribes immediately with a one-click POST on the signed link', function () {
    $subscriber = confirmedSubscriber();

    $this->post($subscriber->unsubscribeUrl(), ['List-Unsubscribe' => 'One-Click'])->assertOk();

    expect($subscriber->fresh()->status)->toBe(NewsletterSubscriber::STATUS_UNSUBSCRIBED)
        ->and(app(MarketingConsent::class)->allows('known@example.com'))->toBeFalse()
        ->and(ConsentEvent::where('event', ConsentEvent::EVENT_UNSUBSCRIBED)->sole()->ip)->toBe('127.0.0.1');
});

it('exempts the one-click unsubscribe target from CSRF (mail clients post without a token)', function () {
    $subscriber = confirmedSubscriber();

    $middleware = new class(app(), app('encrypter')) extends ValidateCsrfToken
    {
        public function excludes(Request $request): bool
        {
            return $this->inExceptArray($request);
        }
    };

    expect($middleware->excludes(Request::create($subscriber->unsubscribeUrl(), 'POST')))->toBeTrue()
        ->and($middleware->excludes(Request::create('/newsletter/subscribe', 'POST')))->toBeFalse();
});

it('keeps the unsubscribe link working while the newsletter is switched off', function () {
    $subscriber = confirmedSubscriber();
    settings()->set('newsletter.enabled', false);

    $this->post($subscriber->unsubscribeUrl())->assertOk();

    expect($subscriber->fresh()->status)->toBe(NewsletterSubscriber::STATUS_UNSUBSCRIBED);
});

// ------------------------------------------------------------------ register

it('subscribes on registration only when the box is ticked', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'No Box',
        'email' => 'nobox@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('verification.pending'));

    expect(NewsletterSubscriber::count())->toBe(0);
    Mail::assertNothingQueued();

    $this->post('/register', [
        'name' => 'With Box',
        'email' => 'withbox@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'newsletter' => true,
    ])->assertRedirect(route('verification.pending'));

    $subscriber = NewsletterSubscriber::sole();
    expect($subscriber->email)->toBe('withbox@example.com')
        ->and($subscriber->source)->toBe('register')
        ->and($subscriber->status)->toBe(NewsletterSubscriber::STATUS_PENDING);
    Mail::assertQueued(NewsletterConfirm::class, fn ($mail) => $mail->hasTo('withbox@example.com'));
});

it('links the subscriber to the account when the registration is verified', function () {
    Notification::fake();
    $existing = User::factory()->create(['email' => 'other@example.com']);

    $this->post('/register', [
        'name' => 'With Box',
        'email' => 'withbox@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'newsletter' => true,
    ]);

    expect(NewsletterSubscriber::sole()->user_id)->toBeNull();

    $pending = PendingUserVerification::where('email', 'withbox@example.com')->sole();
    $verifyUrl = URL::temporarySignedRoute('verification.verify.pending', now()->addHour(), [
        'token' => $pending->verification_token,
        'email' => $pending->email,
    ]);

    $this->get($verifyUrl)->assertRedirect();

    $user = User::where('email', 'withbox@example.com')->sole();
    expect(NewsletterSubscriber::sole()->user_id)->toBe($user->id);

    // An already linked subscriber is never re-assigned
    $linked = NewsletterSubscriber::create(['email' => 'taken@example.com', 'user_id' => $existing->id, 'status' => 'confirmed', 'source' => 'footer']);
    auth()->logout();
    $this->post('/register', [
        'name' => 'Taken',
        'email' => 'taken@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    $pending = PendingUserVerification::where('email', 'taken@example.com')->sole();
    $this->get(URL::temporarySignedRoute('verification.verify.pending', now()->addHour(), [
        'token' => $pending->verification_token,
        'email' => $pending->email,
    ]))->assertRedirect();

    expect($linked->fresh()->user_id)->toBe($existing->id);
});

// ------------------------------------------------------------------- account

it('shows the newsletter status on the profile page and toggles it', function () {
    $user = User::factory()->create(['email' => 'member@example.com']);

    $this->actingAs($user)->get('/settings/profile')->assertInertia(fn (Assert $page) => $page
        ->where('newsletter.status', 'none'));

    $this->actingAs($user)->from('/settings/profile')->put('/settings/newsletter', ['subscribed' => true])
        ->assertRedirect('/settings/profile');

    $subscriber = NewsletterSubscriber::sole();
    expect($subscriber->email)->toBe('member@example.com')
        ->and($subscriber->user_id)->toBe($user->id)
        ->and($subscriber->source)->toBe('account')
        ->and($subscriber->status)->toBe(NewsletterSubscriber::STATUS_PENDING);
    Mail::assertQueued(NewsletterConfirm::class, fn ($mail) => $mail->hasTo('member@example.com'));

    $this->actingAs($user)->get('/settings/profile')->assertInertia(fn (Assert $page) => $page
        ->where('newsletter.status', 'pending'));

    $this->actingAs($user)->from('/settings/profile')->put('/settings/newsletter', ['subscribed' => false])
        ->assertRedirect('/settings/profile');

    expect($subscriber->fresh()->status)->toBe(NewsletterSubscriber::STATUS_UNSUBSCRIBED);
});

it('requires login for the account switch', function () {
    $this->put('/settings/newsletter', ['subscribed' => true])->assertRedirect(route('login'));

    expect(NewsletterSubscriber::count())->toBe(0);
});

// ------------------------------------------------------------- switched off

it('hides everything while the newsletter is switched off', function () {
    settings()->set('newsletter.enabled', false);
    $user = User::factory()->create();

    $this->post('/newsletter/subscribe', ['email' => 'rider@example.com'])->assertNotFound();
    $this->get('/newsletter/confirm/whatever')->assertNotFound();
    $this->post('/newsletter/confirm/whatever')->assertNotFound();
    $this->actingAs($user)->put('/settings/newsletter', ['subscribed' => true])->assertNotFound();

    $this->actingAs($user)->get('/settings/profile')->assertInertia(fn (Assert $page) => $page
        ->where('newsletter', null)
        ->where('settings', fn ($settings) => $settings['newsletter.enabled'] === false));

    auth()->logout();
    Notification::fake();
    $this->post('/register', [
        'name' => 'With Box',
        'email' => 'withbox@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'newsletter' => true,
    ]);

    expect(NewsletterSubscriber::count())->toBe(0);
    Mail::assertNothingQueued();
});

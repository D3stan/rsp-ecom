<?php

use App\Jobs\SendWelcomeEmail;
use App\Mail\WelcomeEmail;
use App\Models\PendingUserVerification;
use App\Models\User;
use App\Notifications\PendingUserEmailVerification;
use App\Services\EmailService;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Mail\Content\Support\EmailFixtures as Fx;

/**
 * Account emails are built by a queue worker that has no request: the visitor's language
 * has to be captured when the mail is dispatched.
 */

/** Runs a pushed job the way a worker would: serialized, then handled under another locale. */
function runAsWorker(object $job, string $workerLocale): void
{
    $job = unserialize(serialize($job));
    app()->setLocale($workerLocale);

    app()->call([$job, 'handle']);
}

it('sends the verification email in the language the visitor registered with', function (string $locale) {
    Queue::fake();
    config(['mail.support_address' => 'help@shop.test']);
    $pending = PendingUserVerification::factory()->create(['name' => 'Luca Neri']);

    app()->setLocale($locale);
    Notification::route('mail', $pending->email)->notify(new PendingUserEmailVerification($pending));

    $queued = Queue::pushed(SendQueuedNotifications::class)->first();
    expect($queued)->not->toBeNull();

    // run the pushed job like a worker whose default locale is Italian
    runAsWorker($queued, 'it');

    $mail = Fx::lastDelivered();
    $html = $mail->getHtmlBody();
    $text = $mail->getTextBody();

    expect($mail->getSubject())->toBe(__('emails.verify_pending.subject', ['app' => config('app.name')], $locale));
    foreach ([$html, $text] as $output) {
        expect($output)->toContain(__('emails.verify_pending.title', [], $locale))
            ->toContain(__('emails.no_reply.title', [], $locale))
            ->not->toContain('emails.');
    }
    expect($html)->toContain('verification/verify')->toContain('signature=');
    expect($text)->toContain(__('emails.common.greeting', ['name' => 'Luca Neri'], $locale));
})->with(['de', 'es', 'fr', 'en', 'it']);

it('sends the welcome email in the language captured when the job was dispatched', function (string $locale) {
    Queue::fake();
    $user = User::factory()->create(['name' => 'Anna Verdi']);

    app()->setLocale($locale);
    app(EmailService::class)->sendWelcomeEmail($user);

    $job = Queue::pushed(SendWelcomeEmail::class)->first();
    expect($job)->not->toBeNull()->and($job->locale)->toBe($locale);

    runAsWorker($job, 'it');

    $mail = Fx::lastDelivered();
    expect($mail->getSubject())->toBe(__('emails.welcome.subject', ['app' => config('app.name')], $locale));
    expect($mail->getTextBody())->toContain(__('emails.welcome.title', [], $locale))
        ->toContain(__('emails.no_reply.title', [], $locale))
        ->toContain(__('emails.common.greeting', ['name' => 'Anna Verdi'], $locale));
    expect($mail->getHtmlBody())->not->toContain('emails.')->not->toContain(route('products'));
})->with(['de', 'es', 'fr', 'en', 'it']);

it('falls back to the default locale when the captured locale is not one of the supported ones', function () {
    config(['app.default_locale' => 'it']);

    expect((new SendWelcomeEmail(1, 'xx'))->locale)->toBe('it')
        ->and((new SendWelcomeEmail(1, 'DE'))->locale)->toBe('it')
        ->and((new SendWelcomeEmail(1, 'de'))->locale)->toBe('de');
});

it('brands the password reset email and sends it in the visitor language', function (string $locale) {
    $user = User::factory()->create(['name' => 'Anna Verdi']);

    app()->setLocale($locale);
    $user->sendPasswordResetNotification('reset-token-123');

    $mail = Fx::lastDelivered();
    $html = $mail->getHtmlBody();
    $text = $mail->getTextBody();

    expect($mail->getSubject())->toBe(__('emails.password_reset.subject', ['app' => config('app.name')], $locale));
    foreach ([$html, $text] as $output) {
        expect($output)->toContain('reset-password/reset-token-123')
            ->toContain(__('emails.password_reset.title', [], $locale))
            ->toContain(__('emails.no_reply.title', [], $locale))
            ->not->toContain('emails.')
            ->not->toContain('Laravel Framework');
    }
    expect($text)->toContain(__('emails.password_reset.expiry', ['minutes' => config('auth.passwords.users.expire')], $locale));
    expect($html)->toContain('<table')->not->toContain('display: flex');
})->with(['de', 'es', 'fr', 'en', 'it']);

it('brands the account verification email', function (string $locale) {
    $user = User::factory()->unverified()->create(['name' => 'Anna Verdi']);

    app()->setLocale($locale);
    $user->sendEmailVerificationNotification();

    $mail = Fx::lastDelivered();

    expect($mail->getSubject())->toBe(__('emails.verify_email.subject', ['app' => config('app.name')], $locale));
    foreach ([$mail->getHtmlBody(), $mail->getTextBody()] as $output) {
        expect($output)->toContain('verify-email/'.$user->id)->toContain('signature=')
            ->toContain(__('emails.verify_email.title', [], $locale))
            ->toContain(__('emails.no_reply.title', [], $locale))
            ->not->toContain('emails.');
    }
})->with(['de', 'es', 'fr', 'en', 'it']);

it('does not use the welcome mailable as a marketing channel', function () {
    $html = Fx::deliver(new WelcomeEmail(User::factory()->create()), 'en')->getHtmlBody();

    expect($html)->not->toContain(route('products'))->toContain(route('dashboard'));
});

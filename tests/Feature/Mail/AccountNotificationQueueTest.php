<?php

use App\Enums\UserNotificationKind;
use App\Jobs\SendUserNotification;
use App\Mail\ContactMessage;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

function lastSentMail(): Symfony\Component\Mime\Email
{
    $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();

    return $messages->last()->getOriginalMessage();
}

function sentMailLinks(): string
{
    $mail = lastSentMail();

    return html_entity_decode((string) $mail->getHtmlBody().' '.(string) $mail->getTextBody());
}

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
});

it('queues the contact message once instead of sending it in the request', function () {
    Mail::fake();

    $this->post('/contact', [
        'firstName' => 'Mario',
        'lastName' => 'Rossi',
        'email' => 'mario@visitor.test',
        'subject' => 'Where is my order?',
        'message' => 'Hello, I would like to know where my order is.',
    ])->assertSessionHas('success');

    Mail::assertQueued(ContactMessage::class, 1);
    Mail::assertNothingSent();
});

it('configures the contact message for retries after commit', function () {
    $mail = new ContactMessage(['firstName' => 'A', 'lastName' => 'B', 'email' => 'a@b.test', 'subject' => 'S', 'message' => 'long enough message']);

    expect($mail)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class)
        ->and($mail->tries)->toBe(5)
        ->and($mail->backoff())->toBe([60, 300, 900, 3600])
        ->and($mail->afterCommit)->toBeTrue();
});

it('shows an error to the visitor when the contact message cannot be queued', function () {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('queue backend down'));

    $this->post('/contact', [
        'firstName' => 'Mario',
        'lastName' => 'Rossi',
        'email' => 'mario@visitor.test',
        'subject' => 'Hello',
        'message' => 'Hello, I would like to know where my order is.',
    ])->assertSessionHas('error')->assertSessionMissing('success');
});

it('queues one job for a password reset request and carries only scalars', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

    Queue::assertPushed(SendUserNotification::class, 1);
    Queue::assertPushed(SendUserNotification::class, function (SendUserNotification $job) use ($user) {
        return $job->userId === $user->id
            && $job->kind === UserNotificationKind::PasswordReset
            && is_string($job->token) && $job->token !== ''
            && $job->afterCommit === true
            && $job->tries === 5
            && $job->backoff() === [60, 300, 900, 3600];
    });
});

it('gives the same answer for an unknown email and queues nothing', function () {
    Queue::fake();
    $user = User::factory()->create();

    $known = $this->post('/forgot-password', ['email' => $user->email]);
    $unknown = $this->post('/forgot-password', ['email' => 'nobody@example.test']);

    expect($unknown->getStatusCode())->toBe($known->getStatusCode())
        ->and(session('status'))->toBe(__('A reset link will be sent if the account exists.'));
    Queue::assertPushed(SendUserNotification::class, 1);
});

it('still sends the framework ResetPassword notification to the user', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('sends a working reset link when the queued job runs', function () {
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    expect(preg_match('#https?://[^\s"<]*/reset-password/([A-Za-z0-9]+)\?email=[^\s"<]+#', sentMailLinks(), $m))->toBe(1);
    $this->get($m[0])->assertOk();

    $this->post('/reset-password', [
        'token' => $m[1],
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertSessionHasNoErrors()->assertRedirect(route('login'));
});

it('renders the password reset mail in the locale active when it was queued', function () {
    Queue::fake();
    $user = User::factory()->create();

    App::setLocale('de');
    $user->sendPasswordResetNotification('token-123');

    Queue::assertPushed(SendUserNotification::class, fn ($job) => $job->locale === 'de');
    $job = Queue::pushedJobs()[SendUserNotification::class][0]['job'];

    App::setLocale('it');
    $job->handle();

    expect(lastSentMail()->getSubject())->toBe(__('emails.password_reset.subject', ['app' => config('app.name')], 'de'))
        ->and(lastSentMail()->getSubject())->not->toBe(__('emails.password_reset.subject', ['app' => config('app.name')], 'it'));
});

it('queues the verification mail and sends a signed link that verifies the user', function () {
    $user = User::factory()->unverified()->create();

    Queue::fake();
    $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('status', 'verification-link-sent');
    Queue::assertPushed(SendUserNotification::class, fn ($job) => $job->userId === $user->id && $job->kind === UserNotificationKind::VerifyEmail && $job->token === null);

    $job = Queue::pushedJobs()[SendUserNotification::class][0]['job'];
    $job->handle();

    expect(preg_match('#https?://[^\s"<]*/verify-email/[^\s"<]+#', sentMailLinks(), $m))->toBe(1);

    $this->actingAs($user)->get($m[0])->assertRedirect();
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('renders the verification mail in the locale active when it was queued', function () {
    Queue::fake();
    $user = User::factory()->unverified()->create();

    App::setLocale('es');
    $user->sendEmailVerificationNotification();
    $job = Queue::pushedJobs()[SendUserNotification::class][0]['job'];

    App::setLocale('it');
    $job->handle();

    expect(lastSentMail()->getSubject())->toBe(__('emails.verify_email.subject', ['app' => config('app.name')], 'es'));
});

it('still sends the framework VerifyEmail notification to the user', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('skips silently when the user no longer exists', function () {
    Notification::fake();
    $user = User::factory()->create();
    $job = new SendUserNotification($user->id, UserNotificationKind::PasswordReset, 'token-123');
    $user->delete();

    $job->handle();

    Notification::assertNothingSent();
});

it('does not send a verification mail to a user who verified in the meantime', function () {
    Notification::fake();
    $user = User::factory()->create();

    (new SendUserNotification($user->id, UserNotificationKind::VerifyEmail))->handle();

    Notification::assertNothingSent();
});

it('logs a masked failure without the token', function () {
    $user = User::factory()->create(['email' => 'mario.rossi@example.test']);
    $logged = [];
    Illuminate\Support\Facades\Log::shouldReceive('error')->once()->andReturnUsing(function ($message, $context) use (&$logged) {
        $logged = $context;
    });

    (new SendUserNotification($user->id, UserNotificationKind::PasswordReset, 'secret-token-xyz'))->failed(new RuntimeException('smtp down'));

    expect($logged['email'])->toBe('m***@example.test')
        ->and(json_encode($logged))->not->toContain('secret-token-xyz')->not->toContain('mario.rossi');
});

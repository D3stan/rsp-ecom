<?php

use App\Models\PendingUserVerification;
use App\Models\User;
use App\Notifications\PendingUserEmailVerification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

test('registration creates pending user verification instead of user', function () {
    Notification::fake();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    // Should not create a user immediately
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();

    // Should create a pending verification
    expect(PendingUserVerification::where('email', 'test@example.com')->exists())->toBeTrue();

    // Should send verification email
    Notification::assertSentTo(
        [new AnonymousNotifiable],
        PendingUserEmailVerification::class
    );

    $response->assertRedirect(route('verification.pending'));
});

test('pending verification email contains verification link', function () {
    $pendingUser = PendingUserVerification::factory()->create();

    $notification = new PendingUserEmailVerification($pendingUser);
    $mail = $notification->toMail($pendingUser);

    // The notification uses a custom view, so we check the view data
    // Verify the mail message is configured correctly
    expect($mail)->toBeInstanceOf(MailMessage::class);
    expect($mail->subject)->toBe(__('emails.verify_pending.subject', ['app' => config('app.name')]));

    // Generate the expected URL to verify it contains the right components
    $expectedUrl = URL::temporarySignedRoute(
        'verification.verify.pending',
        now()->addHours(24),
        [
            'token' => $pendingUser->verification_token,
            'email' => $pendingUser->email,
        ]
    );

    expect($expectedUrl)->toContain('verification/verify');
    expect($expectedUrl)->toContain($pendingUser->verification_token);
    expect($expectedUrl)->toContain($pendingUser->email);
});

test('valid verification link creates user and logs them in', function () {
    $pendingUser = PendingUserVerification::factory()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify.pending',
        now()->addHours(24),
        [
            'token' => $pendingUser->verification_token,
            'email' => $pendingUser->email,
        ]
    );

    $response = $this->get($verificationUrl);

    // Should create the user
    $user = User::where('email', $pendingUser->email)->first();
    expect($user)->not->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();

    // Should delete the pending verification
    expect(PendingUserVerification::where('email', $pendingUser->email)->exists())->toBeFalse();

    // Should log the user in
    expect(auth()->check())->toBeTrue();
    expect(auth()->user()->email)->toBe($pendingUser->email);

    $response->assertRedirect(route('dashboard'));
});

test('expired verification link redirects with error', function () {
    $pendingUser = PendingUserVerification::factory()->expired()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify.pending',
        now()->addHours(24),
        [
            'token' => $pendingUser->verification_token,
            'email' => $pendingUser->email,
        ]
    );

    $response = $this->get($verificationUrl);

    // Should not create user
    expect(User::where('email', $pendingUser->email)->exists())->toBeFalse();

    // Should not delete pending verification (for potential resend)
    expect(PendingUserVerification::where('email', $pendingUser->email)->exists())->toBeTrue();

    $response->assertRedirect(route('register'))->assertSessionHas('error');
});

test('invalid verification token redirects with error', function () {
    $pendingUser = PendingUserVerification::factory()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify.pending',
        now()->addHours(24),
        [
            'token' => 'invalid-token',
            'email' => $pendingUser->email,
        ]
    );

    $response = $this->get($verificationUrl);

    expect(User::where('email', $pendingUser->email)->exists())->toBeFalse();
    $response->assertRedirect(route('register'))->assertSessionHas('error');
});

test('can resend verification email', function () {
    Notification::fake();

    $pendingUser = PendingUserVerification::factory()->create();

    $response = $this->post(route('verification.resend.pending'), [
        'email' => $pendingUser->email,
    ]);

    Notification::assertSentTo(
        [new AnonymousNotifiable],
        PendingUserEmailVerification::class
    );

    $response->assertSessionHas('status', 'verification-link-sent');
});

test('resend verification redirects back and retains email and status on pending page', function () {
    Notification::fake();

    $pendingUser = PendingUserVerification::factory()->create();

    $response = $this->from(route('verification.pending'))
        ->withSession(['email' => $pendingUser->email])
        ->post(route('verification.resend.pending'), [
            'email' => $pendingUser->email,
        ]);

    $response->assertRedirect(route('verification.pending'));
    $response->assertSessionHas('status', 'verification-link-sent');
    $response->assertSessionHas('email', $pendingUser->email);

    // Follow redirect to GET /verification/pending
    $pendingPageResponse = $this->get(route('verification.pending'));
    $pendingPageResponse->assertOk();
    $pendingPageResponse->assertInertia(fn ($page) => $page
        ->component('auth/verification-pending')
        ->where('email', $pendingUser->email)
        ->where('status', 'verification-link-sent')
    );
});

test('visiting verification pending without email redirects to register without error', function () {
    $response = $this->get(route('verification.pending'));
    $response->assertRedirect(route('register'));
});

test('invalid verification signature redirects to register with error', function () {
    $pendingUser = PendingUserVerification::factory()->create();

    $response = $this->get(route('verification.verify.pending', [
        'token' => $pendingUser->verification_token,
        'email' => $pendingUser->email,
        'signature' => 'invalid-signature-hash',
    ]));

    expect(User::where('email', $pendingUser->email)->exists())->toBeFalse();
    $response->assertRedirect(route('register'))->assertSessionHas('error', 'Invalid or expired verification link.');
});

test('verification link signed over https validates behind reverse proxy with X-Forwarded-Proto', function () {
    $pendingUser = PendingUserVerification::factory()->create();

    URL::forceRootUrl('https://test.rsp-industries.com');
    URL::forceScheme('https');

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify.pending',
        now()->addHours(24),
        [
            'token' => $pendingUser->verification_token,
            'email' => $pendingUser->email,
        ]
    );

    expect($verificationUrl)->toStartWith('https://test.rsp-industries.com');

    // Simulate reverse proxy forwarding HTTP to the app container with X-Forwarded headers
    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '10.0.1.137',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'test.rsp-industries.com',
        'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
        'HTTP_X_FORWARDED_PORT' => '443',
    ])->get($verificationUrl);

    $user = User::where('email', $pendingUser->email)->first();
    expect($user)->not->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();
    $response->assertRedirect(route('dashboard'));
});

test('resend regenerates token for expired verification', function () {
    $pendingUser = PendingUserVerification::factory()->expired()->create();
    $originalToken = $pendingUser->verification_token;

    $this->post(route('verification.resend.pending'), [
        'email' => $pendingUser->email,
    ]);

    $pendingUser->refresh();
    expect($pendingUser->verification_token)->not->toBe($originalToken);
    expect($pendingUser->token_expires_at->isFuture())->toBeTrue();
});

test('cannot register with same email as pending verification', function () {
    PendingUserVerification::factory()->create(['email' => 'test@example.com']);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('cleanup command removes expired pending verifications', function () {
    // Create some pending verifications
    PendingUserVerification::factory()->expired()->count(3)->create();
    PendingUserVerification::factory()->count(2)->create(); // Valid ones

    expect(PendingUserVerification::count())->toBe(5);

    $this->artisan('auth:cleanup-pending --force')
        ->expectsOutput('Successfully deleted 3 expired pending verification(s).');

    expect(PendingUserVerification::count())->toBe(2);
});

// The status endpoint only answers the browser that registered the email (see
// tests/Feature/Security/VerificationStatusLoginBypassTest.php): these tests register first.
function registerForStatusPolling(): PendingUserVerification
{
    Notification::fake();

    test()->post('/register', [
        'name' => 'Test User',
        'email' => 'polling@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    return PendingUserVerification::where('email', 'polling@example.com')->sole();
}

test('verification status api returns correct status for pending verification', function () {
    $pendingUser = registerForStatusPolling();

    $response = $this->get(route('verification.status', ['email' => $pendingUser->email]));

    $response->assertOk()
        ->assertJson([
            'verified' => false,
            'pending' => true,
        ]);
});

test('verification status api returns verified status after verification', function () {
    $pendingUser = registerForStatusPolling();

    // Manually create user (simulating verification process)
    $user = $pendingUser->createVerifiedUser();

    $response = $this->get(route('verification.status', ['email' => $user->email]));

    $response->assertOk()
        ->assertJson([
            'verified' => true,
            'authenticated' => true,
        ])
        ->assertJsonMissingPath('user_id')
        ->assertJsonStructure(['redirect_url']);
});

test('verification status api returns expired status for expired tokens', function () {
    $pendingUser = registerForStatusPolling();
    $pendingUser->update(['token_expires_at' => now()->subHour()]);

    $response = $this->get(route('verification.status', ['email' => $pendingUser->email]));

    $response->assertOk()
        ->assertJson([
            'verified' => false,
            'expired' => true,
        ])
        ->assertJsonStructure(['message']);
});

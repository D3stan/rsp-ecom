<?php

use App\Models\PendingUserVerification;
use App\Models\User;
use App\Notifications\PendingUserEmailVerification;
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
        [new \Illuminate\Notifications\AnonymousNotifiable()],
        PendingUserEmailVerification::class
    );

    $response->assertRedirect(route('verification.pending'));
});

test('pending verification email contains verification link', function () {
    $pendingUser = PendingUserVerification::factory()->create();

    $notification = new PendingUserEmailVerification($pendingUser);
    $mail = $notification->toMail($pendingUser);

    expect($mail->actionUrl)->toContain('verification/verify');
    expect($mail->actionUrl)->toContain($pendingUser->verification_token);
    expect($mail->actionUrl)->toContain($pendingUser->email);
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
        [new \Illuminate\Notifications\AnonymousNotifiable()],
        PendingUserEmailVerification::class
    );

    $response->assertSessionHas('status', 'verification-link-sent');
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

test('verification status api returns correct status for pending verification', function () {
    $pendingUser = PendingUserVerification::factory()->create();

    $response = $this->get(route('verification.status', ['email' => $pendingUser->email]));

    $response->assertOk()
        ->assertJson([
            'verified' => false,
            'pending' => true,
        ]);
});

test('verification status api returns verified status after verification', function () {
    $pendingUser = PendingUserVerification::factory()->create();

    // Manually create user (simulating verification process)
    $user = $pendingUser->createVerifiedUser();

    $response = $this->get(route('verification.status', ['email' => $user->email]));

    $response->assertOk()
        ->assertJson([
            'verified' => true,
            'user_id' => $user->id,
        ])
        ->assertJsonStructure(['redirect_url']);
});

test('verification status api returns expired status for expired tokens', function () {
    $pendingUser = PendingUserVerification::factory()->expired()->create();

    $response = $this->get(route('verification.status', ['email' => $pendingUser->email]));

    $response->assertOk()
        ->assertJson([
            'verified' => false,
            'expired' => true,
        ])
        ->assertJsonStructure(['message']);
});

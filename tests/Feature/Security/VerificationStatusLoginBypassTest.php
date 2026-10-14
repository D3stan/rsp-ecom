<?php

use App\Models\PendingUserVerification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

/**
 * The "verification pending" page polls verification/status so that the browser that registered is logged in
 * once the email link is clicked (possibly on another device). Nobody else may be logged in by it, and it must
 * not tell whether an email is registered.
 */
beforeEach(function () {
    Notification::fake();
});

function registerInThisBrowser(string $email = 'new@example.test'): void
{
    test()->post('/register', [
        'name' => 'New Customer',
        'email' => $email,
        'password' => 'a-long-password-1',
        'password_confirmation' => 'a-long-password-1',
    ])->assertRedirect(route('verification.pending'));
}

/** What the verification link does, on whatever device it is clicked. */
function clickVerificationLink(string $email): User
{
    return PendingUserVerification::where('email', $email)->sole()->createVerifiedUser();
}

it('does not log a guest in as a verified user named in the query string', function (string $route) {
    $admin = User::factory()->create(['role' => 'admin', 'email' => 'owner@shop.test', 'email_verified_at' => now()]);

    $response = $this->getJson(route($route, ['email' => $admin->email]));

    $this->assertGuest();
    $response->assertOk()->assertJsonMissingPath('user_id');
    expect($response->json('authenticated'))->not->toBeTrue();
})->with(['verification.status', 'api.verification.status']);

it('answers the same for a verified, a pending and an unknown email', function (string $route) {
    User::factory()->create(['email' => 'verified@example.test', 'email_verified_at' => now()]);
    PendingUserVerification::factory()->create(['email' => 'pending@example.test']);

    $bodies = collect(['verified@example.test', 'pending@example.test', 'nobody@example.test'])
        ->map(fn (string $email) => $this->getJson(route($route, ['email' => $email]))->assertOk()->json())
        ->unique(fn ($body) => json_encode($body));

    expect($bodies)->toHaveCount(1);
    $this->assertGuest();
})->with(['verification.status', 'api.verification.status']);

it('logs the registering browser in once the email is verified', function () {
    registerInThisBrowser();
    $user = clickVerificationLink('new@example.test');

    $response = $this->getJson(route('verification.status', ['email' => 'new@example.test']));

    $response->assertOk()
        ->assertJson(['verified' => true, 'authenticated' => true, 'redirect_url' => route('dashboard')])
        ->assertJsonMissingPath('user_id');
    $this->assertAuthenticatedAs($user);
});

it('still shows the registering browser the pending and expired states', function () {
    registerInThisBrowser();

    $this->getJson(route('verification.status', ['email' => 'new@example.test']))
        ->assertOk()->assertJson(['verified' => false, 'pending' => true]);

    PendingUserVerification::where('email', 'new@example.test')->update(['token_expires_at' => now()->subMinute()]);

    $this->getJson(route('verification.status', ['email' => 'new@example.test']))
        ->assertOk()->assertJson(['verified' => false, 'expired' => true]);
    $this->assertGuest();
});

it('does not log in a different browser', function () {
    registerInThisBrowser();
    $this->flushSession(); // another browser: no registration in its session
    clickVerificationLink('new@example.test');

    $response = $this->getJson(route('verification.status', ['email' => 'new@example.test']));

    $this->assertGuest();
    $response->assertOk()->assertJsonMissingPath('user_id');
});

it('does not let a browser that registered one email log in as another', function () {
    registerInThisBrowser('mine@example.test');
    PendingUserVerification::factory()->create(['email' => 'theirs@example.test']);
    clickVerificationLink('theirs@example.test');

    $this->getJson(route('verification.status', ['email' => 'theirs@example.test']))->assertOk();

    $this->assertGuest();
});

it('logs the registering browser in only once', function () {
    registerInThisBrowser();
    clickVerificationLink('new@example.test');

    $this->getJson(route('verification.status', ['email' => 'new@example.test']));
    $this->assertAuthenticated();
    Auth::logout();

    $this->getJson(route('verification.status', ['email' => 'new@example.test']))->assertOk();

    $this->assertGuest();
});

it('sends the registering browser to the login page when the verification is not recent', function () {
    registerInThisBrowser();
    clickVerificationLink('new@example.test');
    $this->travel(31)->minutes();

    $this->getJson(route('verification.status', ['email' => 'new@example.test']))
        ->assertOk()
        ->assertJson(['verified' => true, 'authenticated' => false, 'redirect_url' => route('login')])
        ->assertJsonMissingPath('user_id');

    $this->assertGuest();
});

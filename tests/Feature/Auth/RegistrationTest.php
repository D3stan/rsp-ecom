<?php

use App\Models\PendingUserVerification;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PendingUserEmailVerification;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register and are sent verification email', function () {
    Notification::fake();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    // User should NOT be authenticated immediately (pending verification flow)
    $this->assertGuest();

    // Should create a pending verification, not a user
    $this->assertFalse(User::where('email', 'test@example.com')->exists());
    $this->assertTrue(PendingUserVerification::where('email', 'test@example.com')->exists());

    // Should send verification email
    Notification::assertSentTo(
        [new \Illuminate\Notifications\AnonymousNotifiable()],
        PendingUserEmailVerification::class
    );

    // Should redirect to pending verification page
    $response->assertRedirect(route('verification.pending'));
});
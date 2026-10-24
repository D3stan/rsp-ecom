<?php

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * bootstrap/app.php renders Errors/ServerError for unexpected exceptions, but not in the testing or local-debug
 * environment, so these tests pretend to be production.
 */

beforeEach(function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.debug' => false]);
});

function inertiaHeaders(): array
{
    return ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) Inertia::getVersion()];
}

it('redirects a guest to the login page instead of rendering the 500 page', function () {
    $this->get('/settings/profile')->assertRedirect(route('login'));
});

it('redirects a guest to the login page for an Inertia visit too', function () {
    $this->get('/settings/profile', inertiaHeaders())->assertRedirect(route('login'));
});

it('keeps the status of an expired CSRF token', function () {
    Route::get('/_test/csrf', fn () => throw new TokenMismatchException);

    $this->get('/_test/csrf')->assertStatus(419);
});

it('keeps the status of a method that is not allowed', function () {
    $this->post('/')->assertStatus(405);
});

it('still renders the server error page for an unexpected exception', function () {
    Route::get('/_test/boom', fn () => throw new RuntimeException('boom'));

    $this->get('/_test/boom', inertiaHeaders())
        ->assertStatus(500)
        ->assertJsonPath('component', 'Errors/ServerError');
});

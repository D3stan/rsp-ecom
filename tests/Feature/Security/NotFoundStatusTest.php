<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Unknown URLs are caught by Route::fallback, which renders the "not found" page. It must answer HTTP 404, not 200
 * (a soft 404 gets indexed by search engines and hides broken links from monitoring).
 */

it('answers an unknown URL with the not-found page and HTTP 404', function () {
    $this->get('/this-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Errors/NotFound'));
});

it('answers an Inertia visit to an unknown URL with the not-found page as JSON and HTTP 404', function () {
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    $this->get('/this-does-not-exist/either', array_filter([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => $version,
    ]))
        ->assertNotFound()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'Errors/NotFound');
});

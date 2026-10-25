<?php

use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'sentry.frontend_dsn' => 'https://public@o0.ingest.sentry.io/1',
        'sentry.environment' => 'sandbox',
        'sentry.release' => 'build-1',
    ]);
});

function consentCookie(array $preferences): array
{
    return [config('cookieconsent.cookie.name'), json_encode(['consent_at' => time(), ...$preferences])];
}

it('shares the browser Sentry configuration with every page', function () {
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('sentry.dsn', 'https://public@o0.ingest.sentry.io/1')
        ->where('sentry.environment', 'sandbox')
        ->where('sentry.release', 'build-1'));
});

it('does not enable session replay for a visitor who has not consented', function () {
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('sentry.replay', false));
});

it('does not enable session replay when the visitor accepted essentials only', function () {
    [$name, $value] = consentCookie(['sentry_replay' => false]);

    $this->withCookie($name, $value)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('sentry.replay', false));
});

it('enables session replay once the visitor accepted the analytics cookies', function () {
    [$name, $value] = consentCookie(['sentry_replay' => true]);

    $this->withCookie($name, $value)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('sentry.replay', true));
});

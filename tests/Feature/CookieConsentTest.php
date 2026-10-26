<?php

use App\Support\CookieDuration;
use Inertia\Testing\AssertableInertia as Assert;

function consentCookieFor(array $preferences): array
{
    return [config('cookieconsent.cookie.name'), json_encode(['consent_at' => time(), ...$preferences])];
}

it('accepts all cookies without an error', function () {
    $this->post(route('cookieconsent.accept.all'))->assertRedirect()->assertCookie(config('cookieconsent.cookie.name'));
});

it('accepts a custom selection of categories', function () {
    $this->post(route('cookieconsent.accept.configuration'), ['categories' => ['essentials', 'optional']])
        ->assertRedirect()
        ->assertCookie(config('cookieconsent.cookie.name'));
});

it('accepts essential cookies only', function () {
    $this->post(route('cookieconsent.accept.essentials'))->assertRedirect()->assertCookie(config('cookieconsent.cookie.name'));
});

it('shares that interface preferences are not allowed before the visitor chooses', function () {
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('cookieConsent.preferences', false));
});

it('shares that interface preferences are not allowed when only essentials were accepted', function () {
    [$name, $value] = consentCookieFor(['appearance' => false, 'sidebar_state' => false, 'locale' => false]);

    $this->withCookie($name, $value)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('cookieConsent.preferences', false));
});

it('shares that interface preferences are allowed once the visitor accepted the optional cookies', function () {
    [$name, $value] = consentCookieFor(['appearance' => true, 'sidebar_state' => true, 'locale' => true]);

    $this->withCookie($name, $value)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('cookieConsent.preferences', true));
});

it('lists the cookies the application really sets on the cookie policy page', function () {
    $this->get(route('cookies'))->assertInertia(fn (Assert $page) => $page->where('categories', function ($categories) {
        $names = collect($categories)->flatMap(fn ($category) => collect($category['cookies'])->pluck('name'));

        expect($names)->toContain(config('session.cookie'), 'XSRF-TOKEN', 'appearance', 'sidebar_state', 'sentry_replay')
            ->not->toContain('darkmode_enabled');

        return true;
    }));
});

it('describes every listed cookie in the visitor language', function () {
    $this->withSession(['locale' => 'it'])->get(route('cookies'))
        ->assertInertia(fn (Assert $page) => $page->where('categories', function ($categories) {
            $descriptions = collect($categories)->flatMap(fn ($category) => collect($category['cookies'])->pluck('description'));

            expect($descriptions->filter(fn (string $description) => str_contains($description, 'cookieConsent::')))->toBeEmpty()
                ->and($descriptions)->toContain(__('cookieConsent::cookies.defaults.appearance', [], 'it'));

            return true;
        }));
});

it('links the cookie banner to the cookie policy', function () {
    $this->get(route('home'))->assertSee(route('cookies'), false);
});

it('states cookie lifetimes in whole calendar units', function (int $minutes, string $expected) {
    expect(CookieDuration::forHumans($minutes))->toBe($expected);
})->with([
    'browser session' => [0, 'Browser session'],
    'two hours' => [120, '2 Hours'],
    'a week' => [10080, '7 Days'],
    'a year' => [525600, '1 Year'],
    'the remember-me cookie' => [576000, '400 Days'],
]);

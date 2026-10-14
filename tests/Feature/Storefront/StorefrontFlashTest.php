<?php

use App\Models\User;
use App\Settings\Settings;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * P0-5: storefront controllers report their outcome as session flashes (->with('success'|'error', ...)), shared with
 * every page as `flash`. Pages must read `flash`: the page-level `success` / `error` props they used to read are
 * never sent.
 */

it('reports a sent contact message as flash.success on the next page, not as a page prop', function () {
    Mail::fake();

    $this->followingRedirects()
        ->from(route('contact'))
        ->post(route('contact.store'), [
            'firstName' => 'Mario',
            'lastName' => 'Rossi',
            'email' => 'mario@visitor.test',
            'subject' => 'Where is my order?',
            'message' => 'Hello, I would like to know where my order is.',
        ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contact')
            ->where('flash.success', fn ($message) => is_string($message) && $message !== '')
            ->where('flash.id', fn ($id) => is_string($id))
            ->missing('success')
            ->missing('error'));
});

it('reports why the login page was shown as flash.error, not as an error prop', function () {
    app(Settings::class)->set('orders.guest_checkout_enabled', false, User::factory()->create(['role' => 'admin']));

    $this->followingRedirects()
        ->get(route('guest.checkout.details'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('flash.error', __('checkout.guest_checkout_disabled'))
            ->missing('error'));
});

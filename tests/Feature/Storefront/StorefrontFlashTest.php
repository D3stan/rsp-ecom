<?php

use App\Models\Cart;
use App\Models\User;
use App\Settings\Settings;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

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

it('does not toast a cart quantity change (the new quantity is visible on screen)', function () {
    $user = User::factory()->create();
    $product = CatalogFixtures::product(['stock_quantity' => 10]);
    $variant = CatalogFixtures::variant($product, ['stock_quantity' => 10, 'price' => 10]);
    $cart = Cart::create(['user_id' => $user->id]);
    $item = $cart->cartItems()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'price' => 10,
        'size_id' => $product->size_id,
    ]);

    // As the cart page sends it: an Inertia visit, not JSON
    $this->actingAs($user)->from(route('cart'))
        ->put(route('cart.update', $item), ['quantity' => 2], ['X-Inertia' => 'true'])
        ->assertRedirect(route('cart'))
        ->assertSessionMissing('success');

    expect($item->fresh()->quantity)->toBe(2);
});

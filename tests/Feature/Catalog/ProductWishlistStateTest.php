<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

/*
 * The product page's wishlist heart must start from the real state (pages/product.tsx passed
 * initialInWishlist={false}, so the first click on a saved product removed it).
 */

beforeEach(function () {
    $this->product = CatalogFixtures::product();
    CatalogFixtures::variant($this->product);
});

it('tells the product page that the product is in the signed-in user\'s wishlist', function () {
    $user = User::factory()->create();
    $user->addToWishlist($this->product);

    $this->actingAs($user)->get(route('products.show', $this->product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('product.inWishlist', true));
});

it('tells the product page that the product is not in the wishlist', function () {
    $user = User::factory()->create();
    $user->addToWishlist(CatalogFixtures::product());

    $this->actingAs($user)->get(route('products.show', $this->product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('product.inWishlist', false));
});

it('sends false to guests', function () {
    $this->get(route('products.show', $this->product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('product.inWishlist', false));
});

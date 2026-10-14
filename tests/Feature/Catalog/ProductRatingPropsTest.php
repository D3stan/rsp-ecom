<?php

use App\Models\Review;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

/*
 * The product page shows the real rating: average and count of the APPROVED reviews (pages/product.tsx used to
 * render renderStars(0) and a literal "(0 reviews)").
 */

function reviewFor($product, int $rating, bool $approved): Review
{
    return Review::factory()->create([
        'product_id' => $product->id,
        'user_id' => User::factory()->create()->id,
        'rating' => $rating,
        'is_approved' => $approved,
    ]);
}

it('sends the average rating and the count of approved reviews to the product page', function () {
    $product = CatalogFixtures::product();
    CatalogFixtures::variant($product);
    reviewFor($product, 4, true);
    reviewFor($product, 5, true);
    reviewFor($product, 1, false); // pending moderation: not counted

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('product')
            ->where('product.average_rating', 4.5)
            ->where('product.review_count', 2));
});

it('sends a zero count and no average for a product without approved reviews', function () {
    $product = CatalogFixtures::product();
    CatalogFixtures::variant($product);
    reviewFor($product, 2, false);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.average_rating', null)
            ->where('product.review_count', 0));
});

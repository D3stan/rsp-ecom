<?php

use App\Models\Review;
use App\Models\ReviewMedia;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;
use Tests\Support\ReviewPhotoFixtures;

/*
 * H3: the home page shows a "Rider gallery" of featured, approved photos (max 12), each linking to its product.
 * Only photos that are approved, featured, on an approved review and an active product may appear.
 */

beforeEach(function () {
    Storage::fake('public');
});

function featuredPhoto($product, array $overrides = []): ReviewMedia
{
    $review = Review::factory()->create(array_merge([
        'product_id' => $product->id,
        'user_id' => User::factory()->create()->id,
        'is_approved' => true,
    ], $overrides['review'] ?? []));

    return ReviewPhotoFixtures::storedMedia($review, ReviewMedia::STATUS_APPROVED, ['featured' => true] + ($overrides['media'] ?? []));
}

it('sends featured approved photos to the home page, newest first, linking to their product', function () {
    $product = CatalogFixtures::product();
    $first = featuredPhoto($product);
    $second = featuredPhoto($product);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('riderGallery', 2)
            ->where('riderGallery.0.id', $second->id)
            ->where('riderGallery.0.thumbnail_url', Storage::disk('public')->url($second->thumbnail_path))
            ->where('riderGallery.0.url', Storage::disk('public')->url($second->path))
            ->where('riderGallery.0.width', $second->width)
            ->where('riderGallery.0.height', $second->height)
            ->where('riderGallery.0.product.slug', $product->slug)
            ->where('riderGallery.0.product.name', $product->name)
            ->where('riderGallery.1.id', $first->id));
});

it('sends an empty gallery when nothing is featured', function () {
    $product = CatalogFixtures::product();
    ReviewPhotoFixtures::storedMedia(
        Review::factory()->create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'is_approved' => true]),
        ReviewMedia::STATUS_APPROVED,
    ); // approved but not featured

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('riderGallery', []));
});

it('never features a photo that is not approved, whose review is unapproved, or whose product is inactive', function () {
    $active = CatalogFixtures::product();
    $inactive = CatalogFixtures::product(['status' => 'draft']);

    // pending media, featured flag set
    featuredPhoto($active, ['media' => ['status' => ReviewMedia::STATUS_PENDING]]);
    // approved+featured media on an unapproved review
    featuredPhoto($active, ['review' => ['is_approved' => false]]);
    // approved+featured on an inactive product
    featuredPhoto($inactive);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('riderGallery', []));
});

it('caps the gallery at 12 photos', function () {
    $product = CatalogFixtures::product();
    foreach (range(1, 14) as $i) {
        featuredPhoto($product);
    }

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('riderGallery', 12));
});

<?php

use App\Models\Review;
use App\Models\ReviewMedia;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;
use Tests\Support\ReviewPhotoFixtures;

/*
 * H3: the product page shows a rating distribution, approved photos and verified reviews, and the Product JSON-LD
 * carries aggregateRating plus up to 5 review entries consistent with what is shown.
 */

beforeEach(function () {
    Storage::fake('public');
    $this->product = CatalogFixtures::product();
    CatalogFixtures::variant($this->product);
});

function approvedReview($product, int $rating, string $comment, ?string $name = null): Review
{
    $user = User::factory()->create($name ? ['name' => $name] : []);

    return Review::factory()->create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'rating' => $rating,
        'comment' => $comment,
        'is_approved' => true,
    ]);
}

it('sends a rating distribution of the approved reviews that adds up to the review count', function () {
    approvedReview($this->product, 5, 'a');
    approvedReview($this->product, 5, 'b');
    approvedReview($this->product, 4, 'c');
    approvedReview($this->product, 1, 'd');
    // pending and rejected do not count
    Review::factory()->create(['product_id' => $this->product->id, 'user_id' => User::factory()->create()->id, 'rating' => 3, 'is_approved' => false]);

    $this->get(route('products.show', $this->product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.review_count', 4)
            ->where('product.rating_distribution.5', 2)
            ->where('product.rating_distribution.4', 1)
            ->where('product.rating_distribution.3', 0)
            ->where('product.rating_distribution.2', 0)
            ->where('product.rating_distribution.1', 1)
            ->where('product.rating_distribution', fn ($distribution) => $distribution->sum() === 4));
});

it('sends an all-zero distribution for a product without approved reviews', function () {
    Review::factory()->create(['product_id' => $this->product->id, 'user_id' => User::factory()->create()->id, 'rating' => 5, 'is_approved' => false]);

    $this->get(route('products.show', $this->product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.review_count', 0)
            ->where('product.rating_distribution', fn ($distribution) => $distribution->sum() === 0));
});

it('adds aggregateRating and up to 5 review entries to the Product JSON-LD, matching what is shown', function () {
    foreach (range(1, 6) as $i) {
        approvedReview($this->product, 5, "Great light number {$i}", "Rider {$i}");
    }

    $html = $this->get(route('products.show', $this->product->slug))->assertOk()->getContent();

    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches);
    $blocks = [];
    preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $all);
    foreach ($all[1] as $json) {
        $blocks[] = json_decode($json, true);
    }
    $product = collect($blocks)->firstWhere('@type', 'Product');

    expect($product)->not->toBeNull()
        ->and($product['aggregateRating']['ratingValue'])->toBe(5)
        ->and($product['aggregateRating']['reviewCount'])->toBe(6)
        ->and($product['review'])->toHaveCount(5)
        ->and($product['review'][0]['@type'])->toBe('Review')
        ->and($product['review'][0]['author']['name'])->toBe('Rider 1')
        ->and($product['review'][0]['reviewRating']['ratingValue'])->toBe('5')
        ->and($product['review'][0]['reviewBody'])->toBe('Great light number 1');
});

it('omits the review entries from the JSON-LD when there are no approved reviews', function () {
    $html = $this->get(route('products.show', $this->product->slug))->assertOk()->getContent();

    preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $all);
    $product = collect($all[1])->map(fn ($j) => json_decode($j, true))->firstWhere('@type', 'Product');

    expect($product)->not->toBeNull()
        ->and($product)->not->toHaveKey('aggregateRating')
        ->and($product)->not->toHaveKey('review');
});

it('sends approved photos with the reviews and marks them verified purchases', function () {
    $review = approvedReview($this->product, 5, 'with a photo');
    $media = ReviewPhotoFixtures::storedMedia($review, ReviewMedia::STATUS_APPROVED);
    ReviewPhotoFixtures::storedMedia($review, ReviewMedia::STATUS_PENDING);

    $this->get(route('products.show', $this->product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('reviews', 1)
            ->where('reviews.0.verified_purchase', true)
            ->has('reviews.0.photos', 1)
            ->where('reviews.0.photos.0.id', $media->id));
});

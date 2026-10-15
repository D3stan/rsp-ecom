<?php

use App\Models\Review;
use App\Models\ReviewMedia;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;
use Tests\Support\ReviewPhotoFixtures;

/*
 * H2: admins moderate review photos one by one (approve / reject / feature on the home page) or approve a review
 * together with its photos. Rejecting a review rejects its photos; deleting a review, the product or the customer's
 * account removes the files too (the DB cascade alone would leave them on the public disk).
 */

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->customer = User::factory()->create(['role' => 'customer']);
    $this->product = CatalogFixtures::product();
    $this->review = Review::factory()->create([
        'product_id' => $this->product->id,
        'user_id' => $this->customer->id,
        'is_approved' => false,
    ]);
});

function mediaRoute(string $action, ReviewMedia $media): string
{
    return route("admin.reviews.media.{$action}", ['review' => $media->review_id, 'media' => $media->id]);
}

it('approves and rejects a single photo', function () {
    $media = ReviewPhotoFixtures::storedMedia($this->review);
    $other = ReviewPhotoFixtures::storedMedia($this->review);

    $this->actingAs($this->admin)->patch(mediaRoute('approve', $media))->assertRedirect()->assertSessionHas('success');
    expect($media->fresh()->status)->toBe(ReviewMedia::STATUS_APPROVED)
        ->and($other->fresh()->status)->toBe(ReviewMedia::STATUS_PENDING);

    $this->actingAs($this->admin)->patch(mediaRoute('reject', $media))->assertRedirect()->assertSessionHas('success');
    expect($media->fresh()->status)->toBe(ReviewMedia::STATUS_REJECTED);

    // A rejected photo can be approved again
    $this->actingAs($this->admin)->patch(mediaRoute('approve', $media))->assertRedirect();
    expect($media->fresh()->status)->toBe(ReviewMedia::STATUS_APPROVED);
});

it('features only approved photos on the home page, and rejecting a photo un-features it', function () {
    $pending = ReviewPhotoFixtures::storedMedia($this->review);
    $approved = ReviewPhotoFixtures::storedMedia($this->review, ReviewMedia::STATUS_APPROVED);

    expect($approved->featured)->toBeFalse();

    $this->actingAs($this->admin)->patch(mediaRoute('feature', $pending), ['featured' => true])
        ->assertRedirect()->assertSessionHas('error');
    expect($pending->fresh()->featured)->toBeFalse();

    $this->actingAs($this->admin)->patch(mediaRoute('feature', $approved), ['featured' => true])
        ->assertRedirect()->assertSessionHas('success');
    expect($approved->fresh()->featured)->toBeTrue();

    $this->actingAs($this->admin)->patch(mediaRoute('feature', $approved), ['featured' => false])->assertRedirect();
    expect($approved->fresh()->featured)->toBeFalse();

    $this->actingAs($this->admin)->patch(mediaRoute('feature', $approved), ['featured' => true]);
    $this->actingAs($this->admin)->patch(mediaRoute('reject', $approved));
    expect($approved->fresh())->status->toBe(ReviewMedia::STATUS_REJECTED)->featured->toBeFalse();
});

it('approves a review together with its pending photos, leaving rejected ones rejected', function () {
    $pending = ReviewPhotoFixtures::storedMedia($this->review);
    $pending2 = ReviewPhotoFixtures::storedMedia($this->review);
    $rejected = ReviewPhotoFixtures::storedMedia($this->review, ReviewMedia::STATUS_REJECTED);
    $otherReview = Review::factory()->create(['product_id' => $this->product->id, 'is_approved' => false]);
    $untouched = ReviewPhotoFixtures::storedMedia($otherReview);

    $this->actingAs($this->admin)
        ->patch(route('admin.reviews.approve-with-media', $this->review))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->review->fresh()->is_approved)->toBeTrue()
        ->and($pending->fresh()->status)->toBe(ReviewMedia::STATUS_APPROVED)
        ->and($pending2->fresh()->status)->toBe(ReviewMedia::STATUS_APPROVED)
        ->and($rejected->fresh()->status)->toBe(ReviewMedia::STATUS_REJECTED)
        ->and($untouched->fresh()->status)->toBe(ReviewMedia::STATUS_PENDING);
});

it('rejects (and un-features) the photos when the review is rejected, on every reject path', function (string $path) {
    $this->review->update(['is_approved' => true]);
    $approved = ReviewPhotoFixtures::storedMedia($this->review, ReviewMedia::STATUS_APPROVED, ['featured' => true]);
    $pending = ReviewPhotoFixtures::storedMedia($this->review);

    $this->actingAs($this->admin);
    match ($path) {
        'reject' => $this->patch(route('admin.reviews.reject', $this->review)),
        'update' => $this->patch(route('admin.reviews.update', $this->review), ['is_approved' => false]),
        'bulk' => $this->patch(route('admin.reviews.bulk-update'), ['review_ids' => [$this->review->id], 'action' => 'reject']),
    };

    expect($this->review->fresh()->is_approved)->toBeFalse()
        ->and($approved->fresh())->status->toBe(ReviewMedia::STATUS_REJECTED)->featured->toBeFalse()
        ->and($pending->fresh()->status)->toBe(ReviewMedia::STATUS_REJECTED);
})->with(['reject', 'update', 'bulk']);

it('does not touch photos when a review is approved without them', function () {
    $pending = ReviewPhotoFixtures::storedMedia($this->review);

    $this->actingAs($this->admin)->patch(route('admin.reviews.approve', $this->review));

    expect($this->review->fresh()->is_approved)->toBeTrue()
        ->and($pending->fresh()->status)->toBe(ReviewMedia::STATUS_PENDING);
});

it('refuses a photo addressed through another review', function () {
    $otherReview = Review::factory()->create(['product_id' => $this->product->id]);
    $media = ReviewPhotoFixtures::storedMedia($otherReview);

    $this->actingAs($this->admin)
        ->patch(route('admin.reviews.media.approve', ['review' => $this->review->id, 'media' => $media->id]))
        ->assertNotFound();

    expect($media->fresh()->status)->toBe(ReviewMedia::STATUS_PENDING);
});

it('forbids customers and redirects guests on every moderation route', function () {
    $media = ReviewPhotoFixtures::storedMedia($this->review);
    $requests = [
        ['patch', mediaRoute('approve', $media), []],
        ['patch', mediaRoute('reject', $media), []],
        ['patch', mediaRoute('feature', $media), ['featured' => true]],
        ['patch', route('admin.reviews.approve-with-media', $this->review), []],
    ];

    foreach ($requests as [$method, $url, $data]) {
        $this->actingAs($this->customer)->{$method}($url, $data)->assertForbidden();
    }

    auth()->logout();
    foreach ($requests as [$method, $url, $data]) {
        $this->{$method}($url, $data)->assertRedirect(route('login'));
    }

    expect($media->fresh())->status->toBe(ReviewMedia::STATUS_PENDING)->featured->toBeFalse()
        ->and($this->review->fresh()->is_approved)->toBeFalse();
});

it('shows every photo with its state to the admin, and filters reviews with photos to check', function () {
    $media = ReviewPhotoFixtures::storedMedia($this->review);
    $approvedReview = Review::factory()->create(['product_id' => $this->product->id, 'is_approved' => true]);
    ReviewPhotoFixtures::storedMedia($approvedReview); // text auto-approved, photo still waiting
    Review::factory()->create(['product_id' => $this->product->id, 'is_approved' => true]); // no photos

    $this->actingAs($this->admin)->get(route('admin.reviews.show', $this->review))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('review.media', 1)
            ->where('review.media.0.id', $media->id)
            ->where('review.media.0.status', ReviewMedia::STATUS_PENDING)
            ->where('review.media.0.featured', false)
            ->where('review.media.0.url', Storage::disk('public')->url($media->path))
            ->where('review.media.0.thumbnail_url', Storage::disk('public')->url($media->thumbnail_path)));

    $this->actingAs($this->admin)->get(route('admin.reviews.index', ['status' => 'photos']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.pending_photos', 2)
            ->has('reviews.data', 2)
            ->has('reviews.data.0.media', 1)
            ->where('reviews.data.0.media.0.status', ReviewMedia::STATUS_PENDING));
});

it('deletes the photo files when an admin deletes the review', function () {
    $media = ReviewPhotoFixtures::storedMedia($this->review);
    $otherReview = Review::factory()->create(['product_id' => $this->product->id]);
    $kept = ReviewPhotoFixtures::storedMedia($otherReview);

    $this->actingAs($this->admin)->delete(route('admin.reviews.destroy', $this->review))->assertRedirect();

    $disk = Storage::disk('public');
    expect(ReviewMedia::find($media->id))->toBeNull()
        ->and($disk->exists($media->path))->toBeFalse()
        ->and($disk->exists($media->thumbnail_path))->toBeFalse()
        ->and($disk->exists($kept->path))->toBeTrue()
        ->and($disk->exists($kept->thumbnail_path))->toBeTrue();
});

it('deletes the photo files when the customer deletes their account', function () {
    $media = ReviewPhotoFixtures::storedMedia($this->review, ReviewMedia::STATUS_APPROVED);
    $kept = ReviewPhotoFixtures::storedMedia(Review::factory()->create(['product_id' => $this->product->id]));

    $this->actingAs($this->customer)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    $disk = Storage::disk('public');
    expect(User::find($this->customer->id))->toBeNull()
        ->and(Review::find($this->review->id))->toBeNull()
        ->and($disk->exists($media->path))->toBeFalse()
        ->and($disk->exists($media->thumbnail_path))->toBeFalse()
        ->and($disk->exists($kept->path))->toBeTrue();
});

it('deletes the photo files when the product is deleted', function () {
    $media = ReviewPhotoFixtures::storedMedia($this->review);
    $otherProduct = CatalogFixtures::product();
    $kept = ReviewPhotoFixtures::storedMedia(Review::factory()->create(['product_id' => $otherProduct->id]));

    // Model level: Admin\ProductController@destroy ends in $product->delete() (the DB cascade removes the reviews)
    $this->product->delete();

    $disk = Storage::disk('public');
    expect(Review::find($this->review->id))->toBeNull()
        ->and($disk->exists($media->path))->toBeFalse()
        ->and($disk->exists($kept->path))->toBeTrue();
});

<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\ReviewMedia;
use App\Models\User;
use App\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;
use Tests\Support\ReviewPhotoFixtures;

/*
 * H1: buyers can attach up to 4 photos to a review. The server sniffs the content, re-encodes to WebP (which drops
 * EXIF/GPS), caps the long edge at 2000 px, makes a 600 px thumbnail and stores both under an unguessable name.
 * Photos always start `pending`, even when reviews.auto_approve publishes the text, and only approved photos reach
 * the storefront.
 */

beforeEach(function () {
    Storage::fake('public');
    $this->customer = User::factory()->create(['role' => 'customer']);
    $this->product = CatalogFixtures::product();
    CatalogFixtures::variant($this->product);
    $order = Order::factory()->create(['user_id' => $this->customer->id, 'status' => 'delivered']);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $this->product->id]);
});

function submitReviewWithPhotos($test, array $photos)
{
    return $test->actingAs($test->customer)->post(route('reviews.store'), [
        'product_id' => $test->product->id,
        'rating' => 5,
        'comment' => 'Fits perfectly',
        'photos' => $photos,
    ]);
}

it('stores photos as pending WebP media under reviews/{review}/ with a capped size and a thumbnail', function () {
    submitReviewWithPhotos($this, [ReviewPhotoFixtures::jpeg(2400, 1200), ReviewPhotoFixtures::png(800, 600)])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('orders.index'));

    $review = Review::sole();
    $media = ReviewMedia::where('review_id', $review->id)->orderBy('sort_order')->get();

    // reviews.auto_approve is on by default: the text is published, the photos still wait for moderation
    expect($review->is_approved)->toBeTrue()
        ->and($media)->toHaveCount(2)
        ->and($media->pluck('status')->all())->toBe([ReviewMedia::STATUS_PENDING, ReviewMedia::STATUS_PENDING])
        ->and($media->pluck('sort_order')->all())->toBe([0, 1]);

    $disk = Storage::disk('public');
    foreach ($media as $item) {
        expect($item->path)->toMatch('#^reviews/'.$review->id.'/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}\.webp$#')
            ->and($disk->exists($item->path))->toBeTrue()
            ->and($disk->exists($item->thumbnail_path))->toBeTrue()
            ->and(getimagesizefromstring($disk->get($item->path))['mime'])->toBe('image/webp');
    }

    // Long edge capped at 2000 px; a smaller image is not upscaled
    expect([$media[0]->width, $media[0]->height])->toBe([2000, 1000])
        ->and([$media[1]->width, $media[1]->height])->toBe([800, 600]);

    // 600 px thumbnail
    [$thumbWidth, $thumbHeight] = getimagesizefromstring($disk->get($media[0]->thumbnail_path));
    expect([$thumbWidth, $thumbHeight])->toBe([600, 300]);
});

it('accepts WebP photos', function () {
    submitReviewWithPhotos($this, [ReviewPhotoFixtures::webp(640, 480)])->assertSessionHasNoErrors();

    $media = ReviewMedia::sole();
    expect([$media->width, $media->height])->toBe([640, 480])
        ->and(Storage::disk('public')->exists($media->path))->toBeTrue();
});

it('tells the customer the photos wait for a check even when the text is published at once', function () {
    submitReviewWithPhotos($this, [ReviewPhotoFixtures::jpeg(400, 300)])
        ->assertSessionHas('success', __('reviews.photos_pending'));
});

it('keeps photos pending when the review itself waits for moderation', function () {
    app(Settings::class)->set('reviews.auto_approve', false, User::factory()->create(['role' => 'admin']));

    submitReviewWithPhotos($this, [ReviewPhotoFixtures::jpeg(400, 300)])->assertSessionHasNoErrors();

    expect(Review::sole()->is_approved)->toBeFalse()
        ->and(ReviewMedia::sole()->status)->toBe(ReviewMedia::STATUS_PENDING);
});

it('refuses more than 4 photos and creates nothing', function () {
    $photos = array_map(fn () => ReviewPhotoFixtures::jpeg(300, 200), range(1, 5));

    submitReviewWithPhotos($this, $photos)->assertSessionHasErrors('photos');

    expect(Review::count())->toBe(0)
        ->and(ReviewMedia::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('refuses a non-image disguised as .jpg', function () {
    submitReviewWithPhotos($this, [ReviewPhotoFixtures::disguised()])->assertSessionHasErrors('photos.0');

    expect(Review::count())->toBe(0)
        ->and(ReviewMedia::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('refuses a truncated image that only looks like a JPEG', function () {
    $header = substr((string) ReviewPhotoFixtures::jpeg(400, 300)->getContent(), 0, 200);

    submitReviewWithPhotos($this, [ReviewPhotoFixtures::upload($header, 'broken.jpg', 'image/jpeg')])
        ->assertSessionHasErrors('photos.0');

    expect(Review::count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('strips EXIF and GPS data from the stored photo and its thumbnail', function () {
    $upload = ReviewPhotoFixtures::jpeg(1200, 800, withExif: true);

    // Precondition: the upload really carries camera and GPS data
    $exif = exif_read_data($upload->getPathname(), null, true);
    expect($exif['IFD0']['Make'])->toBe('SpyCam')
        ->and($exif['GPS']['GPSLatitudeRef'])->toBe('N');

    submitReviewWithPhotos($this, [$upload])->assertSessionHasNoErrors();

    $media = ReviewMedia::sole();
    foreach ([$media->path, $media->thumbnail_path] as $path) {
        $stored = Storage::disk('public')->get($path);
        expect(substr($stored, 0, 4))->toBe('RIFF')
            ->and(substr($stored, 8, 4))->toBe('WEBP')
            ->and($stored)->not->toContain('SpyCam')
            ->and($stored)->not->toContain('Exif')
            ->and($stored)->not->toContain('EXIF');
    }
});

it('applies the EXIF orientation before dropping it, so phone photos are not sideways', function () {
    // Orientation 6 = the camera was rotated 90° clockwise: a 1200x800 sensor image is a portrait photo
    submitReviewWithPhotos($this, [ReviewPhotoFixtures::jpeg(1200, 800, orientation: 6)])->assertSessionHasNoErrors();

    $media = ReviewMedia::sole();
    expect([$media->width, $media->height])->toBe([800, 1200]);
});

it('sends only approved photos to the product page', function () {
    $review = Review::factory()->create([
        'product_id' => $this->product->id,
        'user_id' => $this->customer->id,
        'is_approved' => true,
    ]);
    $approved = ReviewMedia::create([
        'review_id' => $review->id, 'path' => "reviews/{$review->id}/approved.webp",
        'thumbnail_path' => "reviews/{$review->id}/approved-thumb.webp",
        'width' => 1600, 'height' => 1200, 'sort_order' => 1, 'status' => ReviewMedia::STATUS_APPROVED,
    ]);
    ReviewMedia::create([
        'review_id' => $review->id, 'path' => "reviews/{$review->id}/pending.webp",
        'thumbnail_path' => "reviews/{$review->id}/pending-thumb.webp",
        'width' => 1600, 'height' => 1200, 'sort_order' => 0, 'status' => ReviewMedia::STATUS_PENDING,
    ]);
    ReviewMedia::create([
        'review_id' => $review->id, 'path' => "reviews/{$review->id}/rejected.webp",
        'thumbnail_path' => "reviews/{$review->id}/rejected-thumb.webp",
        'width' => 1600, 'height' => 1200, 'sort_order' => 2, 'status' => ReviewMedia::STATUS_REJECTED,
    ]);

    $this->get(route('products.show', $this->product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('reviews', 1)
            ->has('reviews.0.photos', 1)
            ->where('reviews.0.photos.0.id', $approved->id)
            ->where('reviews.0.photos.0.url', Storage::disk('public')->url($approved->path))
            ->where('reviews.0.photos.0.thumbnail_url', Storage::disk('public')->url($approved->thumbnail_path))
            ->where('reviews.0.photos.0.width', 1600)
            ->where('reviews.0.photos.0.height', 1200));
});

it('never sends photos of a review that is not approved', function () {
    $review = Review::factory()->create([
        'product_id' => $this->product->id,
        'user_id' => $this->customer->id,
        'is_approved' => false,
    ]);
    ReviewMedia::create([
        'review_id' => $review->id, 'path' => "reviews/{$review->id}/a.webp", 'thumbnail_path' => "reviews/{$review->id}/a-thumb.webp",
        'width' => 10, 'height' => 10, 'sort_order' => 0, 'status' => ReviewMedia::STATUS_APPROVED,
    ]);

    $this->get(route('products.show', $this->product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('reviews', 0));
});

<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;
use App\Settings\Settings;
use Tests\Support\CatalogFixtures;

beforeEach(function () {
    $this->customer = User::factory()->create(['role' => 'customer']);
    $this->product = CatalogFixtures::product();
    $order = Order::factory()->create(['user_id' => $this->customer->id, 'status' => 'delivered']);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $this->product->id]);
});

function reviewApprovalSubmit($test)
{
    return $test->actingAs($test->customer)->post(route('reviews.store'), [
        'product_id' => $test->product->id,
        'rating' => 5,
        'comment' => 'Great',
    ]);
}

it('publishes a review immediately by default (reviews.auto_approve on)', function () {
    reviewApprovalSubmit($this)
        ->assertRedirect(route('orders.index'))
        ->assertSessionHas('success', 'Thank you for your review!');

    expect(Review::first()->is_approved)->toBeTrue()
        ->and($this->product->approvedReviews()->count())->toBe(1);
});

it('stores the review unapproved and says so when reviews.auto_approve is off', function () {
    app(Settings::class)->set('reviews.auto_approve', false, User::factory()->create(['role' => 'admin']));

    reviewApprovalSubmit($this)
        ->assertRedirect(route('orders.index'))
        ->assertSessionHas('success', fn ($message) => str_contains($message, 'approved it'));

    expect(Review::first()->is_approved)->toBeFalse()
        ->and($this->product->approvedReviews()->count())->toBe(0);
});

it('answers in the visitor locale when the review waits for moderation', function () {
    app(Settings::class)->set('reviews.auto_approve', false, User::factory()->create(['role' => 'admin']));
    $this->withSession(['locale' => 'it']);

    reviewApprovalSubmit($this)->assertSessionHas('success', fn ($message) => str_contains($message, 'approvazione'));
});

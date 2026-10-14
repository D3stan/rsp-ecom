<?php

use App\Models\Review;
use App\Models\User;
use Tests\Support\CatalogFixtures;

it('redirects to the reviews index with the flash after deleting a review', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $review = Review::factory()->create(['product_id' => CatalogFixtures::product()->id]);

    // Coming from the review's own page, back() would send the admin to the deleted review (404)
    $this->actingAs($admin)
        ->from(route('admin.reviews.show', $review))
        ->delete(route('admin.reviews.destroy', $review))
        ->assertRedirect(route('admin.reviews.index'))
        ->assertSessionHas('success', 'Review deleted successfully.');

    expect(Review::find($review->id))->toBeNull();
});

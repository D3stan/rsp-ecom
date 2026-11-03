<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CatalogFixtures;

/*
 * The "Flash Discount" / "Quick Discount" actions set products.compare_price, the "was" price the storefront strikes
 * through next to the current price.
 */

beforeEach(function () {
    Queue::fake();
});

it('sets and clears the compare price', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = CatalogFixtures::product(['price' => 100, 'compare_price' => null]);

    $this->actingAs($admin)
        ->patch(route('admin.products.quick-discount', $product), ['compare_price' => '130'])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->compare_price)->toBe(130.0)
        ->and($product->fresh()->discount_percentage)->toBe(23.08);

    $this->actingAs($admin)
        ->patch(route('admin.products.quick-discount', $product), ['compare_price' => ''])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->compare_price)->toBeNull();
});

it('rejects a compare price that is not above the current price', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = CatalogFixtures::product(['price' => 100, 'compare_price' => null]);

    $this->actingAs($admin)
        ->patch(route('admin.products.quick-discount', $product), ['compare_price' => '100'])
        ->assertSessionHasErrors('compare_price');

    expect($product->fresh()->compare_price)->toBeNull();
});

it('is admin only', function () {
    $product = CatalogFixtures::product(['price' => 100]);

    $this->actingAs(User::factory()->create())
        ->patch(route('admin.products.quick-discount', $product), ['compare_price' => '130']);

    expect($product->fresh()->compare_price)->toBeNull();
});

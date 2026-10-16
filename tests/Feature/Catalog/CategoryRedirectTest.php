<?php

use App\Models\Category;

it('301-redirects an active category to the filtered product list', function () {
    $category = Category::factory()->create(['is_active' => true]);

    $this->get("/categories/{$category->slug}?sort=price")
        ->assertStatus(301)
        ->assertRedirect(route('products', ['category' => $category->slug, 'sort' => 'price']));
});

it('404s for an inactive or unknown category', function () {
    $category = Category::factory()->create(['is_active' => false]);

    $this->get("/categories/{$category->slug}")->assertNotFound();
    $this->get('/categories/nope')->assertNotFound();
});

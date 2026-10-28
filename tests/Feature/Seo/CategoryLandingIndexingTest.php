<?php

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * /products?category={slug} is a category's landing page: /categories/{slug} redirects there and the sitemap lists it.
 * It used to be served noindex like any filtered list, so every category URL in the sitemap was one Google may not index.
 */

function robotsAndCanonicalOf(string $html): array
{
    preg_match('/<meta name="robots" content="([^"]*)"/', $html, $robots);
    preg_match_all('/<link rel="canonical" href="([^"]*)"/', $html, $canonicals);
    preg_match('/<meta property="og:url" content="([^"]*)"/', $html, $ogUrl);

    return [$robots[1] ?? null, $canonicals[1], $ogUrl[1] ?? null];
}

it('indexes an active category landing page with a canonical of just the category', function () {
    $category = Category::factory()->active()->create();

    [$robots, $canonicals, $ogUrl] = robotsAndCanonicalOf($this->get("/products?category={$category->slug}&utm_source=ad")->getContent());

    expect($robots)->not->toContain('noindex')
        ->and($canonicals)->toBe([route('products', ['category' => $category->slug])])
        ->and($ogUrl)->toBe($canonicals[0]);
});

it('keeps search, price, sort and unknown or inactive category lists noindex', function (string $query) {
    Category::factory()->active()->create(['slug' => 'lights']);
    Category::factory()->inactive()->create(['slug' => 'retired']);

    [$robots] = robotsAndCanonicalOf($this->get("/products?{$query}")->getContent());

    expect($robots)->toBe('noindex,follow');
})->with([
    'category with sort' => 'category=lights&sort=price',
    'category with price' => 'category=lights&min_price=10',
    'search' => 'search=angel',
    'unknown category' => 'category=nope',
    'inactive category' => 'category=retired',
]);

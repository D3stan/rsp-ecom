<?php

use App\Models\Product;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

beforeEach(fn () => Queue::fake());

function describedProduct(string $description, array $attributes = []): Product
{
    $product = CatalogFixtures::product($attributes);
    $product->defaultVariant->update(['images' => [], 'description' => $description]);

    return $product->fresh();
}

it('sends the rendered and the plain description of every variant to the product page', function () {
    $product = describedProduct("**Bright**\nsecond line");

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.variants.0.description', "**Bright**\nsecond line")
            ->where('product.variants.0.description_html', fn ($html) => str_contains($html, '<strong>Bright</strong>') && str_contains($html, '<br />'))
            ->where('product.variants.0.description_text', 'Bright second line'));
});

it('uses the plain text of the variant description as meta description when no SEO description is set', function () {
    $product = describedProduct('**Bright** angel eye light', ['seo_description' => null]);

    $this->get(route('products.show', $product->slug))
        ->assertSee('content="Bright angel eye light"', false);
});

it('prefers the SEO description for the meta description', function () {
    $product = describedProduct('**Bright** angel eye light', ['seo_description' => 'Hand written SEO text']);

    $html = $this->get(route('products.show', $product->slug))->getContent();

    // Only the <meta name="description"> tag is checked: the Inertia page JSON also carries the variant text
    preg_match('/<meta name="description" content="([^"]*)"/', $html, $meta);
    expect($meta[1] ?? null)->toBe('Hand written SEO text');
});

it('cannot break out of the JSON-LD script tag through the description', function () {
    $product = describedProduct('Nice &lt;/script&gt;&lt;script&gt;alert(1)&lt;/script&gt;', ['seo_description' => null]);

    $html = $this->get(route('products.show', $product->slug))->getContent();

    expect($html)->not->toContain('<script>alert(1)')
        ->and($html)->toContain('</script>')
        ->and($html)->toContain('{"@context":"https://schema.org","@type":"Product"');
});

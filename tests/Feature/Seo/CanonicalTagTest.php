<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CatalogFixtures;

uses(RefreshDatabase::class);

/*
 * Every page carries exactly one <link rel="canonical">. The layout used to print its own on top of the one SEOMeta
 * prints, so search engines saw two canonicals per page.
 */

function canonicalsOf(string $html): array
{
    preg_match_all('/<link rel="canonical" href="([^"]*)"/', $html, $matches);

    return $matches[1];
}

it('prints one canonical on a page that sets its own', function () {
    $product = CatalogFixtures::product();

    expect(canonicalsOf($this->get('/faq')->getContent()))->toBe([route('faq')])
        ->and(canonicalsOf($this->get(route('products.show', $product->slug))->getContent()))->toBe([route('products.show', $product)]);
});

it('prints one canonical without the query string on a page that sets none', function () {
    expect(canonicalsOf($this->get('/cart?utm_source=newsletter')->getContent()))->toBe([url('/cart')]);
});

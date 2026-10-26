<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\CatalogFixtures;

/*
 * A product without photos must resolve to the default image. The product factory used to hand the variant the
 * already-encoded images string, so the column held the JSON string "[]" and the URL ended in "/[]".
 */

it('stores a real empty array on the default variant the product factory creates', function () {
    $product = CatalogFixtures::product();

    $raw = DB::table('product_variants')->where('product_id', $product->id)->value('images');

    expect($raw)->toBe('[]')
        ->and($product->defaultVariant->image_urls)->toBe(['/images/product.png'])
        ->and($product->image_url)->toEndWith('/images/product.png');
});

it('falls back to the default image when the images column is double-encoded', function () {
    $variant = CatalogFixtures::variant(CatalogFixtures::product(), ['is_default' => false]);
    DB::table('product_variants')->where('id', $variant->id)->update(['images' => json_encode('[]')]);

    expect($variant->fresh()->image_urls)->toBe(['/images/product.png'])
        ->and($variant->fresh()->main_image_url)->toBe('/images/product.png');
});

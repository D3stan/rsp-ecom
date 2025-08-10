# SEO Implementation Guide — Point 3 & Point 4 (no breadcrumbs)

This guide assumes **Point 2** (base layout generators) is already in place:

```blade
{!! SEOMeta::generate() !!}
{!! OpenGraph::generate() !!}
{!! Twitter::generate() !!}
{!! JsonLd::generate() !!}
```

**Packages installed**: `artesaos/seotools`, `spatie/schema-org`, `spatie/laravel-sitemap`

> Tips
> - Always pass **absolute HTTPS** image URLs to OG/Twitter (no relative paths).

---

## Point 3 — Use SEO per page

Goal: set page-specific `<title>`, meta description, canonical, Open Graph/Twitter images, and Product JSON‑LD.

You can place this in controllers, a view composer, or a response macro. Examples below use controllers.

### 3.1 Product page (`ProductController@show`)

```php
use Artesaos\SEOTools\Facades\{SEOMeta, OpenGraph, Twitter, JsonLd};
use Illuminate\Support\Str;

public function show(\App\Models\Product $product)
{
    // Meta
    SEOMeta::setTitle($product->name . ' – ' . config('app.name'));
    SEOMeta::setDescription(Str::limit(strip_tags($product->description), 160));
    SEOMeta::setCanonical(route('products.show', $product));

    // Open Graph
    OpenGraph::setType('product');
    OpenGraph::setUrl(route('products.show', $product));
    $socialUrl = $product->social_image_url ?? $product->primary_image_url ?? asset('img/social/default.jpg');
    OpenGraph::addImage($socialUrl); // must be absolute HTTPS

    // Twitter
    Twitter::setType('summary_large_image');
    Twitter::setImage($socialUrl);

    // JSON-LD Product
    JsonLd::setType('Product');
    JsonLd::addValue('name', $product->name);
    JsonLd::addValue('image', $product->images ?: [$socialUrl]); // array
    JsonLd::addValue('description', Str::limit(strip_tags($product->description), 300));
    JsonLd::addValue('sku', $product->sku);
    JsonLd::addValue('offers', [
        '@type' => 'Offer',
        'price' => (string) $product->price,
        'priceCurrency' => 'EUR',
        'availability' => $product->stock_quantity > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url' => route('products.show', $product),
    ]);

    // Return your response (Blade or Inertia)
    return inertia('Products/Show', ['product' => $product]);
}
```

### 3.2 Category page (`CategoryController@show`)

```php
use Illuminate\Support\Str;

public function show(\App\Models\Category $category)
{
    SEOMeta::setTitle($category->name . ' – ' . config('app.name'));
    SEOMeta::setDescription(Str::limit(strip_tags($category->seo_description ?? ''), 160));
    SEOMeta::setCanonical(route('categories.show', $category));

    OpenGraph::setType('website');
    OpenGraph::setUrl(route('categories.show', $category));

    if (!empty($category->social_image_url)) {
        OpenGraph::addImage($category->social_image_url);
        Twitter::setImage($category->social_image_url);
    }

    return inertia('Categories/Show', ['category' => $category]);
}
```

### 3.3 Static CMS page (`PageController@show`)

```php
use Illuminate\Support\Str;

public function show(\App\Models\Page $page)
{
    $title = $page->seo_title ?: $page->title;

    SEOMeta::setTitle($title . ' – ' . config('app.name'));
    SEOMeta::setDescription(Str::limit(strip_tags($page->seo_description ?? ''), 160));
    SEOMeta::setCanonical(url($page->slug));

    if (!empty($page->social_image_url)) {
        OpenGraph::addImage($page->social_image_url);
        Twitter::setImage($page->social_image_url);
    }

    return inertia('Pages/Show', ['page' => $page]);
}
```

### 3.4 Pages that must **not** be indexed

For cart/checkout/account, add this before returning the response:

```php
SEOMeta::addMeta('robots', 'noindex,nofollow', 'name');
```

---

## Point 4 — Sitelinks Search Box JSON‑LD (no breadcrumbs)

Goal: enable the Google sitelinks **search box** below your main search result.

Generate a `WebSite` JSON‑LD with a `SearchAction` on the **homepage only**.

### 4.1 Homepage controller (or a view composer)

```php
use Spatie\SchemaOrg\Schema;

public function home()
{
    $websiteJsonLd = Schema::webSite()
        ->url(config('app.url'))
        ->potentialAction(
            Schema::searchAction()
                ->target(url('/products/search') . '?q={search_term_string}')
                ->queryInput('required name=search_term_string')
        )
        ->toScript(); // returns a ready-to-embed <script type="application/ld+json">...</script>

    return view('home', compact('websiteJsonLd'));
}
```

> Adjust the search URL if your route differs. If your query parameter is not `q`, change it **in both places** above.

### 4.2 Print it in the `<head>` of the base layout

```blade
@isset($websiteJsonLd)
    {!! $websiteJsonLd !!}
@endisset
```

> Share `$websiteJsonLd` **only** on the homepage so it doesn’t repeat on every page.

---

### Done

- **Point 3**: per-page metas + OG/Twitter + Product JSON‑LD.
- **Point 4**: Sitelinks Search Box JSON‑LD (no breadcrumbs).

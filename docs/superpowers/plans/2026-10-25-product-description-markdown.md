# Markdown Product Descriptions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Variant descriptions support markdown (links, uploaded photos) with a Write/Preview editor in the admin, rendered safely on the product page, plus two admin/storefront bug fixes.

**Architecture:** A server-side `MarkdownRenderer` (league/commonmark, already installed) turns the stored markdown into sanitized HTML and plain text; controllers send `description_html` / `description_text` as extra props, so any frontend only has to print a string. The admin editor previews through the same renderer via a small JSON endpoint and uploads description photos to their own storage folder.

**Tech Stack:** Laravel (PHP 8.5), league/commonmark 2.10, Inertia + React + Tailwind, Pest. No new dependencies.

Spec: `docs/superpowers/specs/2026-10-25-product-description-markdown-design.md`

**Conventions to follow (project rules):**
- Run `vendor/bin/pint --dirty --format agent` after PHP edits.
- Run the narrowest Pest file: `php artisan test --compact <file>`.
- All UI strings go through `t('key')`; add every key to all five files `lang/{en,it,de,es,fr}.json` (flat dotted keys, 4-space indent, insert after the anchor key named in the task).
- Run `graphify update .` after the last code change.
- Commit steps below are per task. Do not push or deploy (deploy is done once at the end, only when the user asks).

---

## File structure

| File | Responsibility |
|------|----------------|
| `app/Services/ImageUploadService.php` (modify) | Store description photos in `products/descriptions/`, expose the URL prefix they live under |
| `app/Services/MarkdownRenderer.php` (create) | Markdown to safe HTML / plain text. The only markdown-aware PHP class |
| `app/Http/Controllers/Admin/ProductDescriptionController.php` (create) | Admin JSON endpoints: preview and image upload |
| `routes/admin.php` (modify) | Two new routes, declared before `Route::resource('products', ...)` |
| `app/Http/Controllers/Admin/ProductController.php` (modify) | Description max length (store + update) |
| `app/Http/Controllers/ProductsController.php` (modify) | `description_html` / `description_text` per variant, meta description from plain text |
| `resources/js/components/product/RichText.tsx` (create) | The only storefront place that prints description HTML, mobile collapse |
| `resources/js/pages/product.tsx` (modify) | Use `RichText`, share text from `description_text` |
| `resources/js/components/admin/MarkdownEditor.tsx` (create) | Write/Preview tabs, photo upload button |
| `resources/js/components/admin/VariantManager.tsx` (modify) | Use `MarkdownEditor` for the variant description |
| `resources/js/pages/Admin/Products/{Create,Edit}.tsx` (modify) | Show server field errors, open the SEO card on SEO errors, SEO counter |
| `lang/*.json` (modify) | New keys |
| `tests/Feature/Catalog/DescriptionImageStorageTest.php`, `MarkdownRendererTest.php`, `ProductDescriptionPropsTest.php`; `tests/Feature/Admin/ProductDescriptionEndpointsTest.php`, `ProductDescriptionFormTest.php` (create) | Pest coverage |

---

### Task 1: Description image storage

**Files:**
- Modify: `app/Services/ImageUploadService.php`
- Test: `tests/Feature/Catalog/DescriptionImageStorageTest.php`

Context: existing `generateFileName()` uses the client-supplied extension. The new method must not: it detects the type from the file content (`getMimeType()` reads the magic bytes) and maps it through a fixed allowlist, so an upload named `evil.php` that contains a PNG is stored as `.png`, and anything that is not one of the four image types is refused (fail closed, never `null` or an unexpected extension).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Catalog/DescriptionImageStorageTest.php`:

```php
<?php

use App\Services\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** A 1x1 transparent PNG. */
const DESCRIPTION_TEST_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

beforeEach(function () {
    config(['filesystems.default' => 'local']);
    Storage::fake('public');
});

it('stores a description image under the description folder and returns a URL below the base URL', function () {
    $service = app(ImageUploadService::class);

    $url = $service->uploadDescriptionImage(UploadedFile::fake()->image('photo.jpg'));

    expect($url)->toStartWith($service->descriptionImageBaseUrl());
    $files = Storage::disk('public')->files('products/descriptions');
    expect($files)->toHaveCount(1)
        ->and($url)->toEndWith(basename($files[0]));
});

/** A real temp file (UploadedFile::fake() reports its mime type from the file NAME, which would hide the bug). */
function descriptionUpload(string $clientName, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'desc');
    file_put_contents($path, $content);

    return new UploadedFile($path, $clientName, null, null, true);
}

it('names the stored file after its real content, not the client file name', function () {
    $file = descriptionUpload('evil.php', base64_decode(DESCRIPTION_TEST_PNG));

    $url = app(ImageUploadService::class)->uploadDescriptionImage($file);

    expect($url)->toEndWith('.png')->not->toContain('.php');
});

it('refuses content that is not a supported image, whatever its name says', function () {
    $file = descriptionUpload('photo.png', '<?php echo 1;');

    app(ImageUploadService::class)->uploadDescriptionImage($file);
})->throws(InvalidArgumentException::class);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Catalog/DescriptionImageStorageTest.php`
Expected: FAIL with "Call to undefined method App\Services\ImageUploadService::uploadDescriptionImage()".

- [ ] **Step 3: Write the implementation**

In `app/Services/ImageUploadService.php`, add the constant as the first member of the class (right after `class ImageUploadService {`):

```php
    /** Shared folder for photos embedded in variant descriptions (the product may not exist yet when they are uploaded). */
    private const DESCRIPTION_DIRECTORY = 'products/descriptions';

    /** Detected mime type to stored extension: the only types a description photo may have. */
    private const DESCRIPTION_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
```

and add these methods directly before `generateFileName()`:

```php
    /**
     * Upload a photo used inside a variant description. The extension comes from the detected file content through a
     * fixed allowlist, never from the client name; anything else is refused.
     */
    public function uploadDescriptionImage(UploadedFile $image): string
    {
        $extension = self::DESCRIPTION_EXTENSIONS[$image->getMimeType()] ?? throw new \InvalidArgumentException('Unsupported description image type');

        $disk = $this->descriptionDisk();
        $filename = now()->timestamp.'_'.Str::random(8).'.'.$extension;

        $image->storeAs(self::DESCRIPTION_DIRECTORY, $filename, $disk);

        return Storage::disk($disk)->url(self::DESCRIPTION_DIRECTORY.'/'.$filename);
    }

    /**
     * URL prefix every description photo starts with; the markdown renderer keeps only images under it.
     */
    public function descriptionImageBaseUrl(): string
    {
        return Storage::disk($this->descriptionDisk())->url(self::DESCRIPTION_DIRECTORY.'/');
    }

    private function descriptionDisk(): string
    {
        return config('filesystems.default') === 's3' ? 's3' : 'public';
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Catalog/DescriptionImageStorageTest.php`
Expected: 2 passed.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/ImageUploadService.php tests/Feature/Catalog/DescriptionImageStorageTest.php
git commit -m "feat: store description images in their own folder"
```

---

### Task 2: MarkdownRenderer

**Files:**
- Create: `app/Services/MarkdownRenderer.php`
- Test: `tests/Feature/Catalog/MarkdownRendererTest.php`

Rules (from the spec, plus one found in the Task 1 review: a "starts with the base URL" check alone also accepts `.../descriptions/../../x`, so image URLs containing a `..` segment, plain or percent-encoded, are dropped): raw HTML stripped; only `http`, `https`, `mailto` and relative links; external links open in a new tab with `nofollow noopener noreferrer`; images only under `ImageUploadService::descriptionImageBaseUrl()` and `loading="lazy"`; headings demoted by two levels (h1 becomes h3, capped at h6); single newlines become `<br />` so legacy plain text looks unchanged. Code blocks are CommonMark core and render as plain `<pre><code>` (harmless; not specially removed). No tables.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Catalog/MarkdownRendererTest.php`:

```php
<?php

use App\Services\ImageUploadService;
use App\Services\MarkdownRenderer;

function markdownRenderer(): MarkdownRenderer
{
    return app(MarkdownRenderer::class);
}

it('returns an empty string for null or blank input', function () {
    expect(markdownRenderer()->toHtml(null))->toBe('')
        ->and(markdownRenderer()->toHtml("  \n "))->toBe('');
});

it('keeps single newlines as line breaks so legacy plain text looks the same', function () {
    expect(markdownRenderer()->toHtml("Line one\nLine two"))->toContain('Line one<br />');
});

it('strips raw html', function () {
    $html = markdownRenderer()->toHtml('Hi <script>alert(1)</script> <b onclick="x()">bold</b>');

    expect($html)->not->toContain('<script')->not->toContain('<b')->not->toContain('onclick');
});

it('removes links with unsafe schemes but keeps their text', function (string $url) {
    $html = markdownRenderer()->toHtml("[click me]({$url})");

    expect($html)->not->toContain('<a')->not->toContain($url)->toContain('click me');
})->with(['javascript:alert(1)', 'ftp://example.org/a', 'data:text/html;base64,AAAA']);

it('keeps http, mailto and relative links and opens external ones safely', function () {
    $html = markdownRenderer()->toHtml("[a](https://example.org/x) [b](mailto:info@example.org) [c](/products)");

    expect($html)->toContain('href="https://example.org/x"')
        ->toContain('target="_blank"')
        ->toContain('nofollow')
        ->toContain('noopener')
        ->toContain('href="mailto:info@example.org"')
        ->toContain('href="/products"');
});

it('keeps images from the description storage and drops every other image', function () {
    $base = app(ImageUploadService::class)->descriptionImageBaseUrl();

    $kept = markdownRenderer()->toHtml("![photo]({$base}a.png)");
    $dropped = markdownRenderer()->toHtml('![photo](https://evil.test/a.png)');

    expect($kept)->toContain('src="'.$base.'a.png"')->toContain('loading="lazy"')
        ->and($dropped)->not->toContain('<img');
});

it('drops images that use path traversal to leave the description folder', function (string $suffix) {
    $base = app(ImageUploadService::class)->descriptionImageBaseUrl();

    expect(markdownRenderer()->toHtml("![x]({$base}{$suffix})"))->not->toContain('<img');
})->with(['../../private/a.png', '%2e%2e/a.png', '%2E%2E/%2e%2e/a.png', 'a/../../b.png']);

it('demotes headings so the page never gets a second h1', function () {
    $html = markdownRenderer()->toHtml("# Title\n\n## Sub\n\n###### Deep");

    expect($html)->toContain('<h3>Title</h3>')->toContain('<h4>Sub</h4>')->toContain('<h6>Deep</h6>')->not->toContain('<h1');
});

it('produces plain text without markup or markdown syntax', function () {
    $text = markdownRenderer()->toPlainText("**Bold** and [a link](https://x.test)\n\n- one\n- two\n\nFish & chips");

    expect($text)->toBe('Bold and a link one two Fish & chips');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Catalog/MarkdownRendererTest.php`
Expected: FAIL with "Class App\Services\MarkdownRenderer not found".

- [ ] **Step 3: Write the implementation**

Create `app/Services/MarkdownRenderer.php`:

```php
<?php

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Turns admin-written markdown (variant descriptions) into safe HTML and plain text. This is the only class that knows
 * markdown: controllers hand its output to the frontend, so a new frontend only has to print a string.
 */
class MarkdownRenderer
{
    /** Longest markdown text accepted for one description (characters). */
    public const MAX_LENGTH = 10000;

    private const ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto'];

    private ?MarkdownConverter $converter = null;

    public function __construct(private ImageUploadService $images) {}

    public function toHtml(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        return trim($this->converter()->convert($markdown)->getContent());
    }

    /**
     * Text for meta tags, JSON-LD and share sheets: no tags, no markdown syntax, whitespace collapsed.
     */
    public function toPlainText(?string $markdown): string
    {
        $html = preg_replace('#<(?:br|/p|/li|/h[1-6])[^>]*>#i', ' ', $this->toHtml($markdown));
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private function converter(): MarkdownConverter
    {
        if ($this->converter !== null) {
            return $this->converter;
        }

        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            // Legacy descriptions are plain text with single newlines: keep them as line breaks
            'renderer' => ['soft_break' => "<br />\n"],
            'external_link' => [
                'internal_hosts' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
                'open_in_new_window' => true,
                'nofollow' => 'external',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new ExternalLinkExtension);
        $environment->addEventListener(DocumentParsedEvent::class, $this->restrict(...));

        return $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Runs before the external-link processor: unwrap unsafe links, drop foreign images, demote headings.
     */
    private function restrict(DocumentParsedEvent $event): void
    {
        $imageBase = $this->images->descriptionImageBaseUrl();

        // Collect first: the tree must not change while it is being walked
        foreach (iterator_to_array($event->getDocument()->iterator(), false) as $node) {
            if ($node instanceof Heading) {
                $node->setLevel(min(6, $node->getLevel() + 2));
            } elseif ($node instanceof Link && ! $this->isAllowedLink($node->getUrl())) {
                foreach (iterator_to_array($node->children(), false) as $child) {
                    $node->insertBefore($child);
                }
                $node->detach();
            } elseif ($node instanceof Image) {
                if ($this->isDescriptionImage($node->getUrl(), $imageBase)) {
                    $node->data->set('attributes/loading', 'lazy');
                } else {
                    $node->detach();
                }
            }
        }
    }

    /**
     * Under the description folder, and no `..` segment (plain or percent-encoded) that would climb out of it.
     */
    private function isDescriptionImage(string $url, string $imageBase): bool
    {
        return str_starts_with($url, $imageBase) && ! str_contains(rawurldecode($url), '..');
    }

    private function isAllowedLink(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return false;
        }

        return ! isset($parts['scheme']) || in_array(strtolower($parts['scheme']), self::ALLOWED_LINK_SCHEMES, true);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Catalog/MarkdownRendererTest.php`
Expected: all pass. If `loading="lazy"` is missing from the image output, the `data->set('attributes/loading', ...)` path syntax is the suspect: replace it with `$node->data->set('attributes', ['loading' => 'lazy'])` and rerun. If the `rel` assertions fail, print the HTML and assert against the actual attribute order (the check is only that `nofollow` and `noopener` appear).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/MarkdownRenderer.php tests/Feature/Catalog/MarkdownRendererTest.php
git commit -m "feat: add a safe markdown renderer for descriptions"
```

---

### Task 3: Product page props, meta description, validation limit

**Files:**
- Modify: `app/Http/Controllers/ProductsController.php:233` and `:336`
- Modify: `app/Http/Controllers/Admin/ProductController.php:150` and `:410`
- Modify: `resources/views/app.blade.php:14` (JSON-LD sink, see Step 3b)
- Test: `tests/Feature/Catalog/ProductDescriptionPropsTest.php`

**Security note found in the Task 2 review:** `toPlainText()` decodes entities, so markdown like `&lt;/script&gt;&lt;script&gt;alert(1)&lt;/script&gt;` becomes live `</script><script>...` text. Meta tags are safe (SEOTools applies `htmlspecialchars`), but the JSON-LD block is printed by `JsonLd::generate()` with `JSON_UNESCAPED_SLASHES` and no `JSON_HEX_TAG` inside a raw `{!! !!}`, so the description would break out of the `<script>` tag. The same sink already exists today for buyer review comments (`reviewBody`). The fix is at the sink (Step 3b), not in the renderer.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Catalog/ProductDescriptionPropsTest.php`:

```php
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
        ->and($html)->toContain('</script>');
});
```

If the second test's `assertSee('content="Bright angel eye light"', false)` does not match, open the response HTML once to see how SEOTools prints the description tag and adapt both assertions to that format.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Catalog/ProductDescriptionPropsTest.php`
Expected: FAIL (`description_html` missing, meta text not found).

- [ ] **Step 3: Implement in `ProductsController`**

At the top of `app/Http/Controllers/ProductsController.php` add `use App\Services\MarkdownRenderer;` (with the other `use App\...` lines).

Change the signature of `show` to inject the renderer:

```php
    public function show(string $slug, MarkdownRenderer $markdown): Response
```

Replace line 233:

```php
        $metaDescription = $product->seo_description ?: strip_tags($product->description);
```

with:

```php
        $metaDescription = $product->seo_description
            ?: $markdown->toPlainText($product->defaultVariant?->description ?: $product->description);
```

In the variants map (line ~336) replace:

```php
                'description' => $v->description,
```

with:

```php
                'description' => $v->description,
                'description_html' => $markdown->toHtml($v->description),
                'description_text' => $markdown->toPlainText($v->description),
```

- [ ] **Step 3b: Escape `<` and `>` in the JSON-LD output**

In `resources/views/app.blade.php`, replace the line

```blade
        {!! \Artesaos\SEOTools\Facades\JsonLd::generate() !!}
```

with the same output plus `JSON_HEX_TAG` (identical JSON, `<` and `>` become `<` and `>`, so no text can close the script tag):

```blade
        <script type="application/ld+json">{!! json_encode(array_merge(['@@context' => 'https://schema.org'], \Artesaos\SEOTools\Facades\JsonLd::convertToArray()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
```

(`JsonLd::generate()` does exactly `array_merge(['@context' => ...], convertToArray())` and the same json_encode flags without `JSON_HEX_TAG`: `vendor/artesaos/seotools/src/SEOTools/JsonLd.php:88-98`.) This also closes the existing hole where an approved buyer review containing `</script>` reached the same sink. Leave the separate `websiteJsonLd` prop below it alone (homepage, server-built).

- [ ] **Step 4: Implement the length limit in `Admin/ProductController`**

Add `use App\Services\MarkdownRenderer;` to the imports. In both rule arrays (store ~line 150, update ~line 410) replace:

```php
                'variants.*.description' => 'nullable|string',
```

with:

```php
                'variants.*.description' => 'nullable|string|max:'.MarkdownRenderer::MAX_LENGTH,
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Catalog/ProductDescriptionPropsTest.php tests/Feature/Catalog/ProductSafetyTest.php`
Expected: all pass (ProductSafetyTest guards the product page props).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers tests/Feature/Catalog/ProductDescriptionPropsTest.php
git commit -m "feat: send rendered variant descriptions to the product page"
```

---

### Task 4: Admin preview and image endpoints, form validation tests

**Files:**
- Create: `app/Http/Controllers/Admin/ProductDescriptionController.php`
- Modify: `routes/admin.php:43-44`
- Test: `tests/Feature/Admin/ProductDescriptionEndpointsTest.php`, `tests/Feature/Admin/ProductDescriptionFormTest.php`

- [ ] **Step 1: Write the failing endpoint tests**

Create `tests/Feature/Admin/ProductDescriptionEndpointsTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['filesystems.default' => 'local']);
    Storage::fake('public');
});

it('renders a markdown preview for an admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson(route('admin.products.markdown-preview'), ['markdown' => '**Bold**'])
        ->assertOk()
        ->assertJson(['html' => '<p><strong>Bold</strong></p>']);
});

it('rejects a preview text over the description limit', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson(route('admin.products.markdown-preview'), ['markdown' => str_repeat('a', 10001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('markdown');
});

it('uploads a description image for an admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.products.description-images'), ['image' => UploadedFile::fake()->image('photo.png')])
        ->assertOk();

    expect($response->json('url'))->toContain('products/descriptions/');
    expect(Storage::disk('public')->files('products/descriptions'))->toHaveCount(1);
});

it('rejects files that are not images', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson(route('admin.products.description-images'), ['image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');
});

it('keeps both endpoints away from guests and customers', function () {
    $payload = ['markdown' => 'x'];

    $this->postJson(route('admin.products.markdown-preview'), $payload)->assertUnauthorized();
    $this->actingAs(User::factory()->create())
        ->postJson(route('admin.products.markdown-preview'), $payload)->assertForbidden();
    $this->actingAs(User::factory()->create())
        ->postJson(route('admin.products.description-images'), ['image' => UploadedFile::fake()->image('a.png')])
        ->assertForbidden();
});
```

Create `tests/Feature/Admin/ProductDescriptionFormTest.php`:

```php
<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CatalogFixtures;

beforeEach(fn () => Queue::fake());

/** @return array{0: User, 1: Product, 2: ProductVariant} */
function descriptionFormFixture(): array
{
    $product = CatalogFixtures::product();
    $variant = $product->defaultVariant;
    $variant->update(['images' => [], 'stock_quantity' => 5]);

    return [User::factory()->create(['role' => 'admin']), $product->fresh(), $variant->fresh()];
}

/** Payload as Admin/Products/Edit.tsx builds it, with the description and SEO description under test. */
function descriptionFormPayload(Product $product, ProductVariant $variant, string $description, string $seoDescription = ''): array
{
    return [
        'name' => $product->name,
        'category_id' => (string) $product->category_id,
        'size_id' => (string) $product->size_id,
        'status' => $product->status,
        'featured' => '0',
        'base_sku' => $product->base_sku,
        'seo_title' => '',
        'seo_description' => $seoDescription,
        'variants' => json_encode([[
            'id' => $variant->id,
            'name' => $variant->name,
            'sku_suffix' => $variant->sku_suffix,
            'price' => (string) $variant->price,
            'stock_quantity' => '5',
            'stock_quantity_original' => 5,
            'description' => $description,
            'is_default' => true,
            'sort_order' => $variant->sort_order,
            'existing_images' => [],
        ]]),
    ];
}

it('saves a markdown description untouched', function () {
    [$admin, $product, $variant] = descriptionFormFixture();

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), descriptionFormPayload($product, $variant, "**Bold**\n\n- one\n- two"))
        ->assertSessionHasNoErrors();

    expect($variant->fresh()->description)->toBe("**Bold**\n\n- one\n- two");
});

it('rejects a description over the limit with a field error', function () {
    [$admin, $product, $variant] = descriptionFormFixture();

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), descriptionFormPayload($product, $variant, str_repeat('a', 10001)))
        ->assertSessionHasErrors('variants.0.description');
});

it('rejects an SEO description over 500 characters with a field error', function () {
    [$admin, $product, $variant] = descriptionFormFixture();

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), descriptionFormPayload($product, $variant, 'ok', str_repeat('a', 501)))
        ->assertSessionHasErrors('seo_description');
});

it('accepts a 500 character SEO description', function () {
    [$admin, $product, $variant] = descriptionFormFixture();

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), descriptionFormPayload($product, $variant, 'ok', str_repeat('a', 500)))
        ->assertSessionHasNoErrors();
});
```

- [ ] **Step 2: Run tests to verify the endpoint tests fail**

Run: `php artisan test --compact tests/Feature/Admin/ProductDescriptionEndpointsTest.php tests/Feature/Admin/ProductDescriptionFormTest.php`
Expected: endpoint tests FAIL ("Route [admin.products.markdown-preview] not defined"). Form tests should already PASS (they pin the behaviour Task 3 added and the existing SEO limit).

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/ProductDescriptionController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FileUploadConfigService;
use App\Services\ImageUploadService;
use App\Services\MarkdownRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Support endpoints of the markdown description editor (Admin MarkdownEditor.tsx).
 */
class ProductDescriptionController extends Controller
{
    /**
     * Render markdown with the same renderer the product page uses.
     */
    public function preview(Request $request, MarkdownRenderer $markdown): JsonResponse
    {
        $validated = $request->validate(['markdown' => 'nullable|string|max:'.MarkdownRenderer::MAX_LENGTH]);

        return response()->json(['html' => $markdown->toHtml($validated['markdown'] ?? null)]);
    }

    /**
     * Store a photo for use inside a description and return its URL.
     */
    public function image(Request $request, ImageUploadService $images): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|'.FileUploadConfigService::getFileValidationRule(),
        ]);

        return response()->json(['url' => $images->uploadDescriptionImage($request->file('image'))]);
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/admin.php`, add the import next to `use App\Http\Controllers\Admin\ProductController;` (line 9):

```php
use App\Http\Controllers\Admin\ProductDescriptionController;
```

and replace lines 43-44:

```php
    // Products Management
    Route::resource('products', ProductController::class);
```

with:

```php
    // Products Management
    // Markdown description editor helpers (declared before the resource so `products/...` never reads as a product id)
    // No throttle middleware: an unnamed `throttle:N,1` shares one counter per user with every other plain throttle route
    Route::post('products/markdown-preview', [ProductDescriptionController::class, 'preview'])->name('products.markdown-preview');
    Route::post('products/description-images', [ProductDescriptionController::class, 'image'])->name('products.description-images');
    Route::resource('products', ProductController::class);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Admin/ProductDescriptionEndpointsTest.php tests/Feature/Admin/ProductDescriptionFormTest.php`
Expected: all pass. If the guest assertion returns 302 instead of 401, the `auth` middleware is redirecting JSON requests: keep `postJson` and change that one assertion to `->assertRedirect(route('login'))` only after confirming the actual status.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/ProductDescriptionController.php routes/admin.php tests/Feature/Admin
git commit -m "feat: add admin endpoints for markdown preview and description photos"
```

---

### Task 5: Storefront `RichText` and product page

**Files:**
- Create: `resources/js/components/product/RichText.tsx`
- Modify: `resources/js/pages/product.tsx` (interface ~line 22, share text ~line 167, description block ~lines 392-397, import block)
- Modify: `lang/{en,it,de,es,fr}.json` (two keys, after the last `"product.` key; find it with `grep -n '"product\.' lang/en.json | tail -1`)

- [ ] **Step 1: Add the translation keys**

Insert after the last `"product.` key in each file (keep the trailing comma pattern of the surrounding lines):

| key | en | it | de | es | fr |
|-----|----|----|----|----|----|
| `product.read_more` | Read more | Leggi tutto | Mehr lesen | Leer más | Lire la suite |
| `product.read_less` | Show less | Mostra meno | Weniger anzeigen | Mostrar menos | Afficher moins |

- [ ] **Step 2: Create the component**

Create `resources/js/components/product/RichText.tsx`:

```tsx
import { useIsMobile } from '@/hooks/use-mobile';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';
import { useCallback, useLayoutEffect, useRef, useState } from 'react';

// About 6 lines of body text
const COLLAPSED_HEIGHT_PX = 144;

interface RichTextProps {
    /** Sanitized HTML from the server (MarkdownRenderer): the backend is the only place that decides what is allowed. */
    html: string;
    /** On mobile, fold content taller than about 6 lines behind a read-more button. */
    collapsible?: boolean;
    className?: string;
}

/**
 * The single place where the storefront prints server-rendered description HTML.
 */
export default function RichText({ html, collapsible = false, className }: RichTextProps) {
    const { t } = useTranslation();
    const isMobile = useIsMobile();
    const ref = useRef<HTMLDivElement>(null);
    const [expanded, setExpanded] = useState(false);
    const [overflows, setOverflows] = useState(false);

    const measure = useCallback(() => {
        if (ref.current) {
            setOverflows(ref.current.scrollHeight > COLLAPSED_HEIGHT_PX + 8);
        }
    }, []);

    useLayoutEffect(() => {
        setExpanded(false);
        measure();
    }, [html, measure]);

    const canCollapse = collapsible && isMobile && overflows;

    return (
        <div className={className}>
            <div
                ref={ref}
                // Images that finish loading later change the height
                onLoadCapture={measure}
                style={canCollapse && !expanded ? { maxHeight: COLLAPSED_HEIGHT_PX } : undefined}
                className={cn(
                    'leading-relaxed break-words text-gray-700',
                    '[&_a]:underline [&_a]:underline-offset-2',
                    '[&_img]:my-3 [&_img]:h-auto [&_img]:max-w-full [&_img]:rounded-md',
                    '[&_p]:my-2 [&_ul]:my-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:my-2 [&_ol]:list-decimal [&_ol]:pl-5',
                    '[&_h3]:mt-4 [&_h3]:text-lg [&_h3]:font-semibold [&_h4]:mt-3 [&_h4]:font-semibold [&_h5]:font-semibold [&_h6]:font-semibold',
                    '[&_blockquote]:border-l-2 [&_blockquote]:border-gray-300 [&_blockquote]:pl-3 [&_blockquote]:text-gray-500',
                    canCollapse && !expanded && 'overflow-hidden',
                )}
                dangerouslySetInnerHTML={{ __html: html }}
            />
            {canCollapse && (
                <button type="button" onClick={() => setExpanded((open) => !open)} className="mt-2 text-sm font-medium underline underline-offset-2">
                    {expanded ? t('product.read_less') : t('product.read_more')}
                </button>
            )}
        </div>
    );
}
```

- [ ] **Step 3: Use it on the product page**

In `resources/js/pages/product.tsx`:

1. Add the import with the other component imports (after `import ProductSafety ...`):

```tsx
import RichText from '@/components/product/RichText';
```

2. In `interface ProductVariant` (after `description: string;`) add:

```tsx
    description_html: string;
    description_text: string;
```

3. Line ~167, share text:

```tsx
                    text: selectedVariant.description_text || product.name,
```

4. Replace the block at lines ~392-397:

```tsx
                            {/* Short Description */}
                            {selectedVariant.description && (
                                <div className="border-t border-gray-200 pt-6">
                                    <p className="leading-relaxed text-gray-700">{selectedVariant.description}</p>
                                </div>
                            )}
```

with:

```tsx
                            {/* Description (markdown rendered by the server) */}
                            {selectedVariant.description_html && (
                                <div className="border-t border-gray-200 pt-6">
                                    <RichText html={selectedVariant.description_html} collapsible />
                                </div>
                            )}
```

- [ ] **Step 4: Verify types and lint**

Run: `npm run types && npx eslint resources/js/components/product/RichText.tsx resources/js/pages/product.tsx`
Expected: no errors.

- [ ] **Step 5: Verify in the browser**

Run `composer run dev` (or ask the user to). Open a product page with a variant description that has a newline, `**bold**` and a link, at desktop width and at phone width (devtools, under 768px). Expected: line breaks preserved, bold and link rendered, content over about 6 lines folds behind "Read more" on the phone only.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/product/RichText.tsx resources/js/pages/product.tsx lang
git commit -m "feat: render markdown descriptions on the product page"
```

---

### Task 6: Admin `MarkdownEditor`

**Files:**
- Create: `resources/js/components/admin/MarkdownEditor.tsx`
- Modify: `resources/js/components/admin/VariantManager.tsx` (imports ~line 1-8, description block lines 200-208)
- Modify: `lang/{en,it,de,es,fr}.json` (keys below, after `"admin.products.variants.description_placeholder"`)

- [ ] **Step 1: Add the translation keys**

| key | en | it | de | es | fr |
|-----|----|----|----|----|----|
| `admin.products.markdown.write` | Write | Scrivi | Schreiben | Escribir | Écrire |
| `admin.products.markdown.preview` | Preview | Anteprima | Vorschau | Vista previa | Aperçu |
| `admin.products.markdown.add_photo` | Add photo | Aggiungi foto | Foto hinzufügen | Añadir foto | Ajouter une photo |
| `admin.products.markdown.uploading` | Uploading... | Caricamento... | Wird hochgeladen... | Subiendo... | Envoi en cours... |
| `admin.products.markdown.hint` | Markdown: **bold**, *italic*, [text](https://...), lists. A new line stays a new line. | Markdown: **grassetto**, *corsivo*, [testo](https://...), elenchi. Un a capo resta un a capo. | Markdown: **fett**, *kursiv*, [Text](https://...), Listen. Ein Zeilenumbruch bleibt ein Zeilenumbruch. | Markdown: **negrita**, *cursiva*, [texto](https://...), listas. Un salto de línea se mantiene. | Markdown : **gras**, *italique*, [texte](https://...), listes. Un retour à la ligne reste un retour à la ligne. |
| `admin.products.markdown.preview_empty` | Nothing to preview yet. | Niente da mostrare per ora. | Noch nichts zur Vorschau. | Aún no hay nada que previsualizar. | Rien à prévisualiser pour l'instant. |
| `admin.products.markdown.preview_error` | Preview unavailable. You can still save. | Anteprima non disponibile. Puoi comunque salvare. | Vorschau nicht verfügbar. Speichern ist weiterhin möglich. | Vista previa no disponible. Aún puedes guardar. | Aperçu indisponible. Vous pouvez quand même enregistrer. |
| `admin.products.markdown.upload_error` | Photo upload failed. Check the file type and size. | Caricamento foto non riuscito. Controlla tipo e dimensione del file. | Foto-Upload fehlgeschlagen. Dateityp und -größe prüfen. | Error al subir la foto. Comprueba el tipo y el tamaño del archivo. | Échec de l'envoi de la photo. Vérifiez le type et la taille du fichier. |
| `admin.products.markdown.counter` | {count}/{max} characters | {count}/{max} caratteri | {count}/{max} Zeichen | {count}/{max} caracteres | {count}/{max} caractères |

- [ ] **Step 2: Create the editor**

Create `resources/js/components/admin/MarkdownEditor.tsx`:

```tsx
import RichText from '@/components/product/RichText';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import { ImagePlus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { route } from 'ziggy-js';

interface MarkdownEditorProps {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    /** Same limit as the server (MarkdownRenderer::MAX_LENGTH) */
    maxLength?: number;
}

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

/**
 * Markdown textarea with Write / Preview tabs and a photo button. The preview comes from the server renderer, so it
 * shows exactly what customers will see. Tabs (not side by side) keep it usable on a phone.
 */
export function MarkdownEditor({ value, onChange, placeholder, maxLength = 10000 }: MarkdownEditorProps) {
    const { t } = useTranslation();
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [tab, setTab] = useState<'write' | 'preview'>('write');
    const [previewHtml, setPreviewHtml] = useState('');
    const [previewFailed, setPreviewFailed] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [uploadFailed, setUploadFailed] = useState(false);

    useEffect(() => {
        if (tab !== 'preview') {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            try {
                const response = await fetch(route('admin.products.markdown-preview'), {
                    method: 'POST',
                    signal: controller.signal,
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                    body: JSON.stringify({ markdown: value }),
                });
                if (!response.ok) {
                    throw new Error(String(response.status));
                }
                setPreviewHtml((await response.json()).html);
                setPreviewFailed(false);
            } catch {
                if (!controller.signal.aborted) {
                    setPreviewFailed(true);
                }
            }
        }, 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [tab, value]);

    const insertAtCursor = (text: string) => {
        const textarea = textareaRef.current;
        const start = textarea?.selectionStart ?? value.length;
        const end = textarea?.selectionEnd ?? value.length;
        onChange(value.slice(0, start) + text + value.slice(end));
    };

    const uploadPhoto = async (file: File) => {
        setUploading(true);
        setUploadFailed(false);
        try {
            const body = new FormData();
            body.append('image', file);
            const response = await fetch(route('admin.products.description-images'), {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body,
            });
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            const { url } = await response.json();
            // The file name is the starting alt text; the admin can edit it between the brackets
            insertAtCursor(`\n![${file.name.replace(/\.[^.]+$/, '')}](${url})\n`);
        } catch {
            setUploadFailed(true);
        } finally {
            setUploading(false);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        }
    };

    return (
        <Tabs value={tab} onValueChange={(next) => setTab(next as 'write' | 'preview')}>
            <div className="flex items-center justify-between gap-2">
                <TabsList>
                    <TabsTrigger value="write">{t('admin.products.markdown.write')}</TabsTrigger>
                    <TabsTrigger value="preview">{t('admin.products.markdown.preview')}</TabsTrigger>
                </TabsList>
                <Button type="button" variant="outline" size="sm" disabled={uploading} onClick={() => fileInputRef.current?.click()}>
                    <ImagePlus className="mr-1 h-4 w-4" />
                    {uploading ? t('admin.products.markdown.uploading') : t('admin.products.markdown.add_photo')}
                </Button>
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    className="hidden"
                    onChange={(event) => event.target.files?.[0] && uploadPhoto(event.target.files[0])}
                />
            </div>

            <TabsContent value="write" className="mt-2">
                <Textarea
                    ref={textareaRef}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder={placeholder}
                    rows={8}
                    maxLength={maxLength}
                />
                <div className="mt-1 flex justify-between gap-2 text-xs text-gray-500">
                    <span>{t('admin.products.markdown.hint')}</span>
                    <span className="shrink-0">{t('admin.products.markdown.counter', { count: [...value].length, max: maxLength })}</span>
                </div>
            </TabsContent>

            <TabsContent value="preview" className="mt-2 min-h-[10rem] rounded-md border border-input p-3">
                {previewFailed ? (
                    <p className="text-sm text-red-500">{t('admin.products.markdown.preview_error')}</p>
                ) : previewHtml ? (
                    <RichText html={previewHtml} />
                ) : (
                    <p className="text-sm text-gray-500">{t('admin.products.markdown.preview_empty')}</p>
                )}
            </TabsContent>

            {uploadFailed && <p className="mt-1 text-sm text-red-500">{t('admin.products.markdown.upload_error')}</p>}
        </Tabs>
    );
}
```

Note: the hint contains markdown punctuation; it is plain text in the UI, which is intended.

- [ ] **Step 3: Use it in `VariantManager`**

In `resources/js/components/admin/VariantManager.tsx`:

1. Replace `import { Textarea } from '@/components/ui/textarea';` (line 5) with `import { MarkdownEditor } from '@/components/admin/MarkdownEditor';` and move it to the top of the import block (the first line), as the file lists `@/components/...` imports alphabetically. Check no other `Textarea` use remains: `grep -n Textarea resources/js/components/admin/VariantManager.tsx` must print nothing.

2. Replace the Textarea element (lines ~201-208):

```tsx
                                        <Label>{t('admin.products.variants.description')}</Label>
                                        <Textarea
                                            value={variant.description}
                                            onChange={(e) => updateVariant(index, { description: e.target.value })}
                                            placeholder={t('admin.products.variants.description_placeholder')}
                                            rows={3}
                                        />
```

with:

```tsx
                                        <Label>{t('admin.products.variants.description')}</Label>
                                        <MarkdownEditor
                                            value={variant.description}
                                            onChange={(description) => updateVariant(index, { description })}
                                            placeholder={t('admin.products.variants.description_placeholder')}
                                        />
```

- [ ] **Step 4: Verify types and lint**

Run: `npm run types && npx eslint resources/js/components/admin/MarkdownEditor.tsx resources/js/components/admin/VariantManager.tsx`
Expected: no errors (the `i18next` lint rule flags hard-coded strings; there should be none).

- [ ] **Step 5: Verify in the browser**

Edit a product in the admin. At phone width and desktop width check: typing in Write; Preview tab shows bold, a link, a list and line breaks exactly like the storefront; "Add photo" uploads and inserts `![name](url)`; Preview then shows the photo; a non-image file shows the red upload error; saving the form still works.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/admin lang
git commit -m "feat: markdown editor with preview and photo upload for variant descriptions"
```

---

### Task 7: Admin SEO error fix and counter

**Root cause (verify first):** `Edit.tsx` and `Create.tsx` submit with `router.post(...)`, but display errors from `useForm`'s own `errors` state, which `router.post` never fills. The validation message for `seo_description` is therefore never shown, and `Edit.tsx` falls through to the generic "check the console" alert. The SEO field also sits in a closed "advanced options" card.

**Files:**
- Modify: `resources/js/pages/Admin/Products/Edit.tsx` (imports line 15, `useForm` destructure ~line 75-100, `router.post` options ~lines 236-252, SEO textarea ~lines 462-471)
- Modify: `resources/js/pages/Admin/Products/Create.tsx` (imports line 15, `useForm` destructure line 68, `router.post` ~line 156, SEO textarea ~lines 356-365)
- Modify: `lang/{en,it,de,es,fr}.json` (key after `"admin.products.placeholder_seo_description"`)

- [ ] **Step 1: Add the translation key**

| key | en | it | de | es | fr |
|-----|----|----|----|----|----|
| `admin.products.seo_description_counter` | {count} characters (about 160 show in Google, max 500) | {count} caratteri (circa 160 compaiono su Google, massimo 500) | {count} Zeichen (etwa 160 erscheinen bei Google, maximal 500) | {count} caracteres (unos 160 aparecen en Google, máximo 500) | {count} caractères (environ 160 s'affichent dans Google, maximum 500) |

- [ ] **Step 2: Edit `Edit.tsx`**

1. `import { useState } from 'react';` becomes `import { useEffect, useState } from 'react';`.
2. In the `useForm(...)` call, add `setError` to the destructured values (it currently reads `const { data, setData, errors, ... } = useForm(...)`; keep the existing names and add `setError`, and run `grep -n "useForm" resources/js/pages/Admin/Products/Edit.tsx` to find the exact line).
3. Directly after `const formErrors = errors as Record<string, any>;` (the line just before `return (`) add:

```tsx
    // A server error on an SEO field must be visible: open the card that holds them
    useEffect(() => {
        if (formErrors.seo_title || formErrors.seo_description) {
            setIsAdvancedOpen(true);
        }
    }, [formErrors.seo_title, formErrors.seo_description]);
```

4. In the `router.post(...)` options, at the start of `onError`, after `console.error('Server errors:', errors);`, add `setError(errors);` and replace the final `else { alert(t('admin.products.error_occurred')); }` with:

```tsx
                } else if (!errors.seo_title && !errors.seo_description) {
                    // SEO errors are shown under their field; anything else: the first server message, never "check the console"
                    alert(Object.values(errors)[0] || t('admin.products.error_occurred'));
                }
```

5. Below the SEO `Textarea`'s error line (`{formErrors.seo_description && ...}`) add the counter:

```tsx
                                            <p
                                                className={`mt-1 text-xs ${((data.seo_description as string) || '').length > 500 ? 'text-red-500' : ((data.seo_description as string) || '').length > 160 ? 'text-amber-600' : 'text-gray-500'}`}
                                            >
                                                {t('admin.products.seo_description_counter', { count: ((data.seo_description as string) || '').length })}
                                            </p>
```

- [ ] **Step 3: Edit `Create.tsx`**

1. `import { useState } from 'react';` becomes `import { useEffect, useState } from 'react';`.
2. Line 68: `const { data, setData, processing, errors } = useForm<ProductFormData>({` becomes `const { data, setData, processing, errors, setError } = useForm<ProductFormData>({`.
3. Directly after the `useForm(...)` call closes (before the first handler), add:

```tsx
    // A server error on an SEO field must be visible: open the card that holds them
    useEffect(() => {
        if (errors.seo_title || errors.seo_description) {
            setIsAdvancedOpen(true);
        }
    }, [errors.seo_title, errors.seo_description]);
```

4. `router.post(route('admin.products.store'), formData, { forceFormData: true, });` gets an `onError`:

```tsx
        router.post(route('admin.products.store'), formData, {
            forceFormData: true,
            onError: (serverErrors) => setError(serverErrors),
        });
```

5. After `{errors.seo_description && <p ...>}` add the counter:

```tsx
                                            <p
                                                className={`mt-1 text-xs ${data.seo_description.length > 500 ? 'text-red-500' : data.seo_description.length > 160 ? 'text-amber-600' : 'text-gray-500'}`}
                                            >
                                                {t('admin.products.seo_description_counter', { count: data.seo_description.length })}
                                            </p>
```

- [ ] **Step 4: Verify types and lint**

Run: `npm run types && npx eslint resources/js/pages/Admin/Products/Edit.tsx resources/js/pages/Admin/Products/Create.tsx`
Expected: no errors. If `setError(errors)` is rejected by the type checker in `Edit.tsx` (the form data generic is `Record<string, unknown>`-like), cast: `setError(errors as Record<string, string>)`.

- [ ] **Step 5: Reproduce and verify in the browser**

Edit a product, type 3 lines of SEO description over 500 characters (paste a long text), save. Expected before the fix: generic alert. After: no generic alert, the advanced card opens, the red message sits under the SEO field, the counter is red. Shorten under 500 and save: saves. Repeat once on the Create page.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/Admin/Products lang
git commit -m "fix: show server errors for SEO fields in the product form"
```

---

### Task 8: Final verification

- [ ] **Step 1: Run the affected PHP tests**

Run: `php artisan test --compact tests/Feature/Catalog tests/Feature/Admin`
Expected: all pass. Ask the user to run the full suite (`php artisan test --compact`) as the last check, per project rules.

- [ ] **Step 2: Frontend checks and build**

Run: `npm run check && npm run test:js && npm run build`
Expected: lint and types clean, vitest passes, build succeeds.

- [ ] **Step 3: Confirm no other consumer reads the description**

Run: `grep -rnE "(variant|v|selectedVariant|defaultVariant)(\?)?(->|\.)description" app resources routes --include='*.php' --include='*.tsx' --include='*.ts' | grep -v "Admin/Products\|VariantManager"`
Expected: only `ProductsController.php` (the three map lines) and `product.tsx` (no `selectedVariant.description` left except none). The homepage, listings, cart, wishlist, quick view and emails do not read variant descriptions.

- [ ] **Step 4: Update the knowledge graph and spec**

Run: `graphify update .`

Update the spec `docs/superpowers/specs/2026-10-25-product-description-markdown-design.md` so it matches what was built: (a) code blocks are not disabled, they render as plain preformatted text; (b) search is unchanged (it only reads the product-level column); (c) bug 2 root cause is `router.post` not filling `useForm` errors, plus the closed card.

- [ ] **Step 5: Commit**

```bash
git add graphify-out docs
git commit -m "docs: align the description spec with the implementation"
```

Do not deploy. The deploy is done once for all remediation work, only when the user asks.

---

## Self-review (spec coverage)

- Renderer with `toHtml` / `toPlainText` and all sanitization rules: Task 2. Image folder and base URL: Task 1.
- Backend wiring (props, meta fallback, max length): Task 3. Preview and upload routes with admin protection, limits, throttle: Task 4.
- Admin editor (tabs, preview via server, photo upload, replaces the Textarea): Task 6. Storefront `RichText`, mobile collapse, share text: Task 5.
- SEO error fix and counter: Task 7. Legacy newline behaviour: renderer soft-break test (Task 2) and the product-page prop test (Task 3).
- Names used across tasks: `uploadDescriptionImage`, `descriptionImageBaseUrl`, `MarkdownRenderer::MAX_LENGTH`, `toHtml`, `toPlainText`, routes `admin.products.markdown-preview` and `admin.products.description-images`, props `description_html` / `description_text`.
- Known limits: description photos are stored as absolute URLs inside the markdown, so changing `APP_URL` (or the s3 base URL) makes the renderer drop them until the text is re-saved; removed photos stay in storage; the 10000 limit is an estimate; photos in descriptions are limited to the upload folder (pasting a product photo URL is dropped).

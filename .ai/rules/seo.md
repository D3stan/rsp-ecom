---
paths:
  - config/seotools.php
  - app/Console/Commands/GenerateSitemap.php
  - app/Console/Commands/GenerateSitemapIndex.php
  - app/Traits/RegeneratesSitemap.php
  - resources/views/app.blade.php
---
# SEO

Page meta/OpenGraph/JSON-LD, canonical, robots and sitemap generation. Does not cover translated content (see `i18n.md`).

## Entry points
- `config/seotools.php`: package defaults; description and keywords are overridden by settings `seo.default_description` / `seo.keywords` (`config/settings.php`).
- `resources/views/app.blade.php`: emits canonical, SEOMeta, OpenGraph, TwitterCard and JSON-LD for every page.
- `app/Http/Controllers/ProductsController.php`: list and product pages (title, canonical, OG image, Product JSON-LD).
- `app/Console/Commands/GenerateSitemapIndex.php`: writes `public/sitemap*.xml` and updates `public/robots.txt`; `GenerateSitemap.php` is the single-file variant; `ClearSitemaps.php` is `sitemap:clear`.
- `app/Traits/RegeneratesSitemap.php`: used by `Product` and `Category`; triggers regeneration on change.
- `routes/console.php`, `docker/entrypoint.d/60-rsp-startup.sh`: schedules and startup rebuild.

## Rules
- MUST set per-page meta in the controller via the `SEOMeta`/`OpenGraph`/`TwitterCard` facades before rendering; the layout only prints them. Unenforced.
- MUST mark private/transactional pages (cart, dashboard) `noindex,nofollow` with `SEOMeta::addMeta('robots', ...)`. Unenforced.
- MUST keep filtered/searched `/products` pages `noindex,follow`. Unenforced.
- NEVER emit JSON-LD without `JSON_HEX_TAG` (it is inlined in a `<script>`). Unenforced.
- Product page title = `seo_title` or `name`; OG/Twitter image = `social_image_url` or main image, made absolute. `seo_title` is validated `nullable|string|max:255` in `app/Http/Controllers/Admin/ProductController.php`. Unenforced by tests.
- Only active, in-stock products and categories with at least one such product belong in the sitemap. Unenforced.
- SEO defaults reach the package despite lazy singletons. Enforced by `tests/Feature/Settings/ConfigBridgeTest.php` (opened: description and keywords only).

## How it works
1. Controller fills the facades; `app.blade.php` prints them plus JSON-LD.
2. Saving/creating/deleting a `Product` or `Category` (slug, status, is_active, name changed) queues `generate:sitemap-index` after the response, debounced by cache key `sitemap_regeneration_scheduled` (5 min).
3. `generate:sitemap-index` writes `sitemap-main.xml`, `sitemap-categories.xml`, `sitemap-products-N.xml` (5000 URLs per file) and the `sitemap.xml` index, then rewrites the `Sitemap:` line of `public/robots.txt` using `config('app.url')`.

## Traps
- Duplicate canonical: `app.blade.php` always prints `<link rel="canonical">` (`APP_URL` + request URI, query string included) and then `SEOMeta::generate()` prints a second one on every page whose controller calls `setCanonical` (home, products list, product, contact, static pages). Such pages emit two canonical tags; only pages without `setCanonical` get one. Known; do not add more `setCanonical` calls without fixing the layout.
- `public/robots.txt` exists (tracked) and shadows the `/robots.txt` route in `routes/web.php`: the web server serves existing static files first, so the route is effectively dead. Keep the rules in both in sync. The tracked file's `Sitemap:` line is localhost until `generate:sitemap-index` rewrites it.
- `generate:sitemap` writes the single `sitemap.xml` to the same path as the index, so the Sunday 03:00 run replaces the index until the next daily 02:00 run; the `sitemap-*.xml` chunk files stay but are unreferenced meanwhile.
- No hreflang tags: locales are session-based on the same URLs, so there are no per-locale URLs to point at.
- Sitemaps live in `public/`, which is rebuilt each deploy; they are gitignored.
- Category sitemap URLs are `/products?category=slug`, which `ProductsController` serves as `noindex,follow`.

## Operate
- `php artisan generate:sitemap-index [--force]`: daily 02:00 (`routes/console.php`), also run at container start by `docker/entrypoint.d/60-rsp-startup.sh` (skipped, together with the cache clear, when `RSP_STARTUP_TASKS=false`; the queue and scheduler containers set it).
- `php artisan generate:sitemap`: weekly Sunday 03:00. `php artisan sitemap:clear [--confirm]`: deletes `sitemap.xml`, `sitemap-main.xml`, `sitemap-categories.xml` and `sitemap-products-*.xml` (not `robots.txt`).
- Logs: `storage/logs/sitemap.log`, `storage/logs/sitemap-simple.log` (last run only).
- `APP_URL`: base for sitemap URLs, robots `Sitemap:` line and the layout canonical.

## Tests
- `tests/Feature/Settings/ConfigBridgeTest.php`: SEO settings reach SEOTools defaults.
- No test covers sitemap generation, robots or JSON-LD output.

---
paths:
  - config/seotools.php
  - app/Console/Commands/GenerateSitemapIndex.php
  - app/Traits/RegeneratesSitemap.php
  - resources/views/app.blade.php
---
# SEO

Page meta/OpenGraph/JSON-LD, canonical, robots and sitemap generation. Does not cover translated content (see `i18n.md`).

## Entry points
- `config/seotools.php`: package defaults; description and keywords are overridden by settings `seo.default_description` / `seo.keywords` (`config/settings.php`).
- `resources/views/app.blade.php`: prints SEOMeta (incl. the one canonical), OpenGraph, TwitterCard and JSON-LD for every page.
- `app/Http/Controllers/ProductsController.php`: list and product pages (title, canonical, OG image, Product JSON-LD).
- `app/Console/Commands/GenerateSitemapIndex.php`: the only sitemap writer: `public/sitemap*.xml` plus the `Sitemap:` line of `public/robots.txt`; `ClearSitemaps.php` is `sitemap:clear`.
- `app/Traits/RegeneratesSitemap.php`: used by `Product` and `Category`; triggers regeneration on change.
- `routes/console.php`, `docker/entrypoint.d/60-rsp-startup.sh`: schedules and startup rebuild.

## Rules
- MUST set per-page meta in the controller via the `SEOMeta`/`OpenGraph`/`TwitterCard` facades before rendering; the layout only prints them. Unenforced.
- MUST mark private/transactional pages (cart, dashboard) `noindex,nofollow` with `SEOMeta::addMeta('robots', ...)`. Unenforced.
- MUST keep searched, price-filtered, sorted and unknown/inactive-category `/products` pages `noindex,follow`; a plain active `?category=` page is the category landing page and is indexed, canonical and `og:url` = just the category. Enforced by `tests/Feature/Seo/CategoryLandingIndexingTest.php`.
- MUST print exactly one canonical per page: only SEOMeta prints it (controller `setCanonical`, else `defaults.canonical = 'current'`, the URL without its query string). Never add a canonical to the layout. Enforced by `tests/Feature/Seo/CanonicalTagTest.php`.
- NEVER write `public/sitemap.xml` from anything but `generate:sitemap-index`: it is the index of the chunk files. Enforced by `tests/Feature/Seo/SitemapScheduleTest.php` (schedule).
- NEVER emit JSON-LD without `JSON_HEX_TAG` (it is inlined in a `<script>`). Unenforced.
- Product page title = `seo_title` or `name`; OG/Twitter image = `social_image_url` or main image, made absolute. `seo_title` is validated `nullable|string|max:255` in `app/Http/Controllers/Admin/ProductController.php`. Unenforced by tests.
- Only active, in-stock products and categories with at least one such product belong in the sitemap. Unenforced.
- SEO defaults reach the package despite lazy singletons. Enforced by `tests/Feature/Settings/ConfigBridgeTest.php` (opened: description and keywords only).

## How it works
1. Controller fills the facades; `app.blade.php` prints them plus JSON-LD.
2. Saving/creating/deleting a `Product` or `Category` (slug, status, is_active, name changed) queues `generate:sitemap-index` after the response, debounced by cache key `sitemap_regeneration_scheduled` (5 min).
3. `generate:sitemap-index` writes `sitemap-main.xml`, `sitemap-categories.xml`, `sitemap-products-N.xml` (5000 URLs per file) and the `sitemap.xml` index, then rewrites the `Sitemap:` line of `public/robots.txt` using `config('app.url')`.

## Traps
- `public/robots.txt` exists (tracked) and shadows the `/robots.txt` route in `routes/web.php`: the web server serves existing static files first, so the route is effectively dead. Keep the rules in both in sync. The tracked file's `Sitemap:` line is localhost until `generate:sitemap-index` rewrites it.
- No hreflang tags: locales are session-based on the same URLs, so there are no per-locale URLs to point at.
- Sitemaps live in `public/`, which is rebuilt each deploy; they are gitignored.

## Operate
- `php artisan generate:sitemap-index [--force]`: daily 02:00 (`routes/console.php`), also run at container start by `docker/entrypoint.d/60-rsp-startup.sh` (skipped, together with the cache clear, when `RSP_STARTUP_TASKS=false`; the queue and scheduler containers set it).
- `php artisan sitemap:clear [--confirm]`: deletes `sitemap.xml`, `sitemap-main.xml`, `sitemap-categories.xml` and `sitemap-products-*.xml` (not `robots.txt`).
- Log: `storage/logs/sitemap.log` (last run only).
- `APP_URL`: base for the robots `Sitemap:` line; sitemap URLs and canonicals use the request host with https forced.

## Tests
- `tests/Feature/Settings/ConfigBridgeTest.php`: SEO settings reach SEOTools defaults.
- `tests/Feature/Seo/`: one canonical per page, category landing indexing, sitemap schedule.
- No test covers sitemap file contents, robots or JSON-LD output.

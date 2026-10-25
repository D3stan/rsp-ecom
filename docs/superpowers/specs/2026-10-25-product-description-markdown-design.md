# Markdown product descriptions + two admin fixes

Date: 2026-10-25

## Goals

1. Variant descriptions support markdown (links, additional photos) with an edit/preview editor in the admin.
2. Customers see the rendered description on the product page.
3. The markdown handling is independent of the frontend: a future frontend overhaul must not need to know about markdown.
4. Fix: the storefront variant description ignores line breaks.
5. Fix: a long SEO description fails with a generic "check the console" alert.

## Decisions

- Scope: the per-variant `product_variants.description` column only. No schema change, no product-level description.
- Rendering: on the server, with `league/commonmark` (already installed). No new npm or composer dependency.
- Images: an upload button in the editor, stored on our own storage.
- Mobile: the editor uses Write/Preview tabs on every screen size (no side-by-side layout).
- Descriptions are a single language (no per-locale content).

## Root causes of the two bugs

- Bug 1: `resources/js/pages/product.tsx:395` renders the description in a plain `<p>` without `whitespace-pre-line`, so newlines collapse. Superseded by markdown rendering, which keeps legacy line breaks (see Renderer).
- Bug 2: `seo_description` is validated `max:500` (`Admin/ProductController.php`, store and update). `Edit.tsx` and `Create.tsx` submit with `router.post(...)` but display errors from `useForm`'s own `errors` state, which a separate `router.post` never fills (verified in the installed Inertia 2.0.17), so the field message was never rendered; `Edit.tsx` fell through to a generic `alert()`. The SEO fields also sit in a collapsed "advanced options" card.

## Components

### 1. `app/Services/MarkdownRenderer.php`

- `toHtml(?string $markdown): string`
  - Raw HTML input is stripped.
  - Only `http`, `https` and `mailto` links are allowed. Links get `rel="nofollow noopener"` and `target="_blank"`.
  - Images are allowed only when their URL points at our storage (the public disk / configured S3 URL). Any other image is dropped.
  - Headings are demoted to `h3` and below, so the page never gets a second `h1`.
  - Single newlines render as `<br>`, so existing plain-text descriptions look the same as before.
  - Tables are not enabled. Code blocks are CommonMark core and render as plain preformatted text (not specially removed).
  - Image URLs must be under the description folder and contain no `..` segment (plain or percent-encoded); a link or paragraph left empty by a dropped image is removed.
- `toPlainText(?string $markdown): string`: the rendered HTML stripped of tags, whitespace collapsed. Used for meta description, JSON-LD and search so markdown syntax never leaks into SEO tags.

### 2. Backend wiring

- `ProductsController` (product page props): each variant gets `description_html` next to the existing `description`.
- `ProductsController` meta description fallback and JSON-LD use `toPlainText` of the default variant description. The search is unchanged (it only reads the product-level `description` column). The page's JSON-LD block is now encoded with `JSON_HEX_TAG` in `resources/views/app.blade.php`, because `toPlainText` decodes entities and text such as `</script>` must never close the script tag (this also closes the same hole for buyer review comments).
- The two admin routes have no throttle middleware: an unnamed `throttle:N,1` shares one counter per user with every other plain throttle route.
- Admin validation: `variants.*.description` gets `max:10000`. `seo_description` stays `max:500`.
- Two admin routes under the existing product admin group:
  - `admin.products.markdown-preview` (POST, text in, `{html}` out, via `MarkdownRenderer::toHtml`).
  - `admin.products.description-images` (POST, image file in, `{url}` out). Validated with `FileUploadConfigService::getFileValidationRule()`. Stored under `products/descriptions/` through the same disk logic as `ImageUploadService`, so it works on Create, where no product id exists yet.

### 3. Admin editor: `resources/js/components/admin/MarkdownEditor.tsx`

- Write / Preview tabs. Preview posts to `markdown-preview` with a debounce and shows the returned HTML in the same `RichText` styling the storefront uses.
- "Add photo" button uploads to `description-images` and inserts `![](url)` at the cursor.
- Replaces the `Textarea` in `components/admin/VariantManager.tsx`. No change to the form payload shape (the field is still `description`).

### 4. Storefront: `resources/js/components/product/RichText.tsx`

- The only place that uses `dangerouslySetInnerHTML`. Minimal styling: images `max-w-full` and lazy loaded, links underlined.
- Replaces the `<p>` at `product.tsx:395`.
- On mobile, content taller than about 6 lines is collapsed behind a "read more" button.
- Other pages (home, listings, quick view, cart, wishlist, emails) do not show descriptions and are unchanged.

### 5. Admin SEO error fix

- `Edit.tsx` and `Create.tsx`: when the server returns field errors, open the advanced card if the error is on an SEO field, and show a toast naming the field instead of the generic alert.
- Add a character counter to `seo_description` (hint at 160, hard limit still 500).

## Error handling

- Preview and upload failures show an inline message in the editor and never block saving the form.
- The renderer never throws on malformed markdown (CommonMark accepts any input).

## Tests (Pest)

- `MarkdownRenderer`: `<script>` stripped, `javascript:` link blocked, raw HTML stripped, off-site image dropped, own-storage image kept, legacy newline text keeps `<br>`, headings demoted, `toPlainText` has no markdown syntax.
- Product page: the Inertia response carries `description_html`; meta description fallback uses plain text.
- Endpoints: preview and upload require an admin, upload rejects non-images and oversize files, upload returns a storage URL.
- Validation: description over 10000 characters is rejected; an SEO description over 500 returns the field error.

## Known limits (accepted)

- Images removed from the text stay in storage (no orphan cleanup).
- Description photos are stored as absolute URLs inside the markdown, so changing `APP_URL` (or the s3 base URL) makes the renderer drop them until the text is re-saved.
- The Create page shows no message for server errors on variant fields (`variants.N.*`): a pre-existing gap, not changed here.
- The 10000-character description limit is an estimate.
- Preview needs a network round trip to the server.

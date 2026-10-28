---
paths:
  - app/Services/Reviews/*.php
  - app/Http/Controllers/ReviewController.php
  - app/Http/Controllers/Admin/Review*.php
  - app/Models/Review*.php
  - tests/Feature/Reviews/*.php
---
# Reviews

Customer reviews, review photos, admin moderation and the review-request email. Does not cover the generic email pipeline (`.ai/rules/email-newsletter.md`) or settings storage (`.ai/rules/settings.md`).

## Entry points
- `app/Http/Controllers/ReviewController.php`: customer create/store; eligibility checks and the transaction that writes review plus photos.
- `app/Services/Reviews/ReviewPhotoProcessor.php`: the only place an upload is decoded, re-encoded and written.
- `app/Models/Review.php`, `app/Models/ReviewMedia.php`: moderation state transitions (`approve`, `reject`, `approveWithMedia`); use these, never raw updates.
- `app/Http/Controllers/Admin/ReviewsController.php`, `ReviewMediaController.php`: text and per-photo moderation.
- `app/Services/Reviews/ReviewRequests.php`, `app/Console/Commands/SendReviewRequests.php`: who gets the request email.
- `routes/console.php`: `reviews:send-requests` schedule.

## Rules
- MUST re-encode every photo through `ReviewPhotoProcessor` (GD decode, WebP re-encode, EXIF dropped after orientation is applied). Enforced by `tests/Feature/Reviews/ReviewPhotosTest.php` (EXIF/GPS strip, orientation, size cap).
- MUST accept only JPEG, PNG, WebP detected from file content, max 4 photos. Enforced by `ReviewPhotosTest.php` (disguised and truncated files, more than 4 refused, nothing created).
- MUST create photos as `pending`, even when `reviews.auto_approve` is on. Enforced by `ReviewPhotosTest.php`.
- MUST send only `approved` photos of `approved` reviews to storefront props. Enforced by `ReviewPhotosTest.php` and `ProductReviewsDisplayTest.php`.
- MUST reject a review's photos (and un-feature them) on every reject path, single and bulk. Enforced by `tests/Feature/Reviews/ReviewMediaModerationTest.php`.
- MUST only feature `approved` photos. Enforced by `ReviewMediaModerationTest.php`.
- MUST scope a photo moderation URL to its review. Enforced by `ReviewMediaModerationTest.php` (photo addressed through another review refused).
- MUST delete photo files when a review, its user or its product is deleted. Enforced by `ReviewMediaModerationTest.php` (the `static::deleted` hook in `Review.php`).
- MUST keep one review per user and product. Enforced by DB unique index on `reviews(user_id, product_id)` and a controller check.
- MUST allow reviewing only a product from the user's own `delivered` order. Unenforced by tests (the controller check has no failing-case test).
- MUST send at most one review request per order. Enforced by `OrderEmail` dedupe, tested in `tests/Feature/Reviews/ReviewRequestEmailTest.php`.
- MUST send requests only for registered customers, delivered orders older than the delay, not cancelled or refunded, with marketing consent and an unreviewed product. Enforced by `ReviewRequestEmailTest.php`.
- NEVER write review photos to any disk but `ReviewMedia::DISK`. Unenforced.

## How it works
1. `POST reviews` (auth and verified) validates rating, comment (max 1000) and photos, then checks one-review and delivered-purchase rules.
2. Every photo is prepared (decoded, capped at 2000 px long edge, 600 px thumbnail) before anything is saved; an unreadable file is a validation error on `photos.N`.
3. In one DB transaction the review is created with `is_approved = reviews.auto_approve` (default on) and photos are stored as `pending` under `reviews/{review_id}/`. If it fails, files already written are deleted.
4. Moderation is two independent states: `reviews.is_approved` for the text, `review_media.status` (pending, approved, rejected) per photo. Approving text does not approve photos.
5. `approveWithMedia` approves the text and all `pending` photos; photos already `rejected` stay rejected.
6. `reviews:send-requests` runs daily and does nothing while `reviews.request_enabled` is off; it walks eligible orders in chunks and calls `EmailService::sendReviewRequest`.

## Traps
- Photos wait for admin approval even with auto-approve on: intentional, the success message differs (`reviews.photos_pending`).
- Reject is not the inverse of approve for photos: approve touches text only, reject cascades to photos.
- The admin `products.reviews.store` route creates reviews outside the customer rules (no purchase check, no photos, `is_approved` not set); do not reuse it as the model of the customer flow.
- `verified_purchase` shown on product pages is hardcoded `true` in `app/Http/Controllers/ProductsController.php`; it is valid only because customers must have a delivered order.
- Photo file size limit comes from `FileUploadConfigService` (PHP ini aware), not a constant.

## Operate
- `php artisan reviews:send-requests` (daily 09:00, `withoutOverlapping`). Safe to rerun.
- Settings (admin, see `.ai/rules/settings.md`): `reviews.auto_approve`, `reviews.request_enabled` (default off), `reviews.request_delay_days` (default 10, 1 to 60).
- Needs GD with WebP support; `ReviewPhotoProcessor` throws otherwise.

## Tests
- `tests/Feature/Reviews/ReviewPhotosTest.php`: upload validation, re-encoding, EXIF, pending state.
- `tests/Feature/Reviews/ReviewMediaModerationTest.php`: photo and review moderation, permissions, file cleanup.
- `tests/Feature/Reviews/ReviewAutoApproveTest.php`: `reviews.auto_approve` on and off.
- `tests/Feature/Reviews/ReviewRequestEmailTest.php`: request eligibility and dedupe.
- `tests/Feature/Reviews/ProductReviewsDisplayTest.php`: storefront props and JSON-LD.

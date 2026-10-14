# WS-H: Photo reviews & consented review request

> Branch `feat/photo-reviews` from `fixes` **after WS-B, WS-D and WS-F are merged** (it uses B's PDP rating props,
> D's `delivered_at`, and F's `MarketingConsent::allows()`). Wave 2. Read `00-overview.md` first; follow its per-item
> protocol. Migration prefix: `2026_10_16_20xxxx`. **Size: M.** Order: H1 → H2 → H3 → H4.
> **Don't touch the admin "add review" form** on `Admin/Products/Show.tsx` (owner decision).

**What exists:** reviews by buyers of **delivered** orders only (`ReviewController.php:86-95`, routes under `auth`), one
per user per product (unique key), moderation with `reviews.auto_approve` (`Admin\ReviewsController`), and image
handling in `App\Services\ImageUploadService` + `FileUploadConfigService`. `app/Mail/OrderDelivered.php:13` notes that
the team treats review requests as marketing. Keep that stance.

---

### H1: photos on reviews (upload + storage). Size **M**

- Table `review_media`: id, review_id FK cascade, path, width, height, sort_order, status enum(pending, approved,
  rejected), timestamps.
- `Reviews/Create.tsx`: up to **4** images (JPEG/PNG/WebP; max size from `FileUploadConfigService`), with client-side
  previews and remove buttons. Server: validate mime by content, **re-encode** (this strips EXIF/GPS: verify with a
  test that reads EXIF from the stored file), cap the long edge at 2000 px plus a 600 px thumbnail, and store under
  `reviews/{review_id}/{uuid}.webp` on the public disk with an unguessable name.
- **Photos are never exposed before approval**, even when `reviews.auto_approve` approves the text. Only `approved` media
  appear in any storefront prop.
- Tests: an upload creates pending media; more than 4 is refused; a non-image disguised as `.jpg` is refused; EXIF is stripped;
  pending media is absent from PDP props.

### H2: moderation of media. Size **S**

- Admin review list/show: thumbnails with a per-image **Approve / Reject** and a bulk "approve review + all photos". A
  **"Feature on home page"** toggle per image (`review_media.featured` bool, default false).
- Rejecting a review rejects its media. Deleting a review deletes the files (test it with `Storage::fake`).
- Tests: state transitions; customers get 403; file cleanup.

### H3: PDP reviews block and home gallery. Size **M**

- PDP (`product.tsx`): rating summary (average + distribution bars), a photo strip of approved media opening a
  lightbox (shadcn `Dialog`, keyboard navigable, labelled controls), the reviews list **expanded by default when there
  are photos**, and a "Verified purchase" badge (all reviews come from buyers of delivered orders). Add one line
  "How we verify reviews: only customers with a delivered order can leave a review." (Omnibus Directive transparency),
  linked to the FAQ or terms. Put it in `components/reviews/*` and keep the edits to `product.tsx` minimal.
- JSON-LD: add `aggregateRating` and up to 5 `review` entries to the existing Product schema in `ProductsController@show`,
  consistent with what's displayed.
- Home: a "Rider gallery" strip of featured approved photos (max 12) linking to their PDPs, hidden when empty.
  `loading="lazy"` with intrinsic sizes.
- Strings in 5 locales. Tests: props contain only approved media, the distribution adds up, JSON-LD is present and
  matches.

### H4: consented review-request email. Size **S-M**

- Settings `reviews.request_enabled` (bool, default **false**) and `reviews.request_delay_days` (int, default 10,
  min 1, max 60), with labels/help in 5 locales.
- A daily scheduled command finds orders `delivered_at ≤ now − delay` that have a `user_id` (reviews need an account),
  haven't been requested yet, aren't refunded or cancelled, and have at least one product the user hasn't reviewed, **and**
  `MarketingConsent::allows($order->getCustomerEmail())`. It sends one `ReviewRequest` email per order: product thumbnails
  with direct links to `reviews.create/{product}` and a one-click unsubscribe (the newsletter unsubscribe, since the
  basis is the same marketing consent).
- Dedupe through `OrderEmail` with a new `TYPE_REVIEW_REQUEST` (fingerprint default) → at most one per order.
- Tests: the eligibility matrix (no consent ⇒ none; guest order ⇒ none; refunded ⇒ none; all products reviewed ⇒ none;
  sent once even if the command runs twice); the setting off ⇒ none; the unsubscribe header is present.

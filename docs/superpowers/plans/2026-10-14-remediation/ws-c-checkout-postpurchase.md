# WS-C: Checkout, confirmation, translations fallback, hero, free shipping

> Branch `fix/checkout-postpurchase` from `fixes`. Wave 1. Read `00-overview.md` first; follow its per-item protocol.
> Migration prefix: `2026_10_15_30xxxx` (probably none needed).
> WS-B adds Vitest (task B0). You don't have it on your branch, so use Pest tests + static reproductions only.
> WS-F will later add one consent checkbox component to `Checkout/Details.tsx`: leave a clean spot above the
> Pay button (the coordinator resolves the merge).

Order: C1 (free shipping, S) → C2 (missing keys + fallback, S) → C3 (success pending, S) → C4 (checkout, M) → C5 (hero, M).

---

### C1 (P2): free-shipping threshold (admin setting, default 1000 EUR). Size **S**

**Feature check:** shipping is the sum of each line's box `shipping_cost` (`app/Models/Cart.php:42-49`), with the
docblock "never waived or capped" at `CheckoutController.php:~307`. There's no threshold setting in `config/settings.php`.

**Spec:**
- New setting `shipping.free_threshold`: `float`, default `1000`, min `0`, max `100000`, unit `EUR`, `public: true`.
  **0 disables it.** Labels/help in 5 locales. Help text: "Orders whose items total (after discount) reaches this amount ship free. 0 = off."
- Rule: `items_total_after_discount >= threshold && threshold > 0` ⇒ shipping = 0. "Items total after discount" uses
  the same numbers `CheckoutController::checkoutTotals` (`:312`) and `couponDiscount` (`:331`) already compute.
- **Single source of truth:** put the rule in one place (e.g. a small `App\Support\Shipping::costFor(float $itemsTotal, float $baseShipping): float`
  or a method on `Cart`) and use it from `Cart::getShippingCostAttribute`, `checkoutTotals`, and therefore
  `CheckoutSnapshot::capture`, the emails and the Stripe amount. Grep for every place that reads `shipping_cost` /
  `shipping` totals and make sure none bypass it.
- UI: cart and checkout show "Free shipping" when it applies, otherwise "Add €X more for free shipping" (only when the
  threshold > 0). Use `useMoney`, translated keys `shipping.free`, `shipping.free_remaining` in 5 locales.

**Tests** `tests/Feature/Checkout/FreeShippingThresholdTest.php`: under the threshold → shipping charged; exactly at
the threshold → free; a coupon pushing the total below the threshold → charged; threshold 0 → always charged; the snapshot
and the Stripe session amount match the displayed total (use the existing Stripe fake).

---

### C2 (P1, H8): missing de/fr/es keys and no English fallback. Size **S**

**Claim:** de/fr/es lack ~17 keys used in cart/checkout (`cart.coupon_*`, `cart.checkout_error*`, `cart.missing_information`,
`cart.email_required`, `cart.invalid_email`, `cart.shipping_required`, `cart.error`, `cart.discount_applied_label`), and
it/fr/es lack `dashboard.account_settings`. `t()` returns the raw key when a key is missing
(`resources/js/contexts/TranslationContext.tsx:139-182`).

**Repro test** extend `tests/Feature/LocalesTest.php` with a **parity test**: flatten every locale file to dotted keys;
each non-en locale must have every en key. It fails today and lists the missing keys. (If parity across the *whole*
file shows many more gaps than the report says, include all of them and translate them all.)

**Fix:** add the missing keys with real translations. Add an English fallback per key: the server shares en merged
under the active locale (check `app/Http/Middleware/ShareTranslations.php` and the `/api/translations/{locale}`
route in `routes/web.php:123`), or `t()` falls back to an `en` dictionary. Pick the one that doesn't double the payload
(server-side deep merge is preferred). Test: a key missing from `de` renders the en value.

---

### C3 (P1, H4): Success page spins forever when the order isn't created yet. Size **S**

**Claim:** when order creation lags behind or fails after payment, `CheckoutController::success` returns `id: 'pending'`
(`:675-697`), and `pages/Checkout/Success.tsx:81,113` wraps the page in `LoadingOverlay isLoading`, a full-screen
spinner with no timeout or polling.

**Repro test:** fake a Stripe session that is paid but has no order yet (existing fixtures) → GET `checkout.success`
→ assert the props carry the pending marker (documents the state). Static reproduction of the overlay with no exit.

**Fix:** no overlay. Show a clear state: "Payment received. We're finalising your order and will email you the
confirmation." Poll with `router.reload({ only: ['order'] })` every 3 s for at most 60 s, then stop and show a support
contact line (`contact.support_email` setting). When the order appears, render the normal page. Strings in 5 locales.
Also fix `LoadingOverlay` so it applies `select-none` only while loading (`components/LoadingOverlay.tsx:32`), since it is
in the same component.

---

### C4 (P1, H1 + H2): checkout lists no items; no form semantics; generic errors. Size **M**

**Claim:** `pages/Checkout/Details.tsx` never maps `cartItems` (`:77,296`: used only for the empty check). There is no
`<form>`, no `autocomplete` tokens, the phone field isn't `type="tel"`, and there is no `inputMode` on the postal code.
Validation is client-side toasts with one generic message, and server errors show only `Object.values(errors)[0]`.
The Pay button re-enables in `onFinish` before the Stripe redirect, which allows a double submit.

**Repro:** a Pest test asserting the details page props include the cart lines (data is there; the UI ignores it),
plus a static reproduction for each sub-claim.

**Spec:**
- **Order summary component** `resources/js/components/checkout/OrderSummary.tsx`: thumbnail, name, **variant name**,
  qty, line total; subtotal, discount, shipping (with C1's free-shipping message), VAT-included line, total.
  Desktop: right column, sticky. Mobile: collapsible "Show order summary (€X)" at the top.
- **Form semantics:** wrap the fields in `<form onSubmit>` (keep the existing submit logic). Add
  `autocomplete` = `email`, `given-name`, `family-name`, `organization`, `address-line1`, `address-line2`, `address-level2`,
  `address-level1`, `postal-code`, `country`, `tel`. Phone `type="tel"`, postal code `inputMode="text"` with
  `autoCapitalize="characters"`.
- **Errors:** field-level errors under each input with the existing `InputError` component (`login.tsx:82`), for both
  client-side checks and server `errors`. `aria-invalid` on invalid inputs. Focus the first invalid field on submit.
  Remove the generic "missing information" toast.
- **Double submit:** keep `isProcessing` true until navigation (don't reset it in `onFinish` when a redirect URL was
  returned).
- Keep the visual style of the page. Don't redesign the layout beyond adding the summary column.
- New strings in 5 locales.

**Tests:** Pest: the details props include the line data the summary needs (name, variant name, qty, unit price,
image). Server validation errors come back keyed by field (`assertSessionHasErrors(['email', 'postal_code'])`).

---

### C5 (P1, H13): hero performance and motion. Size **M**

**Claim:** `pages/home.tsx:188-241` loads `public/images/angels.png` (1.9 MB) with a CSS `filter: blur(3px)` at full
viewport, loads `angel-eye.png` twice (RingMaskedImage + a darkened `<img>`), and leaves `angeleye.webp` (50 KB) unused.
`respectReducedMotion={false}` (`:221`), the bounce cue runs forever, and no storefront `<img>` has `loading="lazy"`.

**Repro:** static reproduction with file sizes (`ls -la public/images`) and the quoted lines.

**Fix:**
- Pre-blur the background offline and export it as WebP (and AVIF if available) at 1600/1000/600 widths. Use
  `cwebp`/`sharp`/`ImageMagick`, whichever exists locally; if none does, use `npx sharp-cli` as a one-off. Commit
  the generated files, serve them with `srcset`/`sizes` and `fetchpriority="high"`, and remove the CSS blur filter.
- Load the eye image once (prefer `angeleye.webp` if it is the same asset; check visually by comparing dimensions).
- `respectReducedMotion` → true; disable the bounce cue under `prefers-reduced-motion`.
- `loading="lazy"` + `decoding="async"` + intrinsic `width`/`height` on below-the-fold storefront images
  (product cards, category cards, PDP thumbnails), never on the LCP image.
- Don't delete unused images in this item (WS-I or a later cleanup decides that).
- Report before/after bytes for the hero.

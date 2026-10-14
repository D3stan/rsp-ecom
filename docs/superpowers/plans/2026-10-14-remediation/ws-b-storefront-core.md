# WS-B: Storefront core fixes

> Branch `fix/storefront-core` from `fixes`. Wave 1. Read `00-overview.md` first; follow its per-item protocol.
> No migrations expected.

**Goal:** fix the broken storefront flows: losing the cart at login, invisible server messages, wrong filters,
fake ratings, the wishlist heart, wrong order links and numbers, and missing variant labels.

Order: B0 → B1 → … → B8. B1 is medium-sized; the rest are small.

---

### B0: Vitest for pure frontend logic (infrastructure, no repro needed)

Some bugs here live only in React. Add a minimal Vitest setup so pure functions can be tested:
- `npm i -D vitest` (no jsdom unless an item needs it); add `"test:js": "vitest run"` to `package.json`.
- `vitest.config.ts` reusing the `@/` alias from `vite.config.ts` / `tsconfig.json`.
- One smoke test, `resources/js/lib/__tests__/smoke.test.ts`.
- Commit as `chore(B0): add vitest for pure frontend logic`. Report it and wait for ACCEPTED like any other item.

---

### B1 (P0-4): guest cart lost on login/registration; wrong post-login destination. Size **M**

**Claim:** guest carts are keyed by `session_id` (`app/Http/Controllers/CartController.php:434-450`), and logged-in
carts by `user_id`. Login calls `session()->regenerate()` (`Auth/AuthenticatedSessionController.php:34-35`), and
nothing merges the guest cart into the user cart. The same goes for registration and Google login
(`GoogleAuthController.php:86,101,124,140`). `cart.tsx:134-136,463` sends users to `/login` with no intended URL,
so they land on `/dashboard`.

**Repro tests** `tests/Feature/Cart/GuestCartMergeTest.php`:
1. As a guest, add product X (qty 2) to the cart; then POST `login` with valid credentials of a user whose cart has
   X (qty 1) and Y (qty 1). Then GET `cart` → the cart contains X qty 3 (capped, see below) and Y qty 1. The guest cart
   row is gone.
2. Guest with items and a user with no cart: after login the user cart contains the guest items.
3. Registration (`register` route) keeps the guest cart.
4. Google login: follow the existing `tests/Feature/Auth/GoogleAuthTest.php` mocking style and assert the same.
5. Capping: merged qty never exceeds variant stock or `orders.max_qty_per_line` (when > 0).
6. Redirect: GET `cart` as guest → follow "sign in to continue" target → after login the redirect is the checkout
   details page (`checkout.details`), not `/dashboard`.

**Fix:**
- A `CartMerger` service (`app/Services/CartMerger.php`): `merge(string $guestSessionId, User $user): void`, in a DB
  transaction. Lines match on `(product_id, product_variant_id)`. Quantities are summed and capped by stock and
  max-per-line. Keep the user's coupon if any, else take the guest's. Delete the guest cart.
- Call it from a listener on `Illuminate\Auth\Events\Login` (also fires for Google login and for login after
  registration). **Check the timing:** the listener must read the guest session id **before** regeneration. The
  `Login` event fires inside `Auth::attempt()/login()`, before the controller regenerates, so `Session::getId()` there
  is still the guest id. Prove it with the test, don't assume. If any path regenerates first, capture the old id in
  that path.
- The cart's "Sign in to continue" sets the intended URL: link to a route that calls
  `redirect()->setIntendedUrl(route('checkout.details'))` before going to login, or pass `?redirect=` if the app already
  supports it (check `routes/web.php:49-69`, where the `/checkout` closure may already do this).
- Don't touch `CheckoutSnapshot.guest_session_id` / order ownership logic.

---

### B2 (P0-5): storefront never renders flash messages

**Claim:** `FlashToaster` is mounted only in `layouts/admin-layout.tsx:16` and `layouts/app/app-sidebar-layout.tsx:22`.
Storefront pages never show `flash.success/error` (shared in `app/Http/Middleware/HandleInertiaRequests.php:63-70`).
`pages/contact.tsx:14-21,56-72` reads `props.success/error`, which are never sent. `pages/auth/login.tsx:24,46-50`
reads an `error` prop that `AuthenticatedSessionController::create` never passes. In `Checkout/Details.tsx`, a backend
failure (`CheckoutController` `back()->with('error')`) looks like a button that does nothing.

**Repro:** PHP: a test that POSTs the contact form and asserts the redirect carries `flash.success` in the next Inertia
response (proves the server sends it). Frontend: a static reproduction showing no storefront layout mounts the toaster
(grep evidence), plus the dead prop reads.

**Fix:** mount `<FlashToaster />` **once** for every page (in `resources/js/app.tsx` around the page, or in the
storefront layouts), and remove it from the admin layouts so admin toasts don't show twice. Delete the dead
`success/error` prop reads in `contact.tsx` and `login.tsx` and rely on flashes. In `Checkout/Details.tsx`, an
`onSuccess` without a redirect URL shows an error toast. Check that the toaster dedupe (`flash-toaster.tsx`) still holds.

---

### B3 (P0-6): category filter applies the previous selection; price filters wipe search and sort

**Claim:** `pages/products.tsx:583-588` calls `setSelectedCategory(value)` and then `applyFilters()`, which reads the
stale `selectedCategory` from the closure (`:172-196`). The price radios and min/max `onBlur` (`:633-654, 675-695,
708-728`) build params with a hardcoded `search: ''` and `sort: 'name'`.

**Repro:** extract the param building into a pure function `buildProductFilterParams(current, overrides)`
(`resources/js/lib/product-filters.ts`). Vitest tests: overriding the category yields the new category; overriding
the price keeps search and sort. Write the tests against the **current behaviour first**: copy the current inline
logic into the function verbatim so the tests fail, commit that as the repro, then fix. Include the static
reproduction of the stale closure in the report.

**Fix:** one `applyFilters(overrides)` in `products.tsx` that always starts from the current state plus overrides,
using the pure function. Delete the three copy-pasted param builders. Keep the URL format the controller expects.

---

### B4 (P1): PDP shows 0 stars and "(0 reviews)"

**Claim:** `pages/product.tsx:305-309` renders `renderStars(0)` and the literal `(0 reviews)`.

**Repro test** `tests/Feature/Catalog/ProductRatingPropsTest.php`: a product with 2 approved reviews (4 and 5)
→ GET `products.show` → assert the props include `average_rating = 4.5` and `review_count = 2` (fails today if the
controller doesn't send them; if it already sends them, the bug is frontend-only: say so and give the static reproduction).

**Fix:** pass the values from `ProductsController@show` with `withAvg/withCount` on approved reviews. Render the real
stars with the translated count (`t('product.reviews_count', { count })`, adding keys in 5 locales), linking to the
reviews section. Hide the row when there are no reviews.

---

### B5 (P1): wishlist heart always starts unsaved

**Claim:** `pages/product.tsx:371-378` passes `initialInWishlist={false}`; clicking toggles
(`hooks/useWishlist.ts:63-70`), so a saved item gets removed.

**Repro test:** logged-in user with the product in the wishlist → GET `products.show` → assert a prop
`inWishlist === true` (fails today). Add a static reproduction of the hardcoded `false`.

**Fix:** send `inWishlist` from `ProductsController@show` (the listing already computes it; reuse that logic) and pass it
through.

---

### B6 (P1): header "Orders" link 404; account shows the DB id instead of the order number

**Claim:** `components/header.tsx:443-450` links to `/orders`; only `/dashboard/orders` (`orders.index`) and
`/orders/{order}` exist (`routes/web.php:82,228`). `pages/Dashboard/Orders.tsx:208` shows `order.id`, while emails,
Success and GuestShow use `order_number`.

**Repro:** test `GET /orders` → 404 (documents the dead link), plus a static reproduction for both.

**Fix:** `route('orders.index')`; display `order.order_number`. If the order list also searches or filters by number,
keep it consistent.

---

### B7 (P1): cart shows the shipping box size instead of the variant

**Claim:** `CartController.php:96-106` sends `variant.name` and `variant.max_quantity`, but `pages/cart.tsx:17-30,312-316`
omits them and renders `Size: {size.name}`, where `Size` is the shipping **box** (`app/Models/Size.php`). The same goes for
`pages/Dashboard/Orders.tsx:286-288` and `pages/Orders/GuestShow.tsx:118-122`.

**Repro test:** cart with a variant item → GET `cart` → assert props contain `variant.name` (proves the data is there).
Order pages: assert whether `variant_name` (or similar) is in the payload; if not, it has to be added.
Include the static reproduction of the UI.

**Fix:** show the variant name under the product name in the cart, account orders and the guest order page. Remove
the shipping-size label from customer views. Cap "+" at `variant.max_quantity`. Order items: use the stored variant
name (check `order_items` columns, e.g. `product_variant_id` or a name snapshot); if there's no snapshot, read it through the
relation, and if the variant was deleted show nothing. Don't add migrations unless strictly needed (explain if so).

---

### B8: post-merge sanity

Not a code item: run the full suite, `npm run types`, `npm run test:js`, and report a summary of all B items and their commits.

# WS-A: Data integrity & admin lists

> Branch `fix/data-integrity` from `fixes`. Wave 1. Read `00-overview.md` first; follow its per-item protocol.
> Migration prefix: `2026_10_15_10xxxx`.

**Goal:** stop data loss on account deletion, stop leaking internal order data to customers, make admin lists pageable,
make seeding safe in production, and stop catalogue cascades.

All items are small (S). Do them in order A1 → A5.

---

### A1 (P0-1): account deletion must keep orders

**Claim:** `orders.user_id` is `ON DELETE CASCADE` (`database/migrations/*_create_orders_table.php:17`) and
`ProfileController::destroy` hard-deletes the user (`app/Http/Controllers/Settings/ProfileController.php:~56`).
Deleting an account deletes the user's orders, order items, order emails and tracking events. `billing_address_id` /
`shipping_address_id` are also `cascade` (l.21-22). If addresses belong to the user and cascade from users, deleting
the user deletes the addresses and then the orders.

**Repro test** `tests/Feature/Orders/AccountDeletionKeepsOrdersTest.php`:
- create a user with a paid order (use existing factories/fixtures: look in `tests/Feature/Orders/*` for how
  orders are built), including order items and an `order_emails` row;
- `actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])`;
- assert the order, its items and its email row still exist; `orders.user_id` is null;
- assert the order still shows the customer's email and name (via `$order->getCustomerEmail()` / `getCustomerName()`).

**Fix:**
1. Migration `2026_10_15_100000_keep_orders_when_user_is_deleted.php`: change `orders.user_id` FK to
   `nullOnDelete()`. Check the `addresses` table FK to users and the `orders.*_address_id` FKs: make sure no chain
   from `users` deletes orders (addresses used by orders must survive: make `addresses.user_id` nullable +
   `nullOnDelete`, or orders' address FKs `restrictOnDelete`, whichever keeps the data. Explain the choice in the report).
   It must work on SQLite (tests) **and** MySQL. Write `down()`.
2. Before deleting the user, snapshot identity onto their orders so the admin still sees who bought:
   if `guest_email` is null, set it to the user's email; same for a name if the orders table has a place for it.
   Check `Order::getCustomerEmail()` / `getCustomerName()` (`app/Models/Order.php:333-350`) and make them fall back to
   the snapshot. Do this in one place (e.g. a `User` `deleting` model event, or an `AnonymizeUserOrders` action called
   from `ProfileController::destroy`). Prefer the model event, so every deletion path is covered.
3. Make sure the customer-side views don't break for orders without a user.

**Acceptance:** repro test passes; existing order/admin tests still pass; migration up+down works on SQLite.

---

### A2 (P0-3): internal order data leaks to the customer

**Claim:** `DashboardController::orders` builds each order with `$order->toArray()` (`app/Http/Controllers/DashboardController.php:25`),
so `notes` (internal admin notes: shipping dumps, refund notes, IBAN) plus Stripe/session ids reach the customer's page props.
Also check `GuestOrderController::show` and `CheckoutController::success` for the same pattern.

**Repro test** `tests/Feature/Security/CustomerOrderPayloadTest.php`:
- order with `notes = 'INTERNAL-SECRET'`, a `stripe_payment_intent_id`, `stripe_checkout_session_id`, `guest_session_id`;
- GET `route('orders.index')` as the owner → assert the Inertia props JSON doesn't contain `INTERNAL-SECRET`,
  `pi_`, `cs_` or the session id. Use the existing pattern in `tests/Feature/Security/CheckoutSuccessPrivacyTest.php:49`
  (needle loop over the serialised page).
- Same for the signed guest order page (`GuestOrderController@show`) if it leaks.

**Fix:** build the customer payload with an explicit whitelist (`Arr::only`, like `CheckoutController.php:101`):
id, order_number, status, payment_status, created_at, totals (subtotal, tax_amount, shipping_amount, discount fields,
total_amount, currency, tax_rate), tracking_number, tracking_carrier, tracking_status, order_items (already mapped),
addresses (only what the page shows). Keep the frontend working: check `resources/js/pages/Dashboard/Orders.tsx` and
`Orders/GuestShow.tsx` for every field they read, and include exactly those.

**Note:** splitting notes into customer/internal fields is NOT part of this item. Whitelisting fixes the leak.

---

### A3 (P0-7): Orders and Products admin lists can't be paged

**Claim:** `Admin\OrdersController::index` returns `'links' => []` (`app/Http/Controllers/Admin/OrdersController.php:~67`),
and `Orders/Index.tsx:493-512` renders page buttons from `links`. `Products/Index.tsx:120` reads `products.meta`, but
`Admin\ProductController` (`:76`, `->paginate(20)->withQueryString()`) returns a plain paginator (`current_page`,
`last_page`, `links` at the top level), so the pager is hidden.

**Repro tests** `tests/Feature/Admin/AdminListPaginationTest.php`:
- 20 orders → GET `admin.orders.index` → assert prop `orders.links` contains an entry with `url` ending `page=2`;
  GET with `?page=2` → assert `orders.data` contains the 16th-newest order.
- 25 products → GET the products index → assert the props expose the pagination fields the page reads (after the fix,
  the page reads the top-level keys; the test pins the shape: `products.current_page`, `products.last_page == 2`,
  `products.links`).
- The products half is a frontend bug: also add a static reproduction (quote `Index.tsx:120` vs the controller shape).

**Fix:** Orders: return the paginator's `links` (e.g. `$orders->linkCollection()->toArray()` or pass the paginator
through `->through(...)` and adjust the page). Keep the filters in the URLs (`withQueryString()`). Products: read
`products.current_page`, `last_page`, `from`, `to`, `total`, `links` at the top level in `Products/Index.tsx` and
render the existing pager. Don't redesign the pagers.

---

### A4 (P0-8): seeder creates a known admin login in production

**Claim:** `database/seeders/UserSeeder.php:18-30` creates `admin@example.com` / `password` with no environment guard.

**Repro test** `tests/Feature/Security/UserSeederTest.php`: set the app environment to `production`
(`$this->app->detectEnvironment(fn () => 'production')` or `app()['env'] = 'production'`), run
`$this->seed(UserSeeder::class)`, assert no user with email `admin@example.com` exists, or that it doesn't have the password `password`
(`Hash::check('password', $user->password)` is false).

**Fix:** in production the seeder creates the admin only when `ADMIN_EMAIL` and `ADMIN_PASSWORD` env vars are set
(read via `config`, e.g. add `config/app.php` `admin_seed` keys or read `env()` in the seeder; seeders may use env).
Otherwise it skips the admin and prints a warning. Demo/customer users are skipped in production entirely.
Non-production behaviour is unchanged. Document the env vars in `.env.example`.

---

### A5 (related to P0-1, report F-16): catalogue cascades

**Claim:** `products.category_id` and `products.size_id` are `ON DELETE CASCADE`
(`database/migrations/*_create_products_table.php:29-30`). Only a controller check
(`CategoriesAndSizesController.php:101,145`) stops a category delete from wiping products.

**Repro test** `tests/Feature/Catalog/CatalogForeignKeysTest.php`: create a category with a product; delete the
category through the model (`$category->delete()`), not the controller. Assert a `QueryException` is thrown and the product still exists.
SQLite needs `PRAGMA foreign_keys=ON`; check that the test DB has it (Laravel enables it by default for sqlite).

**Fix:** migration `2026_10_15_100100_restrict_category_and_size_deletes.php` → `restrictOnDelete()` on both FKs,
with `down()`. Controller behaviour is unchanged (it already blocks with a friendly message).

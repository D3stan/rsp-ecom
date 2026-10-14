# Backend & Admin UX Audit — rsp-ecom

Branch audited: `fixes` (HEAD `12539c6`, "Admin settings system, Spediamo tracking, VAT/policy rework, order integrity, cleanup").
Date: 2026-10-14. Method: read-only code review (controllers, models, services, migrations, admin React pages, tests) plus
`php artisan route:list --path=admin`. No code was changed and nothing was executed against a database or external API.

Confidence labels: findings are **verified** by reading the code paths cited unless marked *(likely — confirm in browser)*.

---

## 1. Executive summary

- **The money-back path is missing, and the UI says it worked.** `POST admin/orders/{order}/refund` only appends a line to
  `notes` and flashes "Refund processed successfully." (`OrdersController.php:383-402`). No Stripe refund, no `refunded`
  payment state (`create_orders_table.php:33`), no `charge.refunded` webhook (`StripeWebhookListener.php:34-51`). Cancelling a
  **paid** order restocks it and emails the customer "we will refund you" (`OrderCancelled.php:38-39`), but nothing records
  that a refund is owed. Refunds done in the Stripe dashboard never show up in the shop.
- **Deleting a customer account deletes their orders.** `orders.user_id` has `ON DELETE CASCADE` (`create_orders_table.php:17`)
  and self-service deletion is a hard `$user->delete()` (`ProfileController.php:56`). That removes paid orders, order lines,
  email audit rows and tracking history. Italian/EU bookkeeping rules require you to keep these records. This is a data-integrity
  and legal problem.
- **Order lists cannot be paged.** The Orders controller sends `'links' => []` (`OrdersController.php:67`), so the pager
  shows "Showing 1 to 15 of N" with no buttons. The Products page reads `products.meta`, but a Laravel paginator has no `meta`
  key, so its pager never renders (`Products/Index.tsx:120,645`). Past the first page you can only reach an order or product
  by searching for it. *(Verified in code; confirm in browser.)*
- **The shipping workflow points the wrong way.** The list's "Ship" button appears only for `status === 'pending'`
  (`Orders/Index.tsx:356,420`), but paid orders are created as `processing` (`OrderCreationService.php:281`). So the shortcut
  shows on **unpaid** orders and is hidden on paid ones. The Ship form has no tracking-number field and creates no Spediamo
  label: it pastes the form into `notes` (`OrdersController.php:476-516`) and the label contract is an empty interface
  (`SupportsShipmentCreation.php:12`). Customers get a "shipped" email without tracking, then a second one once a number is
  typed in.
- **Order editing is free-form, with no guards and no history.** Admins can set any status from any status. The Cancel button
  on the order page goes through the generic `update()` and skips `can_be_cancelled` (`Show.tsx:351-357`,
  `OrdersController.php:157`). Admins can also flip `payment_status` to `succeeded`, rewrite totals and line quantities with no
  stock or Stripe effect, and "remove" lines that the server never deletes (`Edit.tsx:194-199`, `OrdersController.php:255-269`).
  Edits run without a transaction, and the only history is free text in `notes`.
- **Stock edits can overwrite live sales.** The product edit form posts absolute `stock_quantity` per variant
  (`ProductController.php:479`). An order paid between opening the form and saving it is silently undone. There is no stock
  ledger, low-stock only checks the default variant's mirror column (`Product.php:196-208`), and no low-stock alert is sent.
- **Integration failures are mostly invisible to the owner.** Failed emails (`order_emails.failed_at`), carrier exceptions
  and returns (`ShipmentTrackingService.php:203`), and queue health (`CheckQueueHealth` → `Log::critical`) all go to log files
  only (`LOG_STACK=daily`). Failed jobs are pruned after 7 days (`routes/console.php`). The admin has no screen for any of it.
- **The groundwork is good:** idempotent Stripe order creation with row locks and a unique session index, idempotent stock
  deduct/restore with `lockForUpdate` in a fixed order, deduplicated transactional emails, a registry-driven settings system
  with an audit trail, and a well-isolated tracking module. The main gap is a missing admin layer on top of all that.

---

## 2. Admin workflow walkthroughs

### 2.1 Order: new → paid → ship → tracking → delivered

| Step | What the owner does today | Friction / risk |
|---|---|---|
| Find new paid orders | Admin → Orders. Default sort is newest first. Status/payment filters and search (order number, user name/email, guest email) are available. | **Only page 1 (15 orders) is reachable** (`OrdersController.php:54-68` returns `links: []`; `Index.tsx:493-512` renders buttons from `links`). The backend supports `date_from/date_to` (`OrdersController.php:46-52`) but the UI has no inputs for them. No filter for "paid & not shipped" (the real to-do list), tracking status, or oversold. Search does not cover guest billing name, phone or tracking number. No sorting. No orders bulk UI, even though `PATCH orders/bulk-status` exists (`routes/admin.php:19`). |
| Open order | 1 click → Show | Customer block has a dead "navigate to customer" link (`Show.tsx` `navigateToCustomer` → `console.log`). The timeline uses `updated_at` for every step (`Show.tsx` ~l.385-415), so "Confirmed", "Shipped" and "Delivered" all show the same timestamp. That is misleading. |
| Ship | (a) "Ship" link in the list, **only shown for `pending` = unpaid orders** (`Index.tsx:356,420`). (b) Status button "Shipped" on Show (`Show.tsx:340-348`). (c) Edit form. | Path (a) makes it easy to ship unpaid goods. `processShip` checks neither status nor payment (`OrdersController.php:442-516`). The Ship form collects sender, recipient, box, weight, insurance and COD, then **writes it all into `notes` as text** (`buildShippingNotes`, l.521-604). No label is bought, nothing is sent to Spediamo, and no tracking number is accepted. The owner re-keys everything into Spediamo's site, then comes back to Edit or Show to paste the tracking number. That is at least 2 systems and about 8 clicks per parcel. |
| Customer notified | Automatic on → shipped | Ship form → email **without** tracking (`OrdersController.php:492-493`). Adding the number later sends a **second** shipped email (fingerprint includes the number: `EmailService.php:70-81`). Bulk "shipped" sends emails without tracking (`OrdersController.php:351-355`). |
| Tracking | Spediamo polling every 15 min when configured (`routes/console.php`). Events are shown on Show. | Good engine. But carrier `Exception`/`Returned` only produce a log warning (`ShipmentTrackingService.php:203-204`). There is no "problem shipments" list and orders can't be filtered by `tracking_status`. |
| Delivered | Auto via tracking (`auto_deliver`) or manual button | Fine. |

**Clicks to fulfil one paid order today:** Orders → (search, since paging is broken) → open → Edit → set tracking number and
carrier → Save, plus the out-of-band Spediamo label purchase. **No batch fulfilment at all.**

### 2.2 Cancel / refund / return

| Step | Today | Friction / risk |
|---|---|---|
| Cancel unpaid | Cancel button → `PATCH orders/{id}` `{status: cancelled}` | Works. Observer closes payment (`Order.php:93-100`). |
| Cancel paid | Same button, **no confirmation dialog** (`Show.tsx:351-357`) | Stock is restored (`OrderObserver.php:27-29`) and the customer is emailed that a refund is coming (`OrderCancelled.php:38-39`). **No refund is made and no "refund due" state exists.** `payment_status` stays `succeeded`, so revenue KPIs still count the order (`DashboardController.php:29-47`). |
| Cancel shipped/delivered | Show hides Cancel for delivered only. Edit and bulk allow any transition (`OrdersController.php:157,337`). | Restores stock for goods that have already left the warehouse. |
| Refund | Route exists. **No UI calls it** (no "refund" in `resources/js/pages/Admin/Orders/*`). | Placeholder writes `"REFUND: $..."` (dollar sign, EUR shop) into notes and reports success (`OrdersController.php:396-401`). A test locks in this placeholder behaviour (`OrderStatusEmailsTest.php:165-175`). |
| Partial refund / return (RMA) | Missing | No `refunded`/`partially_refunded` payment status, no returns entity, no credit note. |
| Reopen a cancelled order | Any status button | Stock is **not** re-deducted, by documented policy (`StockService.php:22-24`). The owner gets no hint to adjust stock by hand. |

### 2.3 Products, variants, stock

| Step | Today | Friction / risk |
|---|---|---|
| List | Search (name/sku/description), category/status/stock filter, sort | **Pager never renders** (`Index.tsx:120` reads `products.meta`, which Laravel's paginator does not produce). Two extra queries per row for rating/count (`ProductController.php:79-85` → `Product.php:211-219`). Stock filters and "low stock" use the default-variant mirror only, so non-default variants are invisible. |
| Create/Edit | Single form with variants and images, in a transaction | Stock is an **absolute overwrite** (`ProductController.php:479`), a lost-update race against checkout. Variant `status` is forced to `'active'` on every save (`l.483`), so a variant can't be disabled without deleting it. Renaming regenerates the slug (`l.431`) with no redirect from the old URL (SEO/backlinks break). Raw exception text is shown to the admin (`l.573,581`). Images written to disk are not cleaned up on rollback. |
| Quick stock | ±1 on the default variant (`l.695-719`) | Only ±1, only the default variant, no reason captured, no ledger. |
| Delete | `window.confirm` then hard delete (`Index.tsx:212`, `ProductController.php:588-608`) | Cascades reviews and wishlists. Order lines keep `product_name` (`product_id` set null). No archive/soft delete. |
| Admin "add review" | Products/Show form with a "Customer Name" field | `user_name` is not fillable (`Review.php:12-18`), so it is silently dropped. The review is stored **as the admin user** and a second one throws a unique violation (`reviews` unique `user_id+product_id`). Under the EU Omnibus Directive, publishing reviews you can't attribute to real buyers is a legal risk. Remove this feature. |

### 2.4 Categories & sizes
Single page, inline CRUD, delete confirmation dialog (`CategoriesAndSizes/Index.tsx:227-234`). Delete is blocked when products
exist (`CategoriesAndSizesController.php:101,145`). That guard matters: `products.category_id` and `products.size_id` are
**`ON DELETE CASCADE`** (`create_products_table.php:29-30`), so the controller check is the only thing that stops a category
delete from wiping its catalogue. A tinker/DB delete or a future bulk endpoint would bypass it. No reordering UI beyond a
numeric `sort_order`.

### 2.5 Reviews
Good: pending queue by default, filters, search, bulk approve/reject, delete with redirect fix. Problems:
- "Reject" sets `is_approved=false`, **which is the same state as pending** (`Review.php:42-45,64-66`). Rejected reviews go
  back into the moderation queue forever.
- `validate()` sits inside `try { } catch (\Exception)` (`ReviewsController.php:133-156, 212-236`), so validation errors become
  a generic "Failed to update reviews: ..." flash.
- Show page prev/next ignores the active filter (`ReviewsController.php:111-117`).

### 2.6 Settings
The strongest admin area. Registry-driven, typed, validated, with an audit trail (`SettingChange`), a reset to default, and a
`manage-settings` gate checked in controller and request (`SettingsController.php:28,56,73`, `UpdateSettingsRequest.php:19-21`).
High-risk keys ask for confirmation. No changes recommended beyond the VAT model (F-12).

### 2.7 Customers
**Missing.** `routes/admin.php:58` has a TODO. There is no customer list, order history per customer, GDPR export/erasure tool,
or way to disable an account. `users.is_active` exists but is never enforced.

---

## 3. Findings (prioritised)

Severity: Critical = money/legal/data loss now. High = daily operations broken or materially risky. Medium = efficiency or
latent risk. Low = polish. Effort: S ≤ 1 day, M ≤ 1 week, L > 1 week.

| ID | Area | Issue | Evidence | Business impact | Recommendation | Sev | Eff |
|---|---|---|---|---|---|---|---|
| F-01 | Payments | The refund endpoint is a placeholder that reports success. No Stripe refund, no refunded state, no UI, no `charge.refunded` webhook. | `OrdersController.php:383-402`; `create_orders_table.php:33`; `StripeWebhookListener.php:34-51`; test locks it: `OrderStatusEmailsTest.php:165-175` | Owner believes a refund happened when it didn't. Stripe-dashboard refunds are invisible. Revenue is overstated. | Build a `RefundService` that calls `Stripe Refund::create` with an idempotency key, using a `refunds` table (order_id, amount_cents, reason, stripe_refund_id, status, admin_id). Add `payment_status` values `refunded` and `partially_refunded`. Handle `charge.refunded` and `charge.dispute.*`. Send the refund email only on success. Until then, **delete the route** rather than keep a fake "success". | Critical | M |
| F-02 | Data integrity / legal | Deleting a user cascades to their orders (and order items, emails, tracking). | `create_orders_table.php:17` (`onDelete('cascade')`); `ProfileController.php:46-61` | One click by a customer erases paid orders and accounting records. Breaks bookkeeping retention (IT: 10 years) and revenue history. | Migration: `user_id` → `nullOnDelete()`. On account deletion, anonymise the user and keep the orders (copy email to `guest_email` or an `customer_email` snapshot column). Same review for `billing/shipping_address_id` cascade (`l.21-22`). | Critical | S |
| F-03 | Admin UX | Orders and Products lists can't be paged beyond page 1. | `OrdersController.php:67` (`'links' => []`) + `Orders/Index.tsx:493-512`; `Products/Index.tsx:120,645` (reads non-existent `meta`) + `ProductController.php:76` | After 15 orders or 20 products, older records are reachable only by search. Daily ops degrade as the shop grows. | Pass the paginator straight through (or `->through()`) and render `links`. In Products use the top-level `current_page/last_page`. Add a feature test asserting page 2 is reachable. *(Confirm in browser.)* | Critical | S |
| F-04 | Order workflow | Cancelling a paid order promises a refund in the email but records no refund debt. It also counts as revenue. | `OrderObserver.php:27-37`; `OrderCancelled.php:38-39`; `it.json:788`; `DashboardController.php:29-47` | Customers wait for money nobody is tracking, leading to chargebacks and complaints. KPIs are wrong. | Require a refund decision when cancelling a paid order (refund now / mark "refund due"). Exclude cancelled and refunded orders from revenue. Add a "Refund due" filter. | Critical | M |
| F-05 | Order workflow | The "Ship" shortcut shows only on `pending` (unpaid) orders. `processShip` accepts any status and payment state. | `Orders/Index.tsx:356,420`; `OrderCreationService.php:281,294`; `OrdersController.php:442-516` | Easy to ship goods that were never paid for. Paid orders have no ship shortcut. | Show Ship for `processing && payment_status=succeeded`. Guard server-side: refuse unless paid and not cancelled/shipped. | High | S |
| F-06 | Order workflow | Ship form takes no tracking number, buys no label, and dumps the form into `notes`. The shipped email goes out without tracking, then a second one with it. | `OrdersController.php:476-516,521-604`; `SupportsShipmentCreation.php:12`; `Ship.tsx:160`; `EmailService.php:70-81` | Double data entry with Spediamo. Customers get 2 "shipped" emails. Notes fill with noise. | Short term: add `tracking_number` (+ carrier) to the Ship form and send the email only once a number exists (or after an explicit "send without tracking"). Medium term: implement `SupportsShipmentCreation` for Spediamo (create shipment, store label PDF and tracking on the order) and add a batch "ship selected" with label download. | High | S (form) / L (labels) |
| F-07 | Order integrity | Unrestricted status transitions. Cancel via `update()` skips `can_be_cancelled` and has no confirmation. Bulk cancel of shipped/delivered orders restores stock. | `OrdersController.php:157,307,337,344-356`; `Show.tsx:340-357`; `Order.php:217-220` | Accidental clicks cancel paid or shipped orders, restock phantom units and email the customer, with no undo. | Central state machine (`Order::transitionTo()`) with an allowed-transitions map used by `update`, `updateStatus`, `bulk` and tracking. Confirmation dialog for cancel and backwards moves. | High | M |
| F-08 | Order integrity | Edit form freely rewrites `payment_status`, totals and line qty/price. Line JSON is unvalidated. Removed lines aren't deleted server-side. Discount is ignored in the recompute. No transaction. | `OrdersController.php:156-201,255-284`; `Edit.tsx:182-199,298-323` | Records can drift from what Stripe charged. Setting "succeeded" by hand skips stock deduction. Editing a discounted order inflates `total_amount`. Silent partial saves. | Lock financial fields once paid; changes go through refunds/adjustments. Validate `order_items.*` (int ≥ 1, numeric). Wrap in `DB::transaction`. Drop the item-remove UI or implement it with stock and refund effects. Never let the UI write `payment_status`. | High | M |
| F-09 | Audit | No audit trail for orders, stock or products. History is appended to the free-text `notes` field. | `OrdersController.php:396-399,480`; no activity table in `database/migrations` | You can't answer "who cancelled this, when, and why?". Disputes and staff errors can't be traced. | `order_events` table (order_id, admin_id, type, from, to, payload JSON, created_at) written by the state machine and refund service, shown as the Show timeline (it also fixes the fake `updated_at` timeline). Same idea for a `stock_movements` ledger. | High | M |
| F-10 | Privacy | Internal `notes` (shipping dumps, refund notes, shop IBAN) are sent to the customer's browser in the full `$order->toArray()`. | `DashboardController.php:25` (customer side); `Order.php:78` hides only stock fields | Admins think notes are private, but they're in the customer's page source. Customer notes and internal notes are mixed in one field. | Split `customer_note` and `internal_notes` (or `order_events`). Whitelist the fields in the customer payload (`Arr::only`, like `CheckoutController.php:101`). | High | S |
| F-11 | Inventory | Absolute stock overwrite on product save (lost update). No stock ledger. Low-stock only checks the default variant. No low-stock alert. | `ProductController.php:445,479`; `Product.php:196-208,250-265`; `DashboardController.php:124` | Oversells after the admin saves an unrelated edit. Variants run out unnoticed. | Send stock as a delta, or use optimistic locking (`updated_at`/version check). Write a `stock_movements` row per change. Compute low stock on `product_variants`. Send a daily low-stock email via `EmailService`. | High | M |
| F-12 | Tax / compliance | A single VAT rate (22%) is applied to every destination, including DE/FR/ES/GB (`shipping.destinations`). VAT is only computed on the order total. There are no invoices or credit notes. | `Vat.php:19-28`; `config/settings.php:106-114`; `OrderCreationService.php:403,555`; no "invoice" anywhere in `app/` | Above the €10k EU distance-sales threshold you must charge destination VAT (OSS). UK has separate rules. Italian e-invoicing/"fattura" or at least a numbered invoice or credit note is expected. *Confirm with the commercialista.* | Per-country rate table (settings rows), resolved from the shipping country, snapshotted per line. Sequential invoice and credit-note numbering (or integrate an invoicing provider such as Fatture in Cloud / Aruba SDI). Decide on GB. | High | L |
| F-13 | Ops visibility | Email failures, carrier exceptions, queue health and webhook-to-order failures go only to logs. Failed jobs are pruned after 7 days. | `CheckQueueHealth.php:63`; `ShipmentTrackingService.php:203`; `OrderEmail.php:61-72`; `routes/console.php` (`queue:prune-failed --hours=168`); `.env.example:20` (`LOG_STACK=daily`) | Owner learns about problems from customers. | "Health" admin panel: failed `order_emails` with a Retry button, `failed_jobs` count, tracking exceptions/returns, last webhook received. Route `critical` logs to email/Slack. Show per-order email status on Order Show. | High | M |
| F-14 | Reviews / legal | Admin "add review" form stores the review as the admin, drops "Customer Name", and fails on the 2nd review (unique key). | `ProductController.php:743-761`; `Review.php:12-18`; `create_reviews_table.php:23`; `Products/Show.tsx:96,160,422` | Broken feature that also invites fake reviews (EU Omnibus Directive 2019/2161). | Remove it. If imports are needed, add an explicitly labelled "imported" source with `verified_purchase=false`. | High | S |
| F-15 | Reviews | "Reject" = pending, so rejected reviews come back to the queue. Validation errors are swallowed. | `Review.php:42-45,64-66`; `ReviewsController.php:133-156,212-236` | Moderators re-process the same spam. Unclear errors. | Add `status` enum (pending/approved/rejected) plus `moderated_by/at`. Move `validate()` outside `try`. | Medium | S |
| F-16 | Catalogue integrity | `products.category_id` and `size_id` are `ON DELETE CASCADE`. Only the controller guard prevents mass product deletion. | `create_products_table.php:29-30`; `CategoriesAndSizesController.php:101,145` | One stray delete (tinker, seeder, future bulk action) wipes products, variants, reviews and wishlists. | Change to `restrictOnDelete()`. Add soft deletes / archive for products. | Medium | S |
| F-17 | Missing | No Customers area (list, detail, orders, LTV, GDPR export/erase, disable). `is_active` is never enforced. | `routes/admin.php:58` (TODO); `Show.tsx` `navigateToCustomer`; `AdminMiddleware.php:26` | Support requests mean digging in the DB. GDPR requests are manual. | Read-only customer index and detail first (search, orders, addresses), then anonymise and export actions. | Medium | M |
| F-18 | Reporting | Revenue is by `created_at` in **UTC** and counts paid-then-cancelled orders. "Top products" counts order lines, not units or revenue. "New customers" includes admins. There is no export. | `config/app.php:68`; `DashboardController.php:26-121`; `OrdersController.php:75-91` | Numbers drift from Stripe and the accountant. "Today" is off by 1–2 h in Italy. | Set `timezone` to `Europe/Rome` (or convert in queries). Net revenue = paid − refunded, excluding cancelled. Top products by `SUM(quantity)` and `SUM(total)`. CSV export of orders (with VAT columns) for the accountant. | Medium | S/M |
| F-19 | Performance | N+1 on the product list (avg + count of reviews per row). Order Show/Edit/Ship lazy-load `size` and `productVariant` per line. The dashboard runs about 30 count/sum queries per load. | `ProductController.php:79-85`; `Product.php:211-219`; `Order.php:489-490`; `DashboardController.php:26-121` | Fine at today's size. The product list degrades at 20 rows × 2 queries. | `withAvg('approvedReviews','rating')->withCount('approvedReviews')`; eager-load `orderItems.size`/`.productVariant`; group-by queries on the dashboard; cache 5 min. | Medium | S |
| F-20 | Ops | Seeder creates `admin@example.com` / `password` with no environment guard. | `database/seeders/UserSeeder.php:18-30` | `db:seed` on prod creates a known admin login. | Guard with `app()->isProduction()` or read the password from env. Add an `admin:create` command. Consider 2FA for admin accounts. | Medium | S |
| F-21 | Order data | Order addresses are parsed from a single metadata string with `explode(' ')` (state/postal split on the first space). | `OrderCreationService.php:718-745` | Multi-word provinces/cities and non-IT postcodes get mangled on labels, so parcels are misrouted. | Pass structured address fields in snapshot metadata. Validate `postal_code`/`country` per country. | Medium | S |
| F-22 | Order workflow | Bulk status endpoint exists but has no UI. No bulk on products (status, category, price, stock). No CSV import/export. | `routes/admin.php:19`; `Orders/Index.tsx` (no selection); `Products/Index.tsx` | Every catalogue change is one form at a time. | Row selection plus bulk actions (mark processing/shipped with tracking, export CSV). Product CSV import/export (SKU, price, stock per variant). | Medium | M |
| F-23 | Error UX | Raw exception and SQL messages are flashed to the admin. Validation is wrapped in generic catch blocks. | `ProductController.php:225,230,573,581`; `OrdersController.php:297,514`; `ReviewsController.php:155,235` | Confusing messages, and DB details leak. | Show a friendly message with a log reference id. Let `ValidationException` propagate. | Low | S |
| F-24 | Catalogue | Renaming a product changes the slug, with no redirect. Variants can't be deactivated (forced `active`). | `ProductController.php:431,483` | Broken inbound links and SEO loss. Variants have to be deleted to hide them. | Keep the slug unless edited explicitly, plus a `slug_redirects` table. Respect the variant `status` from the form. | Low | S |
| F-25 | Money handling | Money is `decimal(10,2)` with PHP float arithmetic. VAT is rounded once on the gross total. Refund text uses `$`. | `create_orders_table.php:24-27`; `Vat.php:34-39`; `OrdersController.php:398` | Acceptable today. A risk for per-line VAT, partial refunds and multi-rate invoices. | When building refunds and invoices, move to integer cents (or `brick/money`) for new tables. Store per-line net, VAT and gross. | Low | M |

---

## 4. Missing capabilities roadmap

"Missing" means absent. "Weak" means present but not fit for daily use.

### Now (next 2–4 weeks: stop losing money and data)
1. **Real refunds and refund tracking** (missing): full and partial Stripe refunds, `refunds` table, refunded payment states,
   `charge.refunded`/dispute webhooks, "refund due" on paid cancellations (F-01, F-04).
2. **Order retention** (missing): stop cascades from users and addresses, anonymise on account deletion (F-02).
3. **Working list pagination** on Orders and Products (weak → broken) (F-03).
4. **Order state machine plus audit timeline** (weak) (F-07, F-08, F-09).
5. **Fulfilment fix** (weak): correct Ship gating, tracking number on the Ship form, single shipped email (F-05, F-06).
6. **Split internal/customer notes** (weak) (F-10).

### Next (1–3 months: make daily ops efficient)
1. **Spediamo label creation** via `SupportsShipmentCreation`: batch "ship selected", label PDF download, auto tracking (F-06).
2. **Operations inbox / health page** (missing): paid-not-shipped, refund due, oversold, tracking exceptions, failed emails
   with retry, queue status (F-13).
3. **Customers area** (missing): list, detail, order history, GDPR export/erase (F-17).
4. **Inventory**: stock ledger with reasons, per-variant low-stock view and daily alert, optimistic locking on edits (F-11).
5. **CSV export** of orders for the accountant (VAT columns) and products. **CSV import** for price/stock updates (F-22).
6. **Reporting fixes**: net revenue, Europe/Rome timezone, units-based top products, date-range picker (F-18).
7. **Review moderation states** and removal of the admin "fake review" form (F-14, F-15).

### Later (3–6 months: scale and compliance)
1. **Invoices and credit notes** with sequential numbering, or an Italian e-invoicing provider (SDI) (F-12).
2. **Destination-country VAT (OSS)** with per-line VAT snapshot; decide on UK shipping (F-12).
3. **Discount codes in-app** (today managed only in the Stripe dashboard via `PromotionService.php:15-60`): list, usage,
   create/disable from the admin.
4. **Returns (RMA)** workflow linked to refunds and restock.
5. **Manual/phone orders** (missing) with Stripe payment link.
6. **Roles** beyond a single `admin` (e.g. "fulfilment" staff without refunds or settings), plus 2FA for admins.
7. Product bulk edit, archive/soft delete, slug redirects (F-16, F-24).

---

## 5. Quick wins (each ≤ 1 day)

1. **Fix pagination** on Orders (pass `links`) and Products (read top-level paginator keys). Add a page-2 feature test. (F-03)
2. **Remove or disable the fake refund route**, or at minimum change its flash to "Refund recorded in notes – issue it in Stripe".
   Fix the `$` sign. (F-01)
3. **Ship shortcut on `processing` + paid**, and a server-side guard in `processShip`/`showShip`. (F-05)
4. **Add `tracking_number` to the Ship form** and skip the shipped email when it's empty unless the admin confirms. (F-06)
5. **Confirmation dialog for Cancel**, and route it to `orders.cancel` (which checks `can_be_cancelled`), not the generic update. (F-07)
6. **Migration: `orders.user_id` → `nullOnDelete`**; `products.category_id/size_id` → `restrictOnDelete`. (F-02, F-16)
7. **Whitelist the customer order payload** in `DashboardController::orders` (drop `notes`, stripe ids, session ids). (F-10)
8. **Remove the admin "add review" form** and route. (F-14)
9. **`timezone => 'Europe/Rome'`**, or convert date boundaries in KPI queries. (F-18)
10. **`withAvg`/`withCount`** on the product list. Eager-load `orderItems.size`/`.productVariant`. (F-19)
11. **Production guard on `UserSeeder`.** (F-20)
12. **Move `validate()` out of `try`** in `ReviewsController::update`/`bulkUpdate`. (F-15, F-23)
13. **Exclude `cancelled` orders from revenue KPIs.** (F-18)

---

## 6. Test coverage of admin-critical paths

Present and good: Stripe webhook order creation and idempotency (`tests/Feature/Orders/StripeWebhookOrderTest.php`,
`StripeAsyncEventsTest.php`), stock deduct/restore (`OrderStockTest.php`), status consistency (`OrderStatusConsistencyTest.php`),
lifecycle emails (`tests/Feature/Mail/Lifecycle/*`), shipped email from admin (`AdminShippedEmailTest.php`), settings controller
including 403 for non-admins (`AdminSettingsControllerTest.php:32`), tracking module (extensive), VAT on admin edit
(`AdminOrderVatTest.php`).

Missing:
- **Authorization:** no test that a customer gets 403 on `admin/orders`, `admin/products`, `admin/reviews` or
  `admin/categories-sizes` (only settings is covered). Add one data-provider test over `route:list --path=admin`.
- **Product CRUD:** no tests for store/update/destroy, variant sync, stock overwrite, or image handling.
- **Categories/sizes:** no tests, especially for the "cannot delete with products" guard that protects the cascade.
- **Order admin actions:** no tests for `cancel` on paid orders (restock plus email, but no refund), bulk status transitions,
  `processShip` on unpaid orders, `update()` with `order_items` JSON, or pagination.
- **Refund:** the only test asserts the placeholder (`OrderStatusEmailsTest.php:165-175`). Replace it once real refunds exist.
- **Account deletion vs orders:** no test that deleting a user keeps their orders. It would currently fail (F-02).

---

## 7. What's already done well

- **Order creation is genuinely robust**: unique index on `stripe_checkout_session_id`, `lockForUpdate` lookup, retry on
  deadlock and unique races, frozen checkout snapshot as the source of truth, amount-mismatch logging, and an admin alert when a
  paid session can't become an order (`OrderCreationService.php:141-197,543-565`, `StripeWebhookListener.php:93-108`).
- **The webhook fails closed in production** without a secret (`StripeWebhookListener.php:21-25`).
- **Stock bookkeeping**: claim-by-conditional-UPDATE idempotency, variant rows locked in id order to avoid deadlocks, and an
  explicit oversold record instead of rejecting paid orders (`StockService.php:33-93`). Oversold lines are surfaced on the order
  page.
- **Transactional email** has dedupe fingerprints, retry/backoff, a permanent-failure record and a resend command
  (`EmailService`, `SendOrderEmail.php:43-65,144-152`, `orders:send-pending-emails`).
- **Settings system**: typed registry, validation, audit history, reset, risk confirmation, gate-protected. It's the right
  pattern to copy for orders and stock audit.
- **Tracking module**: clean adapter boundary, idempotent events, forward-only status, admin changes win
  (`docs/shipping-tracking-module.md`).
- **VAT rate snapshot per order** (`tax_rate` column) so later setting changes never rewrite history.
- **All admin routes sit behind `auth` + `verified` + `admin`** (`routes/admin.php:12`; `AdminMiddleware.php:26`). The route
  ordering for `orders/bulk-status` is deliberate and commented.

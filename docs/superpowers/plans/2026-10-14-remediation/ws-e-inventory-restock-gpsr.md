# WS-E: Stock safety, back-in-stock emails (carts + wishlists), GPSR safety info

> Branch `feat/inventory-restock-gpsr` from `fixes`. Wave 1. Read `00-overview.md` first; follow its per-item protocol.
> Migration prefix: `2026_10_15_50xxxx`. Order: E1 (S-M) → E2 (M) → E3 (S-M).

---

### E1 (P1, F-11): product save overwrites concurrent sales. Size **S-M**

**Claim:** the product edit form posts an absolute `stock_quantity` per variant, and `Admin\ProductController::update`
writes it (`app/Http/Controllers/Admin/ProductController.php:~445,479`). A sale between opening the form and saving
it is undone.

**Repro test** `tests/Feature/Admin/ProductStockConcurrencyTest.php`:
1. A variant with stock 10. Build the update payload as the edit page would (stock 10).
2. Simulate a sale: `StockService::deductForOrder` on an order for 3 units (stock is now 7).
3. Submit the payload unchanged → today the stock is back to 10. Expected: 7.
4. A second case: the admin changes the field from 10 to 15 (+5) while a sale of 3 happens → expected 12.

**Spec:** the form sends both the value it **loaded** (`stock_quantity_original`) and the value the admin typed. The
server applies `delta = typed − original` atomically to the current value (`increment/decrement` in the same
transaction, clamped at 0; log a warning if clamped). `delta = 0` ⇒ no stock write. New variants created in the same
save take the typed value as is. The quick ±1 stock action (`:695-719`) already uses relative changes, so keep it.
Show a small notice in the edit form if the stock changed while editing (optional: the response flash says "stock was
adjusted by concurrent orders: now X").

---

### E2 (P2): back-in-stock emails for items in carts and wishlists. Size **M**

**Feature check:** there's no restock notification code (`grep -ri "restock\|back.in.stock" app` finds only stock
restore). Wishlists are product-level (`app/Models/Wishlist.php`: user_id, product_id). Cart items are variant-level
(`cart_items.product_variant_id`), and only logged-in carts (`carts.user_id`) have a reachable email.

**Who gets notified:**
- **Cart:** users whose cart contains the restocked **variant**.
- **Wishlist:** users whose wishlist contains the **product**, but only if the product had **no** sellable variant
  before this restock (otherwise they could already buy it).
- Only users with `users.notify_back_in_stock = true` (new column, default **true**; toggle in account settings;
  every email carries a one-click unsubscribe that sets it to false).
- At most one email per user per variant per **7 days** (cooldown), and one email per user per restock event even if the
  item is in both cart and wishlist.
- Admin master switch: setting `email.back_in_stock` (bool, default **true**, `depends_on: email.enabled`), with
  labels in 5 locales.

> Legal note for the coordinator/owner: these messages relate to items the user saved themselves. They are designed as
> service notifications with an opt-out. If counsel classes them as marketing, flip the user default to false and add an
> opt-in checkbox. That is a one-line change of the migration default plus UI copy.

**Detecting a restock (the core risk: missing a write path):**
- A restock is a variant whose sellable quantity goes from `≤ 0` to `> 0` while `status = active` (or a variant
  turning `active` with stock > 0).
- **Find every path that increases stock or activates a variant.** At least: admin product update (E1), quick stock,
  `StockService::restoreForOrder` (cancellations; it uses conditional query-builder UPDATEs, so model events don't
  fire), and WS-D's `StockService::restoreItems` (not on your branch; leave a clear hook point and note it in the
  report). List them all in your report with file:line.
- Implement one entry point: `App\Events\VariantRestocked(int $variantId)` dispatched **after commit**
  (`DB::afterCommit` / `ShouldDispatchAfterCommit`) from a small helper, e.g.
  `StockService::announceIfRestocked(int $variantId, int $before, int $after)`, called from each path. Also add a
  `ProductVariant` model observer for the Eloquent `save()` paths, so future code paths are covered when they use the model.
- Listener `QueueRestockNotifications` (queued) resolves recipients as above, writes `restock_notifications` rows
  (user_id, product_variant_id, source enum(cart, wishlist), sent_at nullable, unique(user_id, product_variant_id,
  created_at date-bucket) or check the cooldown in the query) and dispatches one `SendRestockEmail` job per user.

**Email** `App\Mail\BackInStock`: product name, variant name, image, price, a "Buy now" link to the PDP with the variant
preselected (or "View cart" for cart sources), and a footer with a one-click unsubscribe (signed route
`restock.unsubscribe`, plus `List-Unsubscribe` and `List-Unsubscribe-Post: List-Unsubscribe=One-Click` headers). Sent in
the user's locale (check how the order emails choose the locale, `orders.locale`; users may not have a locale column,
so use the app default when there isn't one).

**Account settings:** a switch "Email me when items in my cart or wishlist are back in stock" on the profile settings
page.

**Tests** `tests/Feature/Inventory/BackInStockTest.php`: each stock-increase path triggers exactly one event when crossing
0 → >0, and none when 3 → 5; cart recipients and wishlist recipients are resolved correctly (wishlist suppressed when another
variant was already in stock); opted-out users are skipped; the cooldown prevents a second email within 7 days; the admin
switch off ⇒ nothing is sent; the unsubscribe link works without login and is refused when unsigned; the email is queued, not sent
synchronously, inside the request.

---

### E3 (P1): GPSR product-safety information. Size **S-M**

**Feature check:** `products` has no manufacturer/safety fields, and `product.tsx` renders only a plain description (`:396-398`).
Regulation (EU) 2023/988 (GPSR, applies since 13 Dec 2024) requires online listings to show: the manufacturer's name and
postal + electronic address; if the manufacturer is outside the EU, the EU responsible person; a product identifier;
and any warnings or safety information in the consumer's language.

**Spec:**
- New settings group `compliance` (add it to `groups` with an icon) with `compliance.manufacturer_name`,
  `compliance.manufacturer_address` (text), `compliance.manufacturer_email`, `compliance.eu_responsible_person`
  (text, optional), all `public: true`, with defaults empty, falling back to `store.legal_name` / `store.address` /
  `contact.support_email` when empty. Labels and help in 5 locales.
- `products.safety_information` (text, nullable, new column). The admin product form gets a textarea with a hint that this
  is shown to customers as "Safety information".
- PDP: a collapsible "Product safety" section (shadcn `Accordion`) showing manufacturer, address, email, EU responsible person
  (if set), the product identifier (`base_sku` or the selected variant's SKU) and the safety text. Put it in a new component
  `resources/js/components/product/ProductSafety.tsx` and insert one line in `product.tsx` (WS-B also edits this page).
- Strings in 5 locales. The product's safety text is entered by the admin in one language (no per-locale products exist
  today); note this limitation in the report.

**Tests:** product page props include the compliance block (with fallbacks applied) and `safety_information`; the admin
update persists `safety_information`; validation max length 5000.

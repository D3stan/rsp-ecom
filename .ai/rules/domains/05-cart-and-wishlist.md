---
domain: cart-and-wishlist
version: 1.0.0
budget_lines: 150
deep_reference: docs/ecommerce_structure.md
---

# Cart and Wishlist Domain Specification

## 1. Scope & Responsibility

Governs shopping cart persistence (`Cart`, `CartItem`), authenticated customer wishlists (`Wishlist`), guest-to-customer cart reconciliation (`CartMerger`), coupon code session binding, line stock and order quantity capping, dynamic catalog price synchronisation, shipping cost calculations (`App\Support\Shipping`), and client-side cart dispatch (`cartService.ts`).

## 2. Core Invariants

| Invariant | Enforcement Mechanism |
|---|---|
| Single Line Per Product | `cart_items` table enforces a database `UNIQUE(cart_id, product_id)` composite key; an active cart contains at most one line per product. |
| Variant Supersession | Adding another variant of a product already present in the cart replaces the previous line with the customer's latest variant choice. |
| Stock & Line Quantity Cap | Quantities are bounded by `min(stock_quantity, settings('orders.max_qty_per_line'))`. Add or update operations exceeding stock fail with HTTP 422. |
| Dynamic Price Alignment | `Cart::refreshPrices()` and `CartController::index` re-evaluate line prices against current variant catalog prices (`current_price`), preventing checkout with stale prices. |
| Checkout Content Hash | `Cart::contentHash()` produces a sorted SHA-1 fingerprint of variant, size, quantity, and unit price, verified against Stripe session metadata at order creation. |
| Coupon Session Isolation | Applied coupons reside in session storage (`session('applied_coupon')`) rather than database columns, surviving session ID rotation on login without database locking. |
| Authenticated Wishlist | Wishlist items enforce `UNIQUE(user_id, product_id)`. Guest users cannot persist wishlist items; actions require customer authentication. |
| Cascade Deletion | Deleting a parent `Cart` cascades deletions to its `CartItem` rows at the database level. Deleting a `Product` or `ProductVariant` cascades to associated cart items. |

## 3. State Machines & Transitions

```
[Guest Session Cart (session_id)]
        │
        ▼ (User signs in / registers / OAuth callback)
[CartMerger::merge($guestSessionId, $user)]
        ├── Acquire pessimistic lock (lockForUpdate()) on guest cart in DB transaction
        ├── Find or create authenticated user cart: Cart::firstOrCreate(['user_id' => $user->id])
        ├── For each guest CartItem:
        │     ├── Same Product & Same Variant: sum quantities, clamp to min(stock, max_qty),
        │     │     never reduce below user's pre-merge quantity
        │     ├── Same Product & Different Variant: delete user's existing line, insert guest's variant
        │     └── Different Product: insert guest line into user's cart (capped to available stock)
        ├── Delete all guest CartItem rows and delete the guest Cart
        └── Commit transaction (session coupon persists untouched across session regeneration)
        │
        ▼
[Customer Active Cart (user_id)]
        │
        ▼ (Checkout initiated -> Stripe Checkout Session created with contentHash)
[Order Payment Completed] ──> Cart::clear() removes all lines
```

## 4. Key Contracts & Entry Points

- **`CartController`** (`app/Http/Controllers/CartController.php`):
  - `index()`: Renders cart page with eager-loaded items, auto-syncs variant prices (`refreshPrices()`), calculates subtotals, base shipping, and free shipping progress. Sets `robots: noindex,nofollow`.
  - `add(Request)`: Validates `product_id`, `product_variant_id`, `quantity`; enforces stock checks via `canAddToCart()` and `orders.max_qty_per_line`. Assigns product's default size when missing.
  - `update(Request, CartItem)`: Re-checks available variant stock and per-line ceilings; updates quantity and returns total item count. Suppresses noisy flash toasts on incremental adjustments.
  - `remove(CartItem)`, `clear()`: Deletes individual cart lines or all lines in the active cart.
  - `applyCoupon(Request)`, `removeCoupon(Request)`: Validates coupon validity, expiry, and minimum cart amounts in session using `CheckoutTotals`.
- **`CartMerger`** (`app/Services/CartMerger.php`):
  - `merge(string $guestSessionId, User $user): void`: Atomic reconciliation of guest session items into the authenticated customer's cart.
- **`Cart` Model** (`app/Models/Cart.php`):
  - `contentHash(): string`: SHA-1 integrity hash of items for checkout verification.
  - `refreshPrices(): array<int, float>`: Updates cart lines to current catalog variant prices, returning modified line IDs and previous prices.
  - `shippingCostFor(float $discount): float`: Calculates shipping fee via `App\Support\Shipping::costFor()` based on post-discount subtotal.
  - `base_shipping_cost`: Sum of individual item size shipping costs (`Size::shipping_cost`).
  - Helper methods: `addItem(Product, int)`, `removeItem(Product)`, `updateItemQuantity(Product, int)`, `clear()`.
- **`WishlistController`** (`app/Http/Controllers/WishlistController.php`):
  - `store()`, `destroy()`, `destroyByProduct()`, `toggle()`, `count()`, `check()`: Manages user product wishlist entries.
- **`Wishlist` Model** (`app/Models/Wishlist.php`):
  - `addToWishlist(User, Product)`, `removeFromWishlist(User, Product)`, `isInWishlist(User, Product)`.
- **`User` Model Wishlist Helpers** (`app/Models/User.php`):
  - `hasInWishlist(Product)`, `addToWishlist(Product)`, `removeFromWishlist(Product)`.
- **Frontend Service** (`resources/js/services/cartService.ts`):
  - Asynchronous cart actions, background CSRF token recovery (`/csrf-token`), and UI optimistic counter dispatch.

## 5. External Boundaries & Failure Modes

- **Stock Depletion Before Payment**: When inventory decreases between adding to cart and checkout submission, `CartController::update()` or checkout validation fails with HTTP 422 (`Not enough stock`), prompting quantity adjustment.
- **Catalogue Price Drift**: If product pricing changes while items are in a cart, `refreshPrices()` updates prices during cart inspection and checkout initiation, protecting the store from undercharging.
- **Concurrent Guest Merging**: Multiple simultaneous logins or rapid OAuth callbacks for the same guest session are serialized by `CartMerger`'s `lockForUpdate()` within an atomic database transaction.
- **CSRF Token Expiry**: Client-side `cartService.ts` automatically queries `/csrf-token` to refresh expired tokens on background cart modifications.

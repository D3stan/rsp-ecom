# Architectural Domain Specification Router

> **CRITICAL AGENT INSTRUCTIONS:**
> Before reading or modifying code in any domain, you **MUST** follow this protocol:
> 
> 1. **Locate your target file path** in the [Path Glob Routing Matrix](#path-glob-routing-matrix) below.
> 2. **Read the corresponding domain specification** in `.ai/rules/domains/` before planning or writing any code.
> 3. **Preserve Core Invariants:** Each domain spec defines non-negotiable invariants, state machines, and failure modes. Code changes that violate these invariants are considered breaking regressions.
> 4. **Update Specs on Code Changes:** Whenever code changes alter business logic, state transitions, parameters, or failure handling, you MUST update the corresponding domain specification in `.ai/rules/domains/` in the same commit.
> 5. **Maintain the 150-Line Budget:** Domain specifications are kept strictly `<= 150 lines` for token efficiency (enforced by `tests/Unit/Architecture/DomainSpecSchemaTest.php`).
> 6. **Zero Narrative Comments:** Do not write narrative inline comments explaining code mechanics. Write clean, self-documenting code and record domain constraints in the domain specification.

---

## The 9 Architectural Domains

| # | Domain | Specification File | Scope Summary |
|---|---|---|---|
| **01** | **Orders & Checkout** | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) | Stripe sessions, checkout snapshots, orders lifecycle, financial locks, guest orders, checkout recovery. |
| **02** | **Taxation & Localization** | [domains/02-taxation-and-localization.md](domains/02-taxation-and-localization.md) | VAT extraction (22% IT), prices-include-tax, Italian CAP/province validation, SDI/PEC, multi-locale fallback (`it`). |
| **03** | **Catalog & Inventory** | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) | Products, variants, categories, atomic stock deductions, oversold tracking, restock events & cooldowns. |
| **04** | **Shipping & Tracking** | [domains/04-shipping-and-tracking.md](domains/04-shipping-and-tracking.md) | Carrier providers (Spediamo, DVAExpress), rate calculation, waybills, labels, checkpoint hashing, auto-delivery. |
| **05** | **Cart & Wishlist** | [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) | Cart persistence, guest-to-customer cart merge (`CartMerger`), wishlist items, coupon session isolation, price sync. |
| **06** | **Marketing & Communications** | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) | Newsletter double opt-in, broadcast campaigns, abandoned checkout recovery, customer reviews, consent events. |
| **07** | **Settings Registry** | [domains/07-settings-registry.md](domains/07-settings-registry.md) | Authoritative catalogue (`config/settings.php`), typed definitions, audit trails, cache invalidation, runtime ConfigBridge. |
| **08** | **Authentication & Accounts** | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) | Customer/admin roles, Google OAuth (Socialite), pending email verifications, address book, GDPR deletion retention. |
| **09** | **Frontend Core & UI** | [domains/09-frontend-core-and-ui.md](domains/09-frontend-core-and-ui.md) | React 19 / Inertia v2 shells, HandleInertiaRequests shared props, Radix / Shadcn primitives, SSR guards, RingMaskedImage. |

---

## Path Glob Routing Matrix

### 1. Application Models (`app/Models/`)

| Path / Glob | Primary Domain Specification |
|---|---|
| `app/Models/Order.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Models/OrderItem.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Models/OrderEvent.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Models/OrderEmail.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) & [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Models/CheckoutSnapshot.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Models/Refund.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Models/Product.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Models/ProductVariant.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Models/Category.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Models/Size.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Models/RestockNotification.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Models/Shipment.php` | [domains/04-shipping-and-tracking.md](domains/04-shipping-and-tracking.md) |
| `app/Models/ShipmentTrackingEvent.php` | [domains/04-shipping-and-tracking.md](domains/04-shipping-and-tracking.md) |
| `app/Models/Cart.php` | [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) |
| `app/Models/CartItem.php` | [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) |
| `app/Models/Wishlist.php` | [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) |
| `app/Models/NewsletterSubscriber.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Models/NewsletterCampaign.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Models/NewsletterCampaignSend.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Models/Review.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Models/ReviewMedia.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Models/ConsentEvent.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Models/Setting.php` | [domains/07-settings-registry.md](domains/07-settings-registry.md) |
| `app/Models/SettingChange.php` | [domains/07-settings-registry.md](domains/07-settings-registry.md) |
| `app/Models/User.php` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |
| `app/Models/PendingUserVerification.php` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |
| `app/Models/Address.php` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) & [domains/02-taxation-and-localization.md](domains/02-taxation-and-localization.md) |
| `app/Models/Page.php` | [domains/09-frontend-core-and-ui.md](domains/09-frontend-core-and-ui.md) |

---

### 2. Services, Domains & Business Logic (`app/Services/`, `app/Domain/`)

| Path / Glob | Primary Domain Specification |
|---|---|
| `app/Domain/Orders/**` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Services/OrderCreationService.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Services/StripeService.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Services/RefundService.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Services/StockService.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Shipping/**` | [domains/04-shipping-and-tracking.md](domains/04-shipping-and-tracking.md) |
| `app/Services/CartMerger.php` | [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) |
| `app/Services/PromotionService.php` | [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) |
| `app/Services/Newsletter/**` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Services/EmailService.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) & [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Services/Reviews/**` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Services/ImageUploadService.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) & [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Services/FileUploadConfigService.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Settings/**` | [domains/07-settings-registry.md](domains/07-settings-registry.md) |

---

### 3. HTTP Controllers (`app/Http/Controllers/`)

| Path / Glob | Primary Domain Specification |
|---|---|
| `app/Http/Controllers/CheckoutController.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Http/Controllers/CheckoutRecoveryController.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Http/Controllers/OrderController.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Http/Controllers/GuestOrderController.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Http/Controllers/PublicOrderController.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Http/Controllers/Admin/OrdersController.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Http/Controllers/Admin/RefundsController.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Http/Controllers/ProductController.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Http/Controllers/CategoryController.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Http/Controllers/RestockNotificationController.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Http/Controllers/Admin/ProductController.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Http/Controllers/Admin/ProductVariantController.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Http/Controllers/Admin/CategoryController.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Http/Controllers/Admin/ShipmentController.php` | [domains/04-shipping-and-tracking.md](domains/04-shipping-and-tracking.md) |
| `app/Http/Controllers/ShippingWebhookController.php` | [domains/04-shipping-and-tracking.md](domains/04-shipping-and-tracking.md) |
| `app/Http/Controllers/CartController.php` | [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) |
| `app/Http/Controllers/WishlistController.php` | [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) |
| `app/Http/Controllers/CouponController.php` | [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) |
| `app/Http/Controllers/NewsletterController.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Http/Controllers/ContactController.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Http/Controllers/ReviewController.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Http/Controllers/Admin/NewsletterCampaignsController.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Http/Controllers/Admin/NewsletterSubscribersController.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Http/Controllers/Admin/ReviewModerationController.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Http/Controllers/Admin/SettingsController.php` | [domains/07-settings-registry.md](domains/07-settings-registry.md) |
| `app/Http/Controllers/Auth/**` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |
| `app/Http/Controllers/Dashboard/**` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |
| `app/Http/Controllers/Admin/UserController.php` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |
| `app/Http/Controllers/Admin/DashboardController.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) & [domains/09-frontend-core-and-ui.md](domains/09-frontend-core-and-ui.md) |
| `app/Http/Controllers/PageController.php` | [domains/09-frontend-core-and-ui.md](domains/09-frontend-core-and-ui.md) |

---

### 4. Middleware & Requests (`app/Http/Middleware/`, `app/Http/Requests/`)

| Path / Glob | Primary Domain Specification |
|---|---|
| `app/Http/Middleware/HandleInertiaRequests.php` | [domains/09-frontend-core-and-ui.md](domains/09-frontend-core-and-ui.md) & [domains/07-settings-registry.md](domains/07-settings-registry.md) |
| `app/Http/Middleware/AdminMiddleware.php` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |
| `app/Http/Requests/Checkout/**` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) & [domains/02-taxation-and-localization.md](domains/02-taxation-and-localization.md) |
| `app/Http/Requests/Admin/**` | Maps by target resource (Products: `03`, Orders: `01`, Settings: `07`, Newsletter: `06`) |
| `app/Http/Requests/Auth/**` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |

---

### 5. Support Helpers & Utilities (`app/Support/`)

| Path / Glob | Primary Domain Specification |
|---|---|
| `app/Support/Vat.php` | [domains/02-taxation-and-localization.md](domains/02-taxation-and-localization.md) |
| `app/Support/ItalianProvinces.php` | [domains/02-taxation-and-localization.md](domains/02-taxation-and-localization.md) |
| `app/Support/AddressRules.php` | [domains/02-taxation-and-localization.md](domains/02-taxation-and-localization.md) |
| `app/Support/Locales.php` | [domains/02-taxation-and-localization.md](domains/02-taxation-and-localization.md) |
| `app/Support/Money.php` | [domains/02-taxation-and-localization.md](domains/02-taxation-and-localization.md) |
| `app/Support/CheckoutTotals.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Support/Revenue.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Support/CatalogPagination.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Support/Shipping.php` | [domains/04-shipping-and-tracking.md](domains/04-shipping-and-tracking.md) & [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) |
| `app/Support/RegistrationAutoLogin.php` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |
| `app/Support/Privacy.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) & [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |

---

### 6. Events, Observers, Listeners & Jobs (`app/Events/`, `app/Observers/`, `app/Listeners/`, `app/Jobs/`)

| Path / Glob | Primary Domain Specification |
|---|---|
| `app/Observers/OrderObserver.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) & [domains/04-shipping-and-tracking.md](domains/04-shipping-and-tracking.md) |
| `app/Observers/ProductVariantObserver.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Listeners/StripeWebhookListener.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Listeners/ClaimGuestOrders.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Listeners/MergeGuestCartOnLogin.php` | [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) |
| `app/Listeners/QueueRestockNotifications.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Events/VariantRestocked.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Jobs/SendOrderEmail.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) & [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Jobs/SendRestockEmail.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Jobs/SendWelcomeEmail.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Jobs/SendUserNotification.php` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |
| `app/Jobs/Newsletter/**` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |

---

### 7. Mail & Notifications (`app/Mail/`, `app/Notifications/`)

| Path / Glob | Primary Domain Specification |
|---|---|
| `app/Mail/OrderConfirmation.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Mail/OrderCancelled.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Mail/OrderRefunded.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Mail/NewOrderAlert.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Mail/OversoldOrderAlert.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) & [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Mail/PaidOrderMissingAlert.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `app/Mail/BackInStock.php` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `app/Mail/OrderShipped.php` | [domains/04-shipping-and-tracking.md](domains/04-shipping-and-tracking.md) |
| `app/Mail/OrderDelivered.php` | [domains/04-shipping-and-tracking.md](domains/04-shipping-and-tracking.md) |
| `app/Mail/AbandonedCheckoutReminder.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Mail/NewsletterConfirm.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Mail/NewsletterCampaignMail.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Mail/WelcomeEmail.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Mail/ReviewRequest.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Mail/ContactMessage.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Mail/TestEmail.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `app/Notifications/PendingUserEmailVerification.php` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |
| `app/Notifications/AccountMailMessages.php` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |

---

### 8. Routing (`routes/`)

| Path / Glob | Primary Domain Specification |
|---|---|
| `routes/web.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md), [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md), [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) |
| `routes/admin.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md), [domains/04-shipping-and-tracking.md](domains/04-shipping-and-tracking.md), [domains/07-settings-registry.md](domains/07-settings-registry.md) |
| `routes/auth.php` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |
| `routes/settings.php` | [domains/07-settings-registry.md](domains/07-settings-registry.md) |
| `routes/console.php` | Maps by Artisan command (`tracking:sync` -> `04`, reminders -> `06`) |

---

### 9. Application Configuration (`config/`)

| Path / Glob | Primary Domain Specification |
|---|---|
| `config/settings.php` | [domains/07-settings-registry.md](domains/07-settings-registry.md) |
| `config/shipping.php` | [domains/04-shipping-and-tracking.md](domains/04-shipping-and-tracking.md) |
| `config/cashier.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `config/mail.php` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `config/auth.php` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |
| `config/inertia.php` | [domains/09-frontend-core-and-ui.md](domains/09-frontend-core-and-ui.md) |
| `config/app.php` | [domains/02-taxation-and-localization.md](domains/02-taxation-and-localization.md) |
| `config/services.php` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) (Stripe) & [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) (Google) |

---

### 10. Frontend Application (`resources/js/`)

| Path / Glob | Primary Domain Specification |
|---|---|
| `resources/js/pages/Checkout/**` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) & [domains/02-taxation-and-localization.md](domains/02-taxation-and-localization.md) |
| `resources/js/pages/Orders/**` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `resources/js/pages/Admin/Orders/**` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) & [domains/04-shipping-and-tracking.md](domains/04-shipping-and-tracking.md) |
| `resources/js/pages/product.tsx` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `resources/js/pages/products.tsx` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `resources/js/pages/Admin/Products/**` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `resources/js/pages/Admin/Categories/**` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `resources/js/pages/Restock/**` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `resources/js/pages/cart.tsx` | [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) |
| `resources/js/pages/Dashboard/Wishlist.tsx` | [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) |
| `resources/js/pages/Newsletter/**` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `resources/js/pages/Reviews/**` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `resources/js/pages/contact.tsx` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `resources/js/pages/Admin/Newsletter/**` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `resources/js/pages/Admin/Reviews/**` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `resources/js/pages/Admin/Settings/**` | [domains/07-settings-registry.md](domains/07-settings-registry.md) |
| `resources/js/pages/auth/**` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |
| `resources/js/pages/Dashboard/**` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |
| `resources/js/pages/Admin/Users/**` | [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |
| `resources/js/components/ui/**` | [domains/09-frontend-core-and-ui.md](domains/09-frontend-core-and-ui.md) |
| `resources/js/layouts/**` | [domains/09-frontend-core-and-ui.md](domains/09-frontend-core-and-ui.md) |
| `resources/js/components/RingMaskedImage/**` | [domains/09-frontend-core-and-ui.md](domains/09-frontend-core-and-ui.md) |
| `resources/js/components/flash-toaster.tsx` | [domains/09-frontend-core-and-ui.md](domains/09-frontend-core-and-ui.md) |
| `resources/js/components/app-*` | [domains/09-frontend-core-and-ui.md](domains/09-frontend-core-and-ui.md) |
| `resources/js/components/checkout/**` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) & [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) |
| `resources/js/components/orders/**` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) & [domains/04-shipping-and-tracking.md](domains/04-shipping-and-tracking.md) |
| `resources/js/components/address/**` | [domains/02-taxation-and-localization.md](domains/02-taxation-and-localization.md) & [domains/08-auth-and-accounts.md](domains/08-auth-and-accounts.md) |
| `resources/js/components/product/**` | [domains/03-catalog-and-inventory.md](domains/03-catalog-and-inventory.md) |
| `resources/js/components/newsletter/**` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `resources/js/components/reviews/**` | [domains/06-marketing-and-comms.md](domains/06-marketing-and-comms.md) |
| `resources/js/hooks/use-money.ts` | [domains/02-taxation-and-localization.md](domains/02-taxation-and-localization.md) |
| `resources/js/hooks/use-setting.ts` | [domains/07-settings-registry.md](domains/07-settings-registry.md) |
| `resources/js/hooks/use-cart.ts` | [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) |
| `resources/js/services/cartService.ts` | [domains/05-cart-and-wishlist.md](domains/05-cart-and-wishlist.md) |
| `resources/js/services/stripe.ts` | [domains/01-orders-and-checkout.md](domains/01-orders-and-checkout.md) |
| `resources/js/lib/italian-address.ts` | [domains/02-taxation-and-localization.md](domains/02-taxation-and-localization.md) |
| `resources/js/lib/utils.ts` | [domains/09-frontend-core-and-ui.md](domains/09-frontend-core-and-ui.md) |
| `resources/js/contexts/TranslationContext.tsx` | [domains/02-taxation-and-localization.md](domains/02-taxation-and-localization.md) |

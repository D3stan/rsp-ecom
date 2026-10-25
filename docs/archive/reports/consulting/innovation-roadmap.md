# RSP shop: innovation and growth roadmap

**Author:** external growth consultant (read-only review of branch `fixes`, 2026-10-14)
**Scope:** product, commerce, retention, operations and community features. **No AI/ML/LLM features**, by brief.
**Method:** I read the code (`app/`, `routes/`, `resources/js/pages`, `config/settings.php`, `lang/`, `docs/`, `docs/reports/`, `docs/plans/`, `docs/spediamo/`) before proposing anything. Where a feature already exists in part, I cite where (`file:line`) and propose only the extension. I ran no servers, made no external calls and changed no code.

---

## 1. How the business looks today

### What is sold, and to whom

- **Product.** RSP sells **LED "angel eye" headlights for supermoto and enduro bikes** (`lang/en.json:3-5`, `public/images/angeleye.webp`, hero bike in `public/images/homepage.png`). The products are headlight **models** sold as variants with their own price, stock and photos, such as *Rugoso* and *Carbon* (`docs/plans/2026-03-06-product-variants-design.md`, `docs/database.md:260`). The live categories are `fari` (headlights) and `servizi` (services) (`lang/en.json:531-532`). The other categories in `CategorySeeder` are generic demo data.
- **Customer.** Mostly Italian riders, with DE/FR/ES/GB as secondary markets (`config/settings.php:114`, default country IT). They are enthusiasts who modify their own bikes or use a workshop. They mostly shop on mobile and arrive from social media: `fixes.md` says users are *"in larga maggioranza mobile"*, and the social settings list only Instagram, TikTok and Facebook (`config/settings.php:101-103`).
- **Buying pattern.** Each purchase is a considered, one-off decision per bike, in the €100–300 range. The customer's first question is not "is it nice?" but **"does it fit my bike, is it road-legal, and can I install it?"** `docs/fixes.md` (point 4) already says the listing must show *"compatibilità/modello"* first. Repeat purchases are rare per bike, but riders change bikes, own more than one, and talk to each other a lot.

### What already exists (verified in code)

| Area | What exists |
|---|---|
| Catalogue | Products with variants (price, stock and images per variant), categories, search, category and price filters, sorting (`ProductsController.php:65-97`), related products, share button (`product.tsx:171`), schema.org Product JSON-LD, sitemap |
| Cart / checkout | DB cart (guest and user), Stripe-backed coupons (`PromotionService`), guest checkout behind a toggle, Stripe Checkout through Cashier with a frozen `CheckoutSnapshot`, idempotent order creation, async payment events handled (`StripeWebhookListener.php:35-45`) |
| Payment | Stripe Checkout with **no `payment_method_types`**, so payment methods are dynamic and controlled from the Stripe dashboard (`CheckoutController.php:499`, `:618`) |
| Post-purchase | Queued emails for confirmed / shipped / delivered / cancelled orders, signed guest order page (`Orders/GuestShow.tsx`), Spediamo tracking module (polling, normalised events, auto-deliver) |
| Account | Google login, dashboard, order list with status stepper, wishlist (logged-in users only), reviews limited to buyers of **delivered** orders (`ReviewController.php:86-95`), with moderation |
| Admin | Dashboard KPIs, orders (ship, bulk status, cancel), products and variants, categories and parcel sizes, review moderation, a settings registry with audit history |
| Compliance | 5 locales, cookie consent, legal pages, withdrawal days setting (`config/settings.php:134`), VAT-inclusive pricing |

### Gaps against comparable EU DTC moto-parts shops

The benchmarks are niche EU moto-parts and lighting DTC stores (Highsider, Kellermann, Louis and Polo for fitment UX; generic Shopify DTC for post-purchase).

1. **No vehicle fitment.** This is the main gap. Every serious moto-parts store has a make/model/year selector. Here the only filters are category and price.
2. **No structured specs, homologation or GPSR data.** The product page shows one plain `<p>` description (`product.tsx:396-398`). `isSpecsOpen` is declared but never rendered (`product.tsx:95`). Nothing shows the E-mark, lumens, voltage or connector. The EU **General Product Safety Regulation** (applicable since 13 Dec 2024) requires the manufacturer's identity and contact, a product identifier and any safety warnings on the listing. `products` has no fields for them.
3. **Refunds are a placeholder.** `Admin\OrdersController::refund` only appends a note (`OrdersController.php:383-402`, see its TODO). There is no customer-facing withdrawal or returns flow. Under **Directive (EU) 2023/2673**, the *withdrawal function* (an online "withdraw from contract here" button, new Art. 11a of the Consumer Rights Directive) has been mandatory since **19 June 2026**. **This is a legal gap today, not only a growth idea.**
4. **No marketing consent and no list.** There is no newsletter, no consent capture at checkout and no abandoned-checkout recovery. The code deliberately avoids review requests because they count as marketing (`app/Mail/OrderDelivered.php:13`), which is the correct instinct.
5. **No AOV mechanics.** There are no bundles or kits and no free-shipping threshold. Shipping is the sum of each line's parcel `shipping_cost` and is never capped (`Cart.php:42-49`, `CheckoutController.php:307-312`). Two different headlights pay shipping twice, while quantity 2 of the same line pays it once. That is inconsistent and penalises bigger baskets.
6. **Customers see thin post-purchase information.** Customers see only a tracking number and a carrier link (`GuestShow.tsx:86-95`). The rich `shipment_tracking_events` timeline is shown only in `Admin/Orders/Show.tsx`.
7. **Stock-outs lose demand silently.** Out-of-stock variants just disable the button (`product.tsx:362-367`). Small-batch hardware runs out often, and there is no back-in-stock alert or pre-order.
8. **Tax is single-rate.** One `tax.rate` (22%) applies to every destination (`app/Support/Vat.php`). Selling B2C to DE/FR/ES above the €10k/yr EU threshold needs destination VAT through OSS. **GB is in the default destinations** (`config/settings.php:114`), and post-Brexit B2C consignments of £135 or less need UK VAT handling. This is a revenue-protection risk.
9. **Leftovers.** `resources/js/pages/Subscription/Index.tsx` has no route and is orphaned. The admin "customers" section is a TODO (`routes/admin.php:58`).

---

## 2. Feature ideas (19)

Legend: **Impact** H/M/L is the expected effect on revenue, margin or risk. **Effort** S = under 1 week, M = 1–3 weeks, L = more than 3 weeks for one full-stack dev in this codebase.

### Conversion

#### 1. Fitment finder and "My Garage"
- **Problem.** "Will it fit my 2021 Husqvarna 701 / KTM 690 SMC R?" is the main reason people abandon the purchase and the main cause of returns for bike-specific parts.
- **For the customer.** A make → model → year selector on the home hero, `/products` and the PDP. Results show a "Fits your bike" or "Does not fit" badge, plus fitment notes such as "requires bracket kit X" or "wiring adapter for 2017+". Logged-in riders save bikes to **My Garage** and get filtered views automatically. Guests have their choice remembered in session or cookie. This is functional storage and needs no consent banner.
- **Admin.** A CRUD screen for vehicles (make, model, year range, displacement, OEM headlight connector). A fitment matrix on the product edit page, per product or per variant, with free-text notes. CSV import and export, because the owner will want to bulk-edit in a spreadsheet.
- **Integration.** New models `Vehicle`, `ProductFitment` (morph to `Product` or `ProductVariant`), `UserVehicle`. Extend `ProductsController@index` filters (`ProductsController.php:65-97`), `ProductsController@show` (fitment props, plus JSON-LD `isAccessoryOrSparePartFor`), `resources/js/pages/products.tsx`, `product.tsx`, `home.tsx`. Add a "Garage" tab to `Dashboard/`. Generate SEO landing pages `/fits/{make}/{model}` and add them to the sitemap command.
- **Dependencies.** The owner must build the fitment data. That is the real cost.
- **Impact: H.** It addresses the top purchase blocker, cuts "does it fit?" emails and wrong-model returns, and creates long-tail SEO pages ("faro led husqvarna 701"). Measure PDP→cart rate and return rate with the reason "doesn't fit".
- **Effort: M.**
- **Risks.** Wrong fitment data creates liability and returns. Show "verified by RSP" versus "reported by riders", and keep notes visible.

#### 2. Spec sheet, downloads and a GPSR / road-legality block
- **Problem.** Plain-text descriptions are unreadable on mobile (`fixes.md` point 4), and legally required GPSR information is missing.
- **For the customer.** A scannable spec table (lumens low/high, W, V, IP rating, weight, connector, **E-mark / ECE regulation**, "road legal: yes / off-road only") and badges above the fold. Downloads: installation manual, wiring diagram, homologation certificate. A GPSR footer block lists manufacturer, EU responsible person, product ID and safety warnings, translated.
- **Admin.** A spec-template per category (attribute definitions) and values per variant. Per-locale PDF upload. Manufacturer and responsible-person fields as global settings with per-product override.
- **Integration.** `product_attributes` and `product_attribute_values` (variant-level), `product_documents`. Render in `product.tsx`, which already has the unused `isSpecsOpen` state (`:95`). Add GPSR defaults to the settings registry (`config/settings.php`, new `compliance` group). Upload through `ImageUploadService` / `FileUploadConfigService`.
- **Impact: M-H.** Cheap trust, mobile clarity and compliance in one piece of work. Specs also feed idea 1 and comparison.
- **Effort: S-M.**
- **Risks.** Claiming "road legal" without a valid E-mark exposes the shop to consumer-law and traffic-code liability. The owner must hold the certificates.

#### 3. Back-in-stock alerts and pre-order batches
- **Problem.** Small-batch hardware sells out, and the out-of-stock state is a dead end (`product.tsx:362-367`).
- **For the customer.** On out-of-stock variants, "Notify me" takes an email address, guest or user, for that variant. It is a single-purpose service email, so it needs no marketing opt-in, but it must not be reused for marketing. Optional mode: **pre-order** with an explicit ETA ("ships from 15 Nov") and full payment now. The ETA appears in the confirmation email.
- **Admin.** A waitlist count per variant, which doubles as demand forecasting for the next production batch. A "send restock alerts" action, or an automatic send when stock goes from 0 to above 0. A per-variant pre-order toggle with an ETA date and a cap.
- **Integration.** `StockAlert` model (variant_id, email, locale, notified_at, token). Fire on the `ProductVariant` stock update (quick-stock action `ProductController::quickStock`). Send through `EmailService` and its queue. For pre-order, `StockService` already records oversold lines per order (`StockService.php:17-19`, `order_items.oversold_qty`). A pre-order is a *planned* oversell, so add `orders.preorder_eta` and show it in the emails and `GuestShow`.
- **Impact: M-H.** It captures demand that is lost today and tells the owner how many units to produce.
- **Effort: S** for alerts, **M** with pre-orders.
- **Risks.** Pre-order under EU law: if no date is agreed, delivery is due within 30 days (CRD Art. 18), so state the ETA clearly. Keep alert emails strictly transactional. Pre-orders of goods made to the customer's spec would be exempt from withdrawal, but standard models are not.

#### 4. Local payment methods and BNPL, sent with real line items
- **Problem.** Italy over-indexes on PayPal, Satispay and BNPL for €100–300 tickets. NL, BE and DE have their own local methods.
- **For the customer.** PayPal, Klarna ("3 rate senza interessi"), Satispay, Bancontact, iDEAL | Wero and EPS appear depending on country. The PDP shows "or 3 × €X" messaging.
- **Admin / ops.** Mostly Stripe-dashboard toggles, because the sessions are already dynamic (no `payment_method_types` at `CheckoutController.php:499` / `:618`). Async completion is already handled (`StripeWebhookListener.php:38-42`).
- **Code changes.**
  - Send **real line items** instead of the single "Cart Purchase" line (`:499`, `:618-622`). Klarna, PayPal and the Stripe receipts then show what was bought. The `CheckoutSnapshot` lines already exist to build them from.
  - Add a payment badge strip on the PDP and cart.
  - Optional: cash on delivery (*contrassegno*), which Spediamo supports (`cashOnDeliveryAmount`, `docs/spediamo/shipments.md`). Only enable it for IT with a fee and a refusal policy.
- **Impact: M-H.** Fewer checkout drops among buyers who don't use cards. BNPL tends to raise conversion on considered purchases.
- **Effort: S.** COD would be M.
- **Risks.** Check method availability on the account (Satispay and Wero availability vary). Line items must add up exactly to the snapshot total, including discount and shipping, or Stripe rejects the session. COD refusals cost round-trip shipping.

#### 5. Free-shipping threshold and fair parcel consolidation
- **Problem.** Shipping is summed per line with no cap (`Cart.php:42-49`), which punishes multi-item baskets. The docblock at `CheckoutController.php:307` says it is "never waived or capped".
- **For the customer.** A cart progress bar ("€23 to free shipping in IT"). Shipping is charged once per parcel, not per line. The threshold is per country.
- **Admin.** Settings `shipping.free_threshold` (per destination country) and `shipping.consolidation` (max-of-lines or a bin rule).
- **Integration.** `Cart::getShippingCostAttribute`, `CheckoutController::checkoutTotals` (single source, so the emails and snapshot stay consistent), `cart.tsx`, `Checkout/Details.tsx`, and the settings registry in `config/settings.php`.
- **Impact: M-H on AOV.** A threshold set just above the hero headlight price pushes buyers to add an accessory (pairs with idea 6).
- **Effort: S.**
- **Risks.** Margin. Model it on real Spediamo rates first; the `POST /v1/shipments/estimate` endpoint can calibrate them.

### AOV

#### 6. "Complete the kit": curated bundles and add-ons
- **Problem.** A headlight install usually needs more: a wiring harness or relay, a mount or bracket, a number-plate light, a spare ring or cover. None of it is offered at the point of purchase.
- **For the customer.**
  - On the PDP: "Complete the kit" checkboxes with a kit price ("save 10%").
  - In the cart: "Riders also need…", **hand-curated per product, no algorithm**.
  - Kits respect fitment (idea 1), so only the bracket for *your* bike is shown.
- **Admin.** A bundle builder (parent product + required/optional items + discount), and an accessories list per product with sort order.
- **Integration.** `product_bundles`, `bundle_items`, `product_accessories`. Add to cart through `CartController::add` as linked `cart_items` (a `bundle_id` column). Distribute the discount across lines in `CheckoutSnapshot::capture` so refunds per line stay correct (needed by idea 8). Replace or extend the "related products" section in `product.tsx:434`.
- **Impact: H.** Accessories attached at purchase time raise AOV, and the customer also gets a working install.
- **Effort: M.**
- **Risks.** Stock coupling: if one bundle component is out of stock, the bundle is unavailable. Partial withdrawal of a bundle needs a clear refund rule.

#### 7. Gift cards (digital, multi-purpose)
- **Problem.** Christmas and birthday gifts for riders, where the gift buyer doesn't know the bike.
- **For the customer.** Buy a €50/100/150 card and send it by email on a chosen date. The recipient redeems it at checkout, with balance carried over.
- **Admin.** Issue, void and adjust cards, with a balance ledger.
- **Integration.** `GiftCard` and `GiftCardTransaction` models. Redemption reduces the Stripe charge (it is not a Stripe coupon, because Stripe coupons have no balance). Hook into `checkoutTotals` and `CheckoutSnapshot`. Delivery through `EmailService`.
- **Impact: L-M.** Seasonal, but it pairs naturally with the fitment problem: "can't choose? gift a card".
- **Effort: M.**
- **Risks.** VAT: a multi-purpose voucher is taxed at redemption, not at sale. Italian consumer rules on expiry give a reasonable validity, often 12–24 months; check with the accountant. Fraud through stolen cards, so rate-limit and hold for 24h.

### Retention and loyalty

#### 8. Self-service withdrawal and returns / warranty portal (EU withdrawal button)
- **Problem.** This is a legal must since 19 June 2026, and the refund logic is a stub (`OrdersController.php:383-402`).
- **For the customer.**
  - On `Dashboard/Orders` and on the signed guest page `Orders/GuestShow`, a clearly labelled **"Recedi dal contratto / Withdraw from contract"** button. It stays available for `legal.withdrawal_days` after delivery (`config/settings.php:134`).
  - Two steps: choose the items and quantities, then confirm. An acknowledgment email is sent immediately.
  - The same portal handles **warranty claims** (2-year legal guarantee) with photo upload.
- **Admin.** An RMA queue with statuses `requested → label_sent → received → inspected → refunded/rejected`. A return label (optional, through Spediamo with sender and recipient swapped). A **real Stripe refund**, partial or full. Restock through `StockService` restore.
- **Integration.** `ReturnRequest` and `ReturnRequestItem` models. Wire `refund()` to the Stripe Refunds API using `orders.stripe_payment_intent_id`, then `EmailService::sendOrderRefunded` (already exists, `EmailService.php:104`). Spediamo `POST /v1/shipments` and the waybill PDF `GET /v1/waybills/{id}/pdf` (`docs/spediamo/`) behind the reserved `SupportsShipmentCreation` contract (`app/Shipping/Tracking/Contracts/SupportsShipmentCreation.php:12`).
- **Impact: H.** It removes a regulatory exposure, cuts support emails, and a clean returns experience lifts trust and repeat purchase.
- **Effort: M.**
- **Risks.** Refund deadline: 14 days from the withdrawal notice, and the shop may withhold until the goods are back. Exemptions such as custom wiring must be pre-declared. Partial refunds for bundles (see idea 6). The button must not be buried behind login (guests use the signed link).

#### 9. Product registration and extended warranty ("RSP Care")
- **Problem.** Riders buy once per bike, so the shop needs a reason for a durable, permissioned relationship.
- **For the customer.** Register the headlight (order number or serial number on the box, which covers purchases from dealers too) and get **+1 year warranty**, install tips for the bike, and firmware/accessory notices. Registration offers a separate, unticked marketing opt-in.
- **Admin.** Registrations list, serial lookup and warranty status in the RMA screen.
- **Integration.** `ProductRegistration` (user_id, variant_id, serial, order_id nullable, purchased_at, warranty_until). Ties into idea 8 (warranty claims check `warranty_until`) and idea 1 (registered bike → My Garage).
- **Impact: M.** Captures first-party data, including dealer-channel buyers you never see. Lifts trust ("3-year warranty" on the PDP).
- **Effort: M.**
- **Risks.** A commercial warranty must state its terms clearly (Codice del Consumo art. 133) and must not reduce the legal guarantee. Serial generation needs a manufacturing process change.

#### 10. "Ride with a friend" referral
- **Problem.** Supermoto is a tight community (track days, clubs, WhatsApp groups), and word of mouth is the cheapest channel.
- **For the customer.** After delivery the buyer gets a personal code: friend gets €15 off, referrer gets €15 credit when the friend's order passes the withdrawal window. Shareable with the existing Web Share pattern (`product.tsx:171`).
- **Admin.** Referral ledger, fraud review (same address, card or IP), payout state.
- **Integration.** Generate **Stripe promotion codes** per referrer (validated by `PromotionService::validatePromotionCode`, applied through `CartController::applyCoupon` at `:318`). Order creation reads `orders.coupon_code` to attribute it. Credit is issued as a Stripe coupon, or as gift-card balance (idea 7).
- **Impact: M.** Strong fit for a community niche with low acquisition cost.
- **Effort: M.**
- **Risks.** Self-referral fraud. Pay out only after the withdrawal window. The referrer's marketing consent is not needed to show their code, but sending them referral reminders is marketing.

### Post-purchase experience

#### 11. Branded tracking timeline and delivery-stage emails
- **Problem.** "Where is my order?" contacts. The normalised event timeline exists but only admins see it.
- **For the customer.** `GuestShow` and `Dashboard/Orders` show the `shipment_tracking_events` timeline with statuses (`InTransit`, `OutForDelivery`, `Exception`), the estimated delivery date and a link to the install guide for the bike. Optional emails: "out for delivery" and "delivery problem".
- **Admin.** Toggles in the `email` settings group (`config/settings.php:153-158` pattern).
- **Integration.** Expose `Order::trackingEvents()` (`Order.php:149`) in `GuestOrderController` / `DashboardController::orders`. Hook new mails into `ShipmentTrackingService::apply` status transitions. The labels already exist under `tracking_admin.status.*` in `lang/en.json:697-712`, so reuse them.
- **Impact: M.** Fewer support emails, and the tracking page becomes a cross-sell surface (accessories, review prompt).
- **Effort: S.**
- **Risks.** Carrier event wording can leak internal codes. The adapter mapping already prevents this.

#### 12. Photo reviews ("Rider gallery") and a consented review request
- **Problem.** For a visual, aesthetic product, photos of *real bikes* wearing the headlight sell better than studio shots. Review volume is structurally low: reviews require a delivered order (`ReviewController.php:86-95`) and nobody is ever prompted.
- **For the customer.** A review form with up to 4 photos and "my bike" (pre-filled from My Garage). The PDP shows a "Verified purchase" badge, a photo strip and a filter by bike. A **Rider gallery** section sits on the home page and the PDP.
- **Admin.** Media moderation in the existing review moderation (`Admin\ReviewsController` approve/reject/bulk). Toggles to feature photos on the home page and to reuse them on social (with a licence checkbox).
- **Integration.** `review_media` table, upload through `ImageUploadService`. Add `vehicle_id` to `reviews`. Add a review-request email N days after `delivered`, gated on consent (see risks) and sent through `EmailService` / `OrderEmail` dedupe. Add JSON-LD `aggregateRating` and `review` to the existing schema in `ProductsController@show`.
- **Impact: H.** Social proof plus UGC that feeds Instagram and TikTok, and fitment evidence ("works on 2019 FE 501").
- **Effort: M.**
- **Risks.**
  - **Consent for review requests.** The team's view that a review request is marketing (`OrderDelivered.php:13`) is the cautious reading for Italy. Use the checkout opt-in (idea 14) or a soft-opt-in notice with an easy opt-out.
  - **Omnibus Directive.** The shop must disclose how it verifies reviews. It can say "only buyers of delivered orders", which is true here.
  - **Image rights and moderation.** People's faces and number plates appear in photos, so offer blur or a guideline.

#### 13. Product Q&A on the product page
- **Problem.** Pre-sale questions ("2-pin or 3-pin connector?", "does it need a CANbus resistor?") go to the contact form and the answers are lost.
- **For the customer.** "Ask a question" on the PDP. Answers from RSP (or from verified owners) appear publicly and are searchable.
- **Admin.** A Q&A inbox with answer, publish/reject, and "promote to FAQ / fitment note".
- **Integration.** `ProductQuestion` model (product_id, vehicle_id nullable, body, answer, status, locale). Display it on `product.tsx`. Email notification to the asker through `EmailService`. Add `FAQPage` / `QAPage` schema to the PDP JSON-LD.
- **Impact: M.** Fewer support tickets, fresher indexable content, and a feedback loop for fitment data.
- **Effort: S.**
- **Risks.** Multilingual answers (5 locales): start with answers in the asker's language, untranslated. Spam needs throttling, which follows the `contact.store` pattern.

### Marketing hooks

#### 14. EU-compliant newsletter (double opt-in) and abandoned-checkout recovery
- **Problem.** The shop owns zero marketing permissions. Every visitor who leaves is lost.
- **For the customer.**
  - **Newsletter:** an unticked opt-in at checkout and registration, plus a footer form. Double opt-in, one-click unsubscribe, locale-aware.
  - **Abandoned-checkout recovery:** Stripe Checkout's own consent checkbox (`consent_collection.promotions=auto`) plus `after_expiration.recovery.enabled=true`. When a session expires, Stripe returns a recovery URL. Send one reminder with that link, and only if consent was given.
- **Admin.** A subscriber list with consent proof (timestamp, IP, source, text version), CSV export, and a sync job to the ESP (Brevo is EU-hosted, Mailchimp is US).
- **Integration.** `NewsletterSubscription` model and a consent log. Add the checkbox to `Checkout/Details.tsx` and `auth/register.tsx`. The **`checkout.session.expired` branch already exists** (`StripeWebhookListener.php:45`), so the recovery handler goes there. Add session params at `CheckoutController.php:499` / `:618`.
- **Impact: M-H.** Recovery emails on expired checkouts are among the highest-ROI flows in e-commerce, and the owned list is what makes drops (idea 3) and seasonal pushes work.
- **Effort: S-M.**
- **Risks.** GDPR art. 7 (provable consent) and the ePrivacy / Codice Privacy art. 130 (no pre-ticked boxes). Stripe's consent checkbox only shows where required. Keep your own log as the source of truth.

#### 15. Omnibus-compliant sale pricing and launch "drops"
- **Problem.** `compare_price` exists but only the admin sees it (`Admin/Products/Index.tsx:424`), so there is no way to run a visible sale. Showing a crossed-out price without the **lowest price in the prior 30 days** breaks the Omnibus Directive (art. 6a, Price Indication Directive).
- **For the customer.** Strikethrough price, "-15%", and "Lowest price in the last 30 days: €X" in small print. Optional timed drop pages ("new Carbon batch, Friday 18:00").
- **Admin.** Schedule a sale (start/end, price per variant). The 30-day low is computed automatically.
- **Integration.** `variant_price_history` written by a `ProductVariant` observer on price change. Display in `product.tsx`, `ProductCard.tsx` and `products.tsx`. Add JSON-LD `priceValidUntil`.
- **Impact: M.** It unlocks Black Friday and end-of-season campaigns without legal exposure.
- **Effort: S.**
- **Risks.** Italian enforcement (AGCM) on Omnibus is active, so get it right before the first sale.

### Community and content

#### 16. Installation hub and partner-workshop locator
- **Problem.** "Can I install it myself?" blocks purchases. Some buyers want a workshop, and the `servizi` category hints at an install service.
- **For the customer.**
  - A per-product (and per-bike, see idea 1) **installation guide page** with difficulty, time, tools, a video embed (privacy-friendly with click-to-load) and a wiring diagram.
  - A **"Find a fitting workshop near you"** map of partner officine, with an optional add-on "Installation at partner workshop" sold as a `servizi` product.
- **Admin.** Guide editor (reuse the `Page` model, or a `Guide` model linked to products and vehicles). A workshop directory (name, address, lat/long, services, languages).
- **Integration.** `Page` model (`app/Models/Page.php`), `PageController`, sitemap generation. The guide link goes into the shipped and delivered emails and the tracking page (idea 11).
- **Impact: M.** Removes a purchase blocker, creates SEO content ("come montare faro led motard"), and builds a B2B relationship with workshops (idea 17).
- **Effort: S** for guides, **M** for the locator.
- **Risks.** Liability for wrong wiring instructions. Version the guides by product revision.

### Localisation, EU specifics and operations

#### 17. Destination VAT (OSS), B2B checkout and dealer accounts
- **Problem.** One 22% rate for every destination (`app/Support/Vat.php`). No business customers (no VAT ID or reverse charge) and no dealer pricing, although workshops and dealers are a natural channel for this product.
- **For the customer.**
  - B2C buyers in DE/FR/ES pay their country's VAT with the same gross shelf price. You can keep gross prices stable and absorb the difference, which is the common DTC choice.
  - B2B buyers tick "I'm buying for a business", enter a VAT ID (VIES validation), get an intra-EU reverse-charge invoice, and Italian B2B buyers get *fattura elettronica* data (SDI code / PEC).
  - Approved dealers see a wholesale price tier after login.
- **Admin.** A per-country VAT table, a dealer approval queue, price tiers per variant, and an invoice data export for the accountant.
- **Integration.** Extend `Vat::includedIn` to take the destination country, and store the rate per order (already done: `orders.tax_rate`). Add `users.role=dealer` (the `role` column exists in `User.php`), `variant_prices` per tier, and B2B fields in the checkout rules (`CheckoutController.php:350-367`). Decide what to do with GB: drop it or handle UK VAT.
- **Impact: M-H.** Risk removal (OSS) plus a new channel (dealers buy several units).
- **Effort: L.**
- **Risks.** Tax advice is required. E-invoicing to SDI needs a provider (Aruba, Fatture in Cloud API), which is plain integration, not AI.

#### 18. Pickup-point / locker delivery
- **Problem.** Riders are often out when couriers come, and Italian buyers use lockers and pickup points heavily.
- **For the customer.** At checkout: "home" or "pickup point", with a point picker.
- **Integration.** Spediamo shipment creation accepts `recipientServicePointId` (`docs/spediamo/shipments.md`, POST `/v1/shipments`), **but the documented API has no endpoint to list service points**. A carrier-specific locator widget would be needed (BRT Fermopoint, InPost, Poste). Store `orders.pickup_point_id` and the point snapshot. It depends on auto-labels through `SupportsShipmentCreation`.
- **Impact: M.**
- **Effort: L,** because of the data-source gap.
- **Risks.** Locker size limits on bulky headlight boxes (check the `Size` dimensions). Don't build this until Spediamo or the carrier confirms a point-list source.

#### 19. Admin customer view and retention metrics
- **Problem.** The admin cannot see customers (`routes/admin.php:58` TODO), so retention work cannot be measured.
- **Admin.** A customer list with orders, LTV, last order, marketing consent, garage bikes, open RMAs. A cohort card on the dashboard (repeat rate, time to second order) and a top waitlisted variants card (idea 3).
- **Integration.** `Admin\CustomersController`, `Admin/Customers/*`. Extend `Admin\DashboardController@index`.
- **Impact: M** (enabler). It is the measurement layer for ideas 3, 9, 10 and 14.
- **Effort: S-M.**
- **Risks.** Access logging for personal data, and GDPR right-to-access export. `ProfileController::destroy` deletion must cascade to the new tables.

### What I would explicitly **not** build

- **Loyalty points.** Purchase frequency per customer is too low for points to change behaviour. Registration (9) and referral (10) do the retention job better.
- **Subscriptions via Cashier.** Headlights are not consumables. The orphaned `Subscription/Index.tsx` page (no route) should be deleted, not extended. Revisit only if a consumable appears (cleaning kits, spare rings as a "crash replacement plan").
- **A 3D or AR configurator.** High cost, and real-bike photos (12) do the job.
- **A marketplace or used-parts listings.** `fixes.md` uses the word *annuncio*, but turning the shop into classifieds dilutes the brand and adds moderation load.

---

## 3. Impact and effort matrix

```
                 EFFORT →     S (<1 wk)                     M (1–3 wks)                        L (>3 wks)
IMPACT  H   │ 5 Free-ship threshold*        │ 1 Fitment finder / My Garage      │
            │ 4 Payment methods + lines*    │ 8 Withdrawal & returns portal (§)  │
            │                               │ 6 Complete-the-kit bundles        │
            │                               │ 12 Photo reviews + review request │
        M-H │ 2 Specs / GPSR block (S-M)    │ 3 Back-in-stock + pre-order       │ 17 OSS VAT + B2B / dealers (§)
            │ 14 Newsletter + recovery (S-M)│                                   │
        M   │ 11 Tracking timeline          │ 9 Product registration / warranty │ 18 Pickup points
            │ 13 Product Q&A                │ 10 Referral                       │
            │ 15 Omnibus sale pricing       │ 16b Workshop locator              │
            │ 16a Install guides            │ 19 Customer view (S-M)            │
        L-M │                               │ 7 Gift cards                      │
```
`*` = M-H impact placed in the H row for quick-win emphasis. `§` = compliance-driven: the impact is risk removed as well as revenue.

**Quick wins** (high impact, low effort): 4, 5, 2, 14, 11, 15, 3 (alerts only).
**Strategic bets:** 1, 6, 8, 12.
**Later, or only when the scale justifies it:** 17, 18, 7.

---

## 4. Three-phase roadmap

### Phase 1: first 30 days ("stop the leaks, switch on the cheap levers")
| Week | Items |
|---|---|
| 1 | **4** Turn on PayPal, Klarna, Satispay and Bancontact in Stripe. Send real line items. Add payment badges. **5** Free-shipping threshold and per-parcel shipping fix. |
| 2 | **2** Spec table and GPSR / road-legality block, plus manual PDFs. **15** Omnibus price history (needed before any sale). |
| 3–4 | **8** Withdrawal button and returns portal with real Stripe refunds (legal deadline already passed). **14** Checkout and registration opt-in, double opt-in, Stripe recovery in the existing `checkout.session.expired` handler. **11** Customer-facing tracking timeline. |

Exit criteria: refunds move real money; every PDP shows specs, GPSR data and payment options; a consented list is growing; there are no inconsistent shipping charges.

### Phase 2: next quarter ("make the product easy to choose")
- **1** Fitment finder and My Garage, plus `/fits/{make}/{model}` SEO pages. Start the fitment data entry in week 1 of this phase, because it is the long pole.
- **6** Complete-the-kit bundles and curated accessories (pairs with the threshold from 5).
- **12** Photo reviews, Rider gallery and the consented review request.
- **3** Back-in-stock alerts, then pre-order batches for the next production run.
- **13** Product Q&A. **16a** Installation guides. **19** Admin customer view and cohort KPIs.

### Phase 3: later (6–12 months)
- **10** Referral programme, once there is enough review and UGC proof to share.
- **9** Product registration and extended warranty, which needs serials on the boxes.
- **17** OSS destination VAT (do it as soon as cross-border B2C sales approach €10k/yr), then B2B checkout and the dealer tier.
- **16b** Partner-workshop locator and install add-on (the `servizi` category).
- **7** Gift cards, in time for Q4. **18** Pickup points, only once there is a point-list data source.

---

## 5. Top 3: mini-specs

### Top pick 1: Fitment finder and "My Garage"

**Why #1:** it answers the first question every buyer asks, it is defensible (curated data competitors lack), and it compounds into SEO, kits (6), reviews (12) and Q&A (13).

**User stories**
- As a rider, I pick *Husqvarna → 701 Supermoto → 2021* once and the shop shows only headlights and kits that fit, with any notes.
- As a rider on a PDP, I see a green "Fits your 701 (2021)" or a red "Not compatible" badge, plus the list of all compatible bikes.
- As a logged-in rider, I save up to 5 bikes in My Garage and switch between them.
- As the owner, I bulk-import fitment from CSV and edit it per variant with notes ("requires bracket RSP-BRK-07").
- As Google, I can crawl `/fits/husqvarna/701-supermoto` listing compatible products.

**Data model sketch**
```
vehicles            id, make, model, slug, year_from, year_to, displacement_cc nullable,
                    oem_connector nullable, is_active, timestamps
                    unique(make, model, year_from)
product_fitments    id, fittable_type (Product|ProductVariant), fittable_id, vehicle_id,
                    year_from nullable, year_to nullable  (narrower than vehicle range),
                    note nullable (translatable json), status enum(verified, reported), timestamps
                    index(vehicle_id), unique(fittable_type, fittable_id, vehicle_id, year_from)
user_vehicles       id, user_id, vehicle_id, year, nickname nullable, is_primary, timestamps
```
Guests: `session('garage.vehicle')` = {vehicle_id, year}, merged into `user_vehicles` on login. Hook this where the cart merges today.

**Key screens**
1. **Selector component** (`components/FitmentSelector.tsx`): three cascading shadcn `Select`s, used in the home hero, the `/products` header and the PDP. Chosen bike shown as a chip ("701 SM '21 ✕").
2. **Product list** (`products.tsx`): with a bike selected, results are filtered (add a `vehicle` / `year` query to `ProductsController@index:65-97`) and an empty state links to "Ask us" (idea 13).
3. **PDP** (`product.tsx`): fitment badge next to the price; an expandable "Compatible bikes" table; fitment notes highlighted. Variant switching re-evaluates the badge (fitment can be per variant).
4. **Dashboard → Garage** tab.
5. **Admin → Products → Edit → Fitment** tab: vehicle multi-select with year overrides and note. **Admin → Vehicles** CRUD with CSV import and export.
6. **SEO landing** `/fits/{make}/{model}`: H1 "LED headlights for {make} {model}", product grid, link to the install guide. Add to `generate:sitemap-index`.

**Acceptance criteria**
- Choosing make, model and year filters `/products` server-side. Pagination and other filters keep working, and the canonical URL excludes the vehicle params, the same as the existing filter `noindex` logic at `ProductsController.php:46-47`.
- The PDP shows exactly one of: *Fits* / *Does not fit* / *No bike selected* (with prompt). It never shows a fit claim when there is no fitment row.
- Per-variant fitment overrides product-level fitment.
- A guest's selected bike survives navigation and login, and is saved to the garage after login.
- CSV import is idempotent (re-import updates, does not duplicate) and reports row errors.
- `/fits/...` pages return 200 with Product list JSON-LD and appear in the sitemap. Unknown pages return the existing 404 page.
- All new strings exist in the 5 locales (`lang/*.json`).
- Feature tests cover filtering, badge logic and import.

---

### Top pick 2: Self-service withdrawal and returns / warranty portal

**Why #2:** it closes a live legal gap (withdrawal function, mandatory since 19 June 2026), completes the stubbed refund (`OrdersController.php:383-402`), and is the trust feature that lets the PDP say "14-day returns, one click".

**User stories**
- As a customer (logged in, or a guest with the signed order link), I click **"Withdraw from contract"** on a delivered order, choose items and quantities, optionally give a reason, and confirm. I get an acknowledgment email immediately, with date and time.
- As a customer, I see my return's status and, if offered, download a prepaid label.
- As a customer with a faulty unit within 2 years (3 with RSP Care), I open a **warranty claim** with photos.
- As the owner, I process the return in one screen: mark received, inspect, refund all or part through Stripe, and restock or quarantine the unit.

**Data model sketch**
```
return_requests       id, order_id, user_id nullable, type enum(withdrawal, warranty),
                      status enum(requested, label_sent, in_transit, received, inspected,
                                  refunded, partially_refunded, rejected, cancelled),
                      reason_code nullable, customer_note nullable, admin_note nullable,
                      requested_at, received_at nullable, refunded_at nullable,
                      refund_amount decimal nullable, stripe_refund_id nullable,
                      return_label_path nullable, return_tracking_number nullable, locale
return_request_items  id, return_request_id, order_item_id, quantity, condition nullable,
                      restock bool default true
return_request_media  id, return_request_id, path            (warranty photos)
```
Shipping cost refund rule (CRD art. 13): refund the original *standard* delivery cost when the whole order is withdrawn, and keep the policy text in sync with `shipping-returns.tsx`.

**Key screens**
1. **Dashboard/Orders** and **Orders/GuestShow**: a prominent "Withdraw from contract" button, visible while `now() <= delivered_at + legal.withdrawal_days`. After that it changes to "Report a problem (warranty)".
2. **Withdrawal step 1:** item checklist with quantity steppers. **Step 2:** a confirmation page with a **"Confirm withdrawal"** button (the regulation wants an explicit confirmation function), then a success page with an RMA number.
3. **Return status page:** timeline (reuse the stepper style from `Dashboard/Orders.tsx`), label download, return address.
4. **Admin → Returns** queue with filters by status and type. **Admin → Order → Returns** panel. Refund dialog with amount pre-computed per line, "include shipping" toggle, and confirmation before calling Stripe.
5. Emails: withdrawal received (acknowledgment), label available, refund issued (existing `OrderRefunded` mail).

**Acceptance criteria**
- The button is reachable without login through the signed guest URL (`Order::publicUrl()`), is not shown before delivery or after the window, and the window uses `legal.withdrawal_days`.
- The acknowledgment email is sent on a durable medium within minutes of confirmation, through the queued `EmailService` with `OrderEmail` dedupe, and contains the order, items, date and time.
- `refund()` calls Stripe Refunds on `stripe_payment_intent_id` with an idempotency key `return:{id}`. It stores `stripe_refund_id`, sends `OrderRefunded` **only after** Stripe confirms, and never refunds more than the amount captured minus earlier refunds.
- Restock goes through `StockService` (idempotent), only for items marked `restock`.
- Order status reflects refunds (`refunded` / `partially_refunded`), and the admin dashboard revenue excludes refunded amounts.
- The policy pages and the FAQ link to the portal. All strings exist in 5 locales.
- Tests cover window edges, double-submit, partial refund maths (with coupon discount distribution), guest access by signed URL, and Stripe refund failure (no email, error visible to admin).

---

### Top pick 3: Photo reviews, "Rider gallery" and a consented review request

**Why #3:** this is a visual, identity-driven product. A photo of a real supermoto wearing the headlight is the strongest sales asset the brand can have, and today reviews are rare by design (buyers-only, no prompt). It also produces UGC for the Instagram/TikTok channels the shop already links.

**User stories**
- As a buyer of a delivered order who opted in, I get one email 10 days after delivery: "How does it look on your bike?", with a link straight to the review form.
- As a reviewer, I rate, write, attach up to 4 photos, pick my bike (pre-filled from My Garage), and optionally allow RSP to repost my photo.
- As a shopper, I see a photo strip on the PDP, filter reviews "on my bike", and see "Verified purchase" on each review.
- As the owner, I moderate text and photos together, feature the best photos in the home Rider gallery, and see which photos have repost permission.

**Data model sketch**
```
reviews (existing)    + vehicle_id nullable, + verified_purchase bool (true when created through ReviewController),
                      + order_id nullable, + repost_consent bool default false, + featured bool default false
review_media          id, review_id, path, width, height, sort_order, is_approved bool, timestamps
review_requests       id, order_id unique, email, scheduled_for, sent_at nullable, token, clicked_at nullable
users / orders        + review_request_opt_in (or reuse the marketing consent log from idea 14)
```

**Key screens**
1. **Reviews/Create** (existing page): add a multi-image dropzone (client-side resize, EXIF stripped server-side), a bike selector and a repost consent checkbox.
2. **PDP reviews block** (`product.tsx:404`): an average plus distribution header, a photo strip that opens a lightbox, an "On my bike" filter chip, and verified badges. Expanded by default when photos exist.
3. **Home "Rider gallery"** (`home.tsx`, after featured products): a grid of featured review photos linking to the PDP.
4. **Admin → Reviews**: add a media column with per-image approve/reject and a "feature on home" toggle (extend `Admin\ReviewsController` approve/reject/bulkUpdate).
5. **Email**: localised review request with a signed one-tap link that logs in a guest-review token, or requires login for users.

**Acceptance criteria**
- A review request is sent at most once per order, only after `delivered`, only if consent was recorded (or the soft-opt-in notice was shown and the customer did not opt out), and never after a withdrawal request on that order. A per-recipient unsubscribe link is present.
- Upload accepts JPEG, PNG, WebP and HEIC up to the configured size (`FileUploadConfigService`), strips EXIF and GPS data, and generates responsive sizes.
- Photos are never public before approval, even when `reviews.auto_approve` is on for text.
- PDP JSON-LD includes `aggregateRating` and up to 5 `review` entries, consistent with what is shown.
- A "How we verify reviews" sentence (Omnibus) appears near the reviews and links to the policy.
- Metrics: review rate per delivered order and share of reviews with photos appear on the admin dashboard.
- Tests cover eligibility (delivered only, one per product per user, as today), the media moderation state machine, request scheduling and dedupe.

---

## Appendix: key code references

| Topic | Location |
|---|---|
| Product filters | `app/Http/Controllers/ProductsController.php:65-97` |
| Unused specs state, plain description | `resources/js/pages/product.tsx:95`, `:396-398` |
| Out-of-stock dead end | `resources/js/pages/product.tsx:362-367` |
| Per-line shipping sum | `app/Models/Cart.php:42-49`; docblock `CheckoutController.php:307` |
| Single "Cart Purchase" Stripe line | `app/Http/Controllers/CheckoutController.php:499`, `:618-622` |
| Refund placeholder | `app/Http/Controllers/Admin/OrdersController.php:383-402` |
| Review eligibility (delivered only) | `app/Http/Controllers/ReviewController.php:86-95` |
| "Review request = marketing" note | `app/Mail/OrderDelivered.php:13` |
| Expired checkout webhook branch | `app/Listeners/StripeWebhookListener.php:45` |
| Tracking events (admin-only UI) | `app/Models/Order.php:149`; `resources/js/pages/Admin/Orders/Show.tsx`; customer view `Orders/GuestShow.tsx:86-95` |
| Reserved shipment-creation contract | `app/Shipping/Tracking/Contracts/SupportsShipmentCreation.php:12` |
| Spediamo service points, COD, insurance | `docs/spediamo/shipments.md` (`POST /v1/shipments` params) |
| Single VAT rate | `app/Support/Vat.php`; `config/settings.php` `tax.rate` |
| Destinations incl. GB | `config/settings.php:114` |
| Withdrawal days setting | `config/settings.php:134` |
| Orphan subscription page | `resources/js/pages/Subscription/Index.tsx` (no route) |
| Customers admin TODO | `routes/admin.php:58` |

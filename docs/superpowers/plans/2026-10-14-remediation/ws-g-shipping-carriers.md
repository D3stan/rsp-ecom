# WS-G: Shipping workflow & modular carrier layer

> Branch `feat/shipping-carriers` from `fixes` **after WS-D is merged** (uses `Order::transitionTo()`). Wave 2.
> Read `00-overview.md` first; follow its per-item protocol. Migration prefix: `2026_10_16_10xxxx`.
> **Size: L.** Order: G1 → G2 → G3 → G4 → G5.

**Owner requirements:** keep shipping **really modular so a new carrier can be swapped in easily**. Label creation is
**optional**, controlled from the admin settings page, and **off by default**. With labels off, the admin ships by
typing a tracking number.

**What exists:** a tracking module with a clean adapter boundary: `app/Shipping/Tracking/*`, `TrackingManager`
(Laravel `Manager`), drivers `manual|null|fake|spediamo`, contracts `TrackingProvider`, `SupportsWebhooks`, and an
**empty** `SupportsShipmentCreation` placeholder. Carrier codes and tracking URL templates come from the
`shipping.carriers` setting. Read `docs/shipping-tracking-module.md` and `docs/spediamo/*.md` (`shipments.md`,
`waybills.md`) first.

---

## Target architecture

```
app/Shipping/
  Tracking/…                 (unchanged, except SupportsShipmentCreation is removed or points to Labels)
  Labels/
    Contracts/LabelProvider.php      name(); supports(string $carrierCode): bool;
                                     createShipment(ShipmentRequest): ShipmentResult;
                                     label(ShipmentResult|string $externalId): LabelDocument
    Contracts/CancelsShipments.php   cancelShipment(string $externalId): void   (optional capability)
    DTO/Address.php, Parcel.php, ShipmentRequest.php, ShipmentResult.php, LabelDocument.php
    Exceptions/LabelProviderUnavailable.php, LabelRejected.php
    LabelManager.php                 extends Illuminate\Support\Manager; drivers: manual (none), fake, + extend()
    Drivers/FakeLabelProvider.php    deterministic tracking numbers, tiny PDF; refused in production
    Drivers/Spediamo/SpediamoLabelProvider.php   POST /v1/shipments, GET /v1/waybills/{id}/pdf (reuse the auth classes
                                                 and HTTP base of the tracking driver; don't duplicate credentials)
    ShipmentBuilder.php              Order → ShipmentRequest (sender from settings, recipient from the order's
                                     shipping address, parcels from order items' Size boxes + weights)
  ShippingService.php                the ONLY entry point controllers use:
                                     ship(Order, ShipInput, User): Shipment   (labels on/off logic, transition, email)
```
- Adding a carrier = one driver class + one `extend()` line in a service provider + env credentials. Nothing in
  controllers, React pages or emails is carrier-specific. Document this in a "How to add a carrier" section.
- `shipments` table: id, order_id FK, carrier_code, driver, external_id nullable, tracking_number nullable,
  label_path nullable (**private** disk, e.g. `storage/app/private/labels/{order}/{id}.pdf`), status
  enum(created, cancelled), request_payload json nullable (no secrets), created_by FK users nullOnDelete, timestamps.
  The order keeps `tracking_number` / `tracking_carrier` (existing columns) as the current values, so the tracking module
  and emails keep working unchanged.
- Settings (`shipping` group, labels/help in 5 locales):
  - `shipping.labels_enabled`: bool, default **false**, risk high.
  - `shipping.label_driver`: enum, options = registered label drivers except `fake` in production (if the registry needs
    static options, use `['spediamo']` and validate at runtime), `depends_on: shipping.labels_enabled`.
  - `shipping.sender_*` (name, street, city, postal_code, province, country, phone, email): `depends_on` labels,
    defaults from `store.*` where they exist. Check how the Ship form gets the sender today (`OrdersController::showShip`)
    and reuse that source if it already exists.
  - `shipping.default_parcel_weight_kg`: float, default 1.0.

---

### G1 (P1, F-05): Ship shortcut on unpaid orders; no server guard. Size **S**

**Claim:** `Admin/Orders/Index.tsx:356,420` shows "Ship" only for `status === 'pending'` (unpaid); paid orders are
created as `processing` (`OrderCreationService.php:281`). `OrdersController::processShip`/`showShip` (`:442-516`) don't
check status or payment.

**Repro tests:** as admin, POST `processShip` for a `pending` unpaid order → succeeds today (it must be refused);
GET the orders index → the `processing` + paid order has no ship action in the props (if the action visibility is
computed client-side, give a static reproduction).

**Fix:** a server guard: shippable ⇔ `status = processing` and `payment_status ∈ {succeeded, partially_refunded}`.
Expose `can_ship` per order in the index/show props and use it in the UI.

### G2 (P1, F-06): ship form with tracking number, one shipped email, no notes dump. Size **M**

**Claim:** the Ship form has no tracking field and writes the whole form into `notes` (`buildShippingNotes`,
`OrdersController.php:521-604`). The shipped email goes out without tracking (`:492-493`), and then a second one when the
number is added later (fingerprint includes the number: `EmailService.php:70-81`). Bulk "shipped" also emails without
tracking (`:351-355`).

**Repro tests:** process the ship form → notes contain the form dump (today); adding a tracking number afterwards → a
second shipped email row (today).

**Spec (labels OFF, the default):**
- The Ship page shows: a carrier select (from `shipping.carriers`, required), a tracking number (required), and a
  checkbox "Ship without tracking number" that unlocks submitting without one (with a confirmation).
- `ShippingService::ship()` creates a `shipments` row, sets `tracking_number`/`tracking_carrier`, calls
  `transitionTo('shipped')`, and sends **one** shipped email: with tracking when present. Without tracking, the email is
  sent only if the admin ticks "Notify the customer now"; when a number is added later and no shipped email was sent
  yet, that first email is sent then. When one was already sent without tracking, adding the number sends one
  tracking-update email using the existing fingerprint scheme (explicit and expected).
- Nothing is written into `notes` any more. Delete `buildShippingNotes`.
- Bulk: remove `shipped` from bulk status options (shipping requires per-order data). Keep the other bulk statuses
  (validated by D1's state machine).

### G3 (P1): optional label creation through the carrier layer. Size **L**

**Spec (labels ON):**
- The Ship page shows the parcel fields (as today: box, weight, insurance, COD only if the driver supports it), prefilled
  by `ShipmentBuilder`, plus a **"Create label and ship"** button. The service calls the active `LabelProvider`, stores the
  PDF on the private disk, saves the tracking number from the result, transitions to shipped and sends the email (with
  tracking).
- Failures: `LabelRejected` (validation from the carrier) → show the carrier's message on the form, no state change;
  `LabelProviderUnavailable` → error flash, no state change, logged with a reference id. Never a half-shipped order:
  create the label first, then the DB changes in a transaction. If the DB part fails after the label was created, record the
  shipment row with the external id so the label isn't lost, and surface it.
- An admin route to download the label PDF (authorised, streamed from the private disk); a "Download label" button on
  Order Show; "Cancel shipment" if the driver implements `CancelsShipments` (it doesn't change the order status; the
  admin then uses D1's "undo ship").
- Spediamo driver: implement against `docs/spediamo/shipments.md` / `waybills.md`. Unit-test it with `Http::fake`
  fixtures built from the docs' example payloads. Don't call the real API. If the docs leave a field ambiguous, list
  it in the report instead of guessing silently.

**Tests:** labels off ⇒ no provider is resolved at all; labels on + fake driver ⇒ shipment row + PDF stored + tracking set +
one email; rejected ⇒ no transition and the error shown; unavailable ⇒ the same; label download 403 for customers;
Spediamo driver maps request/response correctly (fixture tests); a "contract test" that every registered label driver
satisfies the DTO contract (mirror `tests/Feature/Shipping/TrackingProviderContractTest.php`).

### G4 (P1, H7): customers can't see tracking on the site. Size **S**

**Claim:** `Order::trackingUrl()`, `trackingCarrierName()` and the synced `tracking_status` are used only in the shipped
email (`app/Mail/OrderShipped.php:53-55`). `Dashboard/Orders.tsx:235-240,317-325` and `Orders/GuestShow.tsx:86-98` show
plain text. The timeline greys out every step for cancelled orders.

**Repro test:** customer order page props lack `tracking_url`/`carrier_name`/`tracking_status` (fails today).

**Fix:** add them (plus `tracking_last_synced_at` and the customer-safe tracking events: status label, time, location,
no raw carrier codes) to both payloads. **Note:** WS-A whitelisted these payloads; extend the whitelist. Render a
"Track with {carrier}" button, the carrier status, and a compact event list. A cancelled order shows a dedicated
"Cancelled" state (and "Refunded" when `payment_status` is refunded). Reuse the `tracking_admin.status.*`
labels or add customer-facing ones in 5 locales.

### G5: docs + wrap-up

Update `docs/shipping-tracking-module.md` with the Labels layer and a "How to add a carrier" checklist. Report a summary.

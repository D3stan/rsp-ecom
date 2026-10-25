---
domain: shipping-and-tracking
version: 1.0.0
budget_lines: 150
deep_reference: docs/reference/shipping-tracking-module.md
---

# Shipping and Tracking Domain Specification

## 1. Scope & Responsibility

Governs carrier rating, waybill generation, shipping label management, and post-fulfillment shipment tracking. Provides an isolated, carrier-agnostic architectural boundary between internal fulfillment logic and external courier platforms (e.g. Spediamo, DVAExpress).

Key domain responsibilities include:
- Aggregating carrier rate quotes and calculating package shipping costs.
- Generating and storing shipping labels and customs documentation.
- Polling carrier tracking APIs (`tracking:sync`) and ingesting tracking webhooks.
- Normalizing carrier scan checkpoints into unified tracking events.
- Automating order delivery status transitions upon verified courier delivery.

## 2. Core Invariants

- **Strict Provider Neutrality**: Core shipping services, models, controllers, and jobs must never import or depend on concrete carrier drivers or vendor namespaces (`tests/Unit/Shipping/ArchitectureTest.php`). External carriers live strictly in `app/Shipping/Providers/<Provider>` and expose capabilities exclusively via `ShippingProviderManifest`.
- **Subsystem Decoupling**: Zero hidden dependencies between `App\Shipping\Labels`, `App\Shipping\Tracking`, and `App\Shipping\Rates`. Subsystems communicate solely via shared contracts, traits, or DTOs in `App\Shipping\{Auth,Support,Contracts}`.
- **Event Deduplication via SHA-256 Hash**: Tracking checkpoints are uniquely keyed by `(order_id, provider, event_hash)`. Hashes are calculated deterministically:
  $$\text{Hash} = \begin{cases} \text{sha256}(\text{provider} \mid \text{tracking\_number} \mid \text{"id"} \mid \text{external\_id}), & \text{if external\_id exists} \\ \text{sha256}(\text{provider} \mid \text{tracking\_number} \mid \text{status} \mid \text{UTC timestamp} \mid \text{description} \mid \text{location}), & \text{otherwise} \end{cases}$$
  Duplicate scans from webhook replays or overlapping cron polls are dropped silently.
- **Forward-Only Tracking Lifecycle**: Tracking status cannot regress to a lower rank (`TrackingStatus::canReplace`). Terminal statuses (`delivered`, `returned`) are immutable; subsequent updates cannot alter terminal shipments.
- **Automated Delivery Transition**: When a carrier reports `TrackingStatus::Delivered` and `shipping.tracking.auto_deliver` is enabled (default `true`), `ShipmentTrackingService` automatically transitions `shipped` orders to `delivered` via Eloquent `save()`. This triggers `OrderObserver` to dispatch the order delivery email once via `order_emails`.
- **Fault-Tolerant Rate Quoting**: If an external rate provider fails or times out during `RateManager::quoteAll()`, the exception is captured in an isolated error bucket. Remaining quotes are ranked by price, and fallback calculations (`Cart::shippingCostFor`) ensure checkout continuity.
- **Private Label Storage**: Shipping label PDFs are strictly stored on private storage (`local` disk) and never exposed publicly.
- **Label Purchase Concurrency Lock**: Label generation is protected by a distributed cache lock (`ship-order:{$order->id}`) for 120 seconds, preventing duplicate label charges from concurrent admin clicks.
- **Courier Alias Normalization**: Internal courier codes (e.g. `poste_italiane`, `sda`, `gls`) map to provider-specific carrier codes via `CarrierCodeMap`, shielding business logic from vendor nomenclature differences.
- **Manual and Label Dual-Mode**: Orders can ship via automated label API or manually typed tracking numbers (`Shipment::DRIVER_MANUAL`). Manual shipments support external carrier tracking drivers while omitting label purchase fees.

## 3. State Machines & Transitions

### Normalized Tracking Status Hierarchy (`TrackingStatus`)
```
[ Unknown (0) ] ───► [ Info Received (1) ] ───► [ In Transit (2) ] ───► [ Out for Delivery (3) ] ───► [ Delivered (4, Terminal) ]
                                                       ▲
                                                       │ (Can enter/exit)
                                                       ▼
                                               [ Exception (2) ] ──────────────► [ Returned (4, Terminal) ]
```

- Statuses cannot transition backwards on the happy path.
- `Exception` can occur at any transit stage and return to active delivery.
- `Returned` and `Delivered` are terminal; no further status updates are accepted.

### Shipment Model Lifecycle (`Shipment`)
- `status`: `created` -> `cancelled` (on admin undo ship).
- Asynchronous carriers: `awaiting_payment` (requires off-site carrier payment) -> `created` -> `cancelled`.

### Order Fulfillment Transitions
- `processing` -> `shipped`: Invoked via `ShippingService::ship()` (creates `Shipment`, records tracking details, sends shipped email).
- `shipped` -> `delivered`: Triggered automatically by carrier `Delivered` event or manually by admin.
- `shipped` -> `processing`: Admin "undo ship" requires an explicit reason; voids active shipment labels via `CancelsShipments`.

## 4. Key Contracts & Entry Points

- `App\Shipping\ShippingService::ship(Order $order, ShipInput $input, User $actor): ShipOutcome`
  Canonical entry point to fulfill orders manually or with automated carrier labels.
- `App\Shipping\Tracking\ShipmentTrackingService::apply(Order $order, string $provider, TrackingResult $result): void`
  Ingests normalized carrier tracking scans, deduplicates events, advances status, and triggers auto-delivery.
- `App\Shipping\Rates\RateManager::quoteAll(RateRequest $request, ?array $drivers = null): array`
  Queries active rate drivers in parallel or sequence, collecting quotes, balances, and errors.
- `App\Shipping\Tracking\TrackingManager`
  Resolves active tracking drivers and manages pollable capabilities.
- `App\Console\Commands\SyncShipmentTracking` (`tracking:sync`)
  Cron command executing scheduled polling for all active, non-terminal `shipped` orders.
- `App\Shipping\Contracts\ShippingProviderManifest`
  Contract implemented by all external provider modules in `app/Shipping/Providers/`.

## 5. External Boundaries & Failure Modes

- **External Carrier Outages During Label Creation**: Wrapped in `LabelProviderUnavailable` or `LabelRejected`. Label purchase calls occur before database mutations; network failures leave orders untouched in `processing` status.
- **Carrier Clock Drift & Future Events**: Checkpoints with timestamps exceeding 24 hours into the future are dropped as carrier clock errors (`FUTURE_TOLERANCE_HOURS = 24`).
- **Sync Polling Lock Lease**: Orders queued by `tracking:sync` acquire a 90-minute dispatch lease (`DISPATCH_LEASE_MINUTES = 90`) on `tracking_next_sync_at` to prevent worker starvation and duplicate polling jobs.
- **Recipient Address Rejection**: `RecipientAddressCheck` performs pre-flight checks (verifying 5-digit Italian CAPs, non-empty streets, phone numbers, and two-letter province codes), preventing invalid API payload submission to external carrier endpoints.

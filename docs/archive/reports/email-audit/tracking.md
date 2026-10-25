# Category G - Carrier tracking module: discovery log

Date: 2026-10-13. Line numbers refer to the tree after Waves 1-3 and before Category G unless stated.

## Baseline

`vendor/bin/pest` before Category G: 383 passed, 1 skipped (4546 assertions). PHP 8.5 locally, the Docker image is PHP 8.3 (composer `^8.2`): no 8.4-only syntax used.

## Discovery results

1. **How carriers are captured today.** `OrdersController::processShip` (`:425`) validates `box_info.carrier` (free text, max 100, `:447`) and only writes it into `orders.notes` (`buildShippingNotes`, `:557`); it never touches `tracking_number` (the ship form has no number field; the number is added later through `update()` `:140` / the Show page "Add" button, which flips the order to `shipped` and sends the shipped mail). There was no carrier column: `Order::trackingUrl()` (Category C) already read `tracking_carrier` through `getAttribute()` expecting it.
2. **Where `tracking_number` is read/written.** `Admin/OrdersController::update` (`:140-215`), `EmailService::sendOrderShipped` + `OrderEmail::shippedFingerprint` (fingerprint = tracking number only), `SendOrderEmail`/`OrderShipped` (link via `Order::trackingUrl()`), `DashboardController.php:27`, `GuestOrderController.php:43`, `SetupTestTracking.php:32` (dev helper), `resources/js/pages/Admin/Orders/{Edit,Show}.tsx`, `resources/js/types/order.d.ts`. Column added in `2025_07_27_104230_create_orders_table.php:30` (string, unindexed).
3. **`shipped` orders without tracking exist by design**: `processShip` and the status endpoints ship without a number (the UI warns). The sync therefore selects only orders with a non-empty `tracking_number`; the number can be added/corrected later and a correction starts a fresh cycle (Order `updating` hook).
4. **B/E patterns reused, not duplicated.** Delivered mail is produced by `OrderObserver::updated` (status `wasChanged`) -> `EmailService::sendOrderDelivered` -> `order_emails` unique row -> `SendOrderEmail` (`afterCommit`). The service flips `status` with an ordinary model save inside its transaction, so the observer fires with no authenticated user (it never uses `auth()`), the job is dispatched after commit, and a second application (webhook + poll) is a no-op because the order is no longer `shipped` (and `order_emails` would dedupe anyway). Customers never notified (unpaid, no confirmation) get no delivered mail by the observer rule.
5. **HTTP/Manager conventions.** No `Http::` usage and no `Manager` subclass exist in `app/` (`StripeService` uses the Stripe SDK). The module follows Laravel conventions: `Illuminate\Support\Manager`, `Http` facade in the optional `AbstractHttpProvider`, jobs in the style of `SendOrderEmail` (scalar args, `WithoutOverlapping`, `backoff()`, `failed()`, timeout 60 < retry_after 90).
6. **CSRF.** `bootstrap/app.php` had no `validateCsrfTokens`; Cashier's own route has no `web` group. The webhook lives in `routes/web.php` (web group) and is exempted with `validateCsrfTokens(except: ['webhooks/tracking/*'])` (`bootstrap/app.php:48`). Laravel skips CSRF while running unit tests, so the test forces the real check with a subclass (control: a normal POST returns 419).
7. **Scheduler/worker.** B added the scheduler and queue services (`routes/console.php`, `docker-compose.yml`); polling relies on both, `tracking:sync` is scheduled every 15 minutes only when `TrackingManager::canPoll()`.
8. **Admin ship page props**: `showShip` passes `order`, `adminSettings`, `sizes` (`:390`); now also `carriers`. The Edit/Show pages receive `carriers` too. Admin pages are hard-coded English with no i18n (only `useTranslation` in `admin-layout.tsx`); new strings use `t('tracking_admin.*')` (nested block added to all five `lang/*.json`).
9. **Update validation quirk (pre-existing).** `update()` wraps `$request->validate()` in `try/catch (\Exception)`, so a validation failure becomes a flash `error` instead of field errors; the new `tracking_carrier` rule behaves the same (tested through the flash). `processShip` validates outside the try, so it returns field errors.

## Decisions / deviations from the brief

* **Extra column `orders.tracking_started_at`** (not in the brief): the polling window (`max_age_days`) needs a reference time; `created_at` is wrong for back-ordered goods and `updated_at` moves with every sync. Set when the first poll is queued. Also an index on `orders.tracking_number` (webhook lookup) and a `tracking_number` column on `shipment_tracking_events` (so a corrected number does not mix histories).
* `ShipmentTrackingService::apply()` returns `void` as specified; webhook counts matched orders through `ordersForTrackingNumber()`.
* `PassiveProvider` marker interface added (manual/null) so the base `TrackingProvider` contract stays as specified while the manager knows which drivers can poll.
* `fake` driver is registered always but refuses to start in production (`UnknownTrackingDriver`).
* "Polling disabled" for a rejected order is a parked `tracking_next_sync_at` (about 5 years ahead) instead of another column; editing the number/carrier clears it.
* Ship form: with no carriers configured (default) the free-text carrier input is unchanged; with carriers configured a select (plus "other / unknown" and the free-text name) is shown. `box_info.carrier` keeps feeding the notes.
* Webhook requests go through the `web` middleware group (session cookie created); acceptable, noted in the module doc.

## Out of scope / for the owner

1. The concrete carrier adapter, credentials, status mapping, webhook availability and rate limits (see the questions in the final report and `docs/shipping-tracking-module.md`).
2. The shipped email is not re-sent when only the carrier changes (fingerprint = tracking number).
3. Customer-facing pages (dashboard, guest order page) do not show tracking status yet.
4. `estimatedDelivery` returned by an adapter is not persisted.

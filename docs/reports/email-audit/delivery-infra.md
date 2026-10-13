# Category B discovery log: email delivery, reliability and infrastructure

Date: 2026-10-13. Scope: how mail is sent, retried, scheduled and deployed. Read-only discovery first, then fixes.
Status column: FIXED (in Category B), OPEN (owned by another category / needs the user).

## 1. serversideup/php v4.5.1: how to run `queue:work` and `schedule:work` (verified, not assumed)

Sources (fetched 2026-10-13):

- Queue guide: https://serversideup.net/open-source/docker-php/docs/framework-guides/laravel/queue
- Scheduler guide: https://serversideup.net/open-source/docker-php/docs/framework-guides/laravel/task-scheduler
- Automations guide: https://serversideup.net/open-source/docker-php/docs/framework-guides/laravel/automations
- Env reference: https://serversideup.net/open-source/docker-php/docs/reference/environment-variable-specification
- Image source at the pinned tag (`v4.5.1`): `src/common/usr/local/bin/docker-php-serversideup-entrypoint`,
  `src/common/etc/entrypoint.d/50-laravel-automations.sh`, `.../healthcheck-queue`, `.../healthcheck-schedule`
  (https://github.com/serversideup/docker-php/tree/v4.5.1/src/common)

Findings:

1. **Documented approach = separate containers, same image, `command:` override** (`["php", "/var/www/html/artisan", "queue:work", "--tries=3"]`
   / `["php", "/var/www/html/artisan", "schedule:work"]`), `stop_signal: SIGTERM` (graceful stop, "especially for fpm-apache and fpm-nginx"),
   and the image's native healthchecks `healthcheck-queue` / `healthcheck-schedule`.
   An in-container s6 service is the documented alternative for `fpm-nginx` ("Using S6 Overlay" guide); we do not use it because one
   container per role restarts, scales and logs independently. Full artisan path is required in the command.
2. **The healthchecks are `pgrep -f "queue:work"` / `pgrep -f "schedule:work"`**: they only prove the process exists, not that jobs flow.
   Without overriding `healthcheck:` the container inherits the image's HTTP healthcheck, which fails (no nginx in a worker command).
3. **Pitfall confirmed in the entrypoint source:** `docker-php-serversideup-entrypoint` runs every `/etc/entrypoint.d/*.sh` for ANY command
   (it only decides afterwards what to `exec`). `50-laravel-automations.sh` never looks at the command; it is gated purely by
   `AUTORUN_ENABLED` (+ `AUTORUN_LARAVEL_*`). Our Dockerfile sets `AUTORUN_ENABLED=true` and `AUTORUN_LARAVEL_MIGRATION=true`, so
   unmodified worker containers would each run `migrate --force` + `storage:link` at start (3 concurrent migrations on every deploy).
   **Fix:** worker services set `AUTORUN_ENABLED: "false"` (`environment:` beats the image `ENV` and `env_file`).
4. **Our own startup script `docker/entrypoint.d/60-rsp-startup.sh` has the same problem:** it ran `app:clear-all-cache` and
   `generate:sitemap-index` in every container. Because `queue:work --max-time=3600` exits hourly by design and gets restarted, the whole-site
   cache would have been cleared and sitemaps regenerated every hour. **Fix:** the script now exits early when `RSP_STARTUP_TASKS=false`
   (set on the workers).
5. The docs do not mention `PHP_FPM_*` for workers (those only matter for the fpm process, which a command override replaces).
   No `S6_*` setting is needed for the command-override approach (S6 is only started for the default `/init` command).

## 2. Queue configuration and tests

- `config/queue.php`: default `database`, `retry_after` 90s (`DB_QUEUE_RETRY_AFTER`), `after_commit` false at connection level (jobs opt in with `afterCommit()`).
- `0001_01_01_000002_create_jobs_table.php` creates `jobs`, `job_batches`, `failed_jobs`; `failed.driver` is database-backed.
- `.env.example` already says `QUEUE_CONNECTION=database`, but nothing in the deploy ran a worker, and no code queued anything. Mail was sent synchronously.
- `phpunit.xml`: `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `CACHE_STORE=array`. New tests cover queued dispatch (`Queue::fake`), the job's `handle()`,
  and one real database-queue round trip (`queue:work --stop-when-empty`).
- Job timeout is 60s, below `retry_after` 90s (otherwise a slow SMTP send would be handed to a second worker while the first is still sending).

## 3. Every send site

| Site | Type | Before | After |
|---|---|---|---|
| `EmailService::sendOrderConfirmation` (called from `StripeWebhookListener` x2, `CheckoutController` x2, `SendPendingOrderEmails`) | customer | sync, swallowed errors, non-atomic flag | queued job + `order_emails` claim |
| `EmailService::sendOrderShipped` (`Admin/OrdersController::update`, `::processShip`) | customer | sync, no dedupe | queued job, fingerprint status+tracking |
| `EmailService::sendWelcomeEmail` (`PendingUserVerificationController::verify`) | customer | sync | queued `SendWelcomeEmail` job |
| `PendingUserEmailVerification` notification (`RegisteredUserController`, `PendingUserVerificationController::resend`) | customer | sync | `ShouldQueue`, `afterCommit`, backoff |
| Laravel `ResetPassword` / `VerifyEmail` (built-in) | customer | sync | unchanged (framework notifications; OPEN for Category C branding) |
| `ContactController` -> `ContactMessage` | internal | sync | untouched (Category D) |
| `EmailService::testEmailConfiguration/sendSimpleTestEmail/sendCustomEmail`, `email:*` test commands | dev only | sync | unchanged, sync on purpose |

## 4. Settings usage (`Setting`, SettingsSeeder, SettingsController)

| Key | Read anywhere before? | Now |
|---|---|---|
| `email_notifications_enabled` (master) | no (saved from admin UI, never read) | read by `EmailService::isEnabled()` |
| `order_confirmation_enabled` | no | read |
| `order_shipped_enabled` | only in `Admin/OrdersController` | read centrally in `EmailService` (controller asks `isEnabled`) |
| `order_delivered_enabled`, `order_cancelled_enabled` | no | mapped in `EmailService::TYPE_SETTINGS` for Category E |
| `from_email`, `from_name` | no (admin UI field that does nothing) | OPEN: sender comes from `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME` only; the admin fields are misleading |
| `admin_notification_email` | no | OPEN: for Category E |
| `contact_email` (setting) | only prefilled in the ship form; contact form uses `config('mail.contact_email')` (env) | unchanged |
| `newsletter_enabled` | no | out of scope (marketing) |

`SettingsController` needed no change: it already stores the toggles as booleans; `EmailService` reads them with `FILTER_VALIDATE_BOOLEAN` (works for
boolean-typed and string-typed rows) and defaults to enabled when a row is missing or the table is unreadable.

## 5. `auth:cleanup-pending`

Signature `auth:cleanup-pending {--force}`; `handle()` returns void (exit 0). It was scheduled only in the inert `app/Console/Kernel.php`
(`dailyAt('03:00')`), which Laravel 11/12 never loads (no references: `grep -rn Kernel app bootstrap config routes` only hits the test helper's
`Illuminate\Contracts\Console\Kernel`). FIXED: file deleted, command scheduled in `routes/console.php`.

## 6. Stale docs / env names

- `docs/reports/contact-form-setup.md`: `MAIL_CONTACT_EMAIL` does not exist; the config reads `CONTACT_EMAIL`. FIXED.
- `docs/reports/email-implementation-guide.md`: `MAIL_ENCRYPTION` was removed in Laravel 11 (`MAIL_SCHEME`); sender was an SMTP login address. FIXED.
- OPEN: `docs/reports/email-logo-setup.md` still shows `MAIL_ENCRYPTION=tls` and a personal From address (not in B's ownership).
- OPEN: `docs/reports/email-testing-guide.md` / `checkout-fixes-implementation.md` reference `test:checkout-email`, which no longer exists (see section 8).

## 7. Queued mailable pitfalls

- The mailables hold an `Order`/`User` model and use `SerializesModels`. Queuing a mailable would store only the model id and re-fetch it, failing with
  `ModelNotFoundException` if the order/user was deleted, and `content()` lazily loads `orderItems.product`, which is null for deleted products.
- Design choice: the queued unit is `SendOrderEmail(orderId, type, fingerprint, ?tracking)` with scalar args only. The job loads the order with its
  relations when it runs, builds the mailable and sends it synchronously (mailables are NOT `ShouldQueue`). A deleted order is skipped, a missing
  user falls back to `guest_email`, a deleted product (FK `set null`) still renders (covered by a test).
- Locale: `Mail::to()->locale()` is applied from `orders.locale` (if present and one of it/en/de/es/fr), else `config('app.locale')`.
- Order of events in the job: send, then `sent_at`. See the runbook for the one residual duplicate window.

## 8. Other discrepancies found

1. `Admin/OrdersController::update()` re-sent the shipped mail on every save that touched the tracking number, passed `null` tracking when the order
   already had a number, and could mail "shipped" for orders that were already delivered. Now: only for orders in `shipped` status, once per tracking number.
2. `SendPendingOrderEmails` used `$this->confirm()` (hangs/fails when scheduled) and sent synchronously. Now `--force`, `--dry-run`, `--retry-failed`.
3. `test:checkout-email` (`TestCheckoutEmail.php`) overwrote the customer email of a REAL order (the latest one), reset its sent flag and mailed it. With queued,
   idempotent mail it would mail the real customer after restoring the address. REMOVED (it also referenced behavior that no longer exists). `email:test`,
   `email:send-test`, `email:test-order-confirmation`, `auth:test-verification` call mail directly and still work.
4. `mail.failover` ended in `log`, i.e. a failing SMTP would silently "succeed" by writing to the log. Removed.
5. `HandleInertiaRequests::share()` does not share flash messages and no Admin page reads `flash`, so the `success`/`error` session flashes of the admin
   controllers are never displayed (pages toast client-side). The new `warning` flash is set but will only be visible once flash sharing is added. OPEN (frontend, outside B).
6. `MailServiceProvider` adds `List-Unsubscribe: <APP_URL/unsubscribe>` and `X-Brand-*` headers to every message (bogus unsubscribe URL). OPEN: Category D.
7. `deploy.php` (legacy Deployer) restarts only `laravel.service`; it has no queue/schedule handling. The Docker/Dokploy path is the one in use. OPEN/FYI.
8. `Dockerfile` keeps config caching off because `StripeService` reads `env()` directly; irrelevant to workers but means each worker boot parses `.env` (fine).
9. Cache locks (`WithoutOverlapping` in the job and in the scheduler) need a lock-capable cache: `CACHE_STORE=database` (as in `.env.example`) works; `file` works for
   a single container only; do not use `array` in production.
10. Dokploy: three services build the same Dockerfile (cached layers, but three build steps); acceptable.

## 9. Follow-ups after orchestrator review (wave 1)

1. `orders:send-pending-emails` already returned early (info line) when the confirmation type is disabled; it now also warns on backlogs above 25 orders. Re-enable
   behavior (7-day window) is documented in the runbook.
2. `bulkUpdateStatus` and `updateStatus` now queue the shipped email through `EmailService::sendOrderShipped` (fingerprint without tracking when the order has none,
   only when the order was not already shipped, toggles respected, failures collected into one `warning` flash).
   **Pre-existing bug, OPEN (routes/admin.php, not B's file):** `PATCH admin/orders/bulk-status` is registered after `Route::resource('orders')`, so it resolves to
   `orders/{order}` with `order = "bulk-status"` and returns 404; the bulk endpoint is unreachable (and no frontend code calls it). Move the bulk route above the
   resource route to enable it. Tests call the controller method directly.
3. New `ops:check-queue` command (scheduled every 5 minutes) with thresholds in `config('mail.queue_alerts')`.

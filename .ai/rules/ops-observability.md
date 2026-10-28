---
paths:
  - app/Services/Ops/*.php
  - app/Console/Commands/*.php
  - config/ops.php
  - routes/console.php
---
# Ops and observability

Scheduled health checks, Telegram alerts, the health endpoint, audit and abuse signals, Sentry and logging. It does not cover business logic of orders or mail (see the orders and email rules).

## Entry points
- `config/ops.php`: every threshold and toggle (env-backed). Read it before changing any check.
- `routes/console.php`: the schedule. A new check must be registered here.
- `app/Services/Ops/InvariantChecker.php`, `app/Services/Ops/StripeReconciler.php`: the checks behind the commands.
- `app/Services/Ops/TelegramNotifier.php`: the only alert sender.
- `app/Http/Controllers/HealthController.php`: `GET /api/health`.
- `resources/js/lib/sentry.ts`, `config/sentry.php`, `app/Support/SentryEventFilter.php`: error tracking.

## Rules
- MUST send alerts through `TelegramNotifier::sendOnce()` with a stable key and window; a bare `send()` floods the chat on repeating failures. Unenforced.
- MUST NOT put a bot token, request URL or full exception message in a log or alert (a connection error message holds the token). `TelegramNotifierTest` (token not logged); the rest unenforced.
- MUST report route patterns, never real URLs, in exception alerts (paths carry tokens). Unenforced.
- MUST keep ops code non-fatal: a down cache, DB or Telegram must never break a request or the scheduler. Unenforced (`FailureModesTest` covers only a Stripe outage at payment).
- MUST NOT collect cookies, headers, bodies or query strings in Sentry (frontend `dataCollection` is all off). Unenforced.
- MUST start Sentry replay only after the `sentry_replay` cookie consent; it records errors only, fully masked. `SentryFrontendConfigTest` (consent gate).
- MUST keep `/api/health` free of details: only `ok`/`fail` per check. `HealthEndpointTest` (exact JSON on the healthy path, 503 outcomes).
- NEVER let `AbuseMonitor` block anything; blocking belongs to rate limiters, Cloudflare and Stripe Radar. Unenforced.

## How it works
Each `ops:*` command computes problems, logs them (`Log::error`/`critical`), pushes Telegram once per window, and stores `ok`/`problem` + time in cache (read by Settings > System via `app/Settings/SystemInfo.php` and by the digest).

| Command | Frequency | Alert means |
|---|---|---|
| `ops:check-queue` | 5 min | A runnable job waited longer than `mail.queue_alerts.max_job_age_minutes` (worker down), the `jobs` table is missing, or `failed_jobs` exceeds the threshold. Skipped on `sync`/`null` queues. Exit 1 on problem; Telegram reminder hourly |
| `ops:heartbeat` | 5 min | Pings `OPS_HEARTBEAT_URL` (dead-man's switch). Silent no-op when unset; failures are not reported on purpose, the external service alerts on missing pings |
| `ops:check-invariants` | 15 min | Silent failures, one message per key, repeated every `alert_window_minutes`: `payment-pending` (unpaid orders past the limit, expiry webhook not arriving), `paid-waiting` (paid, not shipped for days), `failed-emails`, `webhook-silence` (paid order but no Stripe webhook seen: endpoint/secret/firewall), `payments-without-orders` (many `payment_started`, zero paid orders: checkout broken), `no-orders` (off by default), `funnel-*` (step conversion collapsed vs the previous week). Always exits 0 (already alerted) |
| `ops:reconcile-stripe` | daily 07:00 | Compares Stripe sessions of the last `--hours` (48) with orders: paid session without order, paid at Stripe but not marked paid, marked paid but not paid at Stripe, amount mismatch, or Stripe unreachable/no key. Exit 1; reminder daily |
| `ops:daily-digest` | daily 08:00 | Yesterday's orders, funnel, abandoned checkouts, failed emails/jobs, last check results. A missing digest means the scheduler is down |
| `stripe:check-config` | manual | Prints (masked) Stripe key sources: env, DB settings, `StripeService`, config-cache state. Debug tool, not scheduled |

Other ops-relevant schedule: `model:prune` daily (`CheckoutSnapshot`, `OpsEvent` 90 days, `AuditLog` 1 year), `queue:prune-failed --hours=168` daily, `orders:send-pending-emails --force` every 10 min.

Data sources:
- `FunnelTracker` writes `ops_events` (add to cart, checkout started, payment started, Stripe webhook received) from the cart/checkout controllers and `StripeWebhookListener`. Visitor counts, not orders.
- `AuditLogger` + `AdminAuditObserver` write `audit_logs` for admin changes to Product, Category, Page, Review, User (secret fields are dropped, values truncated). Admin sign-in from a new address also goes to Telegram (`RecordAdminLogin`).
- `AbuseMonitor` counts signals per window via `RateLimiter` (thresholds in `config/ops.php` `abuse`): failed logins per IP and per admin account, rate-limit hits (`MonitorAbuse` middleware), failed payments, rejected webhooks. IPs are masked in alerts.

`GET /api/health` (503 if any fails, logs a warning): `database` (select 1), `cache` (round trip), `storage` (`storage/framework` writable), `queue` (last `ops:check-queue` verdict), `scheduler` (that verdict is younger than `ops.health.scheduler_max_age_minutes`), `stripe` (secret key configured). No cached queue result yet counts as ok. `/up` is only the framework "app boots" check.

Sentry: backend via `sentry/sentry-laravel` (DSN env) with the `sentry` log channel turning `Log::error()` and above into issues. `SentryEventFilter` sends each distinct failure once per 10 min to protect the quota. Frontend `initSentry()` tags visitors `admin`/`customer`/`guest` by id only, and wires the Inertia `exception`/`invalid` hooks, an error boundary and the feedback form on the 500 page. `AssignRequestId` puts a request id in logs, Sentry tags, the `X-Request-Id` header and the 500 page. Source maps upload during the Docker build when the auth token env vars are set; the release name is shared through `public/build/release.txt`.

## Traps
- `ops:check-invariants` returns SUCCESS even with problems: it already alerted; a failing exit would double-report as a scheduler exception.
- `webhook-silence` ignores orders created before the first `ops_events` row; a fresh or wiped table never alerts.
- `payment_pending_hours` must exceed your slowest payment method (bank transfers), or it alerts on normal orders.
- Abuse `payment_failed` only fires if `payment_intent.payment_failed` is enabled on the Stripe webhook endpoint.
- Telegram and Sentry are both optional: no credentials means silent skip, not an error.
- Negative stock is NOT an invariant: overselling is allowed by design.
- `/api/health` `scheduler` fails only when the queue check has run before and then stopped; use the heartbeat to catch a never-started scheduler.

## Operate
- Run a check now: `php artisan ops:check-invariants`, `ops:check-queue`, `ops:reconcile-stripe --hours=48`, `ops:daily-digest`.
- Env: `OPS_*` thresholds (see `config/ops.php`), `OPS_HEARTBEAT_URL`, `OPS_TELEGRAM_EXCEPTION_ALERTS`, `OPS_TELEGRAM_EXCEPTION_THROTTLE_MINUTES`, Telegram token/chat id in `config/services.php`, `SENTRY_LARAVEL_DSN`, `SENTRY_FRONTEND_DSN`, `SENTRY_ENVIRONMENT`, `SENTRY_TRACES_SAMPLE_RATE`.
- Logs: channel is `LOG_STACK` (comma list of `config/logging.php` channels, e.g. `stderr,daily,sentry`); read container logs in Dokploy. Alerts are prefixed with `[APP_ENV]`.
- Point an external uptime monitor at `/api/health` and a dead-man's-switch at the heartbeat URL.

## Tests
- `tests/Feature/Ops/`: one file per component (`InvariantCheckerTest`, `CheckInvariantsCommandTest`, `StripeReconciliationTest`, `TelegramNotifierTest`, `AbuseMonitorTest`, `AuditLogTest`, `FunnelTrackerTest`, `DailyDigestTest`, `HealthEndpointTest`, `SchedulerHeartbeatTest`, `SentryEventFilterTest`, `SentryFrontendConfigTest`, `RequestIdTest`, `FailureModesTest`).

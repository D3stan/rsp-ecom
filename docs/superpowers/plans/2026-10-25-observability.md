# Observability Implementation Plan

Goal: learn about prod bugs (customer and admin side) without anyone reporting them, and read all logs in the Dokploy UI instead of ssh + `tail laravel.log`.

Status (2026-10-25): phases 1 to 5 are implemented on branch `worktree-observability` and covered by tests (full suite green). Items marked **not done** are explained at the end.

## Decisions

| Topic | Decision |
|---|---|
| Error tracking | Sentry SaaS, free Developer plan (see constraints below) |
| Alerts / daily digest | Telegram bot, sent by our own Laravel notifier (not by Sentry) |
| Session replay | Errors only, masked, started only after the visitor accepts the `sentry_replay` cookie |
| Web analytics | Cloudflare Web Analytics, switched on in the Cloudflare dashboard (no code) |
| Logs | JSON to stderr, read in the Dokploy UI. `laravel.log` stays as a 7-day fallback |
| Funnel / business metrics | Own database (`ops_events`), server-side, independent of any analytics tool |
| Deploys | Never to prod unless explicitly asked. Everything is verified on sandbox first |

## Sentry free-tier constraints (checked on sentry.io/pricing, 2026-10-25)

- 5k errors/month, 1 user, 30-day retention, 50 replays, 1 cron monitor, 1 uptime monitor, user feedback included.
- Alerts are email only. Third-party integrations and the API are not included, so Sentry cannot post to Telegram. The Team plan ($29/month) adds both.
- No spend cap, so events over quota are dropped. The app protects the quota: each distinct failure is sent once per 10 minutes (`App\Support\SentryEventFilter`).
- The Sentry MCP may depend on API access: verify it works on the free plan.

## Phase 0: setup (user)

- [x] Telegram bot, Sentry project, DSN and environment per stack.
- [x] Confirmed that web logs reach the Dokploy UI on sandbox.

## Phase 1: crash visibility and logs in the Dokploy UI

- [x] `sentry/sentry-laravel` (already on `sandbox`) and `@sentry/react`, `@sentry/vite-plugin`.
- [x] Request id: `AssignRequestId` middleware (log context, Sentry tags, `X-Request-Id` header), shown on `Errors/ServerError`.
- [x] `sentry` log channel (level `error`): existing `Log::error/critical` calls become Sentry issues. Add it to `LOG_STACK`.
- [x] Browser SDK: root `ErrorBoundary`, Inertia `exception`/`invalid` hooks, tags for admin/customer/guest, no cookies/headers/bodies/query strings collected.
- [x] Replay on errors only, masked, gated by consent (`CookiesServiceProvider`, `HandleInertiaRequests`).
- [x] Source maps: uploaded at Docker build when `SENTRY_AUTH_TOKEN`, `SENTRY_ORG`, `SENTRY_PROJECT` are set; the release name is shared with the server through `public/build/release.txt`.
- [x] Telegram alert for uncaught exceptions, once per distinct error per 15 minutes.
- [x] Tests: request id, Telegram notifier, Sentry filter, shared frontend config.

## Phase 2: alerts that reach you

- [x] `TelegramNotifier`; `ops:check-queue` uses it.
- [x] `ops:heartbeat` (every 5 min) pings `OPS_HEARTBEAT_URL`.
- [x] `GET /api/health`: database, cache, storage, queue verdict, scheduler pulse, Stripe key. 503 on any failure.
- [ ] **User:** external uptime monitor on `/api/health`, and a healthchecks.io (or similar) check for `OPS_HEARTBEAT_URL` with a 15 minute grace.

## Phase 3: silent failures

- [x] `ops_events` table, `FunnelTracker`: add to cart, checkout started, payment started, Stripe webhook received.
- [x] `ops:check-invariants` (every 15 min): awaiting payment too long, paid orders waiting to ship, failed order emails, webhook silence, many payments but no orders, funnel step collapse, optional "no orders for N hours". Thresholds in `config/ops.php`.
- [x] Admin status: Settings → System shows the invariant result, the last Stripe webhook, whether Sentry/Telegram/heartbeat are configured, reconciliation, recent admin activity.
- [ ] **Not done:** Laravel Pulse (see below).
- [ ] **User:** Cloudflare Web Analytics, enabled in the Cloudflare dashboard for the production hostname.

## Phase 4: admin audit and abuse

- [x] `audit_logs` table; `AdminAuditObserver` on Product, Category, Page, Review, User; admin sign-ins; Telegram on a sign-in from a new address.
- [x] `AbuseMonitor`: failed logins per address and against admin accounts, rate-limit hits, failed-payment bursts (needs `payment_intent.payment_failed` enabled on the Stripe webhook endpoint), rejected webhooks.

## Phase 5: money and polish

- [x] `ops:reconcile-stripe` (daily 07:00): Stripe sessions of the last 48h against orders.
- [x] `ops:daily-digest` (daily 08:00) on Telegram.
- [x] Sentry feedback form on the 500 page, tagged with the request id.
- [x] Failure-mode test: Stripe outage at payment. (Carrier outages never reach a customer; carrier driver failures are covered by the Shipping tests.)
- [x] `.github/workflows/security.yml`: `composer audit` blocks, `npm audit` reports without blocking.
- [ ] **Not done:** synthetic Playwright checkout on sandbox (see below).

## Not done, and why

- **Laravel Pulse:** it duplicates what Sentry tracing already gives (slow requests, queries, jobs), adds migrations, a dashboard route and a database write per request on the shared database. Revisit if Sentry's free quota proves too small.
- **Playwright synthetic checkout:** it needs the sandbox URL, a seeded product and a Stripe test card flow that has to be run against the real sandbox to be trusted. An unverified script would raise false alarms. Do it as a follow-up with those at hand.
- **Negative-stock invariant:** the shop allows oversold orders by design (`has_oversold_items`), so it would alert on normal operation.

## Open items

- Decide after a few weeks whether the free Sentry limits are enough or the Team plan (Telegram integration, 90-day retention) is worth it.
- `npm audit --omit=dev` reports 27 findings (3 critical, 19 high), mostly through the `shadcn` CLI, which is listed under `dependencies`, plus `vite` and `axios`. Not touched here: changing dependencies needs a decision.
- The consent banner re-appears once for returning visitors, because a new optional cookie (`sentry_replay`) was added. The privacy page should mention it.

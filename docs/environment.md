# Environment variables

Files at the repo root:

| File | Purpose |
|---|---|
| `.env.example` | Every variable with local defaults. Copy to `.env` for local work. |
| `.env.production.example` | Template for the production stack (`docker-compose.yml`). |
| `.env.sandbox.example` | Template for the sandbox stack (`docker-compose.sandbox.yml`). |

The two stack templates are pasted into Dokploy's Environment tab (Dokploy writes them to the `.env` the compose file reads). They list only what that stack sets; everything else keeps its default from `config/*.php`. Real values live only in Dokploy, never in git. `.env` and `.env.production` are gitignored.

Legend: **S** = secret. **B** = read at Docker build time (needs a redeploy), all others are read at runtime (restart the stack).

## Application

| Variable | What it does | Options / default | Production | Sandbox |
|---|---|---|---|---|
| `APP_ENV` | Environment name. `local` and `development` show framework debug pages when `APP_DEBUG=true`; anything else shows the custom error pages. | `local`, `development`, `production` | `production` | `development` |
| `APP_KEY` (S) | Encrypts sessions, cookies and stored data. Never regenerate in production. | `php artisan key:generate --show` | copy from the old prod `.env` | its own |
| `APP_DEBUG` | Debug pages leak config and env values. | `true`, `false` | `false` | `true` |
| `APP_URL` | Public URL; its scheme decides whether links are forced to https. | URL | `https://rsp-industries.com` | `https://test.rsp-industries.com` |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` (S) | Used by `db:seed --force` to create the admin, production only. No admin is created without both. Remove after the first seed. | | unset after seeding | unset |

Stock Laravel settings, left at the `.env.example` values on both stacks: `APP_NAME`, `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE`, `APP_MAINTENANCE_DRIVER`, `APP_MAINTENANCE_STORE`, `BCRYPT_ROUNDS`, `PHP_CLI_SERVER_WORKERS` (local `artisan serve` only), `LOG_CHANNEL`, `LOG_DEPRECATIONS_CHANNEL`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_PATH`, `SESSION_DOMAIN`, `CACHE_PREFIX`, `BROADCAST_CONNECTION`, `FILESYSTEM_DISK`, `MEMCACHED_HOST`, `ADMIN_NAME`, `AWS_*` (only with an s3 disk or the ses mailer), `VITE_APP_NAME`.

## Logging

| Variable | What it does | Options / default | Production | Sandbox |
|---|---|---|---|---|
| `LOG_STACK` | Channels used together. `stderr` is readable in the Dokploy UI, `daily` is a 7-day file fallback, `sentry` turns `Log::error()` and above into Sentry issues. Without `sentry`, only uncaught exceptions reach Sentry. | any of `single`, `daily`, `stderr`, `sentry`; default `daily` | `stderr,daily,sentry` | `stderr,daily,sentry` |
| `LOG_STDERR_FORMATTER` | Output format of the `stderr` channel. | `Monolog\Formatter\JsonFormatter` or empty | JSON | JSON |
| `LOG_LEVEL` | Lowest level written. | `debug`, `info`, `notice`, `warning`, `error`, `critical` | `warning` | `info` |

## Database, session, cache, queue

| Variable | What it does | Options / default | Production | Sandbox |
|---|---|---|---|---|
| `DB_*` (`DB_PASSWORD` is S) | Connection. | `DB_CONNECTION`: `sqlite`, `mysql`, `mariadb` | MySQL container of the Dokploy project | MariaDB service `db`; values must match `docker-compose.sandbox.yml` |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | Where sessions, cache and jobs live. `app`, `queue` and `scheduler` must share them. The sandbox uses `database` like production, so it exercises the same code paths. | `database`, `redis`, `file`, `array` (queue: `database`, `redis`, `sync`) | `database` | `database` |
| `REDIS_CLIENT`, `REDIS_*` | Not used by either stack. The Docker image has no `redis` PHP extension and predis is not installed, so a `redis` driver fails every request with `Class "Redis" not found`. To adopt Redis, add `redis` to `install-php-extensions` in the Dockerfile and test it on the sandbox first. | `phpredis`, `predis` | not used | not used |

## Mail

| Variable | What it does | Options / default | Production | Sandbox |
|---|---|---|---|---|
| `MAIL_MAILER` | Transport. `log` and `array` do not send; the app warns at boot if production uses them. | `log`, `smtp`, `array`, `ses`, `postmark`, `resend` | `smtp` | `smtp` |
| `MAIL_SCHEME` | TLS mode (replaces the removed `MAIL_ENCRYPTION`). | `null`, `smtp` (STARTTLS, port 587), `smtps` (port 465) | `smtp` | unset |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` (S) | SMTP server. | | Brevo values | `mailpit`, `1025` (Mailpit catches all mail) |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Sender of automated mail, with no Reply-To. Use a domain with SPF/DKIM/DMARC. | | no-reply address | `sandbox@rsp-industries.com` |
| `MAIL_SUPPORT_ADDRESS` | Monitored inbox shown in every email. | | `support@rsp-industries.com` | same |
| `CONTACT_EMAIL` | Recipient of contact-form messages. | default `MAIL_SUPPORT_ADDRESS` | unset | unset |

Email operations: `docs/reports/email-operations.md`.

## Shipping

| Variable | What it does | Options / default |
|---|---|---|
| `SHIPPING_RATES_DRIVER` | Source of shipping quotes. | `fake`, `spediamo`, `dvaexpress` |
| `SHIPPING_TRACKING_DRIVER` | Default carrier tracking. `manual` uses tracking links without an API; `null` disables tracking. | `manual`, `null`, `spediamo`, `dvaexpress` |
| `SHIPPING_TRACKING_AUTO_DELIVER` | Mark the order delivered and send the delivered email when the carrier reports Delivered. | `true`, `false` |
| `SHIPPING_TRACKING_POLL_MINUTES`, `_SLOW_AFTER_DAYS`, `_SLOW_POLL_MINUTES`, `_MAX_AGE_DAYS` | Polling cadence; only for drivers that call a carrier. | `120`, `7`, `720`, `45` |
| `SHIPPING_LABELS_ENABLED` | Buy labels through the carrier API instead of typing a tracking number. Usually switched from the admin settings. | `true`, `false` (default `false`) |
| `SHIPPING_LABELS_DRIVER` | Label adapter. It reuses the Spediamo credentials. | `spediamo` |
| `SHIPPING_DEFAULT_PARCEL_WEIGHT_KG`, `SHIPPING_SENDER_*` | Parcel weight and the sender address printed on labels (falls back to the store identity). | default `1.0` |
| `SHIPPING_DELIVERY_DAYS_MIN`, `_MAX` | Delivery estimate shown to customers. | `3`, `5` |
| `SHIPPING_TRACKING_SPEDIAMO_URL`, `_TOKEN` (S), `_TIMEOUT` | Spediamo API, used for rates, tracking and labels. | `https://api.spediamo.it`, `10` |
| `DVAEXPRESS_BASE_URL`, `_API_KEY` (S), `_DEFAULT_VETTORE`, `_TIMEOUT` | DVA Express (Easyparcel) API. Only needed when it is the rates driver or an order is tracked with it. | `https://api.easyparcel.it`, `SDAM`, `10` |

Module details: `docs/shipping-tracking-module.md`. Production leaves labels off and tracking on `manual`.

## Stripe

| Variable | What it does | Production | Sandbox |
|---|---|---|---|
| `STRIPE_KEY`, `STRIPE_SECRET` (S), `STRIPE_WEBHOOK_SECRET` (S) | API keys and the webhook signing secret (`whsec_...`, required). | live keys | test keys only |
| `STRIPE_WEBHOOK_TOLERANCE` | Seconds of clock skew accepted on webhooks (default `300`). | | |
| `CASHIER_CURRENCY`, `CASHIER_CURRENCY_LOCALE`, `CASHIER_LOGGER` | Currency and locale, and the log channel for Stripe errors. | `eur`, `it_IT`, `stack` | same |
| `STRIPE_TAX_AUTOMATIC` | Automatic tax calculation (`true`, `false`). | unset | unset |

The webhook endpoint must have `payment_intent.payment_failed` enabled, or the failed-payment abuse signal never fires. More: `docs/stripe.md`.

## Google sign-in

`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` (S), `GOOGLE_REDIRECT_URL` (`<APP_URL>/auth/google/callback`, as a literal value in Dokploy).

## Sentry

One project (`php-laravel`, org `rsp-ik`, EU region), one DSN per stack. Plan: `docs/superpowers/plans/2026-10-25-observability.md`.

| Variable | What it does | Options / default | Production | Sandbox |
|---|---|---|---|---|
| `SENTRY_LARAVEL_DSN` | Where events go. Empty turns Sentry off. The browser SDK gets the same DSN. | DSN named Production / Sandbox in Project Settings > Client Keys | Production key | Sandbox key |
| `SENTRY_ENVIRONMENT` | Tag that tells the stacks apart. Without it, `APP_ENV` is used. | `production`, `sandbox` | `production` | `sandbox` |
| `SENTRY_TRACES_SAMPLE_RATE` | Share of requests traced. Keep it low on the free plan. | `0.0` to `1.0` | `0.1` | `1.0` |
| `SENTRY_FRONTEND_DSN` | Separate DSN for the browser. | default `SENTRY_LARAVEL_DSN` | unset | unset |
| `SENTRY_RELEASE` | Release name. | default: written by the asset build (`public/build/release.txt`) | unset | unset |
| `SENTRY_AUTH_TOKEN` (S, B) | Uploads browser source maps during the Docker build. Skipped when empty. Create it under Settings > Developer Settings > Internal Integration with scope Release: Admin. | | set | set |
| `SENTRY_ORG`, `SENTRY_PROJECT` (B) | Slugs used by the upload. | | `rsp-ik`, `php-laravel` | same |
| `SENTRY_URL` (B) | Sentry host for the upload; required for the EU region. | | `https://de.sentry.io` | same |

The free plan allows 5k errors a month. Each distinct failure is sent once per 10 minutes, using the cache; if the cache is down every event is sent.

## Operational alerts

| Variable | What it does | Options / default |
|---|---|---|
| `TELEGRAM_BOT_TOKEN` (S), `TELEGRAM_CHAT_ID` | Bot (from BotFather) and chat that receive alerts and the daily digest. Empty disables Telegram. A separate chat per stack is advisable. | |
| `OPS_HEARTBEAT_URL` | Ping URL called by the scheduler every 5 minutes. Create a check on healthchecks.io (period 5 min, grace 15 min), one per stack. The service alerts when pings stop. Empty disables it. | URL |
| `OPS_TELEGRAM_EXCEPTION_ALERTS`, `_THROTTLE_MINUTES` | Telegram message for uncaught exceptions, once per distinct error per window. | `true`, `15` |
| `OPS_HEALTH_SCHEDULER_MAX_AGE_MINUTES` | `/api/health` reports the scheduler dead after this long without a queue-check result. | `15` |
| `OPS_INVARIANTS_ALERT_WINDOW_MINUTES` | A standing problem is announced once per window. | `360` |
| `OPS_PAYMENT_PENDING_HOURS` | Orders awaiting payment this long count as stuck. Raise it for slow bank transfers. | `72` |
| `OPS_PAID_WAITING_DAYS` | Paid orders unshipped this long are flagged. | `3` |
| `OPS_FAILED_EMAIL_HOURS` | Failed order emails older than this are flagged. | `24` |
| `OPS_WEBHOOK_GRACE_MINUTES` | Minutes of Stripe webhook silence tolerated. | `15` |
| `OPS_PAYMENTS_WITHOUT_ORDERS` | Visitors sent to Stripe in 24h with zero paid orders before it counts as broken. `0` turns the check off. | `10` |
| `OPS_NO_ORDERS_HOURS` | Alert when no paid order arrives for this long. `0` turns it off. | `0` |
| `OPS_FUNNEL_MIN_BASELINE`, `_MIN_RECENT`, `_DROP_RATIO` | Funnel-collapse check: minimum samples and the share of the weekly rate below which it alerts. | `20`, `10`, `0.4` |
| `QUEUE_ALERT_MAX_JOB_AGE_MINUTES`, `_FAILED_JOBS_THRESHOLD` | Queue health check limits. | `10`, `10` |

External monitors to set up by hand: an uptime check on `GET /api/health` (503 on any failure) and the healthchecks.io check above.

## Container

These are set by the `Dockerfile` and the compose files, not in the stack environment. Do not repeat them in the Dokploy Environment tab.

| Variable | Set by | Meaning |
|---|---|---|
| `AUTORUN_ENABLED`, `AUTORUN_LARAVEL_*` | `Dockerfile` | Startup automations (storage link, migrations). Only `app` runs them; `queue` and `scheduler` override `AUTORUN_ENABLED=false` in compose. |
| `RSP_STARTUP_TASKS` | `docker/entrypoint.d/60-rsp-startup.sh` | Default `true`: clear caches and rebuild sitemaps at startup. `queue` and `scheduler` set `false` in compose. |

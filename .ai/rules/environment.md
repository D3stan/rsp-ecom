---
paths:
  - .env.example
  - .env.production.example
  - .env.sandbox.example
---
# Environment

Env vars by concern and where each is read. Not covered: values (live only in the Dokploy Environment tab), settings edited in the admin UI (`.ai/rules/settings.md`).

## Entry points
- `.env.example`: every var with local defaults; copy to `.env`.
- `.env.production.example`: production stack template.
- `.env.sandbox.example`: sandbox stack template.
- `config/*.php`: the only readers of `env()`; the defaults live there.
- `config/settings.php`: DB-backed admin settings that replaced many keys.

## Rules
- NEVER commit real values; `.env` is gitignored (unenforced).
- NEVER call `env()` outside `config/`: config cache is off today but must stay possible (unenforced).
- MUST keep `APP_KEY` stable in production; regenerating invalidates sessions and encrypted data (unenforced).
- MUST use test Stripe keys on sandbox (unenforced).
- Templates list only what a stack sets; anything absent keeps its `config/` default.

## How it works
Runtime vars need a restart or redeploy. Build-time vars (marked B) are baked into the image and need a rebuild.

| Concern | Vars | Read in |
|---|---|---|
| App, locale | `APP_*`, `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | `config/app.php` |
| Logging | `LOG_STACK`, `LOG_STDERR_FORMATTER`, `LOG_LEVEL` | `config/logging.php` |
| DB, session, cache, queue | `DB_*`, `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | `config/database.php` and siblings |
| Mail | `MAIL_*`, `MAIL_SUPPORT_ADDRESS`, `CONTACT_EMAIL`, `QUEUE_ALERT_MAX_JOB_AGE_MINUTES`, `QUEUE_ALERT_FAILED_JOBS_THRESHOLD` | `config/mail.php` |
| Stripe | `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_WEBHOOK_TOLERANCE`, `CASHIER_*` | `config/cashier.php` |
| Google OAuth | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URL` | `config/services.php` |
| Analytics | `GOOGLE_ANALYTICS_ID`, `GOOGLE_ANALYTICS_ANONYMIZE_IP` | `config/cookieconsent.php` |
| Shipping | `SHIPPING_*`, `SPEDIAMO_PURCHASE_URL`, `DVAEXPRESS_*` | `config/shipping.php` |
| Sentry | `SENTRY_LARAVEL_DSN`, `SENTRY_ENVIRONMENT`, `SENTRY_TRACES_SAMPLE_RATE`, `SENTRY_FRONTEND_DSN`, `SENTRY_RELEASE` | `config/sentry.php` |
| Sentry build (B) | `SENTRY_AUTH_TOKEN`, `SENTRY_ORG`, `SENTRY_PROJECT`, `SENTRY_URL`: source-map upload; skipped when the token is empty | `vite.config.ts`, `Dockerfile` args |
| Telegram | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`: empty disables alerts | `config/services.php` |
| Ops | `OPS_*` thresholds, `OPS_HEARTBEAT_URL` | `config/ops.php` |
| Settings cache | `SETTINGS_CACHE_STORE`, `SETTINGS_RECHECK_SECONDS` | `config/settings.php` |
| Container | `AUTORUN_*`, `RSP_STARTUP_TASKS`, `PHP_*`: set by `Dockerfile` and compose; do not repeat in Dokploy | `Dockerfile`, `docker-compose.yml` |

## Traps
- `LOG_STACK` defaults to `single` in `config/logging.php`; the stack templates set `stderr,daily,sentry`. Without `sentry` only uncaught exceptions reach Sentry.
- `MAIL_ENCRYPTION` is unused; TLS mode is `MAIL_SCHEME`.
- `MAIL_MAILER=log` or `array` sends nothing; the app warns at boot in production.
- `SHIPPING_RATES_DRIVER` accepts `fake`, `spediamo`, `dvaexpress`; `dvaexpress` is also a label driver, so `DVAEXPRESS_*` matters beyond rates.
- Unlisted in templates but read: `DVAEXPRESS_SENDER_TAX_CODE`, `DVAEXPRESS_PICKUP_ENABLED`, `DVAEXPRESS_DEFAULT_COSA_SPEDIRE`, `SPEDIAMO_PURCHASE_URL`, `SETTINGS_CACHE_STORE`, `SETTINGS_RECHECK_SECONDS`, `QUEUE_ALERT_FAILED_JOBS_THRESHOLD`, `GOOGLE_ANALYTICS_ID`.
- Many former keys (shipping toggles, store identity, similar) now live in DB-backed admin settings; env is only the default when no row exists. Check `config/settings.php` before adding an env var.
- Write `GOOGLE_REDIRECT_URL` as a full URL in stack templates; only `.env.example` uses `${APP_URL}`.
- `ADMIN_EMAIL` and `ADMIN_PASSWORD` (`config/app.php`) seed the production admin in `UserSeeder`; without both, no admin is created.
- No Redis vars: the image lacks the extension.

## Operate
- Change a var: edit the template, then the Dokploy Environment tab, then redeploy (`docs/runbooks.md`).
- Generate a key: `php artisan key:generate --show`.
- Inspect a config value: `php artisan config:show <file.key>`.

## Tests
- `tests/Feature/Settings/ConfigBridgeTest.php`: settings override config.
- `tests/Feature/Ops/SentryFrontendConfigTest.php`: Sentry DSN, environment and release are shared with pages.
- No test checks the templates themselves.

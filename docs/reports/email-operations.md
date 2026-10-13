# Email operations runbook

How transactional email (order confirmation, shipped, welcome, account verification) is delivered, and what to do when it isn't.

## 1. How it works

```
checkout / admin action
   -> EmailService::sendOrderConfirmation|sendOrderShipped|sendWelcomeEmail     (never sends, never blocks the request)
        1. admin settings allow it?  (email_notifications_enabled + per-type switch; default: yes)
        2. INSERT order_emails (order_id, type, fingerprint)  <- unique key = the "already handled" decision
        3. dispatch SendOrderEmail job (after DB commit)
   -> queue worker (`php artisan queue:work`)
        SendOrderEmail: reload order -> build mail -> send -> set order_emails.sent_at
```

### Other queued mails (no `order_emails` row)

| Mail | Queued as | Where |
|---|---|---|
| Welcome (after verification) | `SendWelcomeEmail` job | `EmailService::sendWelcomeEmail` |
| Registration verification (pending users) | queued `PendingUserEmailVerification` notification | registration flow |
| **Password reset** (existing users) | `SendUserNotification` job (kind `PasswordReset`) | `User::sendPasswordResetNotification` |
| **Email verification / resend** (existing users) | `SendUserNotification` job (kind `VerifyEmail`) | `User::sendEmailVerificationNotification` |
| **Contact form** (to the shop inbox) | queued `ContactMessage` mailable (`Mail::to(...)->queue`) | `ContactController::store` |

All of them use 5 attempts with waits of 1 min, 5 min, 15 min, 1 h, are dispatched after the DB commit, and end in `failed_jobs` (plus a masked log line) when the last attempt fails.
Details of the three newer ones:

- **Locale:** `SendUserNotification` captures `app()->getLocale()` when the request dispatches it and sends `->locale($locale)` from the worker, so the mail is in the visitor's language
  (unsupported values fall back to `APP_LOCALE`). The contact mail goes to the shop and has no locale handling.
- **Security trade-off:** the password-reset token travels in the queue payload (the `jobs` table) from the request until the worker has sent the mail, i.e. normally seconds,
  longer during an outage or a retry. It is never logged (the failure log has the user id, the masked address and the error message only) and the payload is deleted when the job succeeds.
  Only the hash of the token is stored in `password_reset_tokens`; the token expires after `auth.passwords.users.expire` minutes counted from **request** time, so a mail delayed by retries has less validity left.
  Whoever can read the `jobs` table can therefore read live tokens: treat it like the `password_reset_tokens` plus mailbox access.
- **Verification link:** the signed URL is built in the worker when the mail renders (valid for `auth.verification.expire` minutes from sending time, default 60). The job does nothing if the user was deleted or has verified in the meantime.
- **Password reset anti-enumeration is unchanged:** the controller answers the same for known and unknown addresses; for unknown ones no job is queued.
- **Contact form:** the visitor sees success once the job is queued; if the queue backend cannot even accept it, the visitor sees the usual error message. A message that exhausts its retries is in `failed_jobs`
  (`php artisan queue:retry <id>`) and logged as `Contact form message permanently failed` with the visitor address masked.
- **Deliberately synchronous:** the admin test/custom mail paths (`EmailService::sendCustomEmail`, `testEmailConfiguration`, `sendSimpleTestEmail`) and the `email:test`, `email:send-test`,
  `email:test-order-confirmation`, `auth:test-verification` commands send inside the call, because immediate success/failure feedback is their purpose.

- Returns `true` from `EmailService` mean "accepted for delivery": queued now, already queued, already sent, or switched off in settings.
  `false` means it could not be queued (no recipient, queue backend down, or an earlier permanent failure).
- **Dedupe key** `(order_id, type, fingerprint)`:
  - confirmation: fingerprint `default` -> at most one per order, ever.
  - shipped: fingerprint = hash of the tracking number -> sent when the order first ships, and again only if the tracking number changes.
- **Retries:** 5 attempts, waiting 1 min, 5 min, 15 min, 1 h between them. Each failed attempt writes `order_emails.error`; after the last one `failed_at` is set
  and the job lands in `failed_jobs`.
- **Safety net:** the scheduler runs `orders:send-pending-emails --force` every 10 minutes. It queues confirmations for paid orders of the last 7 days that were
  never sent (job lost, queue was down at checkout) and skips anything already sent, in flight (queued < 3 h ago) or permanently failed.

### What `order_emails` tells you

| sent_at | failed_at | error | meaning |
|---|---|---|---|
| null | null | null | queued, waiting for a worker |
| null | null | text | an attempt failed, a retry is scheduled |
| set | null | null | delivered to the mail server |
| null | set | text | gave up (all retries used, or no recipient address) |

Rows created before this feature were backfilled from `orders.confirmation_email_sent*` (those columns are still maintained).

### Honest limits (read this)

- **Delivery means "accepted by your SMTP server"**, not "reached the inbox". Bounces appear at your mail provider, not here.
- **Worker killed mid-send:** the job is retried after `retry_after` (90 s) because the queue never saw it complete. `sent_at` is written only after the SMTP
  hand-off succeeds, so a crash *before* the send never loses the mail. A crash in the few milliseconds *between* the SMTP hand-off and the `sent_at`
  write can cause **one** duplicate. This cannot be eliminated without two-phase commit with the mail server; it is the accepted trade-off against ever losing a mail.
- Two workers never send the same row at once (cache lock `order-email:<order>:<type>:<fingerprint>`, 75 s; the loser is released for 30 s and then finds the row sent), and a second job for a row that is
  already `sent_at` is a no-op.
- If the queue storage itself is wiped (e.g. truncated `jobs` table) confirmations are recovered by the 10-minute command, **shipped** emails are not (re-save
  the order with a different tracking number or send manually).

## 2. Environment variables

| Variable | Required in production | Notes |
|---|---|---|
| `MAIL_MAILER` | yes | `smtp` (or ses/postmark/...). `log` and `array` deliver nothing; the app logs a CRITICAL "Unsafe production mail configuration" once an hour if production uses them. |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` | yes (smtp) | `MAIL_SCHEME=smtps` for port 465, `smtp` (STARTTLS) for 587. `MAIL_ENCRYPTION` no longer exists. |
| `MAIL_FROM_ADDRESS` | yes | The **no-reply** sender, e.g. `noreply@your-domain`. Every mailable uses this default; there is no per-mail override and no Reply-To. |
| `MAIL_FROM_NAME` | yes | Display name. |
| `MAIL_SUPPORT_ADDRESS` | yes | Monitored mailbox shown in every email (`config('mail.support_address')`). Falls back to `CONTACT_EMAIL`, then a placeholder (warned about at boot). |
| `CONTACT_EMAIL` | optional | Where contact-form messages go; defaults to `MAIL_SUPPORT_ADDRESS`. (Older docs called it `MAIL_CONTACT_EMAIL`: wrong.) |
| `QUEUE_CONNECTION` | yes | `database` (default). `sync` sends inside the web request: debugging only. |
| `CACHE_STORE` | yes | Must support locks and be shared by all containers: `database` (default in `.env.example`). |
| `QUEUE_ALERT_MAX_JOB_AGE_MINUTES` / `QUEUE_ALERT_FAILED_JOBS_THRESHOLD` | optional | Thresholds of `ops:check-queue` (defaults 10 minutes / 10 failed jobs; `config('mail.queue_alerts')`). |
| `DB_QUEUE_RETRY_AFTER` | optional | Seconds before a reserved job is handed to another worker (default 90). Keep it above the 60 s job timeout. |

## 3. Processes that must be running

| Service | Command | Purpose |
|---|---|---|
| `app` | image default (nginx + php-fpm) | web; runs migrations at start (`AUTORUN_LARAVEL_MIGRATION=true`) |
| `queue` | `php /var/www/html/artisan queue:work --tries=3 --max-time=3600 --sleep=3 --timeout=60` | sends email and any other queued job. **Without a running worker, password resets, verification resends and contact-form messages stop as well as order mail**; the `ops:check-queue` alert (section 4) covers it. |
| `scheduler` | `php /var/www/html/artisan schedule:work` | runs `routes/console.php` every minute |

`docker-compose.yml` defines all three from the same image. The two workers set `AUTORUN_ENABLED=false` (so only `app` migrates) and
`RSP_STARTUP_TASKS=false` (so they don't clear the cache / rebuild sitemaps on every restart). They use the serversideup native healthchecks
(`healthcheck-queue`, `healthcheck-schedule`: "is the process alive"), `restart: unless-stopped`, and `SIGTERM` with a 90 s grace period so a job in flight finishes
when the container is stopped. `queue:work --max-time=3600` exits cleanly every hour by design; Docker restarts it.

Registered schedule (`php artisan schedule:list`):

| Task | When |
|---|---|
| `orders:send-pending-emails --force` | every 10 minutes, no overlap |
| `auth:cleanup-pending --force` | daily 03:00 |
| `queue:prune-failed --hours=168` | daily (failed jobs kept 7 days) |
| `ops:check-queue` | every 5 minutes: queue/worker health alert (section 4) |
| `generate:sitemap-index` / `generate:sitemap` | daily 02:00 / Sunday 03:00 |

### Before the first deploy (checklist: skipping any of these breaks mail or orders)

1. **One shared database.** `app`, `queue` and `scheduler` are separate containers and must use the **same** database (`DB_CONNECTION=mysql|pgsql` with a
   real server, set in the Dokploy Environment tab). `.env.example` defaults to SQLite and `docker-compose.yml` has no database service or volume: with a
   per-container SQLite file the workers would see their own empty `jobs` table and **no email would ever be sent**. The cache (`CACHE_STORE=database`) must
   be shared the same way.
2. **Check for duplicate orders first.** The deploy runs a migration that adds a unique index on `orders.stripe_checkout_session_id` and aborts if duplicates
   already exist. Run `php artisan orders:duplicate-sessions` against the production database beforehand (read-only; details in
   `docs/reports/email-audit/order-flow.md`) and merge or remove any duplicates it lists.
3. **`STRIPE_WEBHOOK_SECRET` must be set** and `APP_ENV=production`: in production the webhook answers 403 when no secret is configured (fail closed), so
   without it no order is created from payments. `.env.example` shows `APP_ENV=local`; production must override it.
4. **`APP_URL` is the exact public https URL.** The signed order-status links in the emails are invalid if it differs from the host customers open.
5. **Placeholders**: `MAIL_FROM_ADDRESS` and `MAIL_SUPPORT_ADDRESS` must be real addresses (the `@your-domain` / `@example.com` values trigger a critical log entry
   at boot in production).
6. The sitemap tasks also run in the `scheduler` container, whose `public/` directory is not shared with `app`: only the startup run in `app` effectively
   updates the sitemap.

### Manual steps in Dokploy (cannot be done from the repo)

1. Environment tab: set `MAIL_MAILER=smtp` + SMTP credentials, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_SUPPORT_ADDRESS`, `QUEUE_CONNECTION=database`. Do **not** set
   `AUTORUN_ENABLED` there (it would be overridden for the workers anyway).
2. Redeploy the compose application so the new `queue` and `scheduler` services are created. Confirm all three show healthy.
3. Place a test order (or `php artisan queue:work --once` from the `queue` container) and check `order_emails.sent_at`.
4. Deploys: the workers run the code baked into their image. A normal Dokploy redeploy recreates all three containers, which restarts the workers on the new
   code. If you ever deploy code **without** recreating containers (e.g. the legacy `deploy.php` path), run `php artisan queue:restart` so running workers reload.
5. DNS: SPF / DKIM / DMARC for the sending domain (see Category D's deliverability checklist).

## 4. Inspecting

```bash
php artisan schedule:list
php artisan tinker --execute="echo DB::table('jobs')->count();"       # waiting jobs
php artisan queue:failed                                              # permanently failed jobs
```

SQL (read-only):

```sql
-- unsent / failed mails of the last 3 days
select oe.id, o.order_number, oe.type, oe.queued_at, oe.sent_at, oe.failed_at, left(oe.error, 120)
from order_emails oe join orders o on o.id = oe.order_id
where oe.sent_at is null and oe.queued_at > now() - interval 3 day order by oe.id desc;
```

Logs: `storage/logs/laravel.log` (`Order email queued/sent/permanently failed`, `Failed to queue order email`), `storage/logs/pending-order-emails.log`
(the 10-minute command). Recipient addresses are masked in that output and not logged by the job; look the order up by id/number.

### Queue health alert (`ops:check-queue`)

Without a running worker every customer mail (including registration verification) silently stops. The scheduler therefore runs `ops:check-queue` every
5 minutes. It writes a **critical** entry to `storage/logs/laravel.log` (and exits non-zero) when, with `QUEUE_CONNECTION` other than `sync`:

- the oldest runnable row in `jobs` has been waiting 10 minutes or more (rows waiting out a retry backoff are ignored), or
- `failed_jobs` holds more than 10 rows, or
- the `jobs` table does not exist (migrations not run). A missing `failed_jobs` table is ignored.

Search the log for `Queue health check failed`. **Limitation:** the check runs inside the scheduler container, so if the scheduler is down the check is down too.
Complement it with an external uptime monitor on `https://<your-domain>/up` (Laravel's health route) and, ideally, one that alerts on log lines or on the Dokploy
container health status of the `queue` / `scheduler` services. Manual run: `php artisan ops:check-queue`.

### Re-enabling mail after it was switched off

`orders:send-pending-emails` skips with an info line while confirmations are disabled and writes nothing. When you switch them back on, the next 10-minute run
queues confirmations for **all paid orders of the last 7 days that were never sent**. If more than 25 are pending it prints (and logs) a "Large backlog" warning first.
To avoid a burst you do not want, run `php artisan orders:send-pending-emails --dry-run` before re-enabling, and mark the orders you don't want mailed by
setting `orders.confirmation_email_sent = 1` for them.

## 5. Retrying

| Situation | Action |
|---|---|
| Worker was down; mails waiting | Just start it: `docker compose up -d queue`. Queued jobs are sent in order. |
| Job in `failed_jobs` (SMTP outage, bad credentials fixed since) | `php artisan queue:retry all` (or one id). The job is idempotent: if `sent_at` was set meanwhile it does nothing. |
| `order_emails.failed_at` set but the `failed_jobs` row was pruned | `php artisan orders:send-pending-emails --force --retry-failed` (confirmations, last 7 days) |
| Preview what the command would do | `php artisan orders:send-pending-emails --dry-run` |
| Customer says "never got it" but `sent_at` is set | The mail server accepted it: check spam, the provider's logs, and the address on the order. Do not re-send blindly; delete the `order_emails` row only if you really want a second copy, then run the command. |
| Admin saw the "could not be queued" warning | Queue backend was unreachable (nothing recorded). Check `jobs` table/DB health, then for a confirmation run the command above; for a shipped mail change the tracking number or ask a developer to call `EmailService::sendOrderShipped`. |

## 6. Switching mail off on purpose

Admin -> Settings -> Email: master `email_notifications_enabled`, `order_confirmation_enabled`, `order_shipped_enabled`
(delivered/cancelled switches are read for the lifecycle emails). When off, nothing is queued and **no `order_emails` row is written**, so switching back on lets
the 10-minute command pick up confirmations of the last 7 days. Mails already queued are still sent (the switch is checked when queueing).
Note: the "From email/name" fields in that admin page are not used; the sender is `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME`.

## 7. Local development

`.env`: `MAIL_MAILER=log`, `QUEUE_CONNECTION=database` (or `sync`). With `database`, run `php artisan queue:work` in another terminal; mail appears in
`storage/logs/laravel.log`. Tests use `QUEUE_CONNECTION=sync` and `Mail::fake()` / the `array` mailer.

## 8. Order lifecycle emails (delivered, cancelled, refunded, owner alerts)

All of them use the same pipeline as section 1 (`EmailService` -> `order_emails` claim -> `SendOrderEmail` job -> worker). Status mails are triggered by an **observer on the `Order` model** (`updated` event, `status` changed), so every way of changing a status is covered (edit form, status endpoint, bulk update, `Order::cancel()` / `markAsDelivered()`, the future tracking sync) and the controllers contain no mail code for them.

| Mail | To | Trigger | Dedupe key (`order_emails`) | Setting |
|---|---|---|---|---|
| Order delivered | customer | status becomes `delivered` | `order_delivered` / `default` (once per order) | `order_delivered_enabled` |
| Order cancelled | customer | status becomes `cancelled` | `order_cancelled` / `default` (once per order) | `order_cancelled_enabled` |
| Order refunded | customer | **not wired yet** (see below) | `order_refunded` / hash of the amount | `order_refunded_enabled` |
| New order alert | shop owner | first payment confirmation of an order (webhook or success page, whichever wins) | `admin_new_order` / `default` | master switch only |
| Paid order missing | shop owner | Stripe reported a payment but the order could not be created (critical) | not an order mail: deduped by cache key `paid-order-missing:<session id>` for 6 h | none (always sent) |

Rules worth knowing:

- **Delivered and cancelled mails go only to customers who know about the order**: payment succeeded, or the confirmation email was sent. Cancelling (or the Stripe "cancel" page closing) an abandoned, never-paid checkout sends nothing. Orders created by seeders/factories already `delivered` never send anything (the observer ignores `created`).
- **Cancelling a paid order does not refund it.** The cancellation mail only says "we will refund the amount" (no timeline); someone must actually refund it in the Stripe dashboard. `payment_status` stays `succeeded` until the refund feature exists. The 5 to 10 business days wording exists only in the refund mail (`emails.order_refunded.timing`), which is sent only for a refund that really happened.
- **Refund email is built but intentionally not sent by anything.** The admin "Refund" button is a placeholder that only writes a note; emailing "you have been refunded" for money that did not move would be wrong. When the real Stripe refund exists, call `app(EmailService::class)->sendOrderRefunded($order, $amount)` right after Stripe confirms it (a TODO sits in `OrdersController::refund()`); handling `charge.refunded` is the more robust trigger because it also covers refunds made by hand in Stripe. Each distinct amount produces one email, repeating the same amount does not.
- **Owner address**: Admin -> Settings -> Email -> "Admin Notification Email" (`admin_notification_email`); empty or invalid falls back to `CONTACT_EMAIL` / `MAIL_SUPPORT_ADDRESS`. Check what the production DB holds: the seeder wrote the placeholder `admin@rspecommerce.com`. Alerts are rendered in the app locale (`APP_LOCALE`), customer mails in the order's locale.
- **Languages**: a customer whose order locale is `de`, `es` or `fr` currently receives these mails in the app language, because those translations have not been written yet (JSON translations do not fall back by themselves, so the job checks the language has the strings first). This changes by itself once the de/es/fr keys exist.
- **Paid order missing**: the alert is queued from the webhook handler's error path, then the original error is rethrown so Stripe keeps retrying. It is sent at most once per 6 hours per session. When you get it: open the payment in Stripe, search `storage/logs/laravel.log` for `Stripe payment succeeded but the order could not be created` (it has the session id), and either wait for Stripe's automatic retry to succeed or create the order by hand.
- Switches off => nothing is queued and no `order_emails` row is written (same as section 6). Flipping the status again later therefore still sends once the switch is back on, but only for a *new* status change.


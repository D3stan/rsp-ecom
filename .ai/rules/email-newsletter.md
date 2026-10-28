---
paths:
  - app/Services/Newsletter/*.php
  - app/Jobs/Newsletter/*.php
  - app/Jobs/SendOrderEmail.php
  - tests/Feature/Newsletter/*.php
---
# Email, newsletter and abandoned-checkout reminders

Transactional order email delivery, marketing consent (double opt-in), newsletter campaigns and the single abandoned-checkout reminder. Newsletter and reminders are off by default: Admin > Settings > Newsletter. It does not cover password-reset or contact-form mail.

## Entry points
- `app/Services/Newsletter/MarketingConsent.php`: subscribe, confirm, unsubscribe, erase, `allows($email)`, `CONSENT_TEXT_VERSION`.
- `app/Services/Newsletter/AbandonedCheckoutReminders.php`: consent capture at checkout, reminder scheduling.
- `app/Jobs/Newsletter/DispatchCampaign.php`, `SendCampaignEmail.php`: campaign fan-out and per-recipient send.
- `app/Services/EmailService.php`, `app/Jobs/SendOrderEmail.php`: transactional order mail.
- `app/Http/Controllers/Newsletter/NewsletterController.php`, `app/Http/Controllers/CheckoutRecoveryController.php`: public confirm/unsubscribe and cart recovery.
- `resources/js/components/newsletter/`, `resources/js/components/checkout/MarketingConsentCheckbox.tsx`: signup controls.

## Rules
- MUST start every consent checkbox/switch unticked (Codice Privacy art. 130, GDPR art. 7). Unenforced.
- MUST write a `consent_events` row (event, time, IP, user agent, source, `consent_text_version`) for every consent change. `MarketingConsentTest`.
- MUST send newsletters only to `confirmed` subscribers (`MarketingConsent::allows()`, `NewsletterCampaign::audience()`). `CampaignsTest`.
- MUST include in every marketing mail: sender identity (`store.legal_name`, `store.address`), signed unsubscribe link, `List-Unsubscribe` and `List-Unsubscribe-Post: List-Unsubscribe=One-Click`. `CampaignsTest`.
- MUST keep unsubscribe working while the newsletter is switched off, and immediate. `NewsletterSettingsTest`.
- MUST answer `POST /newsletter/subscribe` identically for new, pending, confirmed and bot submissions (no enumeration). `NewsletterSignupFlowTest`.
- NEVER confirm or unsubscribe on a GET: mail scanners prefetch links; only the button POST acts. `NewsletterSignupFlowTest`.
- NEVER edit the text of a consent key in use: add `newsletter.consent_v2` in all five locales and bump the constant. Unenforced.
- MUST erase on request: subscriber and consent trail deleted, one anonymised `erased` event kept. `AdminSubscribersTest`.
- MUST send the abandoned-checkout reminder at most once, only after the Stripe session is dead. `AbandonedCheckoutTest`.
- MUST gate any other marketing mail (e.g. review requests) on `MarketingConsent::allows()`. Unenforced.

## How it works
Double opt-in:
1. `subscribe()` creates a `pending` subscriber and a `requested` event (or `resubscribed`), queues `NewsletterConfirm`. A pending address gets a new mail at most every 10 min; a confirmed one gets none.
2. The link is valid 7 days; only the token's sha256 is stored, a new token replaces the old.
3. `GET /newsletter/confirm/{token}` shows a button; the CSRF-protected, throttled POST confirms and records IP and user agent.
4. `GET /newsletter/unsubscribe/{subscriber}` (signed, no expiry) shows a button; the POST unsubscribes. It is also the RFC 8058 one-click target, so it is CSRF-exempt in `bootstrap/app.php`; the signature authorises it.

Signup sources (only while `newsletter.enabled`): footer (honeypot `website`, throttle 5/min), registration (linked to the account on verification), profile switch, checkout (`marketing_consent`).

Campaign send:
```
Send now / newsletter:dispatch-scheduled (every minute)
  status draft|scheduled -> sending (conditional UPDATE: claimed once)
  DispatchCampaign: snapshot confirmed subscribers of the locale -> newsletter_campaign_sends (unique per campaign+subscriber)
  SendCampaignEmail x N: delayed by position / send_rate_per_minute, plus shared `newsletter-send` rate limiter
    re-checks campaign `sending` and subscriber `confirmed` (else skipped_at); sends, sets sent_at; last row -> `sent`
```
Finished rows are skipped, `WithoutOverlapping` per row, `sent_at` written after the server accepts. Markdown is rendered with CommonMark: raw HTML stripped, `javascript:`/`data:` links refused, styles inlined.

Abandoned-checkout reminder:
1. Both checkouts create the Stripe session with `expires_at = now + orders.checkout_expiry_minutes`.
2. At checkout `rememberContact()` stores `marketing_consent`, `locale`, `consent_ip` on the snapshot, and the email only with consent (also starts DOI, source `checkout`).
3. On `checkout.session.expired` `SendAbandonedCheckoutReminder` is queued after `newsletter.abandoned_checkout_delay_minutes`, if reminders are on and the snapshot has consent and email.
4. The job skips when: reminders off, already sent, snapshot empty, address unsubscribed or erased, or a paid order exists for that user/email since. Otherwise it claims `reminder_sent_at` by conditional UPDATE, then sends. A mail failure is logged, not retried.
5. The mail's button is a signed `GET /checkout/recover/{snapshot}` (7 days): rebuilds the cart at today's prices and stock (unavailable lines dropped, quantities capped by stock and `orders.max_qty_per_line`, valid coupon re-applied), sets `recovered_at`. A registered customer's snapshot is recoverable only by them (guests go to login, others 403).

Transactional order mail:
1. `EmailService::sendOrderConfirmation|sendOrderShipped` never sends inline: checks admin settings, inserts an `order_emails` row (unique `(order_id, type, fingerprint)` is the "already handled" decision), dispatches `SendOrderEmail` after commit.
2. Fingerprint: confirmation `default` (one per order ever); shipped = hash of tracking number (resend only if it changes).
3. `SendOrderEmail`: 5 tries, backoff 1 min, 5 min, 15 min, 1 h; `WithoutOverlapping` per row; sets `sent_at` only after the mail is accepted; after the last try sets `failed_at` and lands in `failed_jobs`.
4. Safety net: `orders:send-pending-emails --force` every 10 min queues confirmations for paid orders of the last 7 days with none sent, in flight (queued < `EmailService::STALE_AFTER_MINUTES`) or permanently failed. Warns above 25 pending; `--retry-failed` includes failed ones; `--dry-run` previews.
5. `order_emails` state: no `sent_at`/`failed_at` = queued or retrying (`error` set = attempt failed); `sent_at` = accepted by the mail server; `failed_at` = gave up.

## Traps
- "Sent" means accepted by SMTP, not delivered; bounces live at the mail provider.
- A crash between SMTP hand-off and the `sent_at` write can duplicate one mail; accepted over ever losing one.
- A wiped `jobs` table is recovered for confirmations by the 10-min command, not for shipped mail.
- `MAIL_MAILER=log|array` in production delivers nothing; the app logs a critical warning hourly.
- Consent at checkout only counts while `newsletter.enabled` (the checkbox is hidden otherwise).
- The reminder is deliberately sent after session expiry so a customer never has two live payment pages.

## Operate
- Queue worker and scheduler must both run; `ops:check-queue` alerts on a stuck queue (see the ops rule).
- Settings: `newsletter.enabled` (off), `newsletter.sender_name` (empty = store name), `newsletter.send_rate_per_minute` (60, 1-600), `newsletter.abandoned_checkout_enabled` (off), `newsletter.abandoned_checkout_delay_minutes` (60, 0-2880), `orders.checkout_expiry_minutes` (60, 30-1440).
- Admin: Newsletter > Subscribers (status counts, unsubscribe, erase, CSV export of confirmed only, formula-injection safe, 30-day reminder stats) and > Campaigns (Markdown + live preview via the real mailable, test to self, send/schedule with recipient count, cancel).
- Env: `MAIL_*`, `QUEUE_CONNECTION` (`database`), `CACHE_STORE` (shared, supports locks), `QUEUE_ALERT_MAX_JOB_AGE_MINUTES`, `QUEUE_ALERT_FAILED_JOBS_THRESHOLD`, `DB_QUEUE_RETRY_AFTER` (keep above the 60 s job timeout).

## Tests
- `tests/Feature/Newsletter/`: consent, signup flow, campaigns, settings, admin, abandoned checkout.
- `tests/Feature/Mail/OrderEmailDeliveryTest.php`, `tests/Unit/Models/OrderEmailTest.php`: transactional delivery and dedupe.

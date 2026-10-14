# WS-F: Newsletter mechanics & abandoned-checkout reminders

> Branch `feat/newsletter-abandoned-checkout` from `fixes`. Wave 1. Read `00-overview.md` first; follow its per-item protocol.
> Migration prefix: `2026_10_15_60xxxx`. **Size: L.** Order: F1 → F2 → F3 → F4 → F5 → F6.
> WS-C edits `Checkout/Details.tsx` heavily. Add your consent checkbox as a **separate component** and insert it with
> one line just above the Pay button. WS-H (wave 2) reads your consent data to gate review-request emails, so keep
> `MarketingConsent::allows(string $email): bool` (F1) stable.

**Goal:** the shop can collect provable, GDPR/ePrivacy-compliant email consent, send newsletters to confirmed
subscribers, and send one reminder to customers who started paying but didn't finish (only when they consented).

**Legal guardrails (apply everywhere):** no pre-ticked boxes (Codice Privacy art. 130, GDPR art. 7); keep consent proof
(timestamp, IP, user agent, source, text version); double opt-in before any newsletter is sent; one-click unsubscribe in
every marketing email (RFC 8058 headers + visible link); unsubscribing is honoured immediately; no subscriber enumeration
(identical response whether the email exists or not); sender identity (`store.legal_name`, `store.address`) in every footer.

---

### F1: subscriber model, consent log, settings. Size **S**

**Feature check:** grep shows no newsletter/subscriber/consent code.

**Data model:**
```
newsletter_subscribers  id, email unique (lowercased), user_id nullable FK nullOnDelete, locale,
                        status enum(pending, confirmed, unsubscribed), confirm_token_hash nullable,
                        confirmation_sent_at nullable, confirmed_at nullable, unsubscribed_at nullable,
                        source enum(footer, register, checkout, account, admin), timestamps
consent_events          id, subscriber_id FK cascade, event enum(requested, confirmed, unsubscribed, resubscribed,
                        erased), source string, consent_text_version string, ip string nullable,
                        user_agent string nullable, created_at
```
- `App\Services\Newsletter\MarketingConsent`: `allows(string $email): bool` (true only for `confirmed`),
  `subscribe(email, source, locale, ?user, ip, ua): NewsletterSubscriber` (idempotent; a confirmed subscriber stays
  confirmed; an unsubscribed one becomes pending again with a `resubscribed` event; it sends the DOI email), `confirm(token)`,
  `unsubscribe(subscriber, source)`.
- Consent text version: a constant (e.g. `newsletter.consent_v1`) that is also the lang key of the checkbox text, so the
  proof says what the person saw.
- Settings group `newsletter` (add it to `groups`): `newsletter.enabled` (bool, default **false**, public: it hides every
  signup UI when off), `newsletter.sender_name` (string, default = store name), `newsletter.send_rate_per_minute`
  (int, default 60, min 1, max 600). Labels/help in 5 locales.

**Tests:** model and service unit/feature tests: idempotent subscribe, resubscribe flow, `allows()` only when
confirmed, events written with IP/UA.

---

### F2: double opt-in, confirm, unsubscribe, signup entry points. Size **M**

**Routes (web, throttled `throttle:5,1` on POST):**
- `POST /newsletter/subscribe` (email, honeypot field, source): always redirects back with the same neutral flash
  ("Check your inbox to confirm").
- `GET /newsletter/confirm/{token}`: confirms (the token is stored as a hash, valid 7 days), shows a small Inertia page
  "You're subscribed". An invalid/expired token shows a page offering to resend.
- `GET /newsletter/unsubscribe/{subscriber}` (signed URL): page with a confirm button. `POST` on the same signed URL
  (also the RFC 8058 one-click target) unsubscribes immediately and returns 200.

**Emails:** `NewsletterConfirm` mailable (locale-aware, button + plain link, 7-day validity note). Queue it.
Don't use `OrderEmail` (not order-bound). Rate-limit resends to 1 per 10 minutes per email.

**Entry points (each shown only when `newsletter.enabled`):**
- Footer form: a component `components/newsletter/NewsletterSignup.tsx`, inserted where the storefront footer renders
  (find it: `pages/home.tsx:417-478`, `layouts/public-layout.tsx`). One line per insertion point.
- Registration: an unticked checkbox in `pages/auth/register.tsx`. On successful registration call
  `MarketingConsent::subscribe(..., source: 'register')`.
- Account: a switch on the profile settings page (subscribe ⇒ DOI email; unsubscribe ⇒ immediate).
- Checkout: an unticked checkbox component `components/checkout/MarketingConsentCheckbox.tsx`, label
  "Send me news and offers by email. I can unsubscribe at any time." (key `newsletter.consent_v1`), plus a short line
  under it: "If you don't complete your order, we may send you **one** reminder." The checkout posts
  `marketing_consent: boolean`. Backend wiring is in F5.

**Tests:** subscribe → pending + one queued DOI mail; the same email again → still one pending, no duplicate mail within
10 min; a known and an unknown email give identical responses; the confirm token works once and expires after 7 days; the
unsubscribe link works without login, an unsigned one is refused, and one-click POST returns 200; the honeypot blocks;
the register checkbox unticked ⇒ no subscriber; `newsletter.enabled = false` ⇒ routes 404 and no UI props.

---

### F3: admin subscribers. Size **S-M**

- `Admin/Newsletter/Subscribers.tsx`: counts by status, a search, a status filter, a paginated table (email, status,
  source, locale, confirmed_at), and a row action **Unsubscribe** and **Erase** (GDPR: deletes the subscriber, keeps an
  anonymised `erased` event with no email). **CSV export** of confirmed subscribers (email, locale, confirmed_at, source)
  streamed with `response()->streamDownload`.
- Admin sidebar entry "Newsletter".
- Tests: 403 for customers; export contains only confirmed subscribers; erase removes the email everywhere.

---

### F4: campaigns (compose → test → send). Size **M**

**Data model:**
```
newsletter_campaigns       id, subject, preheader nullable, body_markdown text, locale nullable (null = all),
                           status enum(draft, scheduled, sending, sent, cancelled), scheduled_at nullable,
                           started_at nullable, finished_at nullable, created_by FK users nullOnDelete,
                           recipients_count int default 0, sent_count int default 0, failed_count int default 0, timestamps
newsletter_campaign_sends  id, campaign_id FK cascade, subscriber_id FK cascade, sent_at nullable,
                           failed_at nullable, error nullable, unique(campaign_id, subscriber_id)
```
- Admin pages `Admin/Newsletter/Campaigns/{Index,Edit,Show}.tsx`: a markdown editor (textarea + live preview through
  the same renderer as the email), "Send test to me", "Send now" / "Schedule", "Cancel" while sending, and stats.
  Sending asks for confirmation with the recipient count.
- Sending: `DispatchCampaign` job snapshots recipients (confirmed subscribers matching the locale), inserts the `sends`
  rows (the unique key makes it idempotent), then dispatches `SendCampaignEmail` jobs chunked and rate-limited per
  `newsletter.send_rate_per_minute` (Laravel `RateLimited` job middleware or `Redis::throttle`, whichever the app's
  queue setup supports; check `config/queue.php`). Each job re-checks that the subscriber is still confirmed right before
  sending. The scheduler (`routes/console.php`) picks up `scheduled` campaigns every minute.
- Email layout `NewsletterCampaign` mailable: markdown → HTML using Laravel's markdown mail components; footer with sender
  identity and a one-click unsubscribe (+ headers).
- Tests: only confirmed subscribers of the locale receive it; an unsubscribe mid-send skips the rest; a re-dispatch doesn't
  double-send; scheduled campaigns are sent by the scheduler tick; a test send goes only to the admin; the unsubscribe
  header is present.

---

### F5: abandoned-checkout reminders. Size **M**

**Feature check:** `StripeWebhookListener::closeUnpaidSession` handles `checkout.session.expired` (`:45`), but nothing
is sent. `CheckoutSnapshot` (`app/Models/CheckoutSnapshot.php:29-44`) has no email, locale or consent fields. The
checkout collects `email` in both paths (`CheckoutController.php:~505` user, `~632` guest).

**Design: send only after the Stripe session is dead.** This avoids two live payment sessions for one cart:
- New setting `checkout.session_expiry_minutes` (int, default **60**, min 30, max 1440; Stripe's limits), applied as
  `expires_at` on both `Session::create` calls (`CheckoutController.php:~499, ~618`). Add a `checkout` group, or put
  it in `orders`.
- Setting `abandoned_checkout.enabled` (bool, default **false**) and `abandoned_checkout.delay_minutes` (int,
  default 60, min 0, max 2880), in the `newsletter` group or a new `marketing` group. Labels/help in 5 locales.
- Migration: add to `checkout_snapshots`: `email` nullable, `locale` nullable, `marketing_consent` bool default false,
  `consent_ip` nullable, `reminder_sent_at` nullable, `recovered_at` nullable.
- Checkout: store `email`, `locale`, and `marketing_consent` (from F2's checkbox) on the snapshot when it is captured. If
  consent is given, also call `MarketingConsent::subscribe(..., source: 'checkout')` (that starts the newsletter DOI).
- On `checkout.session.expired`: after the existing close logic, if `abandoned_checkout.enabled` and the snapshot has
  `marketing_consent` and an email, dispatch `SendAbandonedCheckoutReminder($snapshotId)` delayed by `delay_minutes`.
- The job re-checks everything at send time and skips when: a reminder was already sent (`reminder_sent_at`); a **paid
  order exists** for the same email or user created after the snapshot; the email is `unsubscribed` in
  `newsletter_subscribers`; or the snapshot has no lines.
  It sets `reminder_sent_at` atomically (conditional UPDATE) before sending, so it is never sent twice.
- Email `AbandonedCheckoutReminder`: the lines (name, variant, qty, price) from the snapshot, a "Complete your order"
  button to a **signed** URL `GET /checkout/recover/{snapshot}` valid 7 days, and an unsubscribe link (signed; it marks
  the subscriber unsubscribed, or creates an `unsubscribed` row so the email is suppressed).
  One reminder only, ever, per snapshot.
- Recovery route: rebuilds the cart for the **current** session/user from the snapshot lines using today's prices and
  stock (skip unavailable lines with a flash notice; cap quantities), re-applies the coupon if it is still valid, sets
  `recovered_at`, and redirects to `cart`. If the visitor is logged in as a different user than the snapshot's, refuse.
- Admin: on the Newsletter page, a small stats card "Abandoned checkout: reminders sent / recovered / converted (paid
  order within 7 days of the reminder)" for the last 30 days.

**Tests:** `expires_at` is set on both Stripe session creates (fake client captures the request); expired with consent ⇒
the job is queued with the delay; without consent ⇒ nothing; a paid order in between ⇒ skipped; an unsubscribed email ⇒
skipped; a double webhook delivery ⇒ one email; the recover link rebuilds the cart for a guest session and a logged-in user,
skips out-of-stock lines, and is refused when unsigned/expired or for another user; settings off ⇒ nothing sent.

---

### F6: docs + wrap-up

`docs/newsletter-and-reminders.md`: consent model, DOI flow, campaign sending, abandoned flow, settings, legal guardrails.
Report a summary of all F items.

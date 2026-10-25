# Newsletter and abandoned-checkout reminders

How the shop collects marketing consent, sends newsletters and sends one reminder after an abandoned checkout.
Everything is off by default: switch it on in **Admin > Settings > Newsletter**.

## Legal guardrails (apply everywhere)

| Rule | Where it is enforced |
|---|---|
| No pre-ticked boxes (Codice Privacy art. 130, GDPR art. 7) | Every checkbox and switch starts unticked: `NewsletterConsentCheckbox`, `MarketingConsentCheckbox`, the profile switch |
| Proof of consent | `consent_events`: event, timestamp, IP, user agent, source (entry point), `consent_text_version` |
| Double opt-in before any newsletter | Only `confirmed` subscribers receive newsletters (`MarketingConsent::allows()`, `NewsletterCampaign::audience()`) |
| One-click unsubscribe in every marketing email | Signed link in the footer, plus `List-Unsubscribe` / `List-Unsubscribe-Post: List-Unsubscribe=One-Click` headers (RFC 8058) |
| Unsubscribing is immediate | `MarketingConsent::unsubscribe()`. The unsubscribe link keeps working while the newsletter is switched off |
| No subscriber enumeration | `POST /newsletter/subscribe` gives the same answer for new, pending, confirmed and bot submissions |
| Sender identity | Every consent and marketing email shows `store.legal_name` and `store.address` in the footer (`senderIdentity` layout option) |
| Right to erasure | Admin > Newsletter > Erase: deletes the subscriber and its consent trail and keeps one anonymised `erased` event |

## Data model

| Table | Purpose |
|---|---|
| `newsletter_subscribers` | One row per lowercased email: `status` (pending / confirmed / unsubscribed), `source` (footer / register / checkout / account / admin), `locale`, `user_id` (nullable, null on account deletion), hashed DOI token |
| `consent_events` | Append-only proof: requested / confirmed / unsubscribed / resubscribed / erased |
| `newsletter_campaigns` | Subject, preheader, markdown body, optional locale, status, schedule, counters |
| `newsletter_campaign_sends` | One row per recipient, unique per campaign + subscriber: `sent_at`, `failed_at`, `skipped_at`, `error` |
| `checkout_snapshots` (added columns) | `email` (only with consent), `locale`, `marketing_consent`, `consent_ip`, `reminder_sent_at`, `recovered_at` |

### Consent text version

`MarketingConsent::CONSENT_TEXT_VERSION = 'newsletter.consent_v1'` is also the lang key of the consent text shown next to
every signup control. Each event stores it, so the proof records exactly what the person saw. To change the wording,
add a new key (`newsletter.consent_v2`) in all five locales and update the constant. Never edit the text of a key that
is already in use.

## Double opt-in flow

1. **Signup**: `MarketingConsent::subscribe($email, $source, …)` creates a `pending` subscriber with a `requested`
   event, or a `resubscribed` event for someone who had unsubscribed. It then queues `NewsletterConfirm`.
   - A pending address gets a new mail at most every 10 minutes.
   - A confirmed address stays confirmed and gets no mail.
2. **Mail**: the link is valid for 7 days. Only the token's sha256 is stored, and a new token replaces the old one.
3. **Confirm**: `GET /newsletter/confirm/{token}` only shows a page with a **Confirm subscription** button. Opening the
   link changes nothing, because mail scanners prefetch links.
   - The button posts (CSRF, throttled) and only that POST confirms.
   - The `confirmed` event records the IP and user agent of the POST.
4. **Unsubscribe**: `GET /newsletter/unsubscribe/{subscriber}` (signed, no expiry) shows a page with a button. A POST
   to the same URL unsubscribes and answers 200.
   - That POST is also the RFC 8058 one-click target, so it is CSRF-exempt (`bootstrap/app.php`); the signature
     authorises it.

### Entry points (only while `newsletter.enabled`)

| Where | Component | Source |
|---|---|---|
| Storefront footer (public layout and home) | `components/newsletter/NewsletterSignup.tsx` (honeypot field `website`, throttle 5/min) | `footer` |
| Registration form | `NewsletterConsentCheckbox` in `pages/auth/register.tsx`. The subscriber is linked to the account when the registration is verified | `register` |
| Profile settings | `NewsletterPreference` switch: on sends the DOI mail, off unsubscribes immediately | `account` |
| Checkout | `components/checkout/MarketingConsentCheckbox.tsx`, posted as `marketing_consent`. It also announces the single reminder | `checkout` |

## Admin

- **Admin > Newsletter > Subscribers** (`/admin/newsletter/subscribers`):
  - Counts by status, search, status filter and pagination.
  - **Unsubscribe** and **Erase** (GDPR) row actions, each with a confirmation dialog.
  - **CSV export** of confirmed subscribers only (email, locale, confirmed_at, source). Cells are protected against
    spreadsheet formula injection.
  - The abandoned-checkout stats card (see below).
- **Admin > Newsletter > Campaigns**:
  - Compose in Markdown with a live preview. The preview is rendered by the real mailable, so it is exactly what is
    sent.
  - **Send test to me** goes to the logged-in admin only and changes nothing.
  - **Send now** or **Schedule** both ask for confirmation and show the recipient count.
  - **Cancel** works while a campaign is scheduled or sending.
  - The campaign page shows the stats (recipients, sent, waiting, skipped, failed).

## Campaign sending

```
Send now / scheduler tick (newsletter:dispatch-scheduled, every minute)
  └─ status: draft|scheduled → sending (conditional UPDATE, so it is claimed once)
     └─ DispatchCampaign            snapshot: confirmed subscribers of the locale (all locales when none)
        │                           → newsletter_campaign_sends (once; unique key)
        └─ SendCampaignEmail × N    delayed by position / send_rate_per_minute minutes,
                                    plus the shared `newsletter-send` RateLimited limiter
             re-checks: campaign still `sending`? subscriber still `confirmed`? (else skipped_at)
             sends NewsletterCampaignMail → sent_at, sent_count; failed() → failed_at, failed_count
             last row done → campaign `sent`
```

- **No double sends:** finished rows are skipped, `WithoutOverlapping` runs per row, and `sent_at` is written after the
  mail server accepts the mail (the same pattern as order emails).
- **Content:** the markdown is rendered with CommonMark (`Str::markdown`). Raw HTML is stripped, unsafe links
  (`javascript:`, `data:`) are refused, and styles are inlined for email clients.
- **Queue:** the queue worker must be running. The scheduler (`schedule:run` every minute) starts scheduled campaigns.

## Abandoned-checkout reminder

The reminder is sent only after the Stripe session is dead, so a customer never has two live payment pages for one
cart.

1. **Session lifetime:** both checkouts create the Stripe session with
   `expires_at = now + orders.checkout_expiry_minutes` (default 60). The value is kept a minute inside Stripe's
   30 min–24 h window.
2. **At checkout:** `AbandonedCheckoutReminders::rememberContact()` records the consent on the snapshot.
   - It stores `marketing_consent`, `locale` and `consent_ip`, and the email only with consent.
   - With consent it also starts the newsletter double opt-in (`source: checkout`).
   - Consent only counts while `newsletter.enabled` is on, because the checkbox is hidden otherwise.
3. **On `checkout.session.expired`:** after the existing close logic, `sessionExpired()` queues
   `SendAbandonedCheckoutReminder`, delayed by `newsletter.abandoned_checkout_delay_minutes`. It does so only if the
   reminders are on and the snapshot has consent and an email.
4. **When the job runs,** it skips the reminder if:
   - the reminders are switched off;
   - a reminder was already sent;
   - the snapshot has no lines;
   - the address is unsubscribed, or was erased (no subscriber row);
   - a paid order exists for the same user or email since the checkout.

   Otherwise it claims `reminder_sent_at` with a conditional UPDATE and sends. A double webhook delivery or a replayed
   job therefore never sends twice. The reminder is sent at most once: a mail-server failure is logged, not retried.
5. **Mail (`AbandonedCheckoutReminder`):** the lines, a **Complete your order** button, a footer with the sender
   identity and unsubscribe link, and the RFC 8058 headers.
   - The button goes to a signed `GET /checkout/recover/{snapshot}` URL valid for 7 days.
6. **Recovery:** the link rebuilds the cart of whoever opens it, using today's prices and stock.
   - Unavailable lines are left out with a notice.
   - Quantities are capped at stock and at `orders.max_qty_per_line`.
   - A coupon that is still valid is re-applied.
   - `recovered_at` is set and the visitor lands on the cart.
   - A registered customer's checkout can only be recovered by that customer: guests go to login, other users get
     403.

**Admin stats** (Newsletter page, last 30 days): reminders sent, carts recovered, and converted (a paid order within
7 days of the reminder).

## Settings

| Key | Default | Notes |
|---|---|---|
| `newsletter.enabled` | off | Public. Off hides every signup control and returns 404 on subscribe and confirm. Unsubscribe still works |
| `newsletter.sender_name` | empty | Empty means the store name |
| `newsletter.send_rate_per_minute` | 60 | 1–600. Keep it within the mail provider's limits |
| `newsletter.abandoned_checkout_enabled` | off | The single reminder after an abandoned checkout |
| `newsletter.abandoned_checkout_delay_minutes` | 60 | 0–2880, counted from the session's expiry |
| `orders.checkout_expiry_minutes` | 60 | 30–1440, the Stripe payment page lifetime |

## Code map

| Area | Files |
|---|---|
| Consent | `app/Services/Newsletter/MarketingConsent.php`, `app/Models/{NewsletterSubscriber,ConsentEvent}.php` |
| Public routes | `app/Http/Controllers/Newsletter/NewsletterController.php`, `app/Http/Controllers/Settings/NewsletterPreferenceController.php` |
| Admin | `app/Http/Controllers/Admin/{NewsletterSubscribersController,NewsletterCampaignsController}.php`, `resources/js/pages/Admin/Newsletter/**` |
| Campaigns | `app/Jobs/Newsletter/{DispatchCampaign,SendCampaignEmail}.php`, `app/Services/Newsletter/CampaignRenderer.php`, `app/Mail/NewsletterCampaignMail.php`, `app/Console/Commands/DispatchScheduledNewsletters.php` |
| Reminders | `app/Services/Newsletter/AbandonedCheckoutReminders.php`, `app/Jobs/Newsletter/SendAbandonedCheckoutReminder.php`, `app/Mail/AbandonedCheckoutReminder.php`, `app/Http/Controllers/CheckoutRecoveryController.php` |
| Emails | `resources/views/emails/{newsletter-confirm,newsletter-campaign,abandoned-checkout-reminder}*.blade.php`. Layout options `senderIdentity`, `unsubscribeUrl`, `hideHeading` |
| Tests | `tests/Feature/Newsletter/*` |

### Other features that read this consent

`MarketingConsent::allows(string $email): bool` is the stable check for any other marketing email, such as review
requests. It is true only for confirmed subscribers.

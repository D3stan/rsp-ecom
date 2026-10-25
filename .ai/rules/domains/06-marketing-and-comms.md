---
domain: marketing-and-comms
version: 1.0.0
budget_lines: 150
deep_reference: docs/reference/newsletter-and-reminders.md
---

# Marketing and Communications Domain Specification

## 1. Scope & Responsibility

Governs GDPR/ePrivacy-compliant marketing consent (`ConsentEvent`), double opt-in (DOI) newsletter subscriptions (`NewsletterSubscriber`), campaign orchestration and queued dispatch (`NewsletterCampaign`, `NewsletterCampaignSend`), abandoned checkout recovery reminders (`AbandonedCheckoutReminders`), transactional review requests (`ReviewRequests`), and customer review media processing (`ReviewPhotoProcessor`, `ReviewMedia`).

## 2. Core Invariants

| Invariant | Enforcement Mechanism |
|---|---|
| Explicit Unticked Consent | Every consent checkbox/switch defaults to unticked (GDPR Art. 7, Italian Privacy Code Art. 130). Pre-ticked controls are forbidden across registration, checkout, account, and footer. |
| Immutable Consent Audit | Every consent change writes an append-only `consent_events` row recording IP, User-Agent, source, timestamp, and immutable `consent_text_version` (`MarketingConsent::CONSENT_TEXT_VERSION = 'newsletter.consent_v1'`). |
| Strict Double Opt-In (DOI) | Only `confirmed` subscribers may receive marketing campaigns or reminders (`MarketingConsent::allows()`). Confirmation requires an explicit button POST; link visits do not confirm. |
| Anti-Enumeration Security | Public endpoints (`POST /newsletter/subscribe`) return identical success payloads for new, pending, confirmed, or bot submissions to prevent subscriber address enumeration. |
| One-Click Unsubscribe | Every marketing email includes a signed unsubscribe link and RFC 8058 headers (`List-Unsubscribe` / `List-Unsubscribe-Post: List-Unsubscribe=One-Click`). Processing is immediate without login. |
| Idempotent Campaign Delivery | `newsletter_campaign_sends` enforces a unique key on `(campaign_id, subscriber_id)`. Atomic conditional updates claim pending sends, preventing duplicate dispatch across workers. |
| Single Abandoned Reminder | Exactly one reminder is sent per abandoned checkout, delayed by `newsletter.abandoned_checkout_delay_minutes` after Stripe checkout session expiration. Blocked if a paid order exists. |
| Review Media Sanitisation | Uploads are decoded via GD and converted to WebP (`ReviewPhotoProcessor`), stripping EXIF/GPS. Capped at 4 photos per review, 2000 px max edge, 600 px thumb, and 50 MP decoding memory ceiling. |
| Review Photo Moderation | Customer review photos require explicit admin approval (`status = 'approved'`) before rendering in public storefront props (`approvedMedia()`), regardless of text auto-approval settings. |

## 3. State Machines & Transitions

```
[Newsletter Subscriber Lifecycle]
    [Visitor Signs Up] ──> NewsletterSubscriber (status: pending, token TTL 7d, resend throttle 10m)
                                │
                                ├── User clicks emailed link (GET /newsletter/confirm/{token} renders page)
                                ├── User clicks "Confirm Subscription" (POST confirms, logs IP & UA)
                                ▼
                           [status: confirmed] ──> Receives campaigns & review requests
                                │
                                ├── User clicks unsubscribe (GET / POST signed link, immediate effect)
                                ▼
                           [status: unsubscribed] ──> Resubscribing requires fresh DOI flow
                                │
                                └── Admin GDPR Erase ──> Subscriber row deleted, anonymized 'erased' event kept

[Campaign Dispatch Lifecycle]
    draft ──> scheduled ──> sending (conditional atomic claim) ──> chunked SendCampaignEmail jobs ──> sent
                                │
                                └── SendCampaignEmail re-checks: campaign still sending & subscriber still confirmed

[Review Media Moderation]
    Customer Upload ──> ReviewMedia (status: pending)
                             ├── Admin approves ──> status: approved (visible in storefront props)
                             └── Admin rejects  ──> status: rejected (retained in moderation, hidden from public)
```

## 4. Key Contracts & Entry Points

- **`MarketingConsent`** (`app/Services/Newsletter/MarketingConsent.php`):
  - `allows(string $email): bool`: Verifies confirmed subscriber status before sending marketing comms.
  - `subscribe(string $email, string $source, ...): NewsletterSubscriber`: Creates pending subscriber, records audit event, queues `NewsletterConfirm`. Supported sources: `footer`, `register`, `checkout`, `account`, `admin`.
  - `confirm(string $token, ...): bool`: Validates hashed token, transitions status to confirmed, records proof.
  - `unsubscribe(NewsletterSubscriber, string $source): void`: Instant opt-out with signed validation; bypasses CSRF for RFC 8058 one-click POSTs.
  - `erase(NewsletterSubscriber, ...): void`: GDPR right-to-erasure handler. Deletes subscriber and audit history while retaining a single anonymized `erased` event.
- **`AbandonedCheckoutReminders`** (`app/Services/Newsletter/AbandonedCheckoutReminders.php`):
  - `rememberContact()`: Stores checkout snapshot with explicit marketing consent, IP, and contact details.
  - `sessionExpired()`: Queues `SendAbandonedCheckoutReminder` upon `checkout.session.expired` Stripe webhook.
- **`ReviewRequests`** (`app/Services/Reviews/ReviewRequests.php`):
  - `eligibleOrdersQuery()`: Queries orders delivered >= `reviews.request_delay_days` (default 10), registered users, not refunded, having marketing consent, with unreviewed products.
  - Deduping: Orders with an existing `OrderEmail::TYPE_REVIEW_REQUEST` are strictly excluded.
- **`ReviewPhotoProcessor`** (`app/Services/Reviews/ReviewPhotoProcessor.php`):
  - `prepare(UploadedFile $file): array`: Decodes JPEG/PNG/WebP, applies EXIF rotation, scales to max 2000px, creates 600px thumbnail, outputs WebP.
  - `store(Review $review, array $photos, ...): void`: Writes UUID-named WebP files to public disk and creates `ReviewMedia` records.
- **Campaign Mailables & Jobs**:
  - `NewsletterCampaignMail`: Renders markdown body via CommonMark (`Str::markdown`), strips raw HTML, enforces HTTPS links, inlines CSS styles.
  - `SendCampaignEmail`: Rate-limited campaign execution job checking campaign cancellation and subscriber opt-out prior to send.
- **Models**:
  - `NewsletterSubscriber`, `ConsentEvent`, `NewsletterCampaign`, `NewsletterCampaignSend`, `Review`, `ReviewMedia`.

## 5. External Boundaries & Failure Modes

- **Email Scanner Prefetch Protection**: Email security scanners prefetching links encounter a static confirmation screen on `GET`; consent confirmation strictly requires an interactive `POST` with CSRF or signed token validation.
- **Malicious Upload Mitigation**: Direct uploads are never stored raw. Re-encoding pixel buffers via GD strips malicious polyglots, steganographic payloads, and geolocation metadata before persisting to disk.
- **Abandoned Cart Race Conditions**: `SendAbandonedCheckoutReminder` executes a conditional atomic update on `reminder_sent_at` and checks for recent paid orders, preventing double-sends caused by delayed webhooks.
- **Rate-Limited Campaign Delivery**: Outgoing campaign batches obey `newsletter-send` rate limiters and configured send rates, preventing mail provider IP blacklisting.

# Email deliverability checklist

Manual steps that only you can do (DNS, mail provider, Stripe dashboard). Nothing here can be verified from the code.
Goal: order, shipping and account emails land in the inbox (not spam) and nobody can send mail pretending to be your shop.

Words you will see:

- **Sending domain** - the part after the `@` of `MAIL_FROM_ADDRESS` (e.g. `noreply@shop.example` -> `shop.example`).
- **Mail provider** - the service behind `MAIL_HOST` (Brevo, Postmark, Mailgun, Amazon SES, Google Workspace SMTP, ...). Every provider has a "Domains" / "Senders" / "Authentication" page. That page tells you the exact DNS records to create. Always copy the values from there; the examples below only show the shape.
- **DNS** - the settings page of the company where you bought the domain (or Cloudflare, if the domain points there).

Do the steps in order. Steps 1-4 take about an hour plus DNS propagation (minutes to a few hours).

---

## 0. Decide the three addresses

| Setting (Dokploy env) | Example | Purpose |
|---|---|---|
| `MAIL_FROM_ADDRESS` | `noreply@shop.example` | Sender of every automatic email (confirmation, shipped, account) and of the contact-form notification. Must be on **your own domain**. |
| `MAIL_SUPPORT_ADDRESS` | `support@shop.example` | A mailbox somebody reads. Printed in every automatic email. |
| `CONTACT_EMAIL` (optional) | `support@shop.example` | Where the website contact form delivers. Defaults to `MAIL_SUPPORT_ADDRESS`. |

Never use a free address (`@gmail.com`, `@outlook.com`) as `MAIL_FROM_ADDRESS`: Gmail/Outlook publish rules that forbid other servers from sending as them, so your mail would be rejected or sent to spam.

## 1. Verify the sender with your mail provider

1. In the provider dashboard add your **domain** (preferred) or at least the single sender `noreply@shop.example`.
2. The provider shows DNS records to add (steps 2 and 3). Add them, then press "Verify" / "Authenticate".
3. Wait until the dashboard shows the domain as **verified** (green). Until then mail may be rejected with "sender not verified".
4. Make the `noreply@` address real, otherwise bounces and some spam filters complain:
   - Best: create an alias `noreply@shop.example` that forwards to a mailbox you read (or to `/dev/null` if your host offers a "discard" alias). Replies are not meant to be answered, but a mailbox that exists is a trust signal.
   - Do **not** set a Reply-To on automatic mail (the app does not; the notice in the email points people to `MAIL_SUPPORT_ADDRESS` / the Contact page).

## 2. SPF - "which servers may send for my domain"

One TXT record on the sending domain (the root, e.g. `shop.example`):

```
Type: TXT     Name: @ (or shop.example)
Value: v=spf1 include:<spf-host-from-your-provider> ~all
```

- The `include:` part comes from your provider (e.g. `include:spf.brevo.com`, `include:amazonses.com`, `include:_spf.google.com`).
- **Only one SPF record per domain.** If one already exists (for example for Google Workspace), add the new `include:` to it instead of creating a second record: `v=spf1 include:_spf.google.com include:spf.brevo.com ~all`.
- Start with `~all` (soft fail). After DMARC has been clean for a month you can switch to `-all`.
- Max 10 `include:`/lookups in total.

## 3. DKIM - "this message really came from my domain and was not altered"

The provider gives you 1-3 records (CNAME or TXT), usually named like `selector1._domainkey.shop.example`. Create them exactly as shown. Nothing to configure in the app: the provider signs the mail on its servers.

Check: the provider's dashboard shows DKIM as verified.

## 4. DMARC - "what receivers should do when SPF/DKIM fail"

Create a TXT record named `_dmarc` on the sending domain. Roll out in stages; do not jump to the strict one.

| When | Record value | Effect |
|---|---|---|
| Day 0 | `v=DMARC1; p=none; rua=mailto:dmarc@shop.example; adkim=r; aspf=r` | Monitor only. Nothing is blocked. You receive daily XML reports at `rua`. |
| After 2-4 weeks with no legitimate mail failing | `v=DMARC1; p=quarantine; pct=25; rua=mailto:dmarc@shop.example` | Failing mail goes to spam for 25% of messages. Raise `pct` to 50, then 100 over a few weeks. |
| Optional, after 1-2 months clean | `v=DMARC1; p=reject; rua=mailto:dmarc@shop.example` | Failing mail is refused. |

- `rua=` must be an address that exists. The XML reports are unreadable by hand: use a free DMARC report reader (Postmark DMARC digest, dmarcian, EasyDMARC) by pointing `rua` at the address they give you.
- All of the shop's mail (orders, contact form, account emails) is sent **From `MAIL_FROM_ADDRESS`**, so it is aligned with SPF/DKIM by construction. The visitor of the contact form only appears in the display name and in Reply-To.
- If you also send newsletters or invoices from another tool on the same domain, add that tool to SPF/DKIM before moving beyond `p=none`.

## 5. Set the environment variables (Dokploy)

```
MAIL_MAILER=smtp
MAIL_HOST=...        MAIL_PORT=587     MAIL_SCHEME=smtp      # port 465 -> MAIL_SCHEME=smtps
MAIL_USERNAME=...    MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@shop.example
MAIL_FROM_NAME="Your Shop"
MAIL_SUPPORT_ADDRESS=support@shop.example
APP_URL=https://shop.example          # exact public https URL, see note below
APP_DEBUG=false
APP_ENV=production
```

Note on `APP_URL`: customers' "view your order" links are **signed** (valid 30 days). The signature covers the exact URL, so `APP_URL` must be the public `https://` address. A wrong scheme or host makes every link show "link not valid".

Also keep the queue worker and scheduler running (see `docs/reports/email-operations.md`): mail is sent by the worker, not by the website.

## 6. Test it

1. **Gmail "Show original"** - send yourself an order confirmation (Dokploy terminal on the `app` service: `php artisan email:test-order-confirmation your@gmail.com --create-mock-data --force-send`). In Gmail open the message -> three dots -> **Show original**. The top box must read `SPF: PASS`, `DKIM: PASS`, `DMARC: PASS`. Also look at the raw headers: there must be **no** `List-Unsubscribe` and no `X-Brand-*` header (transactional mail does not carry them).
2. **mail-tester.com** - open the site, copy the temporary address it shows, send the same test mail to it, click "Then check your score". Aim for 9/10 or 10/10. Typical fixes are listed in the report (missing DKIM, SPF typo, a link domain on a blocklist).
3. **Real order** - place a small order from a different mailbox (not Gmail only: also try Outlook/iCloud/Libero/Yahoo, the providers your Italian customers use) and confirm it arrives in the inbox, in the right language, with a working "view order" link.
4. Do not use `php artisan email:send-test`: it sends a joke text ("EMERGENZA CATANZARO") and would train spam filters on nonsense content from your domain.
5. Re-test after any DNS change.

If mail goes to spam: check SPF/DKIM/DMARC pass first (step 1), then the content (broken links, huge images, a different domain in links than in the sender), then warm up: send low volumes the first days.

## 7. Stripe dashboard

The shop relies on one Stripe webhook to create orders even when the customer closes the browser before returning to the site.

1. Dashboard -> Developers -> **Webhooks** -> *Add endpoint* (live mode, and again in test mode if you test with test keys).
2. Endpoint URL: `https://shop.example/stripe/webhook`
3. Events to send (select exactly these, nothing else is used):
   - `checkout.session.completed` - **required**: creates the order (paid, or pending for delayed payment methods) and triggers the confirmation email.
   - `checkout.session.async_payment_succeeded` - **required if delayed payment methods (SEPA debit, bank transfer...) are enabled**: pays the pending order, then confirmation email, admin alert and stock deduction.
   - `checkout.session.async_payment_failed` - closes a still-pending order (payment failed, order cancelled; no customer email).
   - `checkout.session.expired` - closes the pending order of an abandoned checkout and retires its checkout snapshot.
   - `payment_intent.succeeded` is **no longer used**: unsubscribe it if it was selected earlier (the app ignores it).
4. Open the endpoint -> **Signing secret** -> reveal -> copy `whsec_...` into `STRIPE_WEBHOOK_SECRET` in Dokploy and redeploy.
   - **In production the shop refuses every webhook while `STRIPE_WEBHOOK_SECRET` is empty** (otherwise anybody could post a fake "paid" event). If orders stop being created after a deploy, this is the first thing to check: the log shows `Stripe webhook refused: STRIPE_WEBHOOK_SECRET is not set`.
5. After the first real payment, open the endpoint in Stripe and check that deliveries show `200`. Stripe retries non-2xx answers for up to 3 days; the app creates each order only once even when an event is delivered several times.
6. **Stripe receipts**: Dashboard -> Settings -> Business -> *Customer emails* -> "Successful payments". Stripe only sends receipts in live mode. Choose one:
   - On (recommended at first): customers get Stripe's receipt (a proper payment receipt with the Stripe receipt link) *and* your order confirmation. Two emails, but you are covered if your own mail fails.
   - Off: only your confirmation is sent. Do this once you trust your own delivery (steps 1-6) and want a single email.
   Either way set your business name, support email and logo under Settings -> Branding so the receipt looks like your shop.

## 8. Ongoing

- Look at the DMARC reports weekly in the first month, then monthly.
- `php artisan queue:failed` and the `order_emails` table (see the operations runbook) show mail that could not be delivered.
- If you change mail provider: repeat steps 1-4 and update the SPF `include:`; keep the old DKIM records until old mail has expired.
- Logs: keep `LOG_STACK=daily` (and `LOG_DAILY_DAYS=14`) in production so personal data in logs is not kept forever; the default `single` file never rotates.

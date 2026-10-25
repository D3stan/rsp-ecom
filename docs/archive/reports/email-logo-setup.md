# Email logo

How the logo gets into the transactional emails, and how to check it. (The earlier version of this page described
sender-branding headers, an inline attachment and a header-adding provider; none of those exist any more. They were
removed on purpose: see `docs/reports/email-audit/delivery-infra.md`.)

## How it works

- The logo is a plain `<img>` in the shared layout `resources/views/emails/layouts/base.blade.php`, pointing at a public URL.
  There is **no inline attachment** and **no custom header** (`X-Brand-*`, `X-Sender-Image`, `List-Unsubscribe`, `X-Tag`,
  `X-Metadata-*` are never sent; `tests/Feature/Security/TransactionalMailHeadersTest.php` enforces it).
- Each mailable passes `logoUrl` from `App\Mail\Traits\HasLogo::getLogoUrl()`, which uses the first file that exists:
  `public/images/rlogo.png`, then `public/favicon.ico`, then `logo.svg` (there is no `public/logo.svg` today; keep `rlogo.png` in place).
  The layout itself falls back to `asset('images/rlogo.png')`. The URL is built from `APP_URL`, so it must be the public HTTPS address.
- The logo is shown at 36x36 px with alt text from `emails.common.logo_alt` (all five locales).

## Checking it

```bash
php artisan email:test you@example.com        # layout + logo, sent synchronously
php artisan email:send-test you@example.com   # minimal plain test mail
```

With `MAIL_MAILER=log` the rendered message ends up in `storage/logs/laravel.log` instead.
If the logo is missing in a client: check `APP_URL`, that `public/images/rlogo.png` is deployed and reachable without a login,
and that the client is not blocking remote images (many do until the reader allows it; nothing in the mail can change that).

## Sender picture next to the subject line

That avatar is chosen by the mailbox provider, not by headers in our mail. It depends on authentication (SPF, DKIM, DMARC; see
`docs/reports/email-deliverability-checklist.md`), sender reputation, and, for Gmail/Apple Mail, a BIMI record with a verified mark.
Nothing in this application can force it.

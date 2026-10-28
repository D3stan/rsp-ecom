---
paths:
  - app/Http/Controllers/Auth/*.php
  - app/Http/Middleware/AdminMiddleware.php
  - app/Http/Middleware/EnsureUserIsActive.php
  - app/Listeners/*.php
  - routes/auth.php
  - tests/Feature/Auth/*.php
---
# Auth

Sign-in, registration with pending email verification, Google login, listeners that run on login, and admin gating. Does not cover cart merge internals (`app/Services/CartMerger.php`) or abuse monitoring internals (`.ai/rules/ops-observability.md`).

## Entry points
- `routes/auth.php`: all auth routes; they are starter-kit style controllers in `app/Http/Controllers/Auth/`, not Fortify.
- `app/Http/Requests/Auth/LoginRequest.php`: credential check, `is_active` condition, login rate limit.
- `app/Http/Controllers/Auth/PendingUserVerificationController.php`: email verification that creates the account.
- `app/Http/Controllers/Auth/GoogleAuthController.php`: Google login, link, unlink.
- `app/Listeners/`: `ClaimGuestOrders`, `MergeGuestCartOnLogin`, `MonitorFailedLogin`, `RecordAdminLogin`.
- `routes/admin.php`, `app/Http/Middleware/AdminMiddleware.php`: admin gate.

## Rules
- MUST give new password registrations a `PendingUserVerification` row, not a `users` row; the user exists only after the signed link is opened. Enforced by `tests/Feature/Auth/PendingUserVerificationTest.php`.
- MUST reject an unsigned, tampered, wrong-token or expired verification link. Enforced by `PendingUserVerificationTest.php`.
- MUST auto-login after verification only the browser that registered, once, within the window. Enforced by `tests/Feature/Security/VerificationStatusLoginBypassTest.php`.
- MUST NOT let a deactivated account sign in or keep a session, whichever path it used. Enforced by `LoginRequest` (`is_active` condition) and `EnsureUserIsActive`; covered by `tests/Feature/Admin/CustomersTest.php`.
- MUST refuse Google unlink when the account has no password. Enforced by `tests/Feature/Auth/GoogleAuthTest.php` (unlink without password).
- MUST refuse linking a Google account already linked to another user. Enforced by `tests/Feature/Auth/GoogleAuthTest.php`.
- MUST keep every admin route under `auth`, `verified`, `admin` (group in `routes/admin.php`). Enforced by `tests/Feature/Admin/CustomersTest.php` (customer gets 403) and `ReviewMediaModerationTest.php` (guest redirected to login).
- MUST keep login and cart-merge listeners non-fatal: failures are `report()`ed, never block sign-in. Unenforced.
- MUST claim guest orders only for verified emails, case-insensitive match, idempotent. Enforced by `tests/Feature/Checkout/SavedAddressTest.php` (`claimGuestOrders`).
- NEVER add a sign-in path that skips `Auth::login`/`Auth::attempt`: `Login` event drives order claim, cart merge and admin audit. Unenforced; `tests/Feature/Cart/GuestCartMergeTest.php` covers password, verification and Google paths.
- MUST rate limit login at 5 failed attempts per email and IP key. Unenforced by tests (no test asserts the lockout).

## How it works
1. Register: validate, create `PendingUserVerification` (24 h token), remember this browser via `app/Support/RegistrationAutoLogin.php`, email the signed link; optional newsletter opt-in goes out alongside.
2. Verify: signed link creates the `User`, fires `Registered`, sends welcome mail, logs in.
3. Every successful login fires `Login`: `ClaimGuestOrders` attaches matching guest orders, `MergeGuestCartOnLogin` merges the browser's guest cart, `RecordAdminLogin` audits admins (a new IP also notifies Telegram).
4. A failed attempt fires `Failed`: `MonitorFailedLogin` feeds `AbuseMonitor` (watching only, never blocks).
5. Google callback order: logged-in user means link; known `google_id` means login; same email means link to that account and login (inactive account refused); matching pending registration means create user keeping the password chosen at registration; otherwise create a passwordless customer.

## Traps
- Cart merge reads the guest session id captured by `CaptureGuestSessionId`, not `Session::getId()`: the id is already rotated inside `Login`. Tested in `GuestCartMergeTest.php`.
- Google-created users have `password = null`; unlink therefore needs a password set first.
- Google-verified email sets `email_verified_at`, so an existing account linked by matching email becomes verified.
- Admin is `users.role === 'admin'`; there is no policy layer on top of the `admin` middleware.
- A guest cannot call the verification status endpoint to learn about arbitrary emails: it answers the same for every email unless the session owns it.

## Operate
- `php artisan auth:cleanup-pending [--force]` deletes expired pending registrations (scheduled daily 03:00); `auth:pending-status [--email=]` lists them.
- Env: Google OAuth client id, secret and redirect (`config/services.php`, `google` block).
- Throttles: resend 60/min, status poll 200/min, verification email and password confirm 6/min.

## Tests
- `tests/Feature/Auth/`: password login, registration, pending verification, Google, reset, confirm.
- `tests/Feature/Security/VerificationStatusLoginBypassTest.php`: auto-login only for the registering browser.
- `tests/Feature/Cart/GuestCartMergeTest.php`: merge on every login path.
- `tests/Feature/Ops/AbuseMonitorTest.php`: failed-login monitoring thresholds.

---
domain: auth-and-accounts
version: 1.0.0
budget_lines: 150
deep_reference: docs/logged-in-ui.md
---

# Authentication and Accounts Domain Specification

## 1. Scope & Responsibility

Governs customer and administrator identity lifecycles, password and social authentication (Google OAuth via Socialite), email verification flows (`PendingUserVerification`), role-based authorization (`AdminMiddleware`), customer address management (`Address`), and GDPR account deletion with order audit trail preservation.

## 2. Core Invariants

| Invariant | Enforcement Mechanism |
|---|---|
| Role-Based Route Isolation | Binary roles (`admin` and `customer`). `AdminMiddleware` protects all admin routes, requiring `auth()->user()->isAdmin()` and aborting with HTTP 403 on failure. |
| Sole Auth Provider Lockout Guard | Users cannot unlink their Google account (`unlinkGoogle()`) if no local password exists (`has_password: false`). Local password setup is required first. |
| Google Verification Auto-Trust | Accounts registered or linked via Google OAuth are automatically marked verified (`email_verified_at = now()`). |
| Pending Verification Expiry | Native registrations store temporary state in `pending_user_verifications` with a 24-hour token expiry (`token_expires_at = now()->addHours(24)`). Unverified records never create active `User` rows. |
| Order Retention on Deletion | Account deletion (`User::deleting`) never purges historical orders (`orders.user_id` set to `NULL`). Customer's `email` and `phone` are copied to `orders.guest_email` and `orders.guest_phone` before user deletion. |
| Order-Linked Address Preservation | Customer addresses referenced as `billing_address_id` or `shipping_address_id` in orders are retained; only orphan address records are removed upon account deletion. |
| Review Media Disk Cleanup | On user deletion, reviews are deleted individually rather than via bulk cascade, triggering `Review` model events to remove uploaded image files from the public storage disk. |
| Active User Gate | Accounts marked inactive (`is_active: false`) are denied authentication across both credential login and OAuth callbacks. |

## 3. State Machines & Transitions

```
[Standard Email Registration]
    Form Submission ──> PendingUserVerification (token valid 24h, password hashed)
                             │
                             ├── User clicks emailed verification link (token verified & not expired)
                             ▼
                        User::create() ──> is_active: true, role: 'customer', email_verified_at: now()
                             │
                             └── PendingUserVerification record deleted

[Google OAuth Authentication Flow]
    Redirect to Google ──> Socialite OAuth Callback:
        ├── User Already Logged In:
        │     ├── Google ID already linked to current user ──> return with info
        │     ├── Google ID linked to another user ──> reject with error
        │     └── Available ──> link google_id and avatar to current user
        ├── User Exists with Google ID ──> Auth::login($user)
        ├── User Exists with Email ──> link google_id, avatar, verify email, Auth::login($user)
        ├── Pending Verification Exists for Email ──> create verified User (keeping password), delete pending row
        └── New Visitor ──> create verified User (role: 'customer', password: null), Auth::login($user)

[Account Deletion & Data Retention]
    User::delete() triggered
        ├── Copy user email & phone to orders (guest_email, guest_phone) where null
        ├── Delete orphan addresses (whereDoesntHave billing/shipping orders)
        ├── Individually delete user reviews to purge review photo files from disk
        ├── Orders foreign key set to null (orders persist permanently)
        └── User record deleted
```

## 4. Key Contracts & Entry Points

- **`GoogleAuthController`** (`app/Http/Controllers/Auth/GoogleAuthController.php`):
  - `redirectToGoogle()`: Initiates OAuth flow with default browser user-agent compliance.
  - `handleGoogleCallback()`: Manages OAuth resolution, user linking, pending verification upgrading, and automatic verification.
  - `unlinkGoogle()`: Disconnects Google ID only if the user has a confirmed password.
- **`PendingUserVerification`** (`app/Models/PendingUserVerification.php`):
  - `generateToken(): string`: Creates secure 64-character verification token.
  - `isExpired(): bool`: Validates 24-hour expiration window.
  - `createVerifiedUser(): User`: Creates authenticated customer and purges temporary record.
- **`User` Model** (`app/Models/User.php`):
  - Roles: `isAdmin()`, `isCustomer()`, `scopeAdmins()`, `scopeCustomers()`.
  - Attributes: `has_password` (appended boolean), `is_active`, `notify_back_in_stock`.
  - Notifications: Localised queued mailables via `SendUserNotification` (`PasswordReset`, `VerifyEmail`).
  - Model hooks: `static::deleting` handles GDPR order snapshotting and cascade file cleanup.
- **`AdminMiddleware`** (`app/Http/Middleware/AdminMiddleware.php`):
  - Gatekeeper enforcing admin role authorization before route dispatch.
- **`Address` Model** (`app/Models/Address.php`):
  - Belongs to `User`; tracks `type` (`billing` / `shipping`), `is_default`, and relationships to orders.

## 5. External Boundaries & Failure Modes

- **Google User-Agent Policy Violations**: In compliance with Google's secure browser rules, custom HTTP clients or modified embedded user-agents are rejected, defaulting to native browser redirection.
- **Pending Verification Race**: If a customer registers via email and subsequently signs in with Google using the same email before confirming the link, Google auth consumes the pending verification and preserves the hashed password.
- **Orphan Orders Prevention**: The database schema enforces `onDelete('set null')` on `orders.user_id`, guaranteeing financial, accounting, and tax records persist even after GDPR account erasure.
- **Deactivated Accounts**: Deactivated users (`is_active: false`) attempting Google OAuth or password authentication are rejected with generic auth failure errors.

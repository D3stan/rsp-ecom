---
domain: settings-registry
version: 1.0.0
budget_lines: 150
deep_reference: docs/reference/admin-settings.md
---

# Settings Registry Domain Specification

## 1. Scope & Responsibility

Centralized configuration registry declaring and enforcing all administrative settings, operational thresholds, tax policies, carrier configurations, and feature flags. Governs specification declarations (`config/settings.php`), type validation (`SettingType`), audit tracking (`SettingChange`), versioned cache invalidation, runtime framework bridging (`ConfigBridge`), and public prop exposure to Inertia frontend consumers.

## 2. Core Invariants

| Invariant | Enforcement Mechanism |
|---|---|
| Single Catalogue Truth | `config/settings.php` is the sole authoritative definition registry. Unregistered keys throw `InvalidArgumentException` upon access or mutation. |
| Strict Access Controls | Definitions with `access: readonly` or `access: env` (e.g., `store.currency`) cannot be altered via admin UI or API, throwing `InvalidArgumentException`. |
| Hierarchical Precedence | Value resolution strictly follows: Customised Database Row (`settings` table) > Definition Default > Environment/Config Fallback. |
| Zero Secrets in Registry | Credentials, private API keys, and webhook secrets (e.g., Stripe, Mail, Spediamo) must never be stored as settings; they reside exclusively in `.env`. |
| Non-Destructive Audit Trail | All modifications write to `setting_changes` (`key`, `old_value`, `new_value`, `user_id`, `ip`, `created_at`). No-op edits (new value equals current value) are ignored. |
| Masked Sensitive Audit | Definitions marked `sensitive: true` record values as `***` in `setting_changes` and omit payloads from application logs. |
| Automatic Public Sharing | Settings flagged `public: true` in the catalogue are automatically exposed to the frontend via `Settings::public()` inside Inertia shared props. |
| Failure-Tolerant Resilience | Cache failures fall back transparently to database queries; database outages or missing tables fall back to definition defaults without throwing unhandled exceptions. |

## 3. State Machines & Transitions

```
[Catalogue Definition in config/settings.php]
    │
    ▼ (Initial State: default value loaded)
[Source: 'default']
    │
    ├── Admin updates setting: Settings::set($key, $value, $adminUser)
    │     ├── SettingDefinition validates value against rules & schema
    │     ├── Writes/updates JSON-encoded row in settings table
    │     ├── Inserts audit record in setting_changes table
    │     ├── Increments integer version in cache (settings:version)
    │     └── Triggers ConfigBridge to update config() dynamically
    ▼
[Source: 'customised']
    │
    ├── Admin resets setting: Settings::reset($key, $adminUser)
    │     ├── Deletes row from settings table
    │     ├── Records deletion in setting_changes
    │     ├── Bumps settings:version cache key
    │     └── Reverts bridged config() to its initial boot baseline
    ▼
[Source: 'default']
```

## 4. Key Contracts & Entry Points

- **Global Helper** (`app/Settings/helpers.php`):
  - `settings(?string $key = null, mixed $default = null): mixed`: Typed lookup; returns entire `Settings` instance if called without arguments.
- **`App\Settings\Settings`** (`app/Settings/Settings.php`):
  - `get(string $key): mixed`: Resolves typed value (int, float, bool, string, array) from memory/cache/DB.
  - `many(array $keys): array<string, mixed>`: Bulk retrieval with single-pass memory resolution.
  - `set(string $key, mixed $value, ?User $user = null): void`: Validates, persists, audits, and invalidates cache.
  - `setMany(array $keyValues, ?User $user = null): void`: Atomic transaction updating multiple settings.
  - `reset(string $key, ?User $user = null): void`: Purges customized DB row, restoring declared default.
  - `source(string $key): string`: Returns `'default'` or `'customised'`.
  - `public(): array<string, mixed>`: Typed array of all `public: true` settings for frontend serialization.
  - `flush(): void`: Invalidates settings cache by incrementing cache version.
- **`SettingsRegistry`** (`app/Settings/SettingsRegistry.php`):
  - Immutable catalogue parser validating definition integrity, types, constraints (`at_least`, `member_of`), and groups.
- **`ConfigBridge`** (`app/Settings/ConfigBridge.php`):
  - Dynamically overlays customized settings onto Laravel runtime configuration (`config()`) on boot and worker loops. Bridges: `contact.support_email -> mail.support_address`, `contact.form_recipient -> mail.contact_email`, `shipping.dispatch_days_* -> shipping.delivery_estimate.*`, `shipping.carriers -> shipping.carriers`, `tracking.auto_deliver -> shipping.tracking.auto_deliver`, `seo.default_description -> seotools.*`.
- **Authorization & Providers**:
  - `SettingsServiceProvider`: Registers bindings, config bridge hooks, and the `manage-settings` authorization gate.
- **`SettingType` Enum** (`app/Settings/SettingType.php`):
  - Native coercion and structural validation (`string`, `int`, `float`, `bool`, `list`, `countries`, `json`, `enum`).
- **Artisan Command**:
  - `php artisan settings:purge-legacy-secrets`: Purges deprecated database secret rows once environment variables are confirmed.

## 5. External Boundaries & Failure Modes

- **Long-Running Worker Cache Invalidation**: Horizon, queue workers, and Octane processes listen to `JobProcessing`, `Looping`, and `ScheduledTaskStarting` events to reconcile `settings:version`. When the version changes, in-memory caches and bridged configs refresh without worker restarts.
- **Missing Database Migrations**: During initial deployment or rollback steps where the `settings` table is unavailable, `Settings` catches query exceptions and returns hardcoded catalogue defaults.
- **Schema Validation Errors**: JSON-table settings (such as `shipping.carriers`) validate incoming rows against the definition's nested `schema` before writing, rejecting malformed structures via `ValidationException`.

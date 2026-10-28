---
paths:
  - app/Settings/**/*.php
  - config/settings.php
  - tests/Feature/Settings/*.php
  - tests/Unit/Settings/*.php
---
# Admin settings

DB-backed, admin-editable store settings declared in one catalogue and the bridge that overlays them onto `config()`. Does not cover secrets or deploy-time env (those stay in `.env`).

## Entry points
- `config/settings.php`: the catalogue (groups + definitions + `legacy_aliases`) and the full definition-key reference in its header. The source of truth for which keys exist.
- `app/Settings/Settings.php`: read/write service; `settings('group.name')` helper in `app/Settings/helpers.php`.
- `app/Settings/ConfigBridge.php`: overlays stored values onto config for definitions with `config_bridge`.
- `app/Http/Controllers/Admin/SettingsController.php` and `resources/js/pages/Admin/Settings/Index.tsx`: admin page, generated from the catalogue.
- `lang/<locale>.json`: `settings.<key>.label` and `settings.<key>.help` per key.

## Rules
- MUST declare every setting in `config/settings.php`; nothing else (service, validation, audit, admin page, shared props) is hand-written. Enforced by `tests/Unit/Settings/CatalogueTest.php`.
- MUST add `settings.<key>.label` and `.help` to every locale in `lang/*.json`. Enforced by `CatalogueTest.php`.
- NEVER store secrets (API keys, passwords, tokens) as settings; they live in `.env`. Unenforced.
- NEVER mark a definition `public` unless it is safe to expose to the browser (shared as the `settings` Inertia prop). Unenforced.
- MUST mark values whose content must not enter the audit trail `sensitive`. Unenforced.
- MUST read bridged values through `config()` in legacy code and settings through `settings()` in new code; do not read the `settings` table directly. Unenforced.
- MUST use `risk => 'high'` for settings that change money, tax or reachability. Unenforced.

## How it works
1. Boot: `SettingsServiceProvider` runs `ConfigBridge::apply()`, which writes customised values of `config_bridge` definitions into config; reset restores the pre-overlay value.
2. Defaults come from the definition; rows exist only once customised (or seeded, unless `seed => false`).
3. Values are cached as one array under a version key in the shared store; every write bumps the version.
4. Long-running processes re-check the version at most every `recheck_seconds`, and queue workers/scheduler at each job/tick, so changes reach them without restart.
5. `legacy_aliases` map old keys to new ones for deprecated `Setting::get/set` callers.

## Traps
- Env-derived defaults use `seed => false` so the seeder does not freeze an env value into the DB.
- `config/shipping.php` loads after `config/settings.php`, so the catalogue `require`s it directly; `config('shipping...')` is empty there.
- Empty bridged `shipping.sender_*` values do not overwrite a non-empty config baseline (ConfigBridge special case).
- A setting with `config_bridge` only works for code reading that config path at runtime; values captured at boot elsewhere stay stale.
- The `system` group is a read-only panel, not settings.

## Operate
- Groups: store, contact, social, tax, shipping, legal, compliance, orders, inventory, catalog, reviews, email, tracking, seo, newsletter, system.
- Env: `SETTINGS_CACHE_STORE` (null = default cache store), `SETTINGS_RECHECK_SECONDS` (default 5). Use a store shared by web, workers and scheduler.
- `php artisan settings:list [--group=tax]` shows key, value, source (no secrets).
- `php artisan settings:purge-legacy-secrets [--force]` deletes legacy credential rows (`stripe_*`, `paypal_*`) left in the settings table.
- `php artisan db:seed --class=SettingsSeeder` inserts missing rows only.

## Change recipes
Add a setting:
1. Append one array to `definitions` in `config/settings.php` (group must exist in `groups`); set `public`, `risk`, `depends_on`, `config_bridge` as needed.
2. Add `settings.<key>.label` and `settings.<key>.help` to all `lang/*.json`.
3. Read it with `settings('<key>')`, or via config if you set `config_bridge`.
4. Run `php artisan test --compact tests/Unit/Settings tests/Feature/Settings`.

Add a group: add it to `groups` with an icon name, and add `settings.group.<name>.label` and `.description` to every locale (enforced by `CatalogueTest.php`).

## Tests
- `tests/Unit/Settings/CatalogueTest.php`: catalogue validity (keys, groups, translations).
- `tests/Feature/Settings/SettingsServiceTest.php`: read/write, cache, validation.
- `tests/Feature/Settings/ConfigBridgeTest.php`: overlay and restore.
- `tests/Feature/Settings/AdminSettingsControllerTest.php`: admin page.
- `tests/Feature/Settings/SettingsCommandsTest.php`, `LegacyMigrationTest.php`, `LegacySettingsTest.php`: commands and legacy aliases.

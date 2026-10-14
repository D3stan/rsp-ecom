# WS-I: i18n sweep (hardcoded strings) + locale parity guard

> Branch `fix/i18n-sweep` from `fixes` **after all other workstreams are merged**. Wave 3. Read `00-overview.md` first;
> follow its per-item protocol. No migrations. **Size: M.**

### I1 (P1, H9): hardcoded English/Italian strings in storefront screens

**Claim (from the UX audit, re-verify each one; files may have moved since the earlier workstreams merged):** English
is hardcoded in `pages/auth/register.tsx`, `forgot-password.tsx`, `reset-password.tsx`, `verify-email`,
`verification-pending.tsx`, `email-verification-required.tsx`, `confirm-password`, `pages/settings/*`,
`pages/Checkout/Cancel.tsx` (all of it), `pages/Reviews/Create.tsx` (rating labels, buttons, breadcrumbs),
`components/wishlist-button.tsx`, PDP share toasts, cart empty state, home "Adding...", Success "Contact Support", header
"Menu"/"Language", and user menu "Settings"/"Log out". **Italian** is hardcoded in `components/VariantSelector.tsx`
("Variante", "Seleziona variante", "disponibili", "Esaurito").

**Repro:** a script/test that lists literal JSX text in `resources/js/pages` and `resources/js/components` (excluding
`pages/Admin/**` and `components/admin/**`). The simplest is ESLint with `eslint-plugin-i18next` `no-literal-string` in
`jsx-only` mode, scoped to storefront folders: commit the config first (it fails on the current code = the repro), then
fix until it passes. If the plugin is too noisy for attributes, restrict it to JSX text and `aria-label`/`title`/
`placeholder`/`alt`.

**Fix:** move every string to `lang/*.json` with real translations in all 5 locales. Use the `locale` from
`useTranslation()` for all `toLocaleDateString`/`Intl` calls (M11). Keep the lint rule in `eslint.config.js` so
regressions fail `npm run lint`.

### I2: final parity guard

C2 added the key-parity test. Make sure it passes over the merged result (all WS keys present in 5 locales) and that
`settings.*` labels/help exist for every key in `config/settings.php` (add a test that iterates the registry).

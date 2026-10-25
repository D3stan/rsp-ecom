---
domain: taxation-and-localization
version: 1.0.0
budget_lines: 150
deep_reference: docs/EU_Ecommerce_Legal_Pages.md
---

# Taxation and Localization Domain Specification

## 1. Scope & Responsibility

Governs value-added tax (VAT) computation, gross price tax extraction, Italian fiscal compliance, postal and province validations, and application-wide multi-locale resolution.

Key domain responsibilities include:
- Standardized VAT calculation and historical tax snapshotting on orders.
- Italian address validation, 5-digit postal code (CAP) normalization, and province code resolution.
- Italian electronic invoicing prerequisites (Codice Fiscale, SDI recipient codes, PEC).
- Multi-language dictionary hydration and fallback locale hierarchy.

## 2. Core Invariants

- **VAT-Inclusive Gross Pricing**: All catalog, cart, and shipping figures are treated as gross (inclusive of VAT) when `settings('tax.prices_include_tax', true)` is enabled. Tax is extracted from the gross amount, never added on top of quoted prices.
- **Italian Standard 22% VAT**: Default tax rate is 22.0% (`settings('tax.rate', 22)`). The VAT extraction formula is:
  $$\text{VAT} = \text{round}\left(\text{gross} \times \frac{\text{rate} / 100}{1 + (\text{rate} / 100)}, 2\right)$$
  When `tax.prices_include_tax` is disabled, tax rate is `null` and tax portion is `0.0`.
- **Historical Tax Snapshotting**: Orders permanently capture `orders.tax_rate` and `orders.tax_amount` upon creation. Changes to store settings or tax policies never modify historical orders or refund calculations.
- **Default Locale & Fallback**: Default application locale is Italian (`it`, configured in `config('app.default_locale')`). Supported locales are defined in `config('app.supported_locales')` (`['en', 'es', 'fr', 'de', 'it']`). Unspecified, empty, or unsupported locales strictly resolve to `it`.
- **Hierarchical Translation Layering**: Frontend and transactional email translations load from `lang/{locale}.json` and are recursively merged on top of `lang/en.json` (`Locales::translations`). Missing keys transparently fall back to English instead of displaying raw translation keys.
- **5-Digit Italian Postal Code (CAP)**: Italian postal codes must strictly match 5 numeric digits (`/^\d{5}$/`). Values starting with zero (e.g. Rome `00187`) must preserve zero-padding when mapped to external carrier payloads (`RecipientAddressCheck`, `SenderAddress`).
- **Italian Province Code Integrity**: For destinations in Italy (`IT`), the state field must match one of the 107 canonical two-letter uppercase province sigle defined in `ItalianProvinces::ALL` (e.g., `RM`, `MI`, `NA`, `RA`).
- **Fiscal Identity & E-Invoicing**: Italian B2C e-commerce orders require customer fiscal data for invoicing and courier transport documents. Private Italian individuals without an SDI destination code default to `fe_sdi="0000000"`. Codice Fiscale must contain 16 alphanumeric characters; Partita IVA contains 11 digits.

## 3. State Machines & Transitions

### Address Validation & Persistence Lifecycle
```
[ Checkout Form / User Input ]
       │
       ▼
[ AddressRules Validation ] ───(IT checks: 5-digit CAP, 2-letter Province sigla)
       │
       ▼
[ CheckoutSnapshot / Session ] ───(Frozen shipping_address data payload)
       │
       ▼
[ Address Model Created ] ───────(Linked via billing_address_id / shipping_address_id)
       │
       ├─► [ Customer Address Book ] (Promoted to is_default if customer opted in)
       │
       └─► [ Carrier Pre-Flight Check ] (RecipientAddressCheck flags issues before API call)
```

### Locale Resolution Lifecycle
1. Request Detection: Incoming locale is detected from route parameter, user session, or request header.
2. Validation: Checked via `Locales::isSupported($candidate)`.
3. Fallback: If invalid or omitted, resolves to `Locales::fallback()` (`it`).
4. Persistence: Recorded into `orders.locale` on order creation to preserve customer language across all future asynchronous emails and notifications.

## 4. Key Contracts & Entry Points

- `App\Support\Vat::includedIn(float $grossTotal): array{rate: float|null, amount: float}`
  Computes the tax rate and extracted tax portion for checkout summaries, orders, and receipts.
- `App\Support\Vat::portion(float $grossTotal, float $rate): float`
  Extracts the exact VAT portion for an explicitly provided rate, maintaining decimal consistency.
- `App\Support\Locales::supported(): array<int, string>` & `fallback(): string`
  Single source of truth for supported language codes and store default locale.
- `App\Support\Locales::translations(string $locale): array<string, mixed>`
  Hydrates frontend localization dictionaries with English baseline fallback.
- `App\Support\AddressRules::for(string $prefix = ''): array`
  Reusable Laravel validation rules for checkout forms and address book updates. Enforces Italian province and telephone constraints.
- `App\Support\ItalianProvinces::ALL`
  Canonical lookup dictionary of 107 Italian province codes and Italian province names.
- `App\Shipping\Support\RecipientAddressCheck::issues(RateAddress $recipient): array`
  Pre-flight verification for carrier shipments; validates Italian CAP regex and province code presence.
- `resources/js/lib/italian-address.ts: lookupItalianPostcode(cap: string)`
  Client-side asynchronous lookup against `/data/it-cap.json` to autofill city and province from a 5-digit CAP.

## 5. External Boundaries & Failure Modes

- **Invalid Italian Postal Code Rejection**: If an Italian address provides a non-5-digit postal code (e.g. `481` or letters), `RecipientAddressCheck` tags the field as `invalid`, aborting automated label generation before carrier rejection.
- **Client-Side Postal Code Database Outage**: If `/data/it-cap.json` fails to load in the browser, `lookupItalianPostcode` catches the network error and returns `null`, enabling manual entry without breaking checkout flow.
- **Missing Secondary Language Translations**: Incomplete JSON dictionary files in `lang/{locale}.json` fall back cleanly to English strings through deep array replacement, avoiding application crashes or raw UI key exposure.
- **Carrier Courier Format Mismatches**: Carrier APIs (e.g. Spediamo, DVAExpress) enforce strict schema constraints. Address transformers normalize province codes to uppercase, pad postal codes to 5 characters, and assign fallback fiscal identifiers (`0000000` SDI) to prevent carrier validation errors.

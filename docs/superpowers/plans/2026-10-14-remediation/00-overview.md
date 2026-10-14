# Consulting remediation: master plan

> **For agentic workers:** you implement ONE workstream file from this folder, in your own git worktree and branch.
> Read this overview first; the per-item protocol in §5 is **mandatory**. Steps use checkbox (`- [ ]`) syntax.

**Goal:** implement the agreed P0, P1 and P2 items from the three consulting reports
(`docs/reports/consulting/*.md`). Each fix must be proven necessary before code is changed, and proven fixed after.

**Architecture:** nine workstreams (WS-A … WS-I), each on its own branch cut from `fixes`. Waves follow the
dependencies: wave 1 runs in parallel; wave 2 starts after its prerequisites are merged; wave 3 is a final sweep.

**Tech stack:** Laravel 12, Inertia 2, React 19 + TypeScript, Tailwind 4, shadcn/Radix, Pest 3, Stripe via Cashier,
SQLite in-memory for tests (MySQL 8 in production).

---

## 1. Scope

### In scope

| Ref | Item | Workstream |
|---|---|---|
| P0-1 | Account deletion must not delete orders | A |
| P0-2 | Real Stripe refunds issued by the admin, refund status, "refund due" on paid cancellations, reconcile Stripe-dashboard refunds | D |
| P0-3 | Internal order notes leak to the customer's browser | A |
| P0-4 | Guest cart lost on login + redirect to checkout | B |
| P0-5 | Storefront never shows server flash messages | B |
| P0-6 | Category filter applies the previous selection (plus the price-filter param bug next to it) | B |
| P0-7 | Admin Orders/Products lists stuck on page 1 | A |
| P0-8 | Seeder creates `admin@example.com` / `password` in production | A |
| P1 | PDP hardcoded 0 stars / "(0 reviews)" | B |
| P1 | Wishlist heart starts unsaved, click removes saved item | B |
| P1 | Cart shows shipping box "Size" instead of the chosen variant | B |
| P1 | Header "Orders" link 404; account shows DB id instead of order number | B |
| P1 | Checkout lists no items; no `<form>`/autocomplete; no field-level errors | C |
| P1 | Success page spins forever when the order is not yet created | C |
| P1 | de/fr/es missing cart/checkout keys; no English fallback | C |
| P1 | Hardcoded English/Italian strings in storefront screens | I |
| P1 | Hero: 1.9 MB blurred PNG, duplicate image, reduced motion ignored, no lazy-loading | C |
| P1 | Ship: shortcut on unpaid orders, no tracking number, no label, two shipped emails. **Carrier layer modular; label creation optional, admin setting, default OFF** | G |
| P1 | Order editing: free status changes, Cancel without confirmation, payment/totals editable | D |
| P1 | Stock: product save overwrites concurrent sales | E |
| P1 | GPSR product-safety information on product pages | E |
| P2 | Photo reviews + consented review request | H |
| P2 | Free-shipping threshold as admin setting, default **1000 EUR** | C |
| P2 | Newsletter mechanics (double opt-in, consent log, campaigns, unsubscribe) | F |
| P2 | Abandoned-checkout reminders | F |
| P2 | Back-in-stock emails for items in **carts and wishlists** | E |

### Explicitly out of scope (do NOT implement)

- Admin "add review" form (F-14): leave untouched.
- **Self-service withdrawal / returns portal**: cancelled by the owner. Refunds are agreed with customer service by email and issued by the admin from the order page.
- Fitment finder / My Garage; "Complete the kit" bundles; extra payment methods; spec sheet and road-legality block.
- Destination VAT / OSS, invoices, credit notes (needs the accountant first).
- Anything in the reports not listed in the table above (customers area, health panel, CSV import, etc.).
  If you think something adjacent is necessary, **ask in your report**; don't build it.

---

## 2. Workstreams, branches and waves

| WS | Branch | Wave | Depends on | Size |
|---|---|---|---|---|
| A | `fix/data-integrity` | 1 | — | S×5 |
| B | `fix/storefront-core` | 1 | — | M (cart merge) + S×7 |
| C | `fix/checkout-postpurchase` | 1 | — | M (checkout) + S×4 |
| D | `feat/orders-money` | 1 | — | L |
| E | `feat/inventory-restock-gpsr` | 1 | — | M |
| F | `feat/newsletter-abandoned-checkout` | 1 | — | L |
| G | `feat/shipping-carriers` | 2 | D merged | L |
| H | `feat/photo-reviews` | 2 | B, D, F merged | M |
| I | `fix/i18n-sweep` | 3 | everything merged | M |

Every branch is cut from the **current tip of `fixes`** at the time the wave starts. After the coordinator verifies a
whole workstream, it merges the branch into `fixes` locally (no push). Wave-2/3 branches therefore contain the earlier work.

### Migration timestamp ranges (avoid collisions between parallel branches)

| WS | Prefix |
|---|---|
| A | `2026_10_15_100000` – `2026_10_15_109999` |
| C | `2026_10_15_300000` – `…309999` |
| D | `2026_10_15_400000` – `…409999` |
| E | `2026_10_15_500000` – `…509999` |
| F | `2026_10_15_600000` – `…609999` |
| G | `2026_10_16_100000` – `…109999` |
| H | `2026_10_16_200000` – `…209999` |

### Shared files that several branches touch

`config/settings.php`, `lang/*.json`, `routes/web.php`, `routes/admin.php`, `app/Listeners/StripeWebhookListener.php`,
`app/Http/Controllers/CheckoutController.php`, `resources/js/pages/product.tsx`, `resources/js/pages/Checkout/Details.tsx`.
Rules:
- Add, don't reorder. Put new settings at the **end of their group block**; put new lang keys inside your own
  top-level namespace where possible (e.g. `newsletter.*`, `returns.*`, `restock.*`).
- Don't reformat files you touch (no prettier/pint over whole unrelated files). Run `vendor/bin/pint` and
  `npx prettier --write` only on files you changed.
- Keep edits in shared React pages small: put new UI into new components and insert a single line in the page.

---

## 3. Conventions every workstream follows

- **Settings:** add definitions to `config/settings.php` (see its header comment). Every new key needs
  `settings.<key>.label` and `settings.<key>.help` in **all 5** `lang/*.json`. Read values with `settings('group.key')`.
- **Emails:** go through `App\Services\EmailService` and the queue. Order-bound emails use the `OrderEmail`
  dedupe table (add a `TYPE_*` constant and an `email.*` toggle). Non-order emails (newsletter, restock, abandoned
  checkout) get their own dedupe table in their workstream. Every marketing-type email has a working one-click
  unsubscribe (signed URL + `List-Unsubscribe` / `List-Unsubscribe-Post` headers).
- **Translations:** every user-visible string in new/changed storefront and email code goes through `t()` / `__()`,
  with keys in all 5 locales (en, it, de, fr, es), written as real translations, not English copies.
  `lang/*.json` is nested JSON; keep it valid and 4-space indented.
- **Money:** keep using the existing `decimal(10,2)` columns on existing tables. New money columns are **integer cents**.
- **Admin pages:** follow the patterns in `resources/js/pages/Admin/*` (shadcn components, `AdminLayout`).
  Destructive actions get a confirmation dialog (`AlertDialog`), not `window.confirm`.
- **Authorization:** new admin routes go in `routes/admin.php` inside the existing `auth + verified + admin` group.
  Add each new admin route to a 403-for-customers test.
- **Stripe in tests:** use the existing fake HTTP client in `tests/Feature/Orders/Support/BuildsStripeOrderFixtures.php`
  (`$this->stripeHttp->responses[...]`). Never call the real Stripe API.
- **Style:** match the surrounding code (comment density, naming). PHP: run `vendor/bin/pint <files>`.

---

## 4. Worktree setup (each agent, once)

The coordinator creates your worktree and branch. Inside it, run once:

```bash
cd <your worktree>
composer install --no-interaction --prefer-dist
cp ../../.env .env          # the main checkout's .env; tests use phpunit.xml (sqlite in-memory) anyway
npm ci --prefer-offline
./vendor/bin/pest           # baseline: must be green (801 passed, 1 skipped at plan time)
npm run types               # baseline: errors ONLY in resources/js/pages/Admin/Products/Create.tsx
```

If the baseline is not green, stop and report before changing anything.

---

## 5. Per-item protocol (MANDATORY)

Each workstream file lists items (A1, A2, …). Handle **one item at a time**:

1. **Verify the problem exists.** Read the cited code and confirm the claim yourself; don't trust the report.
   - **Bugs:** write a test that reproduces the bug and **fails on the current code for the stated reason**.
     Run it and keep the failing output. Commit the test alone:
     `git commit -m "test(<item>): reproduce <problem>"`. This is the **repro commit**.
   - **Frontend-only logic** (no PHP path): if the logic can be extracted into a pure TS function, test it with
     Vitest (set up in WS-B task B0). Otherwise write a **static reproduction** in your report: quote the exact
     lines and walk the execution step by step to the wrong outcome.
   - **New features:** show the capability doesn't already exist (grep evidence, cited files) and write the
     acceptance tests first (they fail because the feature is missing). Commit them as the repro commit.
   - **If you can't reproduce the problem:** don't change code. Report `NOT REPRODUCED` with your evidence and stop.
2. **Implement** the smallest change that fixes the root cause, as described in the item. Commit:
   `git commit -m "fix(<item>): …"` or `feat(<item>): …` (one or more commits).
3. **Verify:** the repro tests now pass; `./vendor/bin/pest` is fully green; `npm run types` has no new errors;
   `npx eslint <changed ts/tsx files>` is clean; `vendor/bin/pint --test <changed php files>` is clean.
4. **Report and STOP.** End your turn with a report in exactly this shape, then wait for the coordinator:

```
ITEM: <id> — <title>
STATUS: DONE | NOT REPRODUCED | BLOCKED
PROBLEM EVIDENCE: <file:line quotes + why it is wrong>
REPRO COMMIT: <sha>   (test file(s): …; failing output excerpt: …)
FIX COMMIT(S): <sha(s)>
WHAT CHANGED: <files + 1 line each>
TESTS: <new test names>; full suite: <N passed>; types: <ok / baseline only>
RISKS / FOLLOW-UPS: <anything the coordinator must know, incl. deviations from the plan>
```

The coordinator checks out the repro commit and confirms the tests fail, checks out the fix and confirms they pass,
runs the full suite and reviews the diff. You then get **ACCEPTED** (go on to the next item) or **REJECTED**
(with reasons; fix and report again). Don't start the next item before an ACCEPTED.

---

## 6. Coordinator verification checklist (per item)

- [ ] The claimed problem is real: repro test fails on the repro commit **for the stated reason** (read the failure,
      not only the exit code), or the static reproduction is correct when checked against the code.
- [ ] The test exercises real behaviour (no tautologies, no mocking of the thing under test, asserts the outcome
      the user would see).
- [ ] The fix addresses the root cause, not a symptom; no unrelated changes; no scope creep beyond §1.
- [ ] Full suite green on the fix commit; no new type errors; lint/pint clean.
- [ ] Translations present in all 5 locales for new strings; settings have labels/help.
- [ ] Security/privacy: authorization on new routes, no PII in logs, signed URLs where links are emailed.
- [ ] Verdict sent: ACCEPTED, or REJECTED with concrete reasons.

---
domain: frontend-core-and-ui
version: 1.0.0
budget_lines: 150
deep_reference: docs/RingMaskedImage_README.md
---

# Frontend Core and UI Domain Specification

## 1. Scope & Responsibility

Governs the React 19 / Inertia.js v2 client application architecture, shared prop contracts (`HandleInertiaRequests`), UI component design system (Radix UI / Shadcn / Tailwind CSS), SSR execution guardrails, flash notification toaster, and visual presentation engines (`RingMaskedImage`).

## 2. Core Invariants

| Invariant | Enforcement Mechanism |
|---|---|
| Inertia Shared Props Contract | `HandleInertiaRequests` provides standardized props across all page responses: `auth.user`, `settings` (public catalogue keys), `supportedLocales`, `flash` (`success`, `error`, `warning`, `id`), `csrf_token`, and `ziggy`. |
| Single-Shot Flash Notification | Flash messages include a unique UUID `flash.id`. Client toasters (`FlashToaster`) key off this ID to render toasts exactly once, preventing duplicate popups across Inertia partial reloads. |
| Strict SSR Safety Guardrails | Access to browser-only globals (`window`, `document`, `sessionStorage`, `localStorage`) is strictly guarded with `typeof window !== 'undefined'` or deferred inside `useEffect` to prevent SSR node execution crashes. |
| Radix & CVA Component Tokens | UI primitives wrap Radix UI slots, implement `class-variance-authority` (cva), combine styles via `cn(...)`, and consume semantic Tailwind design tokens (`primary`, `destructive`, `background`, etc.). |
| WebKit Mask FPS Guardrail | `RingMaskedImage` utilizes an inline SVG `<mask id="ringMask">` with GPU-accelerated CSS/transform rotation on group elements rather than dynamic path re-calculations to preserve 60 FPS on WebKit/iOS. |
| Accessibility Motion Honor | Visual animation components observe the `prefers-reduced-motion` media query, disabling continuous or scrub rotations and falling back to accessible static presentations. |
| Responsive Mask Centering | Animated ring masks scale relative to the image's shortest side (`innerRadiusPct`, `outerRadiusPct`), using `preserveAspectRatio="xMidYMid slice"` to maintain perfect centering across arbitrary aspect ratios. |

## 3. State Machines & Transitions

```
[Inertia Page Request & Navigation]
    <Link href="..."> or router.visit(...)
        │
        ├── Inertia progress indicator starts
        ├── HandleInertiaRequests resolves shared props (lazy admin badges, fresh flash UUID)
        ├── Dynamic page component resolves via Vite bundle
        ├── Shared props merged with page props
        └── DOM reconciled; FlashToaster triggers on new flash.id

[RingMaskedImage Animation Sequence]
    Component Mounts
        │
        ├── prefers-reduced-motion is TRUE ──> Render static mask slice at 12 o'clock
        │
        └── prefers-reduced-motion is FALSE
                 │
                 ├── Check sessionStorage intro flag with TTL (introTTLSeconds)
                 ├── Flag Valid ──> Skip intro sequence
                 └── Flag Missing or Expired:
                          ├── 1. Rotate clockwise 360°
                          ├── 2. Rotate counter-clockwise 360°
                          ├── 3. Stroke-dash fill from 12 o'clock anchor
                          └── Write timestamp to sessionStorage
                 │
                 ▼
    Enter Target Mode: 'continuous' (infinite transform rotation) or 'scroll' (viewport scrub sync)
```

## 4. Key Contracts & Entry Points

- **`HandleInertiaRequests` Middleware** (`app/Http/Middleware/HandleInertiaRequests.php`):
  - `share(Request)`: Injects core shared props: `auth.user`, `settings` (via `Settings::public()`), `flash` (with UUID), `adminPendingOrders` (lazy admin count), `supportedLocales`, and `ziggy`.
- **UI Primitives** (`resources/js/components/ui/*`):
  - Standardized Radix/CVA components: `Button`, `Dialog`, `Sheet`, `DropdownMenu`, `Card`, `Badge`, `Checkbox`, `Input`, `Select`, `Table`.
  - Polymorphic Composition: Buttons and interactive primitives support the Radix `asChild` slot pattern to merge behavior onto custom elements or Inertia `<Link>` components without invalid nested anchors.
- **Contexts & Translation**:
  - `useTranslation()` / localisation: Client interfaces consume server-provided translation strings matching the user's active locale from `supportedLocales`.
  - State contexts (`resources/js/contexts/*`): Maintain localized client state such as responsive drawer toggles and checkout stepper progression.
- **Helper Utilities** (`resources/js/lib/utils.ts`):
  - `cn(...inputs: ClassValue[]): string`: Tailwind class merger using `clsx` and `tailwind-merge`.
  - `formatCurrency(amount, currency, locale): string`: Internationalized monetary formatting (`Intl.NumberFormat`).
- **`RingMaskedImage`** (`resources/js/components/RingMaskedImage/RingMaskedImage.tsx`):
  - Props: `src`, `alt`, `innerRadiusPct`, `outerRadiusPct`, `sliceDegrees`, `engine` (`'framer'` | `'gsap'`), `mode` (`'continuous'` | `'scroll'`), `baseOpacity`, `sessionKey`, `introTTLSeconds`.
  - Engines: `useFramerEngine.ts` (preferred Framer Motion controller) and `useGsapEngine.ts` (GSAP / ScrollTrigger controller).
- **Layout Shells** (`resources/js/layouts/*`):
  - `AppLayout`: Standard storefront shell with header, cart drawer, footer, and flash toaster.
  - `AdminLayout`: Administrative shell with collapsible sidebar (`sidebarOpen` cookie state) and pending order counter badge.

## 5. External Boundaries & Failure Modes

- **CSRF Token Stalling**: When forms remain idle and session tokens expire, background operations recover the session token by invoking the `/csrf-token` route.
- **Vite Asset Desynchronization**: If new frontend assets are deployed while a user has an active session, Inertia detects asset version mismatches (`HandleInertiaRequests::version`) and initiates a hard window reload.
- **Hardware Acceleration Bottlenecks**: Animating full SVG masks via stroke-dasharray on high-DPI iOS devices causes frame drops; the intro fill runs only once per TTL window, transitioning to transform-only group rotations for steady rendering.

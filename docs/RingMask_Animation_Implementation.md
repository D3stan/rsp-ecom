# Ring-Masked Image Animation (React + Inertia, TypeScript + Tailwind)
**Author:** Your team • **Stack:** React + Inertia (Vite), TypeScript, Tailwind • **Engines:** Framer Motion (preferred) or GSAP (interchangeable)  
**Purpose:** Animate a ring-shaped mask over an image to modulate the image’s opacity. Supports:
- **Continuous rotation (clockwise, 2s/turn)**  
- **Scroll-synced rotation (linear to viewport)**  
- **Intro sequence** (first visit): clockwise 1x → counterclockwise 1x → anchored-top **fill** (slice grows from 12 o’clock).  
- **Mobile-first** responsiveness and `prefers-reduced-motion`

---

## TL;DR (What you’re building)
An **inline SVG** that contains the image (`<image>`) and a **mask** (`<mask>`) that defines per-pixel opacity. The mask has:
- A **base luminance** (e.g. 0.35) that dims the image outside the ring-slice.
- A **ring-slice** that is at luminance **1.0** (fully visible).

We animate either:
- The **rotation of the ring-slice group** (GPU-friendly), or
- The **dash array** on the ring stroke (to create the “fill from top” effect).

The entire unit is a single SVG, so it’s perfectly **responsive** and **crisp** on all screens. The ring is **centered**; its dimensions are provided via props in **percent of the image’s shorter side** so it scales with aspect-ratio changes.

---

## Why SVG Mask (not CSS mask or Canvas)
- **Cross-browser & crisp:** Inline SVG `<mask>` is robust on iOS Safari / Android Chrome. No bitmap blur.
- **Performance:** We rotate a vector **group** using transforms (no per-frame path recalculation except for the fill step).
- **Responsiveness:** SVG `viewBox` + `preserveAspectRatio="xMidYMid slice"` makes the image cover the SVG while keeping the ring centered regardless of aspect ratio.

> **Note:** The image is included as `<image>` inside the SVG. This avoids CSS mask quirks and guarantees perfect alignment of ring to image across device sizes.

---

## Installation
```bash
# Framer Motion (preferred)
npm i framer-motion

# GSAP + ScrollTrigger (alternate engine)
npm i gsap

# Types (in case you need them explicitly)
npm i -D @types/gsap
```
Tailwind is already in the project. No extra Tailwind plugin is required.

---

## Component Overview
Create a reusable component that renders the SVG + mask and exposes props for dimension & behavior. It supports two animation engines:

- **engine="framer"** (default): uses Framer Motion for rotation and scroll-sync.
- **engine="gsap"**: uses GSAP (and ScrollTrigger for scroll-sync).

### Files
```
resources/
  js/
    Components/
      RingMaskedImage/
        RingMaskedImage.tsx          # main component (engine switch + SVG mask structure)
        engines/
          useFramerEngine.ts         # framer-based hooks/controls
          useGsapEngine.ts           # gsap-based hooks/controls
        styles.css                   # (optional) minimal CSS for container/aspect
```

> **Why hooks per engine?** Keeps `RingMaskedImage.tsx` clean. Either hook wires up rotation/scroll/intro, the mask structure is identical.

---

## Prop API (TypeScript)
```ts
type Engine = 'framer' | 'gsap';

type Mode = 'continuous' | 'scroll'; // continuous = 2s/turn clockwise; scroll = linear to viewport

export interface RingMaskedImageProps {
  /** Image source and alt text */
  src: string;
  alt?: string;

  /** Engine selection (default: 'framer') */
  engine?: Engine;

  /** Animation mode (default: 'continuous') */
  mode?: Mode;

  /** Ring geometry as PERCENT of the image’s shorter side (0–100) */
  innerRadiusPct: number;   // e.g., 36 (you will provide exact values per image)
  outerRadiusPct: number;   // e.g., 42 (must be > innerRadiusPct)

  /** Slice size (in degrees). For continuous rotate, this is the visible wedge size. */
  sliceDegrees?: number;    // default: 60

  /** Opacity mapping for mask luminance (tunable) */
  baseOpacity?: number;     // default: 0.35    (outside the slice)
  ringOpacity?: number;     // default: 1.0     (inside the slice)

  /** Rotation speed and direction for continuous mode */
  secondsPerTurn?: number;  // default: 2
  clockwise?: boolean;      // default: true

  /** Scroll behavior (linear to viewport) */
  // 1 turn per viewport height: a full 360° rotation as the user scrolls one screen height.
  turnsPerViewport?: number; // default: 1

  /** Intro sequence control (first visit w/ TTL) */
  introEnabled?: boolean;    // default: true
  introTTLSeconds?: number;  // default: 86400 (1 day)
  sessionKey?: string;       // default: 'ringIntroSeen'

  /** Accessibility */
  respectReducedMotion?: boolean; // default: true

  /** Styling */
  className?: string;        // wrapper container classes
}
```
**Notes**
- **Percent units** tie the ring to the image’s **shorter side** so the ring remains circular and centered on any aspect ratio.
- For the intro **“fill from top”**, the start edge is anchored at 12 o’clock; the visible arc length grows by animating `stroke-dasharray` (not rotation).

---

## SVG & Mask Details (mental model)
- We use a square `viewBox="0 0 100 100"`.
- The image fills the SVG via `<image ... preserveAspectRatio="xMidYMid slice">` (cover behavior).
- The mask comprises two layers:
  1) **Base**: A full-coverage rect at **`fill="white"` + `fill-opacity={baseOpacity}`** (dims the image globally).
  2) **Ring slice**: A stroked circle (`r = (inner+outer)/2`, `strokeWidth = (outer - inner)`), with
     - `stroke-dasharray` set to the **arc length** of the slice,
     - `stroke-linecap="round"` for “angel eyes” ends,
     - `fill="none"`, `stroke="white"`, and **`stroke-opacity={ringOpacity}`**.
- For **rotation**, we wrap the stroked circle in a `<g id="sliceGroup">` centered at the SVG middle and rotate that group.
- For the **fill** intro step, we keep the group **unrotated** and animate the **dash array** from `0 → slice length` while keeping the dash **start at 12 o’clock** via `stroke-dashoffset` logic.

**Circumference math**
- `radiusPx = (innerRadiusPct + outerRadiusPct) / 2` (in viewBox units since viewBox = 100 → units behave like %).
- `strokeWidth = (outerRadiusPct - innerRadiusPct)` (same units).
- `circumference = 2 * π * radiusPx`.
- `sliceLength = circumference * (sliceDegrees / 360)`.  
We use these to set `stroke-dasharray` and handle the fill step precisely.

---

## Engine Behavior

### 1) Framer Motion (preferred)
- **Continuous rotation:** animate `rotate: 0 → 360` with `duration = secondsPerTurn`, `ease: 'linear'`, `repeat: Infinity`.
- **Scroll-synced rotation:** use **`useScroll`** to read viewport progress and **`useTransform`** to map progress to degrees:
  - If `turnsPerViewport = 1`, then degrees = `scrollY / viewportHeight * 360`.
  - Apply this to the `rotate` style of the `sliceGroup` via a `motion.g` wrapper. No easing; it should be linear.
- **Intro sequence:** use `useAnimationControls()` to chain:
  1. Rotate **clockwise 1x** (360°),
  2. Rotate **counterclockwise 1x** (back −360°),
  3. **Fill from top**: keep rotation at 0° and animate `stroke-dasharray` from `0 → sliceLength` while maintaining the dash start at 12 o’clock (control `strokeDasharray`/`strokeDashoffset` via React state & Framer’s `animate` prop).
  - After completion, store `{ timestamp: Date.now() }` under `sessionKey` and begin the selected **mode** (continuous or scroll).

### 2) GSAP (alternate engine)
- **Continuous rotation:** `gsap.to('#sliceGroup', { rotation: 360, transformOrigin: '50% 50%', duration: secondsPerTurn, ease: 'none', repeat: -1 })` (invert sign for counterclockwise if needed).
- **Scroll-synced rotation:** use **ScrollTrigger** with `scrub: true` and a start/end equal to 1 viewport height (e.g., pin a container or simply map `end: '+=100%'`).
- **Intro sequence:** chain three tweens on a timeline:
  1. `to('#sliceGroup', { rotation: 360, duration: 2, ease: 'none' })`
  2. `to('#sliceGroup', { rotation: 0, duration: 2, ease: 'none' })` (or `-=360`)
  3. **Fill:** tween a React state or a CSS variable that controls `stroke-dasharray` from `0 → sliceLength` while rotation is fixed at 0°. On complete, set session flag and start selected mode.

---

## Session Logic (first-visit intro with TTL)
- On mount, read `sessionStorage.getItem(sessionKey)` (JSON with `{timestamp}`).
- If **missing** or **expired** (now − timestamp ≥ `introTTLSeconds * 1000`), run the intro sequence; then set the value with current timestamp.
- Else, skip intro and start the selected mode immediately.

---

## Accessibility: `prefers-reduced-motion`
- If `respectReducedMotion` is **true** and the media query matches, **disable continuous rotation** and **disable scroll-scrub** (or drastically slow them). Still render the static ring at the top (12 o’clock) with the slice visible.

```css
@media (prefers-reduced-motion: reduce) {
  /* In JS, gate animations; CSS here is illustrative in case you add CSS-based motion */
}
```

---

## Styling & Layout (Tailwind)
- Wrap the SVG in a square container that can scale responsively (mobile-first priority). Example approach:
  - Give the wrapper `w-full` and set a **square aspect** via Tailwind’s `aspect-square`.
  - For image prominence on phones, place the wrapper in a container sized with `clamp()` to avoid it exceeding screen width: e.g., `style={{ width: 'clamp(220px, 60vmin, 420px)' }}`.
- The SVG itself should be `w-full h-auto`.  
- The **image** inside SVG uses `preserveAspectRatio="xMidYMid slice"` so it **covers** the SVG area. This is like `object-fit: cover` for `<img>`. Aspect ratios of source images can vary—this ensures the ring remains centered and sized from the **shorter side**, keeping it perfectly circular.

> **You (product) will supply exact `innerRadiusPct` and `outerRadiusPct` values per image**. E.g., `inner=36`, `outer=42`. Because they are % of the shorter side, the ring stays aligned as the image scales.

---

## Implementation Steps

1. **Create the folder structure** above.
2. **Build `RingMaskedImage.tsx` scaffold**:
   - Render a responsive wrapper (`div`) and inside it an **inline SVG** with `viewBox="0 0 100 100"`.
   - Place the `<image>` inside the SVG and wrap it in a `<g>` with a `mask="url(#ringMask)"` attribute.
   - Define the `<mask id="ringMask">` that contains:
     - `<rect width="100" height="100" fill="white" fill-opacity="{baseOpacity}"/>` (global base dim)
     - The **slice** as a `<circle>` with:
       - `cx="50" cy="50" r="{(inner+outer)/2}"`
       - `stroke="white"` + `stroke-opacity="{ringOpacity}"`
       - `stroke-width="{outer - inner}"`
       - `stroke-linecap="round"`
       - `fill="none"`
       - `pathLength="1"` (optional, if you prefer normalized dash math)
   - Compute **circumference** and **sliceLength** from props to set initial `stroke-dasharray` and `stroke-dashoffset`.
   - Wrap the circle in a `<g id="sliceGroup">` centered at `50,50`. This group is what we rotate for the continuous/scroll modes.

3. **Engine switch**:
   - Accept `engine` prop (`'framer' | 'gsap'`, default `'framer'`).
   - Import the respective hook from `engines/` and initialize it with references to:
     - The **slice group** (for rotation)
     - The **circle element** (for dash-based fill)
     - The container element (for scroll calculations if needed)
   - The hook returns **start/stop** handlers or simply runs effects on mount/unmount based on `mode`, `introEnabled`, and session flags.

4. **Framer engine (`useFramerEngine.ts`)**:
   - Use `useReducedMotion()` to honor `prefers-reduced-motion`.
   - If intro should run:
     - **Step 1:** rotate `sliceGroup` 0 → 360 (`duration = secondsPerTurn`, `ease: 'linear'`).
     - **Step 2:** rotate back 360 → 0 (same duration).
     - **Step 3 (fill):** keep rotation at 0; animate `strokeDasharray` of the circle from `0 → sliceLength` while maintaining `strokeDashoffset` so the start is at 12 o’clock. When complete, write session flag and proceed.
   - If mode is **continuous**, animate rotation: `repeat: Infinity`, `ease: 'linear'`, `duration = secondsPerTurn`. If `clockwise = true`, rotate positive; otherwise negative.
   - If mode is **scroll**, wire `useScroll()` to the **viewport** progress and map it with `useTransform` to degrees: `degrees = scrollProgress * (360 * turnsPerViewport)`. Apply this to a `motion.g` wrapper for `sliceGroup`.

5. **GSAP engine (`useGsapEngine.ts`)**:
   - If intro should run, create a timeline with the three steps as above (use `onComplete` to write session flag).
   - For **continuous**, `gsap.to('#sliceGroup', { rotation: 360, transformOrigin: '50% 50%', ease: 'none', repeat: -1, duration: secondsPerTurn })`.
   - For **scroll**, register `ScrollTrigger` and set a trigger on the container: `start: 'top top'`, `end: '+=100%'`, `scrub: true`; tween rotation across that range to `360 * turnsPerViewport` degrees.

6. **Edge cases / math sanity**:
   - Ensure `outerRadiusPct > innerRadiusPct`. Clamp both to `[0, 50]` since the SVG half-size is 50 (viewBox units).
   - `sliceDegrees` must be `> 0`. If you want a very thin highlight, set small degrees, e.g., 8–12.
   - When computing `sliceLength`, use radians: `circ = 2 * Math.PI * r`. Avoid float drift by storing both `circ` and `sliceLength` in refs.

7. **Performance**:
   - Rotational animation uses **transform** on the group (GPU-friendly). Avoid recalculating paths each frame.
   - For the **fill** step (which changes dash), it runs **once** per first visit—acceptable cost.
   - Keep Gaussian blur/glow off the mask (not needed). If you ever add a visible ring overlay, keep blurs subtle on mobile.

8. **Testing checklist**:
   - **Mobile** (iOS Safari, Android Chrome): verify smooth rotation at 2s/turn; check the intro sequence once per TTL window.
   - **Aspect ratios**: feed portrait, landscape, and square images. Confirm the ring remains centered and circular, aligned to the image’s **short side**.
   - **Reduced motion**: toggle via OS settings—ensure animations become static.
   - **Scroll** mode: verify 1 full turn per viewport height (or your chosen `turnsPerViewport`), linear—no easing.
   - **Session TTL**: set small TTL in dev (e.g., 10s) and confirm behavior expires/refreshes properly.

---

## Example Usage (props you will likely set)
> These are **example prop values** based on the product input. Adjust per image.

```ts
<RingMaskedImage
  src="/images/hero.jpg"
  alt="Product hero"
  engine="framer"              // or "gsap"
  mode="continuous"            // or "scroll"
  innerRadiusPct={36}          // you provide exact values
  outerRadiusPct={42}
  sliceDegrees={60}
  baseOpacity={0.35}
  ringOpacity={1.0}
  secondsPerTurn={2}
  clockwise={true}
  turnsPerViewport={1}
  introEnabled={true}
  introTTLSeconds={86400}      // 1 day
  sessionKey="ringIntroSeen"
  respectReducedMotion={true}
  className="mx-auto w-full max-w-sm"
/>
```

---

## FAQ

**Q: What is `clamp()` and should we use it?**  
**A:** `clamp(min, preferred, max)` is a CSS function to keep size responsive within bounds. We recommend using it on the container width (e.g., `clamp(220px, 60vmin, 420px)`) so phones get priority sizing without the ring exploding on large desktops.

**Q: What is `mix-blend-mode`?**  
**A:** A CSS property that defines how an element blends with the backdrop. If you add a **visible** glow/overlay ring later (outside the mask), `mix-blend-mode: screen` can make it “light up” content behind it. It’s **not needed** for the mask itself.

**Q: How do aspect ratios affect alignment?**  
**A:** The SVG uses a square `viewBox`. The **image** is drawn inside with `preserveAspectRatio="xMidYMid slice"` (cover), so it may crop on one axis but remains centered. Our ring radii are **percent of the shorter side**, keeping the ring circular and centered across all aspect ratios.

---

## Optional Enhancements (future)
- Add a **prop** to show a **visible** ring overlay (not just mask) for branding effects (gradient stroke, subtle glow).
- Expose a **debug** prop that outlines the ring geometry and shows current `sliceDegrees`, `dasharray`, and computed radii on screen.
- Provide a **`triggerOnce`** prop for scroll mode to complete exactly one turn as the section enters the viewport, then stop.

---

## Done Criteria
- Fully responsive, mobile-first ring-masked image.
- Framer Motion engine works (continuous + scroll + intro).
- GSAP engine works (continuous + scroll + intro).
- `prefers-reduced-motion` respected.
- Session-based intro plays once per TTL window.
- Developer can tune **opacity**, **slice size**, **radii**, **speed**, **direction**, **scroll ratio** via props.

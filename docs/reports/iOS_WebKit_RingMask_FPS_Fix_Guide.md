# iOS/WebKit Low FPS — Ring‑Masked Image: Fix Guide
**Important:** Apply **one fix at a time** and **test on real Apple devices** (iOS Safari and macOS Safari). Only keep a fix if it measurably improves FPS. If not, **revert** and move to the next section.

This guide targets the common performance pitfall where an **SVG `<mask>` applied to an `<image>`** is animated on iOS/macOS and the browser re-rasterizes the masked bitmap every frame.

---

## Fix 1 — Switch mask to **alpha** + use **CSS transforms** on the rotating group
### Why this helps
WebKit paths are faster when a mask relies on alpha rather than luminance, and when animations use **CSS transforms** (composited) instead of SVG **attribute** transforms.

### Edits to make
1) **Change the mask to alpha**
- In your `<mask id="...">`, add a style: `style={{ maskType: 'alpha' }}`.

2) **Promote the rotating `<g>` to a composited layer**
- Add a class to the `<g ref={sliceGroupRef}>`, e.g. `className="sliceGroup"`.
- In CSS:
  ```css
  .sliceGroup {
    transform-box: fill-box;
    transform-origin: 50% 50%;
    will-change: transform;
  }
  ```

3) **Ensure your engine uses CSS transforms (not attribute transforms)**
- Framer/GSAP should set `style={{ rotate: ... }}` or `element.style.transform = 'rotate(...)'` rather than `setAttribute('transform', 'rotate(...)')`.

### How to test
- On iPhone: open Safari, use Mac Safari **Remote Debugging** to check FPS if possible.
- Compare with/without this fix on the same page, same device.

### Rollback
- If no improvement, revert `maskType` and the CSS transform if it causes rendering differences (rare).

---

## Fix 2 — **iOS-specific strategy**: keep the mask static; animate the **stroke-dashoffset** instead of rotating
### Why this helps
Rotating a group that contains the mask often forces WebKit to re-composite the full masked image. Changing a **single stroke property** (`stroke-dashoffset`) is much cheaper and remains smooth on iOS.

### Edits to make
1) **Detect iOS/WebKit** (only for runtime behavior switch):
   ```ts
   const isIOSWebKit =
     typeof navigator !== 'undefined' &&
     (
       /iP(hone|ad|od)/.test(navigator.platform || '') ||
       (/Mac/.test(navigator.platform || '') && typeof document !== 'undefined' && 'ontouchend' in document)
     );
   ```

2) **When `engine='framer'` and `performanceMode==='auto'` and `isIOSWebKit === true`:**
   - **Do not rotate** the `<g>` after the intro.
   - Instead, **animate the circle’s `strokeDashoffset`**:
     - Continuous mode (2s/turn, clockwise): decrease `dashoffset` by `circumference * time / secondsPerTurn` (mod `circumference`).
     - Scroll mode (linear to viewport): `dashoffset = dashOffsetTop - scrollProgress * turnsPerViewport * circumference`.

3) **Intro sequence stays as-is** (your fill-from-top step already animates `stroke-dasharray`); only change the **post-intro** behavior on iOS.

### How to test
- Confirm iOS now renders at ~60fps (or device max). Desktop/Android should be unchanged.

### Rollback
- If artifacts appear (gaps at the ends), slightly **increase `sliceDegrees`** or **reduce stroke width** by 1–2 units.

---

## Fix 3 — Purge **attribute transforms**; prefer **CSS transforms** everywhere
### Why this helps
On iOS, `setAttribute('transform', 'rotate(...)')` on SVG nodes under a mask is frequently **not** GPU-accelerated. CSS transforms are far more likely to be composited.

### Edits to make
- Replace any vanilla `element.setAttribute('transform', 'rotate(...)')` with a CSS transform (`element.style.transform = 'rotate(...)'`) or use Framer/GSAP CSS-based rotation.
- Keep `transform-origin: 50% 50%` and `transform-box: fill-box` on the rotating group.

### How to test
- Force the app into your “vanilla” or “fallback” path and re-check FPS.

### Rollback
- None; CSS transform is generally safer for perf.

---

## Fix 4 — Reduce **image decode/composite** cost and repaint area
### Why this helps
Safari still has to composite the image beneath the mask. Oversized images and large repaint regions increase the per-frame burden.

### Edits to make
1) **Serve right-sized images** for mobile (via `srcset/sizes` or just smaller assets):
   - Target 1.0–1.5× of the rendered pixel size of the SVG on phones.

2) **Constrain component size** (mobile-first):
   - Wrap with a size clamp: `style={{ width: 'clamp(220px, 60vmin, 420px)' }}` or Tailwind utilities to similar effect.
   - Use `aspect-square` on the container when possible.

3) **Avoid heavy effects inside the mask**:
   - No Gaussian blur in the mask; keep `stroke-linecap="round"` but avoid extremely thick strokes on low-end devices.

### How to test
- Compare with smaller image vs original; profile memory and FPS.

### Rollback
- None; this is generally beneficial.

---

## Fix 5 — WebKit-only fallback: **CSS `-webkit-mask` with gradients**
### Why this helps
A pure CSS mask on a standard HTML element (no inline SVG) avoids some of the costliest SVG mask paths in WebKit.

### What to implement
- Replace the SVG mask **only on iOS** with a wrapper `<div>` containing your `<img>` and apply:
  - A **radial-gradient** to form the ring thickness.
  - A **conic-gradient** to form the rotating slice.
  - Combine with `-webkit-mask-composite` to keep only the ring slice.
  - Animate a custom property `--angle` for rotation; use `background: conic-gradient(...)` for the moving wedge.

### How to test
- iOS only: confirm smoothness.
- Non-iOS: keep your existing SVG path.

### Rollback
- Remove platform switch if the CSS fallback looks different from SVG on some assets.

---

## Fix 6 — (Optional) Trim visual stressors
### Why this helps
Strokes with very large widths and perfect round caps on small radii can produce subpixel “sparkle” on iOS. Easing those values slightly can improve stability.

### Edits to try
- Reduce `strokeWidth` by ~5–10%.
- Increase `sliceDegrees` by ~3–5° to hide cap artifacts.
- Cap FPS by running the animation at 30fps for old devices (rarely needed).

---

## Testing Checklist
- **Devices:** iPhone (recent iOS), iPad if relevant, macOS Safari, plus Android/Chrome as control.
- **Modes:** Continuous (2s/turn) and Scroll-synced (1 turn / viewport).
- **Intro:** Plays once per TTL window, then switches to selected mode.
- **A11y:** `prefers-reduced-motion` stops rotation; ring renders at 12 o’clock.
- **Visuals:** Slice start locked at top; no gaps; opacity mapping looks correct.
- **Perf:** No significant frame drops while scrolling or when tabs are backgrounded/foregrounded.

---

## Where to change in your code (quick pointers)
- The **mask and ring elements** (base rect and circle) live inside the SVG `defs` and the masked `<g>` that draws the `<image>`.
- The **rotating group** is the `<g ref={sliceGroupRef}>` around the `<circle>`; this is where CSS transforms or the dashoffset strategy apply.
- Any **attribute transforms** should be replaced with **CSS transforms** or the dashoffset approach on iOS.
- Image sizing & container clamps live in the **wrapper** around the SVG.

---

## Rollout Strategy
1. Ship **Fix 1**; test on iOS real device.
2. If still janky: **revert Fix 1** (if needed) and ship **Fix 2** (iOS dashoffset strategy).
3. If still janky: ensure **Fix 3** is in place (purge attribute transforms).
4. Then apply **Fix 4** (smaller images + constrained area).
5. If none of the above: implement **Fix 5** (WebKit CSS mask fallback).
6. Optionally tune **Fix 6** for final polish.

> Keep each fix in a separate commit with a toggle (feature flag or prop) so you can quickly A/B on devices.

# Ring Masked Image Component

A React component that creates an animated ring-shaped mask over an image to create stunning visual effects. Perfect for product showcases, hero sections, and interactive elements.

## Features

- **Multiple Animation Engines**: Supports both Framer Motion and GSAP
- **Animation Modes**: Continuous rotation and scroll-synced animation
- **Intro Sequence**: Eye-catching first-visit animation with session-based TTL
- **Responsive Design**: Mobile-first approach with percentage-based dimensions
- **Accessibility**: Respects `prefers-reduced-motion` settings
- **Graceful Degradation**: Falls back to vanilla JavaScript if animation libraries aren't available

## Installation

### Required Dependencies

Before using this component, you need to install the animation libraries:

```bash
# Install Framer Motion (preferred engine)
npm install framer-motion

# Install GSAP (alternate engine)
npm install gsap

# Install TypeScript types for GSAP (dev dependency)
npm install -D @types/gsap
```

### Component Files

The component is located in `resources/js/components/RingMaskedImage/` with the following structure:

```
RingMaskedImage/
├── RingMaskedImage.tsx          # Main component
├── engines/
│   ├── useFramerEngine.ts       # Framer Motion engine
│   └── useGsapEngine.ts         # GSAP engine
├── styles.css                   # Optional CSS utilities
└── index.ts                     # Export file
```

## Usage

### Basic Example

```tsx
import { RingMaskedImage } from '@/components/RingMaskedImage';

function ProductShowcase() {
  return (
    <div className="w-80 mx-auto">
      <RingMaskedImage
        src="/images/product.jpg"
        alt="Product showcase"
        innerRadiusPct={36}
        outerRadiusPct={42}
        sliceDegrees={60}
        mode="continuous"
        engine="framer"
      />
    </div>
  );
}
```

### Advanced Configuration

```tsx
<RingMaskedImage
  src="/images/hero.jpg"
  alt="Hero image with ring animation"
  engine="gsap"                    // 'framer' | 'gsap'
  mode="scroll"                    // 'continuous' | 'scroll'
  innerRadiusPct={30}              // Inner radius (% of shorter side)
  outerRadiusPct={40}              // Outer radius (% of shorter side)
  sliceDegrees={90}                // Visible slice size in degrees
  baseOpacity={0.25}               // Opacity outside the ring
  ringOpacity={1.0}                // Opacity inside the ring
  secondsPerTurn={3}               // Speed for continuous mode
  clockwise={false}                // Rotation direction
  turnsPerViewport={1.5}           // Rotations per viewport height (scroll mode)
  introEnabled={true}              // Enable intro sequence
  introTTLSeconds={86400}          // Intro TTL (24 hours)
  sessionKey="customIntroKey"      // Session storage key
  respectReducedMotion={true}      // Honor accessibility preferences
  className="ring-masked-image-mobile"
/>
```

## Props API

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `src` | `string` | Required | Image source URL |
| `alt` | `string` | `""` | Image alt text |
| `engine` | `'framer' \| 'gsap'` | `'framer'` | Animation engine to use |
| `mode` | `'continuous' \| 'scroll'` | `'continuous'` | Animation mode |
| `innerRadiusPct` | `number` | Required | Inner radius (% of shorter side) |
| `outerRadiusPct` | `number` | Required | Outer radius (% of shorter side) |
| `sliceDegrees` | `number` | `60` | Visible slice size in degrees |
| `baseOpacity` | `number` | `0.35` | Opacity outside the ring |
| `ringOpacity` | `number` | `1.0` | Opacity inside the ring |
| `secondsPerTurn` | `number` | `2` | Rotation speed for continuous mode |
| `clockwise` | `boolean` | `true` | Rotation direction |
| `turnsPerViewport` | `number` | `1` | Rotations per viewport (scroll mode) |
| `introEnabled` | `boolean` | `true` | Enable intro sequence |
| `introTTLSeconds` | `number` | `86400` | Intro sequence TTL in seconds |
| `sessionKey` | `string` | `'ringIntroSeen'` | Session storage key |
| `respectReducedMotion` | `boolean` | `true` | Honor accessibility preferences |
| `className` | `string` | `""` | Additional CSS classes |

## Animation Modes

### Continuous Rotation

The ring slice rotates continuously at a specified speed:

```tsx
<RingMaskedImage
  src="/images/product.jpg"
  mode="continuous"
  secondsPerTurn={2}
  clockwise={true}
  // ... other props
/>
```

### Scroll-Synced Animation

The ring rotation is tied to the scroll position:

```tsx
<RingMaskedImage
  src="/images/hero.jpg"
  mode="scroll"
  turnsPerViewport={1}
  // ... other props
/>
```

## Intro Sequence

On first visit (or after TTL expires), the component plays a special intro sequence:

1. **Clockwise rotation**: Full 360° rotation
2. **Counterclockwise rotation**: Returns to starting position
3. **Fill animation**: Ring slice grows from 12 o'clock position
4. **Normal mode**: Begins the selected animation mode

The intro sequence is controlled by session storage and respects the TTL setting.

## Accessibility

The component automatically respects the `prefers-reduced-motion` media query. When reduced motion is preferred:

- Continuous animations are disabled
- Scroll-synced animations are disabled or significantly slowed
- The ring remains static at the 12 o'clock position

## Responsive Design

The component uses percentage-based dimensions tied to the image's shorter side, ensuring:

- The ring remains perfectly circular on any aspect ratio
- Mobile-first responsive behavior
- Crisp rendering on all screen densities

### Recommended Sizing Classes

```css
/* Mobile-first approach */
.ring-masked-image-mobile {
  width: clamp(220px, 60vmin, 420px);
  margin: 0 auto;
}

/* Desktop sizing */
.ring-masked-image-desktop {
  width: clamp(300px, 40vmin, 600px);
  margin: 0 auto;
}
```

## Technical Implementation

### SVG Mask Approach

The component uses inline SVG with `<mask>` elements for precise control:

- **Base layer**: Controls overall image opacity
- **Ring slice**: Creates the highlighted area
- **Transform animations**: GPU-accelerated rotation

### Engine Fallbacks

1. **Framer Motion**: Primary engine with smooth animations
2. **GSAP**: Alternative engine with robust features
3. **Vanilla JavaScript**: Fallback using `requestAnimationFrame`

## Browser Support

- **Modern browsers**: Full feature support
- **Safari**: Excellent SVG mask support
- **Internet Explorer**: Not supported (requires SVG mask support)

## Performance Considerations

- Rotation animations use CSS transforms (GPU-accelerated)
- Dash array animations only during intro sequence
- Minimal DOM manipulation
- Efficient scroll event handling with `requestAnimationFrame`

## Troubleshooting

### Dependencies Not Found

If you see import errors for Framer Motion or GSAP:

1. Install the required packages: `npm install framer-motion gsap`
2. The component will gracefully fall back to vanilla JavaScript

### Animation Not Working

1. Check that `prefers-reduced-motion` isn't reducing animations
2. Verify the container has proper dimensions
3. Ensure the image source is valid and accessible

### Performance Issues

1. Reduce the `turnsPerViewport` for scroll mode
2. Increase `secondsPerTurn` for slower continuous animation
3. Consider disabling the intro sequence for better initial load performance

## Examples

See `RingMaskedImageDemo.tsx` for comprehensive usage examples and configurations.

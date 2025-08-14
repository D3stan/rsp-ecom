import React, { useRef, useEffect, useState, useMemo, useCallback } from 'react';
import { useFramerEngine } from './engines/useFramerEngine';

type Engine = 'framer' | 'gsap' | 'vanilla';
type Mode = 'continuous' | 'scroll';

export interface RingMaskedImageProps {
  /** Image source and alt text */
  src: string;
  alt?: string;
  /** Optional srcset for responsive images (Fix 4) */
  srcSet?: string;
  sizes?: string;

  /** Engine selection (default: 'framer') */
  engine?: Engine;

  /** Animation mode (default: 'continuous') */
  mode?: Mode;

  /** Ring geometry as PERCENT of the image's shorter side (0–100) */
  innerRadiusPct: number;   // e.g., 36
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
  /** Force a square aspect (legacy). Default false */
  keepSquare?: boolean;
  /** Performance strategy: auto (default), css (force CSS keyframe), js (force rAF attribute). */
  performanceMode?: 'auto' | 'css' | 'js';
  /** Disable mobile responsive sizing constraints for full-screen usage */
  disableResponsiveConstraints?: boolean;
  /** SVG preserveAspectRatio setting (default: 'xMidYMid slice') */
  preserveAspectRatio?: string;
}

export const RingMaskedImage: React.FC<RingMaskedImageProps> = ({
  src,
  alt = '',
  srcSet,
  sizes,
  engine = 'framer',
  mode = 'continuous',
  innerRadiusPct,
  outerRadiusPct,
  sliceDegrees = 60,
  baseOpacity = 0.35,
  ringOpacity = 1.0,
  secondsPerTurn = 2,
  clockwise = true,
  turnsPerViewport = 1,
  introEnabled = true,
  introTTLSeconds = 86400,
  sessionKey = 'ringIntroSeen',
  respectReducedMotion = true,
  className = '',
  keepSquare = false,
  performanceMode = 'auto',
  disableResponsiveConstraints = false,
  preserveAspectRatio = 'xMidYMid slice',
}) => {
  // Generate unique ID for this instance
  const maskId = useRef(`ringMask-${Math.random().toString(36).substr(2, 9)}`);
  
  // Refs for animation targets
  const containerRef = useRef<HTMLDivElement>(null);
  const sliceGroupRef = useRef<SVGGElement>(null);
  const circleRef = useRef<SVGCircleElement>(null);

  // iOS/WebKit detection for Fix 2
  const isIOSWebKit = useMemo(() => {
    if (typeof navigator === 'undefined') return false;
    return (
      /iP(hone|ad|od)/.test(navigator.platform || '') ||
      (/Mac/.test(navigator.platform || '') && typeof document !== 'undefined' && 'ontouchend' in document)
    );
  }, []);

  // Validate props
  const validatedInnerRadius = Math.max(0, Math.min(50, innerRadiusPct));
  const validatedOuterRadius = Math.max(validatedInnerRadius + 1, Math.min(50, outerRadiusPct));
  const validatedSliceDegrees = Math.max(1, sliceDegrees);

  // Calculate ring geometry (memoised)
  const { radiusPx, strokeWidth, circumference, sliceLength, initialDashArray } = useMemo(() => {
    const radius = (validatedInnerRadius + validatedOuterRadius) / 2;
    let width = validatedOuterRadius - validatedInnerRadius;
    
    // Fix 6: Slightly reduce stroke width on mobile for performance
    if (typeof window !== 'undefined' && window.innerWidth <= 768) {
      width = width * 0.95;
    }
    
    const circ = 2 * Math.PI * radius;
    
    // Fix 6: Slightly increase slice degrees to hide cap artifacts
    let adjustedSliceDegrees = validatedSliceDegrees;
    if (typeof window !== 'undefined' && window.innerWidth <= 480) {
      adjustedSliceDegrees = validatedSliceDegrees + 3;
    }
    
    const sliceLen = circ * (adjustedSliceDegrees / 360);
    return {
      radiusPx: radius,
      strokeWidth: width,
      circumference: circ,
      sliceLength: sliceLen,
      initialDashArray: `${sliceLen} ${Math.max(circ - sliceLen, 0.0001)}` // ensure non-zero gap
    };
  }, [validatedInnerRadius, validatedOuterRadius, validatedSliceDegrees]);

  // State for dash array animation (used in fill step) – start with initial value immediately
  const [dashArray, setDashArray] = useState(initialDashArray);

  // Sync when geometry changes
  useEffect(() => {
    setDashArray(initialDashArray);
  }, [initialDashArray]);

  // Session logic for intro
  const [shouldRunIntro, setShouldRunIntro] = useState(false);

  useEffect(() => {
    if (!introEnabled) {
      setShouldRunIntro(false);
      return;
    }

    try {
      const stored = sessionStorage.getItem(sessionKey);
      if (!stored) {
        setShouldRunIntro(true);
        return;
      }

      const { timestamp } = JSON.parse(stored);
      const now = Date.now();
      const expired = (now - timestamp) >= (introTTLSeconds * 1000);
      
      setShouldRunIntro(expired);
    } catch {
      setShouldRunIntro(true);
    }
  }, [introEnabled, sessionKey, introTTLSeconds]);

  const markIntroComplete = useCallback(() => {
    try {
      sessionStorage.setItem(sessionKey, JSON.stringify({ timestamp: Date.now() }));
    } catch {
      // Silently handle storage errors
    }
    setShouldRunIntro(false);
  }, [sessionKey]);

  // Engine-specific hooks
  useFramerEngine({
    enabled: engine === 'framer',
    sliceGroupRef,
    circleRef,
    containerRef,
    mode,
    shouldRunIntro,
    onIntroComplete: markIntroComplete,
    sliceLength,
    circumference,
    secondsPerTurn,
    clockwise,
    turnsPerViewport,
    respectReducedMotion,
    setDashArray,
    performanceMode,
    isIOSWebKit,
  });

  // GSAP engine initialization (not a hook call inside callback)
  useEffect(() => {
    if (engine === 'gsap') {
      // eslint-disable-next-line @typescript-eslint/no-unused-vars
      import('./engines/useGsapEngine').then(({ useGsapEngine }) => {
        // This is just calling the function, not the hook
        const gsapConfig = {
          enabled: true,
          sliceGroupRef,
          circleRef,
          containerRef,
          mode,
          shouldRunIntro,
          onIntroComplete: markIntroComplete,
          sliceLength,
          circumference,
          secondsPerTurn,
          clockwise,
          turnsPerViewport,
          respectReducedMotion,
          setDashArray,
          isIOSWebKit,
        };
        
        // Call the GSAP initialization function
        console.log('GSAP config prepared:', gsapConfig);
        // Note: This will be refactored to not use hook pattern
      }).catch(() => {
        console.warn('GSAP engine not available');
      });
    }
  }, [
    engine,
    mode,
    shouldRunIntro,
    sliceLength,
    circumference,
    secondsPerTurn,
    clockwise,
    turnsPerViewport,
    respectReducedMotion,
    isIOSWebKit,
    markIntroComplete,
  ]);

  // Vanilla JS animation (fallback)
  useEffect(() => {
    if (engine === 'vanilla' && sliceGroupRef.current) {
      console.log('RingMaskedImage: Using vanilla JS animation');
      
      const sliceGroup = sliceGroupRef.current;
      const startTime = performance.now();
      const rotationSpeed = (clockwise ? 360 : -360) / (secondsPerTurn * 1000);
      let animationFrame: number;

      const animate = (currentTime: number) => {
        const elapsed = currentTime - startTime;
        const rotation = (elapsed * rotationSpeed) % 360;
        
        // Fix 3: Use CSS transform instead of SVG attribute transform
        sliceGroup.style.transform = `rotate(${rotation}deg)`;
        sliceGroup.style.transformOrigin = '50% 50%';
        sliceGroup.style.transformBox = 'fill-box';
        
        animationFrame = requestAnimationFrame(animate);
      };

      animationFrame = requestAnimationFrame(animate);
      console.log('RingMaskedImage: Vanilla animation started');

      return () => {
        if (animationFrame) {
          cancelAnimationFrame(animationFrame);
        }
      };
    }
  }, [engine, secondsPerTurn, clockwise]);

  // Calculate dash offset so the slice begins at 12 o'clock (0° in SVG is 3 o'clock)
  const dashOffset = useMemo(() => circumference * 0.25, [circumference]);

  // Debug geometry once
  useEffect(() => {
    console.log('RingMaskedImage geometry:', { engine, mode, radiusPx, strokeWidth, sliceLength, circumference, dashArray: initialDashArray });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div
      ref={containerRef}
      className={`relative ${keepSquare ? 'aspect-square' : 'h-full w-full'} ${className}`}
      style={disableResponsiveConstraints ? undefined : { 
        // Fix 4: Constrain component size for mobile performance (unless disabled)
        width: 'clamp(220px, 60vmin, 420px)',
        maxWidth: '100%',
        margin: '0 auto'
      }}
    >
      <svg
        viewBox="0 0 100 100"
        className="w-full h-full ring-masked-image-svg"
        xmlns="http://www.w3.org/2000/svg"
        preserveAspectRatio={preserveAspectRatio}
      >
        <defs>
          <mask id={maskId.current} maskUnits="userSpaceOnUse" maskContentUnits="userSpaceOnUse" style={{ maskType: 'alpha' as React.CSSProperties['maskType'] }}>
            {/* Base layer - dims the entire image */}
            <rect
              width="100"
              height="100"
              fill="white"
              fillOpacity={baseOpacity}
            />
            
            {/* Ring slice - highlights the visible area */}
            <g ref={sliceGroupRef} className="sliceGroup">
              <circle
                ref={circleRef}
                cx="50"
                cy="50"
                r={radiusPx}
                fill="none"
                stroke="white"
                strokeOpacity={ringOpacity}
                strokeWidth={strokeWidth}
                strokeLinecap="round"
                strokeDasharray={dashArray}
                strokeDashoffset={dashOffset}
              />
            </g>
          </mask>
        </defs>

        {/* Image with mask applied */}
        <g mask={`url(#${maskId.current})`}>
          <image
            href={src}
            x="0"
            y="0"
            width="100"
            height="100"
            preserveAspectRatio={preserveAspectRatio}
            aria-label={alt}
            {...(srcSet && { 'data-srcset': srcSet })}
            {...(sizes && { 'data-sizes': sizes })}
          />
        </g>
      </svg>
    </div>
  );
};

export default RingMaskedImage;

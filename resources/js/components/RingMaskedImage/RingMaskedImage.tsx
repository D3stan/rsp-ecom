import React, { useRef, useEffect, useState } from 'react';
import { useFramerEngine } from './engines/useFramerEngine';
// import { useGsapEngine } from './engines/useGsapEngine';

type Engine = 'framer' | 'gsap';
type Mode = 'continuous' | 'scroll';

export interface RingMaskedImageProps {
  /** Image source and alt text */
  src: string;
  alt?: string;

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
}

export const RingMaskedImage: React.FC<RingMaskedImageProps> = ({
  src,
  alt = '',
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
}) => {
  // Refs for animation targets
  const containerRef = useRef<HTMLDivElement>(null);
  const sliceGroupRef = useRef<SVGGElement>(null);
  const circleRef = useRef<SVGCircleElement>(null);

  // Validate props
  const validatedInnerRadius = Math.max(0, Math.min(50, innerRadiusPct));
  const validatedOuterRadius = Math.max(validatedInnerRadius + 1, Math.min(50, outerRadiusPct));
  const validatedSliceDegrees = Math.max(1, sliceDegrees);

  // Calculate ring geometry
  const radiusPx = (validatedInnerRadius + validatedOuterRadius) / 2;
  const strokeWidth = validatedOuterRadius - validatedInnerRadius;
  const circumference = 2 * Math.PI * radiusPx;
  const sliceLength = circumference * (validatedSliceDegrees / 360);

  // State for dash array animation (used in fill step)
  const [dashArray, setDashArray] = useState(`0 ${circumference}`);

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

  const markIntroComplete = () => {
    try {
      sessionStorage.setItem(sessionKey, JSON.stringify({ timestamp: Date.now() }));
    } catch {
      // Silently handle storage errors
    }
    setShouldRunIntro(false);
  };

  // Engine-specific hooks
  const framerEngine = useFramerEngine({
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
  });

  // GSAP engine with dynamic import
  useEffect(() => {
    if (engine === 'gsap') {
      import('./engines/useGsapEngine').then(({ useGsapEngine }) => {
        useGsapEngine({
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
        });
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
  ]);

  // Calculate dash offset to start at 12 o'clock
  const dashOffset = circumference * 0.25; // Start at top (12 o'clock position)

  return (
    <div ref={containerRef} className={`w-full aspect-square ${className}`}>
      <svg
        viewBox="0 0 100 100"
        className="w-full h-auto"
        xmlns="http://www.w3.org/2000/svg"
      >
        <defs>
          <mask id="ringMask">
            {/* Base layer - dims the entire image */}
            <rect
              width="100"
              height="100"
              fill="white"
              fillOpacity={baseOpacity}
            />
            
            {/* Ring slice - highlights the visible area */}
            <g ref={sliceGroupRef} style={{ transformOrigin: '50px 50px' }}>
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
                pathLength="1"
              />
            </g>
          </mask>
        </defs>

        {/* Image with mask applied */}
        <g mask="url(#ringMask)">
          <image
            href={src}
            x="0"
            y="0"
            width="100"
            height="100"
            preserveAspectRatio="xMidYMid slice"
          />
        </g>
      </svg>
    </div>
  );
};

export default RingMaskedImage;

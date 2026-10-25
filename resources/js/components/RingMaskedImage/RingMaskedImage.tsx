import React, { useRef, useEffect, useState, useMemo, useCallback } from 'react';
import { useFramerEngine } from './engines/useFramerEngine';

type Engine = 'framer' | 'gsap' | 'vanilla';
type Mode = 'continuous' | 'scroll';

export interface RingMaskedImageProps {
  src: string;
  alt?: string;
  srcSet?: string;
  sizes?: string;

  engine?: Engine;

  mode?: Mode;

  innerRadiusPct: number;
  outerRadiusPct: number;

  sliceDegrees?: number;

  baseOpacity?: number;
  ringOpacity?: number;

  secondsPerTurn?: number;
  clockwise?: boolean;

  turnsPerViewport?: number;

  introEnabled?: boolean;
  introTTLSeconds?: number;
  sessionKey?: string;

  respectReducedMotion?: boolean;

  className?: string;
  keepSquare?: boolean;
  performanceMode?: 'auto' | 'css' | 'js';
  disableResponsiveConstraints?: boolean;
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
  const maskId = useRef(`ringMask-${Math.random().toString(36).substr(2, 9)}`);

  const containerRef = useRef<HTMLDivElement>(null);
  const sliceGroupRef = useRef<SVGGElement>(null);
  const circleRef = useRef<SVGCircleElement>(null);

  const isIOSWebKit = useMemo(() => {
    if (typeof navigator === 'undefined') return false;
    return (
      /iP(hone|ad|od)/.test(navigator.platform || '') ||
      (/Mac/.test(navigator.platform || '') && typeof document !== 'undefined' && 'ontouchend' in document)
    );
  }, []);

  const validatedInnerRadius = Math.max(0, Math.min(50, innerRadiusPct));
  const validatedOuterRadius = Math.max(validatedInnerRadius + 1, Math.min(50, outerRadiusPct));
  const validatedSliceDegrees = Math.max(1, sliceDegrees);

  const { radiusPx, strokeWidth, circumference, sliceLength, initialDashArray } = useMemo(() => {
    const radius = (validatedInnerRadius + validatedOuterRadius) / 2;
    let width = validatedOuterRadius - validatedInnerRadius;

    if (typeof window !== 'undefined' && window.innerWidth <= 768) {
      width = width * 0.95;
    }

    const circ = 2 * Math.PI * radius;

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
      initialDashArray: `${sliceLen} ${Math.max(circ - sliceLen, 0.0001)}`
    };
  }, [validatedInnerRadius, validatedOuterRadius, validatedSliceDegrees]);

  const [dashArray, setDashArray] = useState(initialDashArray);

  useEffect(() => {
    setDashArray(initialDashArray);
  }, [initialDashArray]);

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
    }
    setShouldRunIntro(false);
  }, [sessionKey]);

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

  useEffect(() => {
    if (engine === 'gsap') {
      // eslint-disable-next-line @typescript-eslint/no-unused-vars
      import('./engines/useGsapEngine').then(({ useGsapEngine }) => {
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

        console.log('GSAP config prepared:', gsapConfig);
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

  const dashOffset = useMemo(() => circumference * 0.25, [circumference]);

  useEffect(() => {
    console.log('RingMaskedImage geometry:', { engine, mode, radiusPx, strokeWidth, sliceLength, circumference, dashArray });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div
      ref={containerRef}
      className={`relative ${keepSquare ? 'aspect-square' : 'h-full w-full'} ${className}`}
      style={disableResponsiveConstraints ? undefined : {
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

            <rect
              width="100"
              height="100"
              fill="white"
              fillOpacity={baseOpacity}
            />

            <g ref={sliceGroupRef} className="sliceGroup">

              {Array.from({ length: 40 }, (_, i) => {
                const progress = i / 39;
                const segmentOpacity = Math.pow(Math.sin(progress * Math.PI), 0.8) * ringOpacity;
                const extendedSliceLength = sliceLength * 1.6;
                const segmentLength = extendedSliceLength / 40;
                const segmentOffset = dashOffset + (segmentLength * i * 0.9);

                return (
                  <circle
                    key={i}
                    cx="50"
                    cy="50"
                    r={radiusPx}
                    fill="none"
                    stroke="white"
                    strokeOpacity={segmentOpacity}
                    strokeWidth={strokeWidth}
                    strokeDasharray={`${segmentLength * 1.5} ${circumference - segmentLength * 1.5}`}
                    strokeDashoffset={segmentOffset}
                  />
                );
              })}

              <circle
                cx="50"
                cy="50"
                r={radiusPx}
                fill="none"
                stroke="white"
                strokeOpacity={ringOpacity * 0.08}
                strokeWidth={strokeWidth * 2.5}
                strokeDasharray={`${sliceLength * 1.8} ${circumference - sliceLength * 1.8}`}
                strokeDashoffset={dashOffset}
                style={{ filter: 'blur(2px)' }}
              />
              <circle
                ref={circleRef}
                cx="50"
                cy="50"
                r={radiusPx}
                fill="none"
                stroke="white"
                strokeOpacity={ringOpacity * 0.04}
                strokeWidth={strokeWidth * 3.5}
                strokeDasharray={`${sliceLength * 2} ${circumference - sliceLength * 2}`}
                strokeDashoffset={dashOffset}
                style={{ filter: 'blur(3px)' }}
              />
            </g>
          </mask>
        </defs>

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

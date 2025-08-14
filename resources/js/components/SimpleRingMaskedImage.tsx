import React, { useRef, useEffect } from 'react';

export interface SimpleRingMaskedImageProps {
  src: string;
  alt?: string;
  innerRadiusPct: number;
  outerRadiusPct: number;
  sliceDegrees?: number;
  baseOpacity?: number;
  ringOpacity?: number;
  secondsPerTurn?: number;
  clockwise?: boolean;
  className?: string;
}

export default function SimpleRingMaskedImage({ 
  src, 
  // alt,
  innerRadiusPct,
  outerRadiusPct,
  sliceDegrees = 90,
  baseOpacity = 0.3,
  ringOpacity = 1.0,
  secondsPerTurn = 10,
  clockwise = true,
  className = ""
}: SimpleRingMaskedImageProps) {
  // Generate unique ID for this instance
  const maskId = useRef(`ringMask-${Math.random().toString(36).substr(2, 9)}`);
  const sliceGroupRef = useRef<SVGGElement>(null);
  const animationRef = useRef<number | null>(null);

  // Validate props
  const validatedInnerRadius = Math.max(0, Math.min(50, innerRadiusPct));
  const validatedOuterRadius = Math.max(validatedInnerRadius + 1, Math.min(50, outerRadiusPct));
  const validatedSliceDegrees = Math.max(1, sliceDegrees);

  // Calculate ring geometry
  const radiusPx = (validatedInnerRadius + validatedOuterRadius) / 2;
  const strokeWidth = validatedOuterRadius - validatedInnerRadius;
  const circumference = 2 * Math.PI * radiusPx;
  const sliceLength = circumference * (validatedSliceDegrees / 360);

  // Set dash array to show the slice
  const dashArray = `${sliceLength} ${circumference - sliceLength}`;
  const dashOffset = circumference * 0.25; // Start at top (12 o'clock position)

  useEffect(() => {
    if (!sliceGroupRef.current) {
      console.log('SimpleRingMaskedImage: sliceGroupRef not found');
      return;
    }

    const sliceGroup = sliceGroupRef.current;
    console.log('SimpleRingMaskedImage: Starting animation', {
      secondsPerTurn,
      clockwise,
      radiusPx,
      sliceLength,
      circumference
    });

    const startTime = performance.now();
    const rotationSpeed = (clockwise ? 360 : -360) / (secondsPerTurn * 1000); // degrees per millisecond

    const animate = (currentTime: number) => {
      const elapsed = currentTime - startTime;
      const rotation = (elapsed * rotationSpeed) % 360;
      sliceGroup.style.transform = `rotate(${rotation}deg)`;
      
      animationRef.current = requestAnimationFrame(animate);
    };

    animationRef.current = requestAnimationFrame(animate);
    console.log('SimpleRingMaskedImage: Animation started');

    return () => {
      if (animationRef.current) {
        cancelAnimationFrame(animationRef.current);
        console.log('SimpleRingMaskedImage: Animation cleaned up');
      }
    };
  }, [secondsPerTurn, clockwise, circumference, radiusPx, sliceLength]);

  return (
    <div className={`w-full aspect-square ${className}`}>
      <svg
        viewBox="0 0 100 100"
        className="w-full h-auto"
        xmlns="http://www.w3.org/2000/svg"
      >
        <defs>
          <mask id={maskId.current}>
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
            preserveAspectRatio="xMidYMid slice"
          />
        </g>
      </svg>
    </div>
  );
};

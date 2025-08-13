import { useEffect, useRef, MutableRefObject } from 'react';

interface UseFramerEngineProps {
  enabled: boolean;
  sliceGroupRef: MutableRefObject<SVGGElement | null>;
  circleRef: MutableRefObject<SVGCircleElement | null>;
  containerRef: MutableRefObject<HTMLDivElement | null>;
  mode: 'continuous' | 'scroll';
  shouldRunIntro: boolean;
  onIntroComplete: () => void;
  sliceLength: number;
  circumference: number;
  secondsPerTurn: number;
  clockwise: boolean;
  turnsPerViewport: number;
  respectReducedMotion: boolean;
  setDashArray: (value: string) => void;
}

export const useFramerEngine = (props: UseFramerEngineProps) => {
  const {
    enabled,
    sliceGroupRef,
    circleRef,
    containerRef,
    mode,
    shouldRunIntro,
    onIntroComplete,
    sliceLength,
    circumference,
    secondsPerTurn,
    clockwise,
    turnsPerViewport,
    respectReducedMotion,
    setDashArray,
  } = props;

  const animationRef = useRef<number | null>(null);
  const scrollAnimationRef = useRef<number | null>(null);
  const framerMotionRef = useRef<any>(null);

  // Check for reduced motion preference
  const prefersReducedMotion = respectReducedMotion && 
    typeof window !== 'undefined' && 
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

  useEffect(() => {
    if (!enabled || !sliceGroupRef.current || prefersReducedMotion) {
      return;
    }

    // Try to use Framer Motion if available, fallback to vanilla JS
    const initializeFramer = async () => {
      try {
        const framerMotion = await import('framer-motion');
        framerMotionRef.current = framerMotion;
        
        if (shouldRunIntro) {
          runFramerIntroSequence();
        } else {
          startFramerSelectedMode();
        }
      } catch (error) {
        console.warn('Framer Motion not available, using fallback animation');
        // Fallback to vanilla JavaScript animation
        if (shouldRunIntro) {
          runVanillaIntroSequence();
        } else {
          startVanillaSelectedMode();
        }
      }
    };

    initializeFramer();

    return () => {
      if (animationRef.current) {
        cancelAnimationFrame(animationRef.current);
      }
      if (scrollAnimationRef.current) {
        cancelAnimationFrame(scrollAnimationRef.current);
      }
    };
  }, [enabled, shouldRunIntro, mode, prefersReducedMotion]);

  const runFramerIntroSequence = () => {
    if (!framerMotionRef.current || !sliceGroupRef.current) {
      runVanillaIntroSequence();
      return;
    }

    const { animate } = framerMotionRef.current;
    const sliceGroup = sliceGroupRef.current;
    const circle = circleRef.current;

    // Step 1: Clockwise rotation (360°)
    animate(sliceGroup, 
      { rotate: 360 }, 
      { duration: secondsPerTurn, ease: 'linear' }
    ).then(() => {
      // Step 2: Counterclockwise rotation (back to 0°)
      return animate(sliceGroup, 
        { rotate: 0 }, 
        { duration: secondsPerTurn, ease: 'linear' }
      );
    }).then(() => {
      // Step 3: Fill animation from top
      if (circle) {
        return animateFramerFill();
      }
    }).then(() => {
      onIntroComplete();
      startFramerSelectedMode();
    }).catch(() => {
      // Fallback if Framer Motion fails
      runVanillaIntroSequence();
    });
  };

  const animateFramerFill = () => {
    return new Promise<void>((resolve) => {
      const { animate } = framerMotionRef.current;
      const progress = { value: 0 };
      
      animate(progress, 
        { value: 1 }, 
        { 
          duration: secondsPerTurn, 
          ease: 'linear',
          onUpdate: (latest: any) => {
            const currentLength = sliceLength * latest.value;
            const remainingLength = circumference - currentLength;
            setDashArray(`${currentLength} ${remainingLength}`);
          }
        }
      ).then(() => {
        setDashArray(`${sliceLength} ${circumference - sliceLength}`);
        resolve();
      });
    });
  };

  const startFramerSelectedMode = () => {
    if (!framerMotionRef.current || !sliceGroupRef.current) {
      startVanillaSelectedMode();
      return;
    }

    const sliceGroup = sliceGroupRef.current;
    
    // Set initial dash array for slice visibility
    setDashArray(`${sliceLength} ${circumference - sliceLength}`);

    if (mode === 'continuous') {
      startFramerContinuousRotation(sliceGroup);
    } else if (mode === 'scroll') {
      startFramerScrollRotation(sliceGroup);
    }
  };

  const startFramerContinuousRotation = (element: SVGGElement) => {
    const { animate } = framerMotionRef.current;
    const rotation = clockwise ? 360 : -360;
    
    animate(element, 
      { rotate: [0, rotation] }, 
      { 
        duration: secondsPerTurn, 
        ease: 'linear',
        repeat: Infinity,
        repeatType: 'loop'
      }
    );
  };

  const startFramerScrollRotation = (element: SVGGElement) => {
    // For scroll-based animation, we'll use vanilla JS with requestAnimationFrame
    // as Framer Motion's useScroll requires component-level integration
    startVanillaScrollRotation(element);
  };

  // Vanilla JavaScript fallback methods
  const runVanillaIntroSequence = () => {
    const sliceGroup = sliceGroupRef.current;
    const circle = circleRef.current;

    if (!sliceGroup) return;

    // Step 1: Clockwise rotation (360°)
    animateRotation(sliceGroup, 0, 360, secondsPerTurn * 1000, () => {
      // Step 2: Counterclockwise rotation (back to 0°)
      animateRotation(sliceGroup, 360, 0, secondsPerTurn * 1000, () => {
        // Step 3: Fill animation from top
        if (circle) {
          animateFill(circle, () => {
            onIntroComplete();
            startVanillaSelectedMode();
          });
        } else {
          onIntroComplete();
          startVanillaSelectedMode();
        }
      });
    });
  };

  const startVanillaSelectedMode = () => {
    if (!sliceGroupRef.current) return;

    const sliceGroup = sliceGroupRef.current;
    
    // Set initial dash array for slice visibility
    setDashArray(`${sliceLength} ${circumference - sliceLength}`);

    if (mode === 'continuous') {
      startVanillaContinuousRotation(sliceGroup);
    } else if (mode === 'scroll') {
      startVanillaScrollRotation(sliceGroup);
    }
  };

  const animateRotation = (
    element: SVGGElement, 
    startAngle: number, 
    endAngle: number, 
    duration: number, 
    onComplete?: () => void
  ) => {
    const startTime = performance.now();
    
    const animate = (currentTime: number) => {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      
      // Linear interpolation
      const currentAngle = startAngle + (endAngle - startAngle) * progress;
      element.style.transform = `rotate(${currentAngle}deg)`;
      
      if (progress < 1) {
        animationRef.current = requestAnimationFrame(animate);
      } else if (onComplete) {
        onComplete();
      }
    };
    
    animationRef.current = requestAnimationFrame(animate);
  };

  const animateFill = (circle: SVGCircleElement, onComplete?: () => void) => {
    const duration = secondsPerTurn * 1000;
    const startTime = performance.now();
    
    const animate = (currentTime: number) => {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      
      // Animate dash array from 0 to slice length
      const currentLength = sliceLength * progress;
      const remainingLength = circumference - currentLength;
      setDashArray(`${currentLength} ${remainingLength}`);
      
      if (progress < 1) {
        animationRef.current = requestAnimationFrame(animate);
      } else {
        // Set final state to show the slice
        setDashArray(`${sliceLength} ${circumference - sliceLength}`);
        if (onComplete) {
          onComplete();
        }
      }
    };
    
    animationRef.current = requestAnimationFrame(animate);
  };

  const startVanillaScrollRotation = (element: SVGGElement) => {
    const updateScrollRotation = () => {
      if (!containerRef.current) {
        scrollAnimationRef.current = requestAnimationFrame(updateScrollRotation);
        return;
      }

      const scrollY = window.scrollY;
      const viewportHeight = window.innerHeight;
      const scrollProgress = scrollY / viewportHeight;
      const rotation = scrollProgress * 360 * turnsPerViewport;
      
      element.style.transform = `rotate(${clockwise ? rotation : -rotation}deg)`;
      
      scrollAnimationRef.current = requestAnimationFrame(updateScrollRotation);
    };
    
    scrollAnimationRef.current = requestAnimationFrame(updateScrollRotation);
  };

  const startVanillaContinuousRotation = (element: SVGGElement) => {
    const startTime = performance.now();
    const rotationSpeed = (clockwise ? 360 : -360) / (secondsPerTurn * 1000); // degrees per millisecond
    
    const animate = (currentTime: number) => {
      const elapsed = currentTime - startTime;
      const rotation = (elapsed * rotationSpeed) % 360;
      element.style.transform = `rotate(${rotation}deg)`;
      
      animationRef.current = requestAnimationFrame(animate);
    };
    
    animationRef.current = requestAnimationFrame(animate);
  };

  return {
    // Return any public methods if needed
  };
};

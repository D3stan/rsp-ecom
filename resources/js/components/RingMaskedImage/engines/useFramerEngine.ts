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
  performanceMode?: 'auto' | 'css' | 'js';
  isIOSWebKit?: boolean;
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
    performanceMode = 'auto',
    isIOSWebKit = false,
  } = props;

  const animationRef = useRef<number | null>(null);
  const scrollAnimationRef = useRef<number | null>(null);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const framerMotionRef = useRef<any>(null);

  const prefersReducedMotion = respectReducedMotion &&
    typeof window !== 'undefined' &&
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

  useEffect(() => {
    if (!enabled || !sliceGroupRef.current || prefersReducedMotion) {
      console.log('FramerEngine: Skipping initialization', {
        enabled,
        hasSliceGroup: !!sliceGroupRef.current,
        prefersReducedMotion
      });
      return;
    }

    console.log('FramerEngine: Starting initialization', {
      mode,
      shouldRunIntro,
      secondsPerTurn,
      clockwise
    });

    const initializeFramer = async () => {
      try {
        const framerMotion = await import('framer-motion');
        framerMotionRef.current = framerMotion;
        console.log('FramerEngine: Framer Motion loaded successfully');

        if (shouldRunIntro) {
          console.log('FramerEngine: Running intro sequence');
          runFramerIntroSequence();
        } else {
          console.log('FramerEngine: Starting selected mode');
          startFramerSelectedMode();
        }
      } catch (error) {
        console.warn('Framer Motion not available, using fallback animation', error);
        if (shouldRunIntro) {
          console.log('FramerEngine: Running vanilla intro sequence');
          runVanillaIntroSequence();
        } else {
          console.log('FramerEngine: Starting vanilla selected mode');
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
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enabled, shouldRunIntro, mode, prefersReducedMotion]);

  const runFramerIntroSequence = () => {
    if (!framerMotionRef.current || !sliceGroupRef.current) {
      runVanillaIntroSequence();
      return;
    }

    const { animate } = framerMotionRef.current;
    const sliceGroup = sliceGroupRef.current;
    const circle = circleRef.current;

    animate(sliceGroup,
      { rotate: 360 },
      { duration: secondsPerTurn, ease: 'linear' }
    ).then(() => {
      return animate(sliceGroup,
        { rotate: 0 },
        { duration: secondsPerTurn, ease: 'linear' }
      );
    }).then(() => {
      if (circle) {
        return animateFramerFill();
      }
    }).then(() => {
      onIntroComplete();
      startFramerSelectedMode();
    }).catch(() => {
      runVanillaIntroSequence();
    });
  };

  const animateFramerFill = () => {
    return new Promise<void>((resolve) => {
      if (!framerMotionRef.current) {
        resolve();
        return;
      }

      const { animate } = framerMotionRef.current;
      const progress = { value: 0 };

      animate(progress,
        { value: 1 },
        {
          duration: secondsPerTurn,
          ease: 'linear',
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
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
    const circle = circleRef.current;

    setDashArray(`${sliceLength} ${circumference - sliceLength}`);

    if (performanceMode === 'auto' && isIOSWebKit && !shouldRunIntro) {
      console.log('FramerEngine: Using iOS-optimized dashoffset animation');
      if (mode === 'continuous' && circle) {
        startIOSDashOffsetContinuous(circle);
        return;
      } else if (mode === 'scroll' && circle) {
        startIOSDashOffsetScroll(circle);
        return;
      }
    }

    if (mode === 'continuous') {
      const useCss = (performanceMode === 'css') || (
        performanceMode === 'auto' && typeof navigator !== 'undefined' && /iPad|iPhone|iPod/.test(navigator.userAgent)
      );
      if (useCss) {
        console.log('FramerEngine: Using CSS keyframe rotation for performance');
        startCssContinuousRotation(sliceGroup);
      } else {
        console.log('FramerEngine: Using vanilla continuous rotation for SVG mask compatibility');
        startVanillaContinuousRotation(sliceGroup);
      }
    } else if (mode === 'scroll') {
      console.log('FramerEngine: Using vanilla scroll rotation for SVG mask compatibility');
      startVanillaScrollRotation(sliceGroup);
    }
  };

  const runVanillaIntroSequence = () => {
    const sliceGroup = sliceGroupRef.current;
    const circle = circleRef.current;

    if (!sliceGroup) return;

    animateRotation(sliceGroup, 0, 360, secondsPerTurn * 1000, () => {
      animateRotation(sliceGroup, 360, 0, secondsPerTurn * 1000, () => {
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

    console.log('FramerEngine: Starting vanilla selected mode', { mode });

    setDashArray(`${sliceLength} ${circumference - sliceLength}`);

    if (mode === 'continuous') {
      console.log('FramerEngine: Starting vanilla continuous rotation');
      startVanillaContinuousRotation(sliceGroup);
    } else if (mode === 'scroll') {
      console.log('FramerEngine: Starting vanilla scroll rotation');
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

      const currentAngle = startAngle + (endAngle - startAngle) * progress;
      element.style.transform = `rotate(${currentAngle}deg)`;
      element.style.transformOrigin = '50% 50%';
      element.style.transformBox = 'fill-box';

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

      const currentLength = sliceLength * progress;
      const remainingLength = circumference - currentLength;
      setDashArray(`${currentLength} ${remainingLength}`);

      if (progress < 1) {
        animationRef.current = requestAnimationFrame(animate);
      } else {
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
      element.style.transformOrigin = '50% 50%';
      element.style.transformBox = 'fill-box';

      scrollAnimationRef.current = requestAnimationFrame(updateScrollRotation);
    };

    scrollAnimationRef.current = requestAnimationFrame(updateScrollRotation);
  };

  const startVanillaContinuousRotation = (element: SVGGElement) => {
    console.log('FramerEngine: Starting continuous rotation animation', {
      secondsPerTurn,
      clockwise
    });

    const startTime = performance.now();
    const rotationSpeed = (clockwise ? 360 : -360) / (secondsPerTurn * 1000);

    const animate = (currentTime: number) => {
      const elapsed = currentTime - startTime;
      const rotation = (elapsed * rotationSpeed) % 360;
      element.style.transform = `rotate(${rotation}deg)`;
      element.style.transformOrigin = '50% 50%';
      element.style.transformBox = 'fill-box';

      animationRef.current = requestAnimationFrame(animate);
    };

    animationRef.current = requestAnimationFrame(animate);
    console.log('FramerEngine: Continuous rotation animation started');
  };

  const startCssContinuousRotation = (element: SVGGElement) => {
    const doc = element.ownerDocument || document;
    const sheetId = 'ring-rotation-keyframes';
    if (!doc.getElementById(sheetId)) {
      const styleEl = doc.createElement('style');
      styleEl.id = sheetId;
      styleEl.textContent = `@keyframes ring-rot-clockwise { from { transform: rotate(0deg); } to { transform: rotate(360deg); } } @keyframes ring-rot-ccw { from { transform: rotate(0deg); } to { transform: rotate(-360deg); } }`;
      doc.head.appendChild(styleEl);
    }
    const duration = `${secondsPerTurn}s`;
    element.setAttribute('style', `animation: ${clockwise ? 'ring-rot-clockwise' : 'ring-rot-ccw'} ${duration} linear infinite; transform-origin: 50% 50%;`);
  };

  const startIOSDashOffsetContinuous = (circle: SVGCircleElement) => {
    console.log('FramerEngine: Starting iOS dashoffset continuous animation');
    const startTime = performance.now();

    const animate = (currentTime: number) => {
      const elapsed = currentTime - startTime;
      const progress = (elapsed / (secondsPerTurn * 1000)) % 1;

      const dashOffsetProgress = clockwise ? progress : (1 - progress);
      const newDashOffset = circumference * (0.25 - dashOffsetProgress);

      circle.setAttribute('stroke-dashoffset', newDashOffset.toString());
      animationRef.current = requestAnimationFrame(animate);
    };

    animationRef.current = requestAnimationFrame(animate);
  };

  const startIOSDashOffsetScroll = (circle: SVGCircleElement) => {
    console.log('FramerEngine: Starting iOS dashoffset scroll animation');

    const updateScrollDashOffset = () => {
      if (!containerRef.current) {
        scrollAnimationRef.current = requestAnimationFrame(updateScrollDashOffset);
        return;
      }

      const scrollY = window.scrollY;
      const viewportHeight = window.innerHeight;
      const scrollProgress = (scrollY / viewportHeight) % 1;

      const dashOffsetProgress = clockwise ? scrollProgress * turnsPerViewport : (1 - scrollProgress * turnsPerViewport);
      const newDashOffset = circumference * (0.25 - dashOffsetProgress);

      circle.setAttribute('stroke-dashoffset', newDashOffset.toString());
      scrollAnimationRef.current = requestAnimationFrame(updateScrollDashOffset);
    };

    scrollAnimationRef.current = requestAnimationFrame(updateScrollDashOffset);
  };

  return {
  };
};

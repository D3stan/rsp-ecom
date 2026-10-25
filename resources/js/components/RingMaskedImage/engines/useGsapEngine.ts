import { useEffect, useRef, MutableRefObject } from 'react';

interface UseGsapEngineProps {
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
  isIOSWebKit?: boolean;
}

export const useGsapEngine = (props: UseGsapEngineProps) => {
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

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const timelineRef = useRef<any>(null);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const scrollTriggerRef = useRef<any>(null);

  const prefersReducedMotion = respectReducedMotion &&
    typeof window !== 'undefined' &&
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

  useEffect(() => {
    if (!enabled || !sliceGroupRef.current || prefersReducedMotion) {
      return;
    }

    const initializeGsap = async () => {
      try {
        const gsapModule = await import('gsap');
        const { ScrollTrigger } = await import('gsap/ScrollTrigger');

        const gsap = gsapModule.default || gsapModule;
        gsap.registerPlugin(ScrollTrigger);

        if (shouldRunIntro) {
          runIntroSequence(gsap);
        } else {
          startSelectedMode(gsap, ScrollTrigger);
        }
      } catch {
        console.warn('GSAP not available, falling back to basic animation');
        runBasicAnimation();
      }
    };

    initializeGsap();

    return () => {
      if (timelineRef.current) {
        timelineRef.current.kill();
      }
      if (scrollTriggerRef.current) {
        scrollTriggerRef.current.kill();
      }
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enabled, shouldRunIntro, mode, prefersReducedMotion]);

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const runIntroSequence = (gsap: any) => {
    if (!sliceGroupRef.current) return;

    const sliceGroup = sliceGroupRef.current;
    const circle = circleRef.current;

    const tl = gsap.timeline({
      onComplete: () => {
        onIntroComplete();
        startSelectedMode(gsap, null);
      }
    });

    timelineRef.current = tl;

    tl.to(sliceGroup, {
      rotation: 360,
      duration: secondsPerTurn,
      ease: 'none',
      transformOrigin: '50% 50%'
    });

    tl.to(sliceGroup, {
      rotation: 0,
      duration: secondsPerTurn,
      ease: 'none'
    });

    if (circle) {
      tl.to({}, {
        duration: secondsPerTurn,
        ease: 'none',
        onUpdate: function() {
          const progress = this.progress();
          const currentLength = sliceLength * progress;
          const remainingLength = circumference - currentLength;
          setDashArray(`${currentLength} ${remainingLength}`);
        }
      });
    }
  };

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const startSelectedMode = (gsap: any, ScrollTrigger: any) => {
    if (!sliceGroupRef.current) return;

    const sliceGroup = sliceGroupRef.current;

    setDashArray(`${sliceLength} ${circumference - sliceLength}`);

    if (mode === 'continuous') {
      startContinuousRotation(gsap, sliceGroup);
    } else if (mode === 'scroll' && ScrollTrigger) {
      startScrollRotation(gsap, ScrollTrigger, sliceGroup);
    }
  };

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const startContinuousRotation = (gsap: any, element: SVGGElement) => {
    const rotation = clockwise ? 360 : -360;

    gsap.to(element, {
      rotation: rotation,
      duration: secondsPerTurn,
      ease: 'none',
      repeat: -1,
      transformOrigin: '50% 50%'
    });
  };

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const startScrollRotation = (gsap: any, ScrollTrigger: any, element: SVGGElement) => {
    if (!containerRef.current) return;

    const rotation = clockwise ? 360 * turnsPerViewport : -360 * turnsPerViewport;

    scrollTriggerRef.current = ScrollTrigger.create({
      trigger: containerRef.current,
      start: 'top bottom',
      end: '+=100%',
      scrub: true,
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      onUpdate: (self: any) => {
        const progress = self.progress;
        gsap.set(element, {
          rotation: rotation * progress,
          transformOrigin: '50% 50%'
        });
      }
    });
  };

  const runBasicAnimation = () => {
    if (!sliceGroupRef.current) return;

    const sliceGroup = sliceGroupRef.current;

    setDashArray(`${sliceLength} ${circumference - sliceLength}`);

    if (mode === 'continuous') {
      const startTime = performance.now();
      const rotationSpeed = (clockwise ? 360 : -360) / (secondsPerTurn * 1000);

      const animate = (currentTime: number) => {
        const elapsed = currentTime - startTime;
        const rotation = (elapsed * rotationSpeed) % 360;
        sliceGroup.style.transform = `rotate(${rotation}deg)`;
        sliceGroup.style.transformOrigin = '50% 50%';
        sliceGroup.style.transformBox = 'fill-box';
        requestAnimationFrame(animate);
      };

      requestAnimationFrame(animate);
    } else if (mode === 'scroll') {
      const updateScrollRotation = () => {
        const scrollY = window.scrollY;
        const viewportHeight = window.innerHeight;
        const scrollProgress = scrollY / viewportHeight;
        const rotation = scrollProgress * 360 * turnsPerViewport;

        sliceGroup.style.transform = `rotate(${clockwise ? rotation : -rotation}deg)`;
        sliceGroup.style.transformOrigin = '50% 50%';
        sliceGroup.style.transformBox = 'fill-box';
        requestAnimationFrame(updateScrollRotation);
      };

      requestAnimationFrame(updateScrollRotation);
    }
  };

  return {
  };
};

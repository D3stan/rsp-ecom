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
    isIOSWebKit = false,
  } = props;

  const timelineRef = useRef<any>(null);
  const scrollTriggerRef = useRef<any>(null);

  // Check for reduced motion preference
  const prefersReducedMotion = respectReducedMotion && 
    typeof window !== 'undefined' && 
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

  useEffect(() => {
    if (!enabled || !sliceGroupRef.current || prefersReducedMotion) {
      return;
    }

    // Dynamic import for GSAP to avoid build issues if not installed
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
      } catch (error) {
        console.warn('GSAP not available, falling back to basic animation');
        // Fallback to basic JavaScript animation
        runBasicAnimation();
      }
    };

    initializeGsap();

    return () => {
      // Cleanup GSAP animations
      if (timelineRef.current) {
        timelineRef.current.kill();
      }
      if (scrollTriggerRef.current) {
        scrollTriggerRef.current.kill();
      }
    };
  }, [enabled, shouldRunIntro, mode, prefersReducedMotion]);

  const runIntroSequence = (gsap: any) => {
    if (!sliceGroupRef.current) return;

    const sliceGroup = sliceGroupRef.current;
    const circle = circleRef.current;

    // Create timeline for intro sequence
    const tl = gsap.timeline({
      onComplete: () => {
        onIntroComplete();
        startSelectedMode(gsap, null);
      }
    });

    timelineRef.current = tl;

    // Step 1: Clockwise rotation (360°)
    tl.to(sliceGroup, {
      rotation: 360,
      duration: secondsPerTurn,
      ease: 'none',
      transformOrigin: '50% 50%'
    });

    // Step 2: Counterclockwise rotation (back to 0°)
    tl.to(sliceGroup, {
      rotation: 0,
      duration: secondsPerTurn,
      ease: 'none'
    });

    // Step 3: Fill animation from top
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

  const startSelectedMode = (gsap: any, ScrollTrigger: any) => {
    if (!sliceGroupRef.current) return;

    const sliceGroup = sliceGroupRef.current;
    
    // Set initial dash array for slice visibility
    setDashArray(`${sliceLength} ${circumference - sliceLength}`);

    if (mode === 'continuous') {
      startContinuousRotation(gsap, sliceGroup);
    } else if (mode === 'scroll' && ScrollTrigger) {
      startScrollRotation(gsap, ScrollTrigger, sliceGroup);
    }
  };

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

  const startScrollRotation = (gsap: any, ScrollTrigger: any, element: SVGGElement) => {
    if (!containerRef.current) return;

    const rotation = clockwise ? 360 * turnsPerViewport : -360 * turnsPerViewport;

    scrollTriggerRef.current = ScrollTrigger.create({
      trigger: containerRef.current,
      start: 'top bottom',
      end: '+=100%',
      scrub: true,
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
    // Basic fallback animation without GSAP
    if (!sliceGroupRef.current) return;

    const sliceGroup = sliceGroupRef.current;
    
    // Set initial dash array for slice visibility
    setDashArray(`${sliceLength} ${circumference - sliceLength}`);

    if (mode === 'continuous') {
      const startTime = performance.now();
      const rotationSpeed = (clockwise ? 360 : -360) / (secondsPerTurn * 1000);
      
      const animate = (currentTime: number) => {
        const elapsed = currentTime - startTime;
        const rotation = (elapsed * rotationSpeed) % 360;
        // Fix 3: Use CSS transform instead of SVG attribute transform
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
        
        // Fix 3: Use CSS transform instead of SVG attribute transform
        sliceGroup.style.transform = `rotate(${clockwise ? rotation : -rotation}deg)`;
        sliceGroup.style.transformOrigin = '50% 50%';
        sliceGroup.style.transformBox = 'fill-box';
        requestAnimationFrame(updateScrollRotation);
      };
      
      requestAnimationFrame(updateScrollRotation);
    }
  };

  return {
    // Return any public methods if needed
  };
};

import React from 'react';

// Simple vanilla JavaScript ring animation for testing
export const SimpleRingTest: React.FC = () => {
  const circleRef = React.useRef<SVGCircleElement>(null);

  React.useEffect(() => {
    if (!circleRef.current) return;

    const circle = circleRef.current;
    const startTime = performance.now();
    const rotationSpeed = 360 / (3 * 1000); // 3 seconds per rotation
    
    const animate = (currentTime: number) => {
      const elapsed = currentTime - startTime;
      const rotation = (elapsed * rotationSpeed) % 360;
      
      if (circle) {
        circle.style.transformOrigin = 'center';
        circle.style.transform = `rotate(${rotation}deg)`;
      }
      
      requestAnimationFrame(animate);
    };
    
    requestAnimationFrame(animate);
    console.log('Simple ring animation started');
  }, []);

  return (
    <div className="w-80 h-80 mx-auto bg-gray-100 flex items-center justify-center">
      <svg
        width="300"
        height="300"
        viewBox="0 0 100 100"
        className="border"
      >
        <defs>
          <mask id="simpleRingMask">
            <rect width="100" height="100" fill="white" fillOpacity="0.3" />
            <circle
              ref={circleRef}
              cx="50"
              cy="50"
              r="35"
              fill="none"
              stroke="white"
              strokeWidth="6"
              strokeLinecap="round"
              strokeDasharray="30 75"
              strokeDashoffset="25"
            />
          </mask>
        </defs>
        
        <g mask="url(#simpleRingMask)">
          <image
            href="/images/angeleye.png"
            x="0"
            y="0"
            width="100"
            height="100"
            preserveAspectRatio="xMidYMid slice"
          />
        </g>
        
        {/* Visible ring for debugging */}
        <circle
          cx="50"
          cy="50"
          r="35"
          fill="none"
          stroke="red"
          strokeWidth="1"
          opacity="0.5"
        />
      </svg>
    </div>
  );
};

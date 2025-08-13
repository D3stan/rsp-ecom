import React from 'react';
import { RingMaskedImage } from './RingMaskedImage/RingMaskedImage';

// Example usage component showing different configurations
export const RingMaskedImageDemo: React.FC = () => {
  return (
    <div className="space-y-8 p-8">
      <div className="text-center">
        <h2 className="text-2xl font-bold mb-4">Ring Masked Image Demo</h2>
        <p className="text-gray-600 mb-8">
          Showcasing different configurations of the RingMaskedImage component
        </p>
      </div>

      {/* Continuous rotation example */}
      <div className="grid md:grid-cols-2 gap-8 items-center">
        <div>
          <h3 className="text-lg font-semibold mb-2">Continuous Rotation</h3>
          <p className="text-sm text-gray-600 mb-4">
            Clockwise rotation with 2 seconds per turn, using Framer Motion engine
          </p>
          <div className="w-64 mx-auto">
            <RingMaskedImage
              src="/images/hero.jpg"
              alt="Continuous rotation demo"
              engine="framer"
              mode="continuous"
              innerRadiusPct={36}
              outerRadiusPct={42}
              sliceDegrees={60}
              baseOpacity={0.35}
              ringOpacity={1.0}
              secondsPerTurn={2}
              clockwise={true}
              className="ring-masked-image-mobile"
            />
          </div>
        </div>

        <div>
          <h3 className="text-lg font-semibold mb-2">Scroll-Sync Rotation</h3>
          <p className="text-sm text-gray-600 mb-4">
            Rotation synced to scroll position, using GSAP engine
          </p>
          <div className="w-64 mx-auto">
            <RingMaskedImage
              src="/images/hero.jpg"
              alt="Scroll sync demo"
              engine="gsap"
              mode="scroll"
              innerRadiusPct={30}
              outerRadiusPct={38}
              sliceDegrees={90}
              baseOpacity={0.25}
              ringOpacity={1.0}
              turnsPerViewport={1}
              clockwise={false}
              className="ring-masked-image-mobile"
            />
          </div>
        </div>
      </div>

      {/* Configuration examples */}
      <div className="mt-12">
        <h3 className="text-lg font-semibold mb-4">Different Ring Configurations</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          
          {/* Thin ring */}
          <div className="text-center">
            <h4 className="font-medium mb-2">Thin Ring</h4>
            <div className="w-48 mx-auto">
              <RingMaskedImage
                src="/images/hero.jpg"
                alt="Thin ring demo"
                engine="framer"
                mode="continuous"
                innerRadiusPct={38}
                outerRadiusPct={40}
                sliceDegrees={45}
                baseOpacity={0.4}
                secondsPerTurn={3}
                introEnabled={false}
                className="w-full"
              />
            </div>
          </div>

          {/* Wide ring */}
          <div className="text-center">
            <h4 className="font-medium mb-2">Wide Ring</h4>
            <div className="w-48 mx-auto">
              <RingMaskedImage
                src="/images/hero.jpg"
                alt="Wide ring demo"
                engine="framer"
                mode="continuous"
                innerRadiusPct={25}
                outerRadiusPct={35}
                sliceDegrees={120}
                baseOpacity={0.2}
                secondsPerTurn={4}
                clockwise={false}
                introEnabled={false}
                className="w-full"
              />
            </div>
          </div>

          {/* Large slice */}
          <div className="text-center">
            <h4 className="font-medium mb-2">Large Slice</h4>
            <div className="w-48 mx-auto">
              <RingMaskedImage
                src="/images/hero.jpg"
                alt="Large slice demo"
                engine="framer"
                mode="continuous"
                innerRadiusPct={32}
                outerRadiusPct={40}
                sliceDegrees={180}
                baseOpacity={0.3}
                secondsPerTurn={1.5}
                introEnabled={false}
                className="w-full"
              />
            </div>
          </div>
        </div>
      </div>

      {/* Integration note */}
      <div className="mt-12 p-4 bg-blue-50 rounded-lg">
        <h3 className="font-semibold text-blue-900 mb-2">Integration Notes</h3>
        <ul className="text-sm text-blue-800 space-y-1">
          <li>• Install required dependencies: <code className="bg-blue-100 px-1 rounded">npm install framer-motion gsap</code></li>
          <li>• The component gracefully fallbacks to vanilla JS if animation libraries are not available</li>
          <li>• Supports <code className="bg-blue-100 px-1 rounded">prefers-reduced-motion</code> for accessibility</li>
          <li>• Ring dimensions are percentage-based for responsive design</li>
          <li>• Intro sequence plays once per session (configurable TTL)</li>
        </ul>
      </div>
    </div>
  );
};

export default RingMaskedImageDemo;

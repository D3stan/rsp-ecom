/**
 * iOS WebKit Performance Test Script
 * 
 * This script can be run in browser dev tools to verify the 
 * iOS WebKit optimizations are working correctly.
 */

// Test 1: Verify iOS detection
function testIOSDetection() {
  const isIOSWebKit = (
    /iP(hone|ad|od)/.test(navigator.platform || '') ||
    (/Mac/.test(navigator.platform || '') && typeof document !== 'undefined' && 'ontouchend' in document)
  );
  
  console.log('iOS Detection Test:');
  console.log('Platform:', navigator.platform);
  console.log('Touch support:', 'ontouchend' in document);
  console.log('Detected as iOS/macOS:', isIOSWebKit);
  
  return isIOSWebKit;
}

// Test 2: Check if sliceGroup has proper CSS properties
function testSliceGroupOptimization() {
  const sliceGroups = document.querySelectorAll('.sliceGroup');
  
  console.log('SliceGroup Optimization Test:');
  console.log('Found slice groups:', sliceGroups.length);
  
  sliceGroups.forEach((element, index) => {
    const styles = window.getComputedStyle(element);
    console.log(`SliceGroup ${index}:`);
    console.log('  transform-box:', styles.transformBox);
    console.log('  transform-origin:', styles.transformOrigin);
    console.log('  will-change:', styles.willChange);
    console.log('  transform:', styles.transform);
  });
  
  return sliceGroups.length > 0;
}

// Test 3: Check mask type
function testMaskType() {
  const masks = document.querySelectorAll('mask');
  
  console.log('Mask Type Test:');
  console.log('Found masks:', masks.length);
  
  masks.forEach((mask, index) => {
    const maskType = mask.style.maskType || mask.getAttribute('style')?.includes('maskType');
    console.log(`Mask ${index}:`, maskType);
  });
  
  return masks.length > 0;
}

// Test 4: Monitor FPS during animation
function testFPS(duration = 5000) {
  console.log('FPS Test - monitoring for', duration / 1000, 'seconds...');
  
  let frameCount = 0;
  let startTime = performance.now();
  let lastTime = startTime;
  
  function countFrame() {
    const now = performance.now();
    frameCount++;
    
    if (now - startTime >= duration) {
      const avgFPS = (frameCount / (now - startTime)) * 1000;
      console.log('Average FPS:', avgFPS.toFixed(1));
      
      if (avgFPS >= 55) {
        console.log('✅ Excellent performance (55+ FPS)');
      } else if (avgFPS >= 30) {
        console.log('⚠️ Acceptable performance (30-55 FPS)');
      } else {
        console.log('❌ Poor performance (<30 FPS)');
      }
      
      return;
    }
    
    requestAnimationFrame(countFrame);
  }
  
  requestAnimationFrame(countFrame);
}

// Test 5: Check animation method being used
function testAnimationMethod() {
  const sliceGroups = document.querySelectorAll('.sliceGroup');
  
  console.log('Animation Method Test:');
  
  sliceGroups.forEach((element, index) => {
    // Check if using CSS animation
    const animation = window.getComputedStyle(element).animation;
    if (animation && animation !== 'none') {
      console.log(`SliceGroup ${index}: Using CSS animation`);
      return;
    }
    
    // Check if using CSS transform
    const transform = window.getComputedStyle(element).transform;
    if (transform && transform !== 'none') {
      console.log(`SliceGroup ${index}: Using CSS transform`);
    }
    
    // Check if using SVG transform attribute
    const svgTransform = element.getAttribute('transform');
    if (svgTransform) {
      console.log(`SliceGroup ${index}: Using SVG attribute transform`);
    }
  });
}

// Run all tests
function runAllTests() {
  console.log('🧪 Running iOS WebKit Performance Tests...\n');
  
  testIOSDetection();
  console.log('');
  
  testSliceGroupOptimization();
  console.log('');
  
  testMaskType();
  console.log('');
  
  testAnimationMethod();
  console.log('');
  
  console.log('Starting FPS test...');
  testFPS();
}

// Export for manual testing
window.RingMaskPerformanceTest = {
  runAllTests,
  testIOSDetection,
  testSliceGroupOptimization,
  testMaskType,
  testAnimationMethod,
  testFPS
};

console.log('iOS WebKit Performance Test Suite loaded.');
console.log('Run window.RingMaskPerformanceTest.runAllTests() to start testing.');

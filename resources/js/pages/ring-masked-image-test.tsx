import Header from '@/components/header';
import { RingMaskedImageDemo } from '@/components/RingMaskedImageDemo';
import { Head } from '@inertiajs/react';

export default function RingMaskedImageTest() {
    // const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Ring Masked Image Test" />
            <Header />
            
            <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
                <div className="container mx-auto px-4 py-12">
                    {/* Page Header */}
                    <div className="text-center mb-12">
                        <h1 className="text-4xl font-bold text-gray-900 mb-4">
                            Ring Masked Image Component
                        </h1>
                        <p className="text-lg text-gray-600 max-w-2xl mx-auto">
                            Interactive demonstration of the RingMaskedImage component with various 
                            animation modes and configurations. This component creates stunning 
                            ring-shaped mask animations over images.
                        </p>
                    </div>

                    {/* Demo Component */}
                    <RingMaskedImageDemo />

                    {/* Technical Information */}
                    <div className="mt-16 bg-white rounded-xl shadow-lg p-8">
                        <h2 className="text-2xl font-bold text-gray-900 mb-6">
                            Technical Implementation
                        </h2>
                        
                        <div className="grid md:grid-cols-2 gap-8">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-800 mb-3">
                                    Features Implemented
                                </h3>
                                <ul className="space-y-2 text-gray-600">
                                    <li className="flex items-start">
                                        <span className="w-2 h-2 bg-green-500 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        SVG-based ring mask for crisp rendering
                                    </li>
                                    <li className="flex items-start">
                                        <span className="w-2 h-2 bg-green-500 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        Dual animation engines (Framer Motion + GSAP)
                                    </li>
                                    <li className="flex items-start">
                                        <span className="w-2 h-2 bg-green-500 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        Graceful fallback to vanilla JavaScript
                                    </li>
                                    <li className="flex items-start">
                                        <span className="w-2 h-2 bg-green-500 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        Mobile-first responsive design
                                    </li>
                                    <li className="flex items-start">
                                        <span className="w-2 h-2 bg-green-500 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        Accessibility support (prefers-reduced-motion)
                                    </li>
                                    <li className="flex items-start">
                                        <span className="w-2 h-2 bg-green-500 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        Session-based intro sequence with TTL
                                    </li>
                                </ul>
                            </div>
                            
                            <div>
                                <h3 className="text-lg font-semibold text-gray-800 mb-3">
                                    Animation Modes
                                </h3>
                                <div className="space-y-4">
                                    <div className="p-4 bg-blue-50 rounded-lg">
                                        <h4 className="font-medium text-blue-900">Continuous Rotation</h4>
                                        <p className="text-sm text-blue-700 mt-1">
                                            Ring slice rotates continuously at configurable speed
                                        </p>
                                    </div>
                                    <div className="p-4 bg-purple-50 rounded-lg">
                                        <h4 className="font-medium text-purple-900">Scroll-Synced</h4>
                                        <p className="text-sm text-purple-700 mt-1">
                                            Rotation tied to viewport scroll position
                                        </p>
                                    </div>
                                    <div className="p-4 bg-green-50 rounded-lg">
                                        <h4 className="font-medium text-green-900">Intro Sequence</h4>
                                        <p className="text-sm text-green-700 mt-1">
                                            First-visit animation: rotate → counter-rotate → fill
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Usage Example */}
                        <div className="mt-8 p-6 bg-gray-50 rounded-lg">
                            <h3 className="text-lg font-semibold text-gray-800 mb-3">
                                Basic Usage Example
                            </h3>
                            <pre className="text-sm text-gray-700 overflow-x-auto">
{`<RingMaskedImage
  src="/images/product.jpg"
  alt="Product showcase"
  engine="framer"           // 'framer' | 'gsap'
  mode="continuous"         // 'continuous' | 'scroll'
  innerRadiusPct={36}       // Inner radius (% of shorter side)
  outerRadiusPct={42}       // Outer radius (% of shorter side)
  sliceDegrees={60}         // Visible slice size in degrees
  baseOpacity={0.35}        // Opacity outside the ring
  secondsPerTurn={2}        // Rotation speed
  clockwise={true}          // Rotation direction
  className="w-80 mx-auto"
/>`}
                            </pre>
                        </div>

                        {/* Dependencies Note */}
                        <div className="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                            <h4 className="font-medium text-amber-800 mb-2">
                                📦 Required Dependencies
                            </h4>
                            <p className="text-sm text-amber-700 mb-2">
                                To enable full functionality, install the animation libraries:
                            </p>
                            <code className="text-sm bg-amber-100 px-2 py-1 rounded text-amber-800">
                                npm install framer-motion gsap @types/gsap
                            </code>
                            <p className="text-xs text-amber-600 mt-2">
                                The component gracefully falls back to vanilla JavaScript if libraries are not available.
                            </p>
                        </div>
                    </div>

                    {/* Back to Home */}
                    <div className="text-center mt-12">
                        <a 
                            href="/" 
                            className="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            ← Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </>
    );
}

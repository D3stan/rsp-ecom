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
                    <div className="mb-12 text-center">
                        <h1 className="mb-4 text-4xl font-bold text-gray-900">Ring Masked Image Component</h1>
                        <p className="mx-auto max-w-2xl text-lg text-gray-600">
                            Interactive demonstration of the RingMaskedImage component with various animation modes and configurations. This component
                            creates stunning ring-shaped mask animations over images.
                        </p>
                    </div>

                    {/* Demo Component */}
                    <RingMaskedImageDemo />

                    {/* Technical Information */}
                    <div className="mt-16 rounded-xl bg-white p-8 shadow-lg">
                        <h2 className="mb-6 text-2xl font-bold text-gray-900">Technical Implementation</h2>

                        <div className="grid gap-8 md:grid-cols-2">
                            <div>
                                <h3 className="mb-3 text-lg font-semibold text-gray-800">Features Implemented</h3>
                                <ul className="space-y-2 text-gray-600">
                                    <li className="flex items-start">
                                        <span className="mt-2 mr-3 h-2 w-2 flex-shrink-0 rounded-full bg-green-500"></span>
                                        SVG-based ring mask for crisp rendering
                                    </li>
                                    <li className="flex items-start">
                                        <span className="mt-2 mr-3 h-2 w-2 flex-shrink-0 rounded-full bg-green-500"></span>
                                        Dual animation engines (Framer Motion + GSAP)
                                    </li>
                                    <li className="flex items-start">
                                        <span className="mt-2 mr-3 h-2 w-2 flex-shrink-0 rounded-full bg-green-500"></span>
                                        Graceful fallback to vanilla JavaScript
                                    </li>
                                    <li className="flex items-start">
                                        <span className="mt-2 mr-3 h-2 w-2 flex-shrink-0 rounded-full bg-green-500"></span>
                                        Mobile-first responsive design
                                    </li>
                                    <li className="flex items-start">
                                        <span className="mt-2 mr-3 h-2 w-2 flex-shrink-0 rounded-full bg-green-500"></span>
                                        Accessibility support (prefers-reduced-motion)
                                    </li>
                                    <li className="flex items-start">
                                        <span className="mt-2 mr-3 h-2 w-2 flex-shrink-0 rounded-full bg-green-500"></span>
                                        Session-based intro sequence with TTL
                                    </li>
                                </ul>
                            </div>

                            <div>
                                <h3 className="mb-3 text-lg font-semibold text-gray-800">Animation Modes</h3>
                                <div className="space-y-4">
                                    <div className="rounded-lg bg-blue-50 p-4">
                                        <h4 className="font-medium text-blue-900">Continuous Rotation</h4>
                                        <p className="mt-1 text-sm text-blue-700">Ring slice rotates continuously at configurable speed</p>
                                    </div>
                                    <div className="rounded-lg bg-purple-50 p-4">
                                        <h4 className="font-medium text-purple-900">Scroll-Synced</h4>
                                        <p className="mt-1 text-sm text-purple-700">Rotation tied to viewport scroll position</p>
                                    </div>
                                    <div className="rounded-lg bg-green-50 p-4">
                                        <h4 className="font-medium text-green-900">Intro Sequence</h4>
                                        <p className="mt-1 text-sm text-green-700">First-visit animation: rotate → counter-rotate → fill</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Usage Example */}
                        <div className="mt-8 rounded-lg bg-gray-50 p-6">
                            <h3 className="mb-3 text-lg font-semibold text-gray-800">Basic Usage Example</h3>
                            <pre className="overflow-x-auto text-sm text-gray-700">
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
                        <div className="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <h4 className="mb-2 font-medium text-amber-800">📦 Required Dependencies</h4>
                            <p className="mb-2 text-sm text-amber-700">To enable full functionality, install the animation libraries:</p>
                            <code className="rounded bg-amber-100 px-2 py-1 text-sm text-amber-800">npm install framer-motion gsap @types/gsap</code>
                            <p className="mt-2 text-xs text-amber-600">
                                The component gracefully falls back to vanilla JavaScript if libraries are not available.
                            </p>
                        </div>
                    </div>

                    {/* Back to Home */}
                    <div className="mt-12 text-center">
                        <a
                            href="/"
                            className="inline-flex items-center rounded-lg bg-blue-600 px-6 py-3 font-medium text-white transition-colors hover:bg-blue-700"
                        >
                            ← Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </>
    );
}

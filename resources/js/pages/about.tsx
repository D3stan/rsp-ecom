import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/layouts/public-layout';
import { Button } from '@/components/ui/button';

export default function About() {
    return (
        <PublicLayout currentPage="about">
            <Head title="About Us - Your Store" />
            
            {/* Main Content */}
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                <div className="text-center mb-16">
                    <h1 className="text-4xl font-bold text-black mb-6">About Our Store</h1>
                    <p className="text-xl text-gray-600 max-w-3xl mx-auto">
                        We're passionate about bringing you the latest and greatest in technology, 
                        with a focus on quality, innovation, and exceptional customer service.
                    </p>
                </div>

                <div className="grid md:grid-cols-2 gap-12 mb-16">
                    <div>
                        <h2 className="text-2xl font-bold text-black mb-4">Our Mission</h2>
                        <p className="text-gray-600 mb-6">
                            To provide our customers with access to cutting-edge technology products 
                            that enhance their daily lives, backed by unparalleled customer support 
                            and competitive pricing.
                        </p>
                        <p className="text-gray-600">
                            We believe that great technology should be accessible to everyone, 
                            and we work tirelessly to curate a selection of products that meet 
                            the highest standards of quality and innovation.
                        </p>
                    </div>
                    <div>
                        <h2 className="text-2xl font-bold text-black mb-4">Why Choose Us</h2>
                        <ul className="space-y-3 text-gray-600">
                            <li className="flex items-start">
                                <span className="font-semibold text-black mr-2">•</span>
                                Carefully curated product selection
                            </li>
                            <li className="flex items-start">
                                <span className="font-semibold text-black mr-2">•</span>
                                Competitive pricing and regular deals
                            </li>
                            <li className="flex items-start">
                                <span className="font-semibold text-black mr-2">•</span>
                                Fast and reliable shipping
                            </li>
                            <li className="flex items-start">
                                <span className="font-semibold text-black mr-2">•</span>
                                30-day return policy
                            </li>
                            <li className="flex items-start">
                                <span className="font-semibold text-black mr-2">•</span>
                                Dedicated customer support team
                            </li>
                        </ul>
                    </div>
                </div>

                <div className="text-center">
                    <h2 className="text-2xl font-bold text-black mb-4">Ready to Shop?</h2>
                    <p className="text-gray-600 mb-8">
                        Discover our amazing collection of tech products and find something perfect for you.
                    </p>
                    <Link href="/">
                        <Button size="lg" className="bg-black text-white hover:bg-gray-900">Explore Products</Button>
                    </Link>
                </div>
            </div>
        </PublicLayout>
    );
}

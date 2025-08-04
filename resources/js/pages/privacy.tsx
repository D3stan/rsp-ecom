import { Head } from '@inertiajs/react';
import Header from '@/components/header';

export default function Privacy() {
    return (
        <>
            <Head title="Privacy Policy - Your Store" />
            
            <div className="min-h-screen bg-white">
                <Header />

                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                    <div className="text-center mb-12">
                        <h1 className="text-4xl font-bold text-black mb-6">Privacy Policy</h1>
                    </div>

                    <div className="prose prose-lg max-w-none">
                        <div className="space-y-8">
                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">Information We Collect</h2>
                                <p className="text-gray-600 mb-4">
                                    We collect information you provide directly to us, such as when you create an account, 
                                    make a purchase, or contact us for support.
                                </p>
                                <ul className="list-disc list-inside text-gray-600 space-y-2">
                                    <li>Personal information (name, email address, phone number)</li>
                                    <li>Billing and shipping addresses</li>
                                    <li>Payment information (processed securely through Stripe)</li>
                                    <li>Order history and preferences</li>
                                </ul>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">How We Use Your Information</h2>
                                <p className="text-gray-600 mb-4">
                                    We use the information we collect to provide, maintain, and improve our services:
                                </p>
                                <ul className="list-disc list-inside text-gray-600 space-y-2">
                                    <li>Process and fulfill your orders</li>
                                    <li>Send order confirmations and shipping updates</li>
                                    <li>Provide customer support</li>
                                    <li>Improve our products and services</li>
                                    <li>Send promotional emails (with your consent)</li>
                                </ul>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">Information Sharing</h2>
                                <p className="text-gray-600 mb-4">
                                    We do not sell, trade, or otherwise transfer your personal information to third parties, 
                                    except in the following circumstances:
                                </p>
                                <ul className="list-disc list-inside text-gray-600 space-y-2">
                                    <li>With service providers who help us operate our business</li>
                                    <li>When required by law or to protect our rights</li>
                                    <li>With your explicit consent</li>
                                </ul>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">Data Security</h2>
                                <p className="text-gray-600 mb-4">
                                    We implement appropriate security measures to protect your personal information:
                                </p>
                                <ul className="list-disc list-inside text-gray-600 space-y-2">
                                    <li>SSL encryption for all data transmission</li>
                                    <li>Secure payment processing through Stripe</li>
                                    <li>Regular security audits and updates</li>
                                    <li>Limited access to personal information</li>
                                </ul>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">Your Rights</h2>
                                <p className="text-gray-600 mb-4">
                                    You have the right to:
                                </p>
                                <ul className="list-disc list-inside text-gray-600 space-y-2">
                                    <li>Access and update your personal information</li>
                                    <li>Request deletion of your account and data</li>
                                    <li>Opt out of promotional communications</li>
                                    <li>Request a copy of your data</li>
                                </ul>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">Cookies</h2>
                                <p className="text-gray-600 mb-4">
                                    We use cookies to enhance your browsing experience and analyze site traffic. 
                                    You can control cookie settings through your browser preferences.
                                </p>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">Contact Us</h2>
                                <p className="text-gray-600 mb-4">
                                    If you have any questions about this Privacy Policy, please contact us:
                                </p>
                                <ul className="list-none text-gray-600 space-y-2">
                                    <li>Email: privacy@yourstore.com</li>
                                    <li>Phone: (555) 123-4567</li>
                                    <li>Address: 123 Store Street, City, State 12345</li>
                                </ul>
                            </section>
                        </div>
                    </div>

                    <div className="text-center mt-12 pt-8 border-t border-gray-200">
                        <p className="text-sm text-gray-500">
                            Last updated: {new Date().toLocaleDateString()}
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
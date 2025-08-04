import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { 
    Search, 
    ShoppingCart, 
    User, 
    Heart
} from 'lucide-react';

export default function Privacy() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Privacy Policy - Your Store" />
            
            <div className="min-h-screen bg-white">
                {/* Header */}
                <header className="border-b border-gray-200">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16">
                            {/* Logo */}
                            <div className="flex-shrink-0">
                                <Link href="/" className="text-2xl font-bold text-black">
                                    Store
                                </Link>
                            </div>

                            {/* Search Bar */}
                            <div className="flex-1 max-w-2xl mx-8">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                                    <Input 
                                        type="search"
                                        placeholder="Search products..."
                                        className="pl-10 pr-4"
                                    />
                                </div>
                            </div>

                            {/* Navigation & Actions */}
                            <div className="flex items-center space-x-4">
                                <nav className="hidden md:flex space-x-6">
                                    <Link href="/products" className="text-gray-600 hover:text-black">
                                        Categories
                                    </Link>
                                    <Link href="/about" className="text-gray-600 hover:text-black">
                                        About
                                    </Link>
                                    <Link href="/contact" className="text-gray-600 hover:text-black">
                                        Contact
                                    </Link>
                                </nav>

                                <div className="flex items-center space-x-3">
                                    {auth.user ? (
                                        <>
                                            <Button variant="ghost" size="icon">
                                                <Heart className="w-5 h-5" />
                                            </Button>
                                            <Button variant="ghost" size="icon" className="relative">
                                                <ShoppingCart className="w-5 h-5" />
                                                <Badge className="absolute -top-2 -right-2 h-5 w-5 flex items-center justify-center p-0 text-xs">
                                                    3
                                                </Badge>
                                            </Button>
                                            <Link href={route('dashboard')}>
                                                <Button variant="ghost" size="icon">
                                                    <User className="w-5 h-5" />
                                                </Button>
                                            </Link>
                                        </>
                                    ) : (
                                        <>
                                            <Button variant="ghost" size="icon" className="relative">
                                                <ShoppingCart className="w-5 h-5" />
                                                <Badge className="absolute -top-2 -right-2 h-5 w-5 flex items-center justify-center p-0 text-xs">
                                                    0
                                                </Badge>
                                            </Button>
                                            <Link href={route('login')}>
                                                <Button variant="outline" size="sm">
                                                    Login
                                                </Button>
                                            </Link>
                                            <Link href={route('register')}>
                                                <Button size="sm">
                                                    Register
                                                </Button>
                                            </Link>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                {/* Main Content */}
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                    <div className="text-center mb-12">
                        <h1 className="text-4xl font-bold text-black mb-6">Privacy Policy</h1>
                        <p className="text-lg text-gray-600">
                            Last updated: {new Date().toLocaleDateString()}
                        </p>
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

                    <div className="text-center mt-12">
                        <Link href="/">
                            <Button size="lg">Back to Home</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
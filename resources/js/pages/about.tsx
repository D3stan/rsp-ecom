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

export default function About() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="About Us - Your Store" />
            
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
                                    <Link href="/about" className="text-black font-medium">
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
                            <Button size="lg">Explore Products</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}

import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { 
    Search, 
    ShoppingCart, 
    User, 
    Star, 
    Heart,
    Filter,
    Grid3X3,
    List
} from 'lucide-react';

export default function Products() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Products - Your Store" />
            
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
                                    <Link href="/products" className="text-black font-medium">
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
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-black mb-4">All Products</h1>
                        <div className="flex items-center justify-between">
                            <p className="text-gray-600">Showing 1-12 of 48 products</p>
                            <div className="flex items-center space-x-4">
                                <Button variant="outline" size="sm">
                                    <Filter className="w-4 h-4 mr-2" />
                                    Filters
                                </Button>
                                <div className="flex border rounded-md">
                                    <Button variant="ghost" size="sm" className="rounded-r-none">
                                        <Grid3X3 className="w-4 h-4" />
                                    </Button>
                                    <Button variant="ghost" size="sm" className="rounded-l-none">
                                        <List className="w-4 h-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="text-center py-20">
                        <h2 className="text-xl font-semibold text-gray-900 mb-2">Coming Soon</h2>
                        <p className="text-gray-600 mb-4">Product listings will be implemented here</p>
                        <Link href="/">
                            <Button>Back to Home</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}

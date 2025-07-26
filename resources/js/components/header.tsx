import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { 
    Search, 
    ShoppingCart, 
    User, 
    Heart
} from 'lucide-react';

interface HeaderProps {
    currentPage?: 'home' | 'products' | 'about' | 'contact';
}

export default function Header({ currentPage = 'home' }: HeaderProps) {
    const { auth } = usePage<SharedData>().props;

    const isActive = (page: string) => currentPage === page;

    return (
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
                            <Link 
                                href="/products" 
                                className={isActive('products') ? 'text-black font-medium' : 'text-gray-600 hover:text-black'}
                            >
                                Categories
                            </Link>
                            <Link 
                                href="/about" 
                                className={isActive('about') ? 'text-black font-medium' : 'text-gray-600 hover:text-black'}
                            >
                                About
                            </Link>
                            <Link 
                                href="/contact" 
                                className={isActive('contact') ? 'text-black font-medium' : 'text-gray-600 hover:text-black'}
                            >
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
    );
}

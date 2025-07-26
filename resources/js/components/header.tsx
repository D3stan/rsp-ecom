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
    transparent?: boolean;
}

export default function Header({ currentPage = 'home', transparent = false }: HeaderProps) {
    const { auth } = usePage<SharedData>().props;

    const isActive = (page: string) => currentPage === page;

    return (
        <header className={`border-b ${transparent ? 'border-white/20 bg-transparent backdrop-blur-sm' : 'border-gray-200 bg-white'}`}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between h-16">
                    {/* Logo */}
                    <div className="flex-shrink-0">
                        <Link href="/" className={`text-2xl font-bold ${transparent ? 'text-white' : 'text-black'}`}>
                            Store
                        </Link>
                    </div>

                    {/* Search Bar */}
                    <div className="flex-1 max-w-2xl mx-8">
                        <div className="relative">
                            <Search className={`absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 ${transparent ? 'text-white/60' : 'text-gray-400'}`} />
                            <Input 
                                type="search"
                                placeholder="Search products..."
                                className={`pl-10 pr-4 ${transparent ? 'bg-white/10 border-white/20 text-white placeholder:text-white/60' : ''}`}
                            />
                        </div>
                    </div>

                    {/* Navigation & Actions */}
                    <div className="flex items-center space-x-4">
                        <nav className="hidden md:flex space-x-6">
                            <Link 
                                href="/products" 
                                className={isActive('products') 
                                    ? `font-medium ${transparent ? 'text-white' : 'text-black'}` 
                                    : `${transparent ? 'text-white/80 hover:text-white' : 'text-gray-600 hover:text-black'}`
                                }
                            >
                                Categories
                            </Link>
                            <Link 
                                href="/about" 
                                className={isActive('about') 
                                    ? `font-medium ${transparent ? 'text-white' : 'text-black'}` 
                                    : `${transparent ? 'text-white/80 hover:text-white' : 'text-gray-600 hover:text-black'}`
                                }
                            >
                                About
                            </Link>
                            <Link 
                                href="/contact" 
                                className={isActive('contact') 
                                    ? `font-medium ${transparent ? 'text-white' : 'text-black'}` 
                                    : `${transparent ? 'text-white/80 hover:text-white' : 'text-gray-600 hover:text-black'}`
                                }
                            >
                                Contact
                            </Link>
                        </nav>

                        <div className="flex items-center space-x-3">
                            {auth.user ? (
                                <>
                                    <Button variant="ghost" size="icon" className={transparent ? 'text-white hover:bg-white/10' : ''}>
                                        <Heart className="w-5 h-5" />
                                    </Button>
                                    <Button variant="ghost" size="icon" className={`relative ${transparent ? 'text-white hover:bg-white/10' : ''}`}>
                                        <ShoppingCart className="w-5 h-5" />
                                        <Badge className="absolute -top-2 -right-2 h-5 w-5 flex items-center justify-center p-0 text-xs">
                                            3
                                        </Badge>
                                    </Button>
                                    <Link href={route('dashboard')}>
                                        <Button variant="ghost" size="icon" className={transparent ? 'text-white hover:bg-white/10' : ''}>
                                            <User className="w-5 h-5" />
                                        </Button>
                                    </Link>
                                </>
                            ) : (
                                <>
                                    <Button variant="ghost" size="icon" className={`relative ${transparent ? 'text-white hover:bg-white/10' : ''}`}>
                                        <ShoppingCart className="w-5 h-5" />
                                        <Badge className="absolute -top-2 -right-2 h-5 w-5 flex items-center justify-center p-0 text-xs">
                                            0
                                        </Badge>
                                    </Button>
                                    <Link href={route('login')}>
                                        <Button variant={transparent ? "ghost" : "outline"} size="sm" className={transparent ? 'text-white border-white/20 hover:bg-white/10' : ''}>
                                            Login
                                        </Button>
                                    </Link>
                                    <Link href={route('register')}>
                                        <Button size="sm" className={transparent ? 'bg-white text-black hover:bg-white/90' : ''}>
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

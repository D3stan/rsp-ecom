import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { 
    ShoppingCart, 
    User, 
    Heart,
    Globe,
    Menu,
    X,
    HelpCircle,
    Package
} from 'lucide-react';
import useTranslation from '@/hooks/useTranslation';
import { useState, useRef, useEffect } from 'react';
import { cartService } from '@/services/cartService';

interface HeaderProps {
    currentPage?: 'home' | 'products' | 'about' | 'contact';
    transparent?: boolean;
}

export default function Header({ transparent = false }: HeaderProps) {
    const { auth, name } = usePage<SharedData>().props;
    const { t, locale, changeLocale, isLoading } = useTranslation();
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [cartCount, setCartCount] = useState(0);
    const [isScrolled, setIsScrolled] = useState(false);
    const [cartAnimation, setCartAnimation] = useState<'none' | 'success' | 'error'>('none');
    const menuRef = useRef<HTMLDivElement>(null);

    // Load cart count on mount
    useEffect(() => {
        const loadCartCount = async () => {
            try {
                const count = await cartService.getCartCount();
                setCartCount(count);
            } catch (error) {
                console.error('Failed to load cart count:', error);
            }
        };
        
        loadCartCount();
    }, []);

    // Handle scroll to change header appearance on transparent pages
    useEffect(() => {
        if (!transparent) return;

        const handleScroll = () => {
            const scrollPosition = window.scrollY;
            // Change to solid background after scrolling past the hero section (roughly 100vh - header height)
            const heroHeight = window.innerHeight - 64;
            setIsScrolled(scrollPosition > heroHeight); 
        };

        window.addEventListener('scroll', handleScroll, { passive: true });
        handleScroll(); // Check initial position
        
        return () => window.removeEventListener('scroll', handleScroll);
    }, [transparent]);

    // Listen for cart updates
    useEffect(() => {
        const handleCartUpdate = async () => {
            try {
                const count = await cartService.getCartCount();
                setCartCount(count);
            } catch (error) {
                console.error('Failed to refresh cart count:', error);
            }
        };

        const handleCartAnimation = (event: CustomEvent) => {
            const animationType = event.detail?.type || 'success';
            setCartAnimation(animationType);
            
            // Reset animation after 1 second
            setTimeout(() => {
                setCartAnimation('none');
            }, 1000);
        };

        window.addEventListener('cartUpdated', handleCartUpdate);
        window.addEventListener('cartAnimation', handleCartAnimation as EventListener);
        
        return () => {
            window.removeEventListener('cartUpdated', handleCartUpdate);
            window.removeEventListener('cartAnimation', handleCartAnimation as EventListener);
        };
    }, []);

    // Close menu when clicking outside
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
                // Don't close if clicking on the menu button
                const target = event.target as HTMLElement;
                const menuButton = target.closest('[data-menu-button]');
                if (!menuButton) {
                    setIsMenuOpen(false);
                }
            }
        };

        if (isMenuOpen) {
            document.addEventListener('mousedown', handleClickOutside);
            // Prevent body scroll when menu is open on mobile
            if (window.innerWidth < 768) {
                document.body.style.overflow = 'hidden';
            }
        } else {
            document.body.style.overflow = 'auto';
        }

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.body.style.overflow = 'auto';
        };
    }, [isMenuOpen]);

    const closeMenu = () => setIsMenuOpen(false);

    // Determine if header should use dark theme (transparent + not scrolled) or light theme
    const isDarkTheme = transparent && !isScrolled;
    const headerBg = transparent 
        ? (isScrolled ? 'bg-white/95 backdrop-blur-md border-gray-200' : 'bg-transparent backdrop-blur-md border-white/20')
        : 'bg-white/95 backdrop-blur-md border-gray-200';

    // Cart button animation classes
    const getCartButtonClasses = () => {
        const baseClasses = `relative transition-all duration-300 ${isDarkTheme ? 'text-white hover:bg-white/10' : 'text-black hover:bg-gray-100 border-gray-200'}`;
        
        if (cartAnimation === 'success') {
            return `${baseClasses} bg-green-600 text-white scale-110 shadow-lg`;
        } else if (cartAnimation === 'error') {
            return `${baseClasses} bg-red-600 text-white scale-110 shadow-lg`;
        }
        
        return baseClasses;
    };

    return (
        <header className={`sticky top-0 z-50 border-b transition-all duration-300 relative ${headerBg}`}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between h-16">
                    {/* Logo */}
                    <div className="flex-shrink-0">
                        <Link href="/" className={`flex items-center space-x-2 transition-colors duration-300`}>
                            <img 
                                src="/images/rsplogo.png" 
                                alt={name || 'Store'} 
                                className="h-8"
                                onError={(e) => {
                                    // Fallback to favicon.ico if SVG fails
                                    const target = e.target as HTMLImageElement;
                                    target.src = '/favicon.ico';
                                }}
                            />
                        </Link>
                    </div>

                    {/* Center Spacer */}
                    <div className="flex-1" />

                    {/* Right Side Actions */}
                    <div className="flex items-center space-x-4">
                        {/* Language & Currency Selectors */}
                        <div className="hidden md:flex items-center space-x-3">
                            {/* Language Selector */}
                            <div className="flex items-center space-x-1">
                                <Globe className={`w-4 h-4 transition-colors duration-300 ${isDarkTheme ? 'text-white/80' : 'text-gray-600'}`} />
                                <Select value={locale} onValueChange={changeLocale} disabled={isLoading}>
                                    <SelectTrigger className={`w-16 h-8 border-none bg-transparent transition-colors duration-300 ${
                                        isDarkTheme ? 'text-white hover:bg-white/10' : 'text-gray-700 hover:bg-gray-100'
                                    } ${isLoading ? 'opacity-50 cursor-not-allowed' : ''}`}>
                                        {isLoading ? (
                                            <div className="animate-spin rounded-full h-3 w-3 border border-current border-t-transparent" />
                                        ) : (
                                            <SelectValue />
                                        )}
                                    </SelectTrigger>
                                    <SelectContent className="bg-white border border-gray-200">
                                        <SelectItem value="en" className="text-gray-900 hover:bg-gray-100">EN</SelectItem>
                                        <SelectItem value="es" className="text-gray-900 hover:bg-gray-100">ES</SelectItem>
                                        <SelectItem value="fr" className="text-gray-900 hover:bg-gray-100">FR</SelectItem>
                                        <SelectItem value="de" className="text-gray-900 hover:bg-gray-100">DE</SelectItem>
                                        <SelectItem value="it" className="text-gray-900 hover:bg-gray-100">IT</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* User Actions */}
                        <div className="flex items-center space-x-3">

                            <Link href="/cart">
                                <Button variant="ghost" size="icon" className={getCartButtonClasses()}>
                                    <ShoppingCart className="w-5 h-5" />
                                    {cartCount > 0 && (
                                        <Badge className="absolute -top-2 -right-2 h-5 w-5 flex items-center justify-center p-0 text-xs">
                                            {cartCount > 99 ? '99+' : cartCount}
                                        </Badge>
                                    )}
                                </Button>
                            </Link>
                            {auth.user && (
                                <Button variant="ghost" size="icon" className={`transition-colors duration-300 ${isDarkTheme ? 'text-white hover:bg-white/10' : 'text-black hover:bg-gray-100'}`}>
                                    <Heart className="w-5 h-5" />
                                </Button>
                            )}
                            <Link href={auth.user ? route('dashboard') : route('login')}>
                                <Button variant="ghost" size="icon" className={`transition-colors duration-300 ${isDarkTheme ? 'text-white hover:bg-white/10' : 'text-black hover:bg-gray-100'}`}>
                                    <User className="w-5 h-5" />
                                </Button>
                            </Link>
                            
                            {/* Burger Menu Button */}
                            <Button 
                                variant="ghost" 
                                size="icon" 
                                className={`transition-colors duration-300 ${isDarkTheme ? 'text-white hover:bg-white/10' : 'text-black hover:bg-gray-100'}`}
                                onClick={() => setIsMenuOpen(!isMenuOpen)}
                                data-menu-button
                            >
                                <Menu className="w-5 h-5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Overlay for mobile */}
            {isMenuOpen && (
                <div className="fixed inset-0 bg-black/50 z-40 md:hidden" onClick={closeMenu} />
            )}

            {/* Slide-out Menu */}
            <div 
                ref={menuRef}
                className={`fixed z-50 bg-white shadow-lg transition-transform duration-300 ease-in-out overflow-hidden
                right-0 top-0 h-screen w-80 max-w-[85vw]
                md:absolute md:top-full md:right-0 md:w-64 md:h-auto md:rounded-lg md:border md:border-gray-200 md:shadow-xl
                ${isMenuOpen 
                    ? 'translate-x-0 translate-y-0' 
                    : 'translate-x-full md:translate-x-0 md:-translate-y-full'
                }
                ${!isMenuOpen ? 'md:hidden' : 'md:block'}
                `}
            >
                {/* Menu Header */}
                <div className="flex items-center justify-between p-4 border-b border-gray-200">
                    <h3 className="text-lg font-semibold text-gray-900">Menu</h3>
                    <Button variant="ghost" size="icon" onClick={closeMenu}>
                        <X className="w-5 h-5" />
                    </Button>
                </div>

                {/* Menu Content */}
                <div className="p-4 space-y-6 overflow-y-auto">
                    {/* Language Selector */}
                    <div className="space-y-2">
                        <h4 className="text-sm font-medium text-gray-700">Language</h4>
                        <div className="flex items-center space-x-2">
                            <Globe className="w-4 h-4 text-gray-600" />
                            <Select value={locale} onValueChange={(value) => { changeLocale(value); closeMenu(); }} disabled={isLoading}>
                                <SelectTrigger className="w-full h-8 border border-gray-300 text-black">
                                    {isLoading ? (
                                        <div className="animate-spin rounded-full h-3 w-3 border border-current border-t-transparent" />
                                    ) : (
                                        <SelectValue />
                                    )}
                                </SelectTrigger>
                                <SelectContent 
                                    className="bg-white border border-gray-200 z-[9999] max-w-[200px]"
                                    position="popper"
                                    side="bottom"
                                    align="end"
                                    avoidCollisions={true}
                                    collisionPadding={8}
                                >
                                    <SelectItem value="en" className="text-gray-900 hover:bg-gray-100">English</SelectItem>
                                    <SelectItem value="es" className="text-gray-900 hover:bg-gray-100">Español</SelectItem>
                                    <SelectItem value="fr" className="text-gray-900 hover:bg-gray-100">Français</SelectItem>
                                    <SelectItem value="de" className="text-gray-900 hover:bg-gray-100">Deutsch</SelectItem>
                                    <SelectItem value="it" className="text-gray-900 hover:bg-gray-100">Italiano</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {/* Explore Products */}
                    <div className="space-y-2">
                        <Link href="/products" onClick={closeMenu}>
                            <h4 className="text-base font-medium text-gray-900 hover:text-blue-600 transition-colors cursor-pointer">
                                {t('explore_products')}
                            </h4>
                        </Link>
                    </div>

                    {/* Bottom Actions */}
                    <div className="space-y-3 pt-4 border-t border-gray-200">
                        <Link href="/help" onClick={closeMenu} className="flex items-center space-x-3 text-gray-700 hover:text-blue-600 transition-colors">
                            <HelpCircle className="w-5 h-5" />
                            <span>{t('help')}</span>
                        </Link>
                        
                        <Link href="/cart" onClick={closeMenu} className="flex items-center space-x-3 text-gray-700 hover:text-blue-600 transition-colors">
                            <ShoppingCart className="w-5 h-5" />
                            <span>{t('cart')}</span>
                        </Link>
                        
                        {auth.user && (
                            <Link href="/orders" onClick={closeMenu} className="flex items-center space-x-3 text-gray-700 hover:text-blue-600 transition-colors">
                                <Package className="w-5 h-5" />
                                <span>{t('orders')}</span>
                            </Link>
                        )}
                    </div>
                </div>
            </div>
        </header>
    );
}

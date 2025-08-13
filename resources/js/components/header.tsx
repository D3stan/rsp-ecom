import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import useTranslation from '@/hooks/useTranslation';
import { cartService } from '@/services/cartService';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Globe, Heart, HelpCircle, Menu, Package, ShoppingCart, User, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface HeaderProps {
    currentPage?: 'home' | 'products' | 'about' | 'contact';
    transparent?: boolean;
}

export default function Header({ transparent = false }: HeaderProps) {
    const { auth, name } = usePage<SharedData>().props;
    const { t, locale, changeLocale, isLoading } = useTranslation();
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [cartCount, setCartCount] = useState(0);
    const [wishlistCount, setWishlistCount] = useState(0);
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

        const loadWishlistCount = async () => {
            if (!auth.user) return;
            
            try {
                const response = await fetch('/wishlist/count', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                
                if (response.ok) {
                    const data = await response.json();
                    setWishlistCount(data.count || 0);
                }
            } catch (error) {
                console.error('Failed to load wishlist count:', error);
            }
        };

        loadCartCount();
        loadWishlistCount();
    }, [auth.user]);

    // Handle scroll to change header appearance on transparent pages
    useEffect(() => {
        if (!transparent) return;

        let ticking = false;
        let lastScrollY = window.scrollY;
        
        const handleScroll = () => {
            const currentScrollY = window.scrollY;
            
            // Only process if scroll has changed significantly to reduce re-renders
            if (Math.abs(currentScrollY - lastScrollY) < 5 && ticking) return;
            
            if (!ticking) {
                requestAnimationFrame(() => {
                    const scrollPosition = window.scrollY;
                    const heroHeight = window.innerHeight - 64;
                    const newIsScrolled = scrollPosition > heroHeight;
                    
                    // Only update state if it actually changed
                    setIsScrolled(prev => {
                        if (prev !== newIsScrolled) {
                            lastScrollY = scrollPosition;
                            return newIsScrolled;
                        }
                        return prev;
                    });
                    ticking = false;
                });
                ticking = true;
            }
        };

        window.addEventListener('scroll', handleScroll, { passive: true });
        handleScroll(); // Check initial position

        return () => window.removeEventListener('scroll', handleScroll);
    }, [transparent]);

    // Listen for cart and wishlist updates
    useEffect(() => {
        const handleCartUpdate = async () => {
            try {
                const count = await cartService.getCartCount();
                setCartCount(count);
            } catch (error) {
                console.error('Failed to refresh cart count:', error);
            }
        };

        const handleWishlistUpdate = async () => {
            if (!auth.user) return;
            
            try {
                const response = await fetch('/wishlist/count', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                
                if (response.ok) {
                    const data = await response.json();
                    setWishlistCount(data.count || 0);
                }
            } catch (error) {
                console.error('Failed to refresh wishlist count:', error);
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
        window.addEventListener('wishlistUpdated', handleWishlistUpdate);
        window.addEventListener('cartAnimation', handleCartAnimation as EventListener);

        return () => {
            window.removeEventListener('cartUpdated', handleCartUpdate);
            window.removeEventListener('wishlistUpdated', handleWishlistUpdate);
            window.removeEventListener('cartAnimation', handleCartAnimation as EventListener);
        };
    }, [auth.user]);

    // Close menu when clicking outside - optimized to prevent scroll interference
    useEffect(() => {
        if (!isMenuOpen) return;

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

        // Prevent body scroll when menu is open on mobile with optimized class
        if (window.innerWidth < 768) {
            document.body.classList.add('menu-open');
            document.body.style.overflow = 'hidden';
        }

        document.addEventListener('mousedown', handleClickOutside);

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.body.classList.remove('menu-open');
            document.body.style.overflow = 'auto';
        };
    }, [isMenuOpen]);

    const closeMenu = useCallback(() => setIsMenuOpen(false), []);

    // Memoize theme and style calculations to prevent unnecessary re-renders
    const isDarkTheme = useMemo(() => transparent && !isScrolled, [transparent, isScrolled]);
    
    const headerBg = useMemo(() => {
        return transparent
            ? isScrolled
                ? 'bg-white/95 backdrop-blur-md border-gray-200'
                : 'bg-transparent backdrop-blur-md border-white/20'
            : 'bg-white/95 backdrop-blur-md border-gray-200';
    }, [transparent, isScrolled]);

    // Memoize menu classes to prevent animation glitches
    const menuClasses = useMemo(() => {
        const baseClasses = 'mobile-menu mobile-menu-transition fixed top-0 right-0 z-50 h-screen w-80 max-w-[85vw] overflow-hidden bg-white shadow-lg transition-transform duration-300 ease-in-out md:absolute md:top-full md:right-0 md:h-auto md:w-64 md:rounded-lg md:border md:border-gray-200 md:shadow-xl';
        const transformClasses = isMenuOpen 
            ? 'translate-x-0 translate-y-0' 
            : 'translate-x-full md:translate-x-0 md:-translate-y-full';
        const visibilityClasses = !isMenuOpen ? 'md:hidden' : 'md:block';
        
        return `${baseClasses} ${transformClasses} ${visibilityClasses}`;
    }, [isMenuOpen]);

    // Cart button animation classes - memoized to prevent re-renders
    const cartButtonClasses = useMemo(() => {
        const baseClasses = `relative transition-all duration-300 ${isDarkTheme ? 'text-white hover:bg-white/10' : 'text-black hover:bg-gray-100 border-gray-200'}`;

        if (cartAnimation === 'success') {
            return `${baseClasses} bg-green-600 text-white scale-110 shadow-lg`;
        } else if (cartAnimation === 'error') {
            return `${baseClasses} bg-red-600 text-white scale-110 shadow-lg`;
        }

        return baseClasses;
    }, [isDarkTheme, cartAnimation]);

    return (
        <header className={`relative sticky top-0 z-50 border-b transition-all duration-300 ${headerBg}`}>
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex h-16 items-center justify-between">
                    {/* Logo */}
                    <div className="flex-shrink-0">
                        <Link href="/" className={`flex items-center space-x-2 transition-colors duration-300`}>
                            <img
                                src="/images/rsplogo.png"
                                alt={name || 'Store'}
                                className={`h-8 brightness-0 ${isDarkTheme ? 'invert' : ''}`}
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
                        <div className="hidden items-center space-x-3 md:flex">
                            {/* Language Selector */}
                            <div className="flex items-center space-x-1">
                                <Globe className={`h-4 w-4 transition-colors duration-300 ${isDarkTheme ? 'text-white/80' : 'text-gray-600'}`} />
                                <Select value={locale} onValueChange={changeLocale} disabled={isLoading}>
                                    <SelectTrigger
                                        className={`h-8 w-16 border-none bg-transparent transition-colors duration-300 ${
                                            isDarkTheme ? 'text-white hover:bg-white/10' : 'text-gray-700 hover:bg-gray-100'
                                        } ${isLoading ? 'cursor-not-allowed opacity-50' : ''}`}
                                    >
                                        {isLoading ? (
                                            <div className="h-3 w-3 animate-spin rounded-full border border-current border-t-transparent" />
                                        ) : (
                                            <SelectValue />
                                        )}
                                    </SelectTrigger>
                                    <SelectContent className="border border-gray-200 bg-white">
                                        <SelectItem value="en" className="text-gray-900 hover:bg-gray-100">
                                            EN
                                        </SelectItem>
                                        <SelectItem value="es" className="text-gray-900 hover:bg-gray-100">
                                            ES
                                        </SelectItem>
                                        <SelectItem value="fr" className="text-gray-900 hover:bg-gray-100">
                                            FR
                                        </SelectItem>
                                        <SelectItem value="de" className="text-gray-900 hover:bg-gray-100">
                                            DE
                                        </SelectItem>
                                        <SelectItem value="it" className="text-gray-900 hover:bg-gray-100">
                                            IT
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* User Actions */}
                        <div className="flex items-center space-x-3">
                            <Link href="/cart">
                                <Button variant="ghost" size="icon" className={cartButtonClasses}>
                                    <ShoppingCart className="h-5 w-5" />
                                    {cartCount > 0 && (
                                        <Badge className="absolute -top-2 -right-2 flex h-5 w-5 items-center justify-center p-0 text-xs">
                                            {cartCount > 99 ? '99+' : cartCount}
                                        </Badge>
                                    )}
                                </Button>
                            </Link>
                            {auth.user && (
                                <Link href={route('wishlist.index')}>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className={`relative transition-colors duration-300 ${isDarkTheme ? 'text-white hover:bg-white/10' : 'text-black hover:bg-gray-100'}`}
                                    >
                                        <Heart className="h-5 w-5" />
                                        {wishlistCount > 0 && (
                                            <Badge className="absolute -top-2 -right-2 flex h-5 w-5 items-center justify-center p-0 text-xs">
                                                {wishlistCount > 99 ? '99+' : wishlistCount}
                                            </Badge>
                                        )}
                                    </Button>
                                </Link>
                            )}
                            <Link href={auth.user ? route('dashboard') : route('login')}>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className={`transition-colors duration-300 ${isDarkTheme ? 'text-white hover:bg-white/10' : 'text-black hover:bg-gray-100'}`}
                                >
                                    <User className="h-5 w-5" />
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
                                <Menu className="h-5 w-5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Overlay for mobile */}
            {isMenuOpen && <div className="fixed inset-0 z-40 bg-black/50 md:hidden" onClick={closeMenu} />}

            {/* Slide-out Menu */}
            <div
                ref={menuRef}
                className={menuClasses}
            >
                {/* Menu Header */}
                <div className="flex items-center justify-between border-b border-gray-200 p-4">
                    <h3 className="text-lg font-semibold text-gray-900">Menu</h3>
                    <Button variant="ghost" size="icon" onClick={closeMenu}>
                        <X className="h-5 w-5" />
                    </Button>
                </div>

                {/* Menu Content */}
                <div className="space-y-6 overflow-y-auto p-4">
                    {/* Language Selector */}
                    <div className="space-y-2">
                        <h4 className="text-sm font-medium text-gray-700">Language</h4>
                        <div className="flex items-center space-x-2">
                            <Globe className="h-4 w-4 text-gray-600" />
                            <Select
                                value={locale}
                                onValueChange={(value) => {
                                    changeLocale(value);
                                    closeMenu();
                                }}
                                disabled={isLoading}
                            >
                                <SelectTrigger className="h-8 w-full border border-gray-300 text-black">
                                    {isLoading ? (
                                        <div className="h-3 w-3 animate-spin rounded-full border border-current border-t-transparent" />
                                    ) : (
                                        <SelectValue />
                                    )}
                                </SelectTrigger>
                                <SelectContent
                                    className="z-[9999] max-w-[200px] border border-gray-200 bg-white"
                                    position="popper"
                                    side="bottom"
                                    align="end"
                                    avoidCollisions={true}
                                    collisionPadding={8}
                                >
                                    <SelectItem value="en" className="text-gray-900 hover:bg-gray-100">
                                        English
                                    </SelectItem>
                                    <SelectItem value="es" className="text-gray-900 hover:bg-gray-100">
                                        Español
                                    </SelectItem>
                                    <SelectItem value="fr" className="text-gray-900 hover:bg-gray-100">
                                        Français
                                    </SelectItem>
                                    <SelectItem value="de" className="text-gray-900 hover:bg-gray-100">
                                        Deutsch
                                    </SelectItem>
                                    <SelectItem value="it" className="text-gray-900 hover:bg-gray-100">
                                        Italiano
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {/* Explore Products */}
                    <div className="space-y-2">
                        <Link href="/products" onClick={closeMenu}>
                            <h4 className="cursor-pointer text-base font-medium text-gray-900 transition-colors hover:text-blue-600">
                                {t('explore_products')}
                            </h4>
                        </Link>
                    </div>

                    {/* Bottom Actions */}
                    <div className="space-y-3 border-t border-gray-200 pt-4">
                        <Link
                            href="/help"
                            onClick={closeMenu}
                            className="flex items-center space-x-3 text-gray-700 transition-colors hover:text-blue-600"
                        >
                            <HelpCircle className="h-5 w-5" />
                            <span>{t('help')}</span>
                        </Link>

                        <Link
                            href="/cart"
                            onClick={closeMenu}
                            className="flex items-center space-x-3 text-gray-700 transition-colors hover:text-blue-600"
                        >
                            <ShoppingCart className="h-5 w-5" />
                            <span>{t('cart_link')}</span>
                        </Link>

                        {auth.user && (
                            <Link
                                href={route('wishlist.index')}
                                onClick={closeMenu}
                                className="flex items-center space-x-3 text-gray-700 transition-colors hover:text-blue-600"
                            >
                                <Heart className="h-5 w-5" />
                                <span>Wishlist</span>
                            </Link>
                        )}

                        {auth.user && (
                            <Link
                                href="/orders"
                                onClick={closeMenu}
                                className="flex items-center space-x-3 text-gray-700 transition-colors hover:text-blue-600"
                            >
                                <Package className="h-5 w-5" />
                                <span>{t('orders')}</span>
                            </Link>
                        )}
                    </div>
                </div>
            </div>
        </header>
    );
}

import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import Header from '@/components/header';
import { useEffect, useRef, useState } from 'react';
import useTranslation from '@/hooks/useTranslation';
import { useMobileInteractions } from '@/hooks/use-mobile-interactions';
import { cartService, type AddToCartData } from '@/services/cartService';
import { 
    Star, 
    ArrowRight,
    Smartphone,
    Laptop,
    Headphones,
    Watch,
    Camera,
    Gamepad2,
    Shirt,
    Home as HomeIcon,
    Activity,
    Book,
    Heart,
    Package
} from 'lucide-react';

// Social media icons from Simple Icons (replacing deprecated Lucide brand icons)
const FacebookIcon = ({ className }: { className?: string }) => (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor">
        <path d="M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 3.667h-3.533v7.98H9.101z"/>
    </svg>
);

const InstagramIcon = ({ className }: { className?: string }) => (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor">
        <path d="M7.0301.084c-1.2768.0602-2.1487.264-2.911.5634-.7888.3075-1.4575.72-2.1228 1.3877-.6652.6677-1.075 1.3368-1.3802 2.127-.2954.7638-.4956 1.6365-.552 2.914-.0564 1.2775-.0689 1.6882-.0626 4.947.0062 3.2586.0206 3.6671.0825 4.9473.061 1.2765.264 2.1482.5635 2.9107.308.7889.72 1.4573 1.388 2.1228.6679.6655 1.3365 1.0743 2.1285 1.38.7632.295 1.6361.4961 2.9134.552 1.2773.056 1.6884.069 4.9462.0627 3.2578-.0062 3.668-.0207 4.9478-.0814 1.28-.0607 2.147-.2652 2.9098-.5633.7889-.3086 1.4578-.72 2.1228-1.3881.665-.6682 1.0745-1.3378 1.3795-2.1284.2957-.7632.4966-1.636.552-2.9124.056-1.2809.0692-1.6898.063-4.948-.0063-3.2583-.021-3.6668-.0817-4.9465-.0607-1.2797-.264-2.1487-.5633-2.9117-.3084-.7889-.72-1.4568-1.3876-2.1228C21.2982 1.33 20.628.9208 19.8378.6165 19.074.321 18.2017.1197 16.9244.0645 15.6471.0093 15.236-.005 11.977.0014 8.718.0076 8.31.0215 7.0301.0839m.1402 21.6932c-1.17-.0509-1.8053-.2453-2.2287-.408-.5606-.216-.96-.4771-1.3819-.895-.422-.4178-.6811-.8186-.9-1.378-.1644-.4234-.3624-1.058-.4171-2.228-.0595-1.2645-.072-1.6442-.079-4.848-.007-3.2037.0053-3.583.0607-4.848.05-1.169.2456-1.805.408-2.2282.216-.5613.4762-.96.895-1.3816.4188-.4217.8184-.6814 1.3783-.9003.423-.1651 1.0575-.3614 2.227-.4171 1.2655-.06 1.6447-.072 4.848-.079 3.2033-.007 3.5835.005 4.8495.0608 1.169.0508 1.8053.2445 2.228.408.5608.216.96.4754 1.3816.895.4217.4194.6816.8176.9005 1.3787.1653.4217.3617 1.056.4169 2.2263.0602 1.2655.0739 1.645.0796 4.848.0058 3.203-.0055 3.5834-.061 4.848-.051 1.17-.245 1.8055-.408 2.2294-.216.5604-.4763.96-.8954 1.3814-.419.4215-.8181.6811-1.3783.9-.4224.1649-1.0577.3617-2.2262.4174-1.2656.0595-1.6448.072-4.8493.079-3.2045.007-3.5825-.006-4.848-.0608M16.953 5.5864A1.44 1.44 0 1 0 18.39 4.144a1.44 1.44 0 0 0-1.437 1.4424M5.8385 12.012c.0067 3.4032 2.7706 6.1557 6.173 6.1493 3.4026-.0065 6.157-2.7701 6.1506-6.1733-.0065-3.4032-2.771-6.1565-6.174-6.1498-3.403.0067-6.156 2.771-6.1496 6.1738M8 12.0077a4 4 0 1 1 4.008 3.9921A3.9996 3.9996 0 0 1 8 12.0077"/>
    </svg>
);

const TikTokIcon = ({ className }: { className?: string }) => (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor">
        <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
    </svg>
);

interface Product {
    id: number;
    name: string;
    slug: string;
    price: number;
    originalPrice?: number;
    rating: number;
    reviews: number;
    image: string;
    badge?: string;
    category?: {
        id: number;
        name: string;
        slug: string;
    };
}

interface Category {
    id: number;
    name: string;
    slug: string;
    icon: string;
    count: number;
}

interface HomePageProps extends SharedData {
    featuredProducts: Product[];
    categories: Category[];
}

// Map icon names to actual icon components
const iconMap = {
    Smartphone,
    Laptop,
    Headphones,
    Watch,
    Camera,
    Gamepad2,
    Shirt,
    Home: HomeIcon,
    Activity,
    Book,
    Heart,
    Package,
};

const renderStars = (rating: number) => {
    return Array.from({ length: 5 }, (_, i) => (
        <Star 
            key={i} 
            className={`w-4 h-4 ${i < Math.floor(rating) ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} 
        />
    ));
};

export default function Home() {
    const parallaxRef = useRef<HTMLDivElement>(null);
    const [videoLoaded, setVideoLoaded] = useState(false);
    const [addingToCartId, setAddingToCartId] = useState<number | null>(null);
    const { t, isLoading } = useTranslation();
    const { featuredProducts, categories } = usePage<HomePageProps>().props;
    
    // Mobile interactions for products and categories
    const productInteractions = useMobileInteractions<number>();
    const categoryInteractions = useMobileInteractions<number>();

    const handleAddToCart = async (productId: number) => {
        if (addingToCartId === productId) return;
        
        setAddingToCartId(productId);
        
        try {
            const cartData: AddToCartData = {
                product_id: productId,
                quantity: 1,
            };
            
            const response = await cartService.addToCart(cartData);
            
            if (response.success) {
                cartService.triggerCartUpdate();
                cartService.triggerCartAnimation('success');
            } else {
                cartService.triggerCartAnimation('error');
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            cartService.triggerCartAnimation('error');
        } finally {
            setAddingToCartId(null);
        }
    };

    useEffect(() => {
        const handleScroll = () => {
            if (parallaxRef.current && window.innerWidth > 768) {
                const scrolled = window.pageYOffset;
                const speed = 0.5;
                
                if (scrolled < window.innerHeight) {
                    requestAnimationFrame(() => {
                        if (parallaxRef.current) {
                            parallaxRef.current.style.transform = `translateY(${scrolled * speed}px) scale(1.1)`;
                        }
                    });
                }
            }
        };

        window.addEventListener('scroll', handleScroll, { passive: true });
        handleScroll();
        
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    return (
        <>
            <Head title="Home" />
            
            {/* Loading Overlay for Language Changes */}
            {isLoading && (
                <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
                    <div className="bg-white rounded-lg p-4 sm:p-6 flex items-center space-x-3 max-w-xs">
                        <div className="animate-spin rounded-full h-5 w-5 sm:h-6 sm:w-6 border-b-2 border-black flex-shrink-0"></div>
                        <span className="text-black font-medium text-sm sm:text-base">Loading...</span>
                    </div>
                </div>
            )}
            
            <div className={`min-h-screen bg-white transition-opacity duration-300 ${isLoading ? 'opacity-50' : 'opacity-100'} select-none`}>
                {/* Header */}
                <Header currentPage="home" transparent={true} />
                
                {/* Hero Section with Video Background */}
                <section className="hero-section relative flex items-center justify-center overflow-hidden -mt-16 pt-16">
                    {/* Background Media Container */}
                    <div 
                        ref={parallaxRef}
                        className="absolute inset-0 transform scale-110 -top-16"
                        style={{ willChange: 'transform' }}
                    >
                        {/* Video Background */}
                        <video
                            autoPlay
                            muted
                            loop
                            playsInline
                            className={`w-full h-full object-cover transition-opacity duration-1000 ${
                                videoLoaded ? 'opacity-100' : 'opacity-0'
                            }`}
                            onLoadedData={() => setVideoLoaded(true)}
                            onError={() => setVideoLoaded(false)}
                        >
                            <source src="/videos/homepage.mp4" type="video/mp4" />
                        </video>
                        
                        {/* Fallback Background Image */}
                        <div 
                            className={`absolute inset-0 bg-cover bg-center bg-no-repeat transition-opacity duration-1000 ${
                                videoLoaded ? 'opacity-0' : 'opacity-100'
                            }`}
                            style={{ backgroundImage: 'url(/images/homepage.png)' }}
                        />
                    </div>
                    
                    {/* Overlay for Text Readability */}
                    <div 
                        className="absolute inset-0 z-5" 
                        style={{ background: 'linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.5))' }} 
                    />
                    
                    {/* Hero Content */}
                    <div className="relative z-10 text-center text-white px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">
                        <h1 className="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold mb-6 leading-tight drop-shadow-lg">
                            {t('discover_amazing_products')}
                        </h1>
                        <p className="text-lg sm:text-xl md:text-2xl mb-8 max-w-3xl mx-auto opacity-90 leading-relaxed drop-shadow-md">
                            {t('find_perfect_tech')}
                        </p>
                        <div className="flex flex-col sm:flex-row gap-4 justify-center items-center">
                            <Link href={route('products')}>
                                <Button size="lg" className="px-4 sm:px-6 py-3 text-base sm:text-lg bg-white text-black hover:bg-gray-100 font-semibold w-auto">
                                    {t('shop_now')}
                                    <ArrowRight className="w-4 sm:w-5 h-4 sm:h-5 ml-2" />
                                </Button>
                            </Link>
                            <Button variant="outline" size="lg" className="px-4 sm:px-6 py-3 text-base sm:text-lg border-2 border-white text-white hover:bg-white hover:text-black font-semibold w-auto">
                                {t('view_deals')}
                            </Button>
                        </div>
                    </div>
                    
                    {/* Scroll Indicator */}
                    <div className="absolute bottom-6 sm:bottom-8 left-1/2 transform -translate-x-1/2 z-10">
                        <div className="animate-bounce">
                            <div className="w-5 sm:w-6 h-8 sm:h-10 border-2 border-white rounded-full flex justify-center">
                                <div className="w-1 h-2 sm:h-3 bg-white rounded-full mt-1.5 sm:mt-2 animate-pulse" />
                            </div>
                        </div>
                    </div>
                </section>

                {/* Featured Products */}
                <section className="py-16 lg:py-24 bg-white relative z-10">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-12">
                            <h2 className="text-3xl font-bold text-black mb-4">{t('featured_products')}</h2>
                            <p className="text-gray-600 max-w-2xl mx-auto">
                                {t('featured_products_description')}
                            </p>
                        </div>

                        <div className="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {featuredProducts.map((product: Product) => {
                                const isActive = productInteractions.isActive(product.id);
                                return (
                                    <Link
                                        key={product.id}
                                        href={`/products/${product.slug}`}
                                        className="block"
                                        {...productInteractions.getInteractionProps(product.id, () => {
                                            window.location.href = `/products/${product.slug}`;
                                        })}
                                    >
                                        <div className={`group cursor-pointer transition-shadow overflow-hidden bg-white rounded-xl border shadow-sm ${
                                            productInteractions.isMobile 
                                                ? (isActive ? 'shadow-lg touch-feedback' : 'shadow-sm hover:shadow-sm touch-feedback') 
                                                : 'hover:shadow-lg'
                                        }`}>
                                            <div className="relative overflow-hidden">
                                                <img 
                                                    src={product.image} 
                                                    alt={product.name}
                                                    className={`w-full h-48 object-cover transition-transform duration-200 ${
                                                        productInteractions.isMobile 
                                                            ? (isActive ? 'scale-105' : 'scale-100') 
                                                            : 'group-hover:scale-105'
                                                    }`}
                                                />
                                                {product.badge && (
                                                    <Badge className="absolute top-2 left-2">
                                                        {product.badge}
                                                    </Badge>
                                                )}
                                                <Button 
                                                    size="sm" 
                                                    className={`absolute bottom-2 right-2 transition-opacity ${
                                                        productInteractions.isMobile 
                                                            ? (isActive ? 'opacity-100' : 'opacity-0') 
                                                            : 'opacity-0 group-hover:opacity-100'
                                                    }`}
                                                    disabled={addingToCartId === product.id}
                                                    onClick={(e) => {
                                                        e.preventDefault();
                                                        e.stopPropagation();
                                                        handleAddToCart(product.id);
                                                    }}
                                                >
                                                    {addingToCartId === product.id ? 'Adding...' : t('add_to_cart')}
                                                </Button>
                                            </div>
                                            <div className="p-4">
                                                <h3 className="font-semibold text-black mb-2 line-clamp-2">
                                                    {product.name}
                                                </h3>
                                                <div className="flex items-center gap-1 mb-2">
                                                    {renderStars(product.rating)}
                                                    <span className="text-sm text-gray-500 ml-1">
                                                        ({product.reviews})
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-lg font-bold text-black">
                                                        ${product.price}
                                                    </span>
                                                    {product.originalPrice && (
                                                        <span className="text-sm text-gray-500 line-through">
                                                            ${product.originalPrice}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>

                        <div className="text-center mt-12">
                            <Button variant="outline" size="lg">
                                {t('view_all_products')}
                                <ArrowRight className="w-4 h-4 ml-2" />
                            </Button>
                        </div>
                    </div>
                </section>

                {/* Categories Section */}
                <section className="py-16 lg:py-24 bg-gray-50 relative z-10">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-12">
                            <h2 className="text-3xl font-bold text-black mb-4">{t('shop_by_category')}</h2>
                            <p className="text-gray-600 max-w-2xl mx-auto">
                                {t('category_description')}
                            </p>
                        </div>

                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                            {categories.map((category: Category) => {
                                const IconComponent = iconMap[category.icon as keyof typeof iconMap] || iconMap.Package;
                                const isActive = categoryInteractions.isActive(category.id);
                                return (
                                    <Link 
                                        key={category.id} 
                                        href={`/products/category/${category.slug}`}
                                        className="group"
                                        {...categoryInteractions.getInteractionProps(category.id, () => {
                                            window.location.href = `/products/category/${category.slug}`;
                                        })}
                                    >
                                        <div className={`h-full text-center transition-all duration-200 bg-white rounded-xl border shadow-sm overflow-hidden ${
                                            categoryInteractions.isMobile 
                                                ? (isActive ? 'shadow-md scale-95 touch-feedback' : 'shadow-sm touch-feedback') 
                                                : 'hover:shadow-md hover:-translate-y-1'
                                        }`}>
                                            <div className="p-6">
                                                <div className={`w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center transition-all duration-200 ${
                                                    categoryInteractions.isMobile 
                                                        ? (isActive ? 'bg-black text-white scale-110' : 'bg-gray-50 text-gray-700') 
                                                        : 'bg-gray-50 text-gray-700 group-hover:bg-black group-hover:text-white group-hover:scale-110'
                                                }`}>
                                                    <IconComponent className="w-8 h-8" />
                                                </div>
                                                <h3 className="font-semibold text-gray-900 mb-2 text-sm">
                                                    {t(`categories.${category.name}`)}
                                                </h3>
                                                <p className="text-xs text-gray-500 font-medium">
                                                    {t('products_count', { count: category.count })}
                                                </p>
                                            </div>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="bg-black text-white py-12">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
                            <div>
                                <h3 className="text-xl font-bold mb-4">Store</h3>
                                <p className="text-gray-400 mb-4">
                                    {t('store_description')}
                                </p>
                            </div>
                            
                            <div>
                                <h4 className="font-semibold mb-4">{t('quick_links')}</h4>
                                <ul className="space-y-2 text-gray-400">
                                    <li><Link href="/about" className="hover:text-white footer-link md:footer-link-none">{t('about_us')}</Link></li>
                                    <li><Link href="/contact" className="hover:text-white footer-link md:footer-link-none">{t('contact')}</Link></li>
                                    <li><Link href="/faq" className="hover:text-white footer-link md:footer-link-none">{t('faq')}</Link></li>
                                    <li><Link href="/shipping" className="hover:text-white footer-link md:footer-link-none">{t('shipping_info')}</Link></li>
                                </ul>
                            </div>
                            
                            <div>
                                <h4 className="font-semibold mb-4">{t('legal')}</h4>
                                <ul className="space-y-2 text-gray-400">
                                    <li><Link href="/privacy" className="hover:text-white footer-link md:footer-link-none">{t('privacy_policy')}</Link></li>
                                    <li><Link href="/terms" className="hover:text-white footer-link md:footer-link-none">{t('terms_of_service')}</Link></li>
                                    <li><Link href="/shipping-returns" className="hover:text-white footer-link md:footer-link-none">{t('shipping_returns')}</Link></li>
                                </ul>
                            </div>
                            
                            <div>
                                <h4 className="font-semibold mb-4">{t('follow_us')}</h4>
                                <p className="text-gray-400 mb-4">
                                    {t('stay_connected')}
                                </p>
                                <div className="flex space-x-4">
                                    <a href="#" target="_blank" rel="noopener noreferrer" aria-label="Follow us on Facebook">
                                        <Button variant="ghost" size="icon" className="text-gray-400 hover:text-white">
                                            <FacebookIcon className="w-5 h-5" />
                                            <span className="sr-only">Facebook</span>
                                        </Button>
                                    </a>
                                    <a href="#" target="_blank" rel="noopener noreferrer" aria-label="Follow us on Instagram">
                                        <Button variant="ghost" size="icon" className="text-gray-400 hover:text-white">
                                            <InstagramIcon className="w-5 h-5" />
                                            <span className="sr-only">Instagram</span>
                                        </Button>
                                    </a>
                                    <a href="#" target="_blank" rel="noopener noreferrer" aria-label="Follow us on TikTok">
                                        <Button variant="ghost" size="icon" className="text-gray-400 hover:text-white">
                                            <TikTokIcon className="w-5 h-5" />
                                            <span className="sr-only">TikTok</span>
                                        </Button>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div className="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
                            <p>&copy; 2025 Store. {t('rights_reserved')}</p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}

import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import Header from '@/components/header';
import { useEffect, useRef, useState } from 'react';
import useTranslation from '@/hooks/useTranslation';
import { useIsMobile } from '@/hooks/use-mobile';
import { useMobileInteractions } from '@/hooks/use-mobile-interactions';
import { 
    Star, 
    ArrowRight,
    Smartphone,
    Laptop,
    Headphones,
    Watch,
    Camera,
    Gamepad2,
    Facebook,
    Instagram,
    Shirt,
    Home as HomeIcon,
    Activity,
    Book,
    Heart,
    Package
} from 'lucide-react';

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
    const { t, isLoading } = useTranslation();
    const { featuredProducts, categories } = usePage<HomePageProps>().props;
    
    // Mobile interactions for products and categories
    const productInteractions = useMobileInteractions<number>();
    const categoryInteractions = useMobileInteractions<number>();

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
                                    <div 
                                        key={product.id} 
                                        className={`group cursor-pointer transition-shadow overflow-hidden bg-white rounded-xl border shadow-sm ${
                                            productInteractions.isMobile 
                                                ? (isActive ? 'shadow-lg touch-feedback' : 'shadow-sm hover:shadow-sm touch-feedback') 
                                                : 'hover:shadow-lg'
                                        }`}
                                        {...productInteractions.getInteractionProps(product.id)}
                                    >
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
                                            >
                                                {t('add_to_cart')}
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
                                        <Card className={`h-full text-center transition-shadow ${
                                            categoryInteractions.isMobile 
                                                ? (isActive ? 'shadow-md touch-feedback' : 'shadow-sm touch-feedback') 
                                                : 'hover:shadow-md'
                                        }`}>
                                            <CardContent className="p-6">
                                                <div className={`w-12 h-12 mx-auto mb-3 rounded-full flex items-center justify-center transition-colors ${
                                                    categoryInteractions.isMobile 
                                                        ? (isActive ? 'bg-black text-white' : 'bg-gray-100') 
                                                        : 'bg-gray-100 group-hover:bg-black group-hover:text-white'
                                                }`}>
                                                    <IconComponent className="w-6 h-6" />
                                                </div>
                                                <h3 className="font-semibold text-black mb-1">
                                                    {t(category.name)}
                                                </h3>
                                                <p className="text-sm text-gray-500">
                                                    {t('products_count', { count: category.count })}
                                                </p>
                                            </CardContent>
                                        </Card>
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
                                    <li><Link href="/returns" className="hover:text-white footer-link md:footer-link-none">{t('returns')}</Link></li>
                                </ul>
                            </div>
                            
                            <div>
                                <h4 className="font-semibold mb-4">{t('follow_us')}</h4>
                                <p className="text-gray-400 mb-4">
                                    {t('stay_connected')}
                                </p>
                                <div className="flex space-x-4">
                                    <a href="https://facebook.com" target="_blank" rel="noopener noreferrer">
                                        <Button variant="ghost" size="icon" className="text-gray-400 hover:text-white">
                                            <Facebook className="w-5 h-5" />
                                            <span className="sr-only">Facebook</span>
                                        </Button>
                                    </a>
                                    <a href="https://instagram.com" target="_blank" rel="noopener noreferrer">
                                        <Button variant="ghost" size="icon" className="text-gray-400 hover:text-white">
                                            <Instagram className="w-5 h-5" />
                                            <span className="sr-only">Instagram</span>
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

import Header from '@/components/header';
import LoadingOverlay from '@/components/LoadingOverlay';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useMobileInteractions } from '@/hooks/use-mobile-interactions';
import useTranslation from '@/hooks/useTranslation';
import { cartService, type AddToCartData } from '@/services/cartService';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    Book,
    Camera,
    Gamepad2,
    Headphones,
    Heart,
    Home as HomeIcon,
    Laptop,
    Package,
    Shirt,
    Smartphone,
    Star,
    Watch,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const InstagramIcon = ({ className }: { className?: string }) => (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor">
        <path d="M7.0301.084c-1.2768.0602-2.1487.264-2.911.5634-.7888.3075-1.4575.72-2.1228 1.3877-.6652.6677-1.075 1.3368-1.3802 2.127-.2954.7638-.4956 1.6365-.552 2.914-.0564 1.2775-.0689 1.6882-.0626 4.947.0062 3.2586.0206 3.6671.0825 4.9473.061 1.2765.264 2.1482.5635 2.9107.308.7889.72 1.4573 1.388 2.1228.6679.6655 1.3365 1.0743 2.1285 1.38.7632.295 1.6361.4961 2.9134.552 1.2773.056 1.6884.069 4.9462.0627 3.2578-.0062 3.668-.0207 4.9478-.0814 1.28-.0607 2.147-.2652 2.9098-.5633.7889-.3086 1.4578-.72 2.1228-1.3881.665-.6682 1.0745-1.3378 1.3795-2.1284.2957-.7632.4966-1.636.552-2.9124.056-1.2809.0692-1.6898.063-4.948-.0063-3.2583-.021-3.6668-.0817-4.9465-.0607-1.2797-.264-2.1487-.5633-2.9117-.3084-.7889-.72-1.4568-1.3876-2.1228C21.2982 1.33 20.628.9208 19.8378.6165 19.074.321 18.2017.1197 16.9244.0645 15.6471.0093 15.236-.005 11.977.0014 8.718.0076 8.31.0215 7.0301.0839m.1402 21.6932c-1.17-.0509-1.8053-.2453-2.2287-.408-.5606-.216-.96-.4771-1.3819-.895-.422-.4178-.6811-.8186-.9-1.378-.1644-.4234-.3624-1.058-.4171-2.228-.0595-1.2645-.072-1.6442-.079-4.848-.007-3.2037.0053-3.583.0607-4.848.05-1.169.2456-1.805.408-2.2282.216-.5613.4762-.96.895-1.3816.4188-.4217.8184-.6814 1.3783-.9003.423-.1651 1.0575-.3614 2.227-.4171 1.2655-.06 1.6447-.072 4.848-.079 3.2033-.007 3.5835.005 4.8495.0608 1.169.0508 1.8053.2445 2.228.408.5608.216.96.4754 1.3816.895.4217.4194.6816.8176.9005 1.3787.1653.4217.3617 1.056.4169 2.2263.0602 1.2655.0739 1.645.0796 4.848.0058 3.203-.0055 3.5834-.061 4.848-.051 1.17-.245 1.8055-.408 2.2294-.216.5604-.4763.96-.8954 1.3814-.419.4215-.8181.6811-1.3783.9-.4224.1649-1.0577.3617-2.2262.4174-1.2656.0595-1.6448.072-4.8493.079-3.2045.007-3.5825-.006-4.848-.0608M16.953 5.5864A1.44 1.44 0 1 0 18.39 4.144a1.44 1.44 0 0 0-1.437 1.4424M5.8385 12.012c.0067 3.4032 2.7706 6.1557 6.173 6.1493 3.4026-.0065 6.157-2.7701 6.1506-6.1733-.0065-3.4032-2.771-6.1565-6.174-6.1498-3.403.0067-6.156 2.771-6.1496 6.1738M8 12.0077a4 4 0 1 1 4.008 3.9921A3.9996 3.9996 0 0 1 8 12.0077" />
    </svg>
);

const TikTokIcon = ({ className }: { className?: string }) => (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor">
        <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z" />
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
        <Star key={i} className={`h-4 w-4 ${i < Math.floor(rating) ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} />
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

            <LoadingOverlay isLoading={isLoading} className="min-h-screen bg-white">
                {/* SEO H1 - Hidden from view but visible to search engines */}
                <h1 className="sr-only">{t('seo_h1_title')}</h1>
                
                {/* Header */}
                <Header currentPage="home" transparent={true} />

                {/* Hero Section with Video Background */}
                <section className="hero-section relative -mt-16 flex items-center justify-center overflow-hidden pt-16">
                    {/* Background Media Container */}
                    <div ref={parallaxRef} className="absolute inset-0 -top-16 scale-110 transform" style={{ willChange: 'transform' }}>
                        {/* Video Background */}
                        <video
                            autoPlay
                            muted
                            loop
                            playsInline
                            className={`h-full w-full object-cover transition-opacity duration-1000 ${videoLoaded ? 'opacity-100' : 'opacity-0'}`}
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
                    <div className="absolute inset-0 z-5" style={{ background: 'linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.5))' }} />

                    {/* Hero Content */}
                    <div className="relative z-10 mx-auto max-w-5xl px-4 text-center text-white sm:px-6 lg:px-8">
                        <h2 className="mb-6 text-4xl leading-tight font-bold drop-shadow-lg sm:text-5xl md:text-6xl lg:text-7xl">
                            {t('discover_amazing_products')}
                        </h2>
                        <p className="mx-auto mb-8 max-w-3xl text-lg leading-relaxed opacity-90 drop-shadow-md sm:text-xl md:text-2xl">
                            {t('find_perfect_tech')}
                        </p>
                        <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                            <Link href={route('products')}>
                                <Button
                                    size="lg"
                                    className="border-grey-600 w-auto border border-1 bg-white px-4 py-3 text-base font-semibold text-black shadow hover:bg-gray-200 sm:px-6 sm:text-lg"
                                >
                                    {t('shop_now')}
                                    <ArrowRight className="ml-2 h-4 w-4 sm:h-5 sm:w-5" />
                                </Button>
                            </Link>
                            {/* <Button variant="outline" size="lg" className="px-4 sm:px-6 py-3 text-base sm:text-lg border-2 border-white text-white hover:bg-white hover:text-black font-semibold w-auto">
                                {t('view_deals')}
                            </Button> */}
                        </div>
                    </div>

                    {/* Scroll Indicator */}
                    <div className="absolute bottom-6 left-1/2 z-10 -translate-x-1/2 transform sm:bottom-8">
                        <div className="animate-bounce">
                            <div className="flex h-8 w-5 justify-center rounded-full border-2 border-white sm:h-10 sm:w-6">
                                <div className="mt-1.5 h-2 w-1 animate-pulse rounded-full bg-white sm:mt-2 sm:h-3" />
                            </div>
                        </div>
                    </div>
                </section>

                {/* Featured Products */}
                <section className="relative z-10 bg-white py-16 lg:py-24">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-12 text-center">
                            <h2 className="mb-4 text-3xl font-bold text-black">{t('featured_products')}</h2>
                            <p className="mx-auto max-w-2xl text-gray-600">{t('featured_products_description')}</p>
                        </div>

                        <div className="grid grid-cols-2 gap-6 md:grid-cols-2 lg:grid-cols-3">
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
                                        <div
                                            className={`group cursor-pointer overflow-hidden rounded-xl border bg-white shadow-sm transition-shadow ${
                                                productInteractions.isMobile
                                                    ? isActive
                                                        ? 'touch-feedback shadow-lg'
                                                        : 'touch-feedback shadow-sm hover:shadow-sm'
                                                    : 'hover:shadow-lg'
                                            }`}
                                        >
                                            <div className="relative overflow-hidden">
                                                <img
                                                    src={product.image}
                                                    alt={product.name}
                                                    className={`h-48 w-full object-cover transition-transform duration-200 ${
                                                        productInteractions.isMobile
                                                            ? isActive
                                                                ? 'scale-105'
                                                                : 'scale-100'
                                                            : 'group-hover:scale-105'
                                                    }`}
                                                />
                                                {product.badge && <Badge className="absolute top-2 left-2">{product.badge}</Badge>}
                                                <Button
                                                    size="sm"
                                                    className={`absolute right-2 bottom-2 transition-opacity ${
                                                        productInteractions.isMobile
                                                            ? isActive
                                                                ? 'opacity-100'
                                                                : 'opacity-0'
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
                                                <h3 className="mb-2 line-clamp-2 font-semibold text-black">{product.name}</h3>
                                                <div className="mb-2 flex items-center gap-1">
                                                    {renderStars(product.rating)}
                                                    <span className="ml-1 text-sm text-gray-500">({product.reviews})</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-lg font-bold text-black">${product.price}</span>
                                                    {product.originalPrice && (
                                                        <span className="text-sm text-gray-500 line-through">${product.originalPrice}</span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                        <Link href="/products">
                            <div className="mt-12 text-center">
                                <Button variant="outline" size="lg">
                                    {t('view_all_products')}
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Button>
                            </div>
                        </Link>
                    </div>
                </section>

                {/* Categories Section */}
                <section className="relative z-10 bg-gray-50 py-16 lg:py-24">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="mb-12 text-center">
                            <h2 className="mb-4 text-3xl font-bold text-black">{t('shop_by_category')}</h2>
                            <p className="mx-auto max-w-2xl text-gray-600">{t('category_description')}</p>
                        </div>

                        <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
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
                                        <div
                                            className={`h-full overflow-hidden rounded-xl border bg-white text-center shadow-sm transition-all duration-200 ${
                                                categoryInteractions.isMobile
                                                    ? isActive
                                                        ? 'touch-feedback scale-95 shadow-md'
                                                        : 'touch-feedback shadow-sm'
                                                    : 'hover:-translate-y-1 hover:shadow-md'
                                            }`}
                                        >
                                            <div className="p-6">
                                                <div
                                                    className={`mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl transition-all duration-200 ${
                                                        categoryInteractions.isMobile
                                                            ? isActive
                                                                ? 'scale-110 bg-black text-white'
                                                                : 'bg-gray-50 text-gray-700'
                                                            : 'bg-gray-50 text-gray-700 group-hover:scale-110 group-hover:bg-black group-hover:text-white'
                                                    }`}
                                                >
                                                    <IconComponent className="h-8 w-8" />
                                                </div>
                                                <h3 className="mb-2 text-sm font-semibold text-gray-900">{t(`categories.${category.name}`)}</h3>
                                                <p className="text-xs font-medium text-gray-500">{t('products_count', { count: category.count })}</p>
                                            </div>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="bg-black py-12 text-white">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="grid grid-cols-1 gap-8 md:grid-cols-4">
                            <div>
                                <h3 className="mb-4 text-xl font-bold">RSP</h3>
                                <p className="mb-4 text-gray-400">{t('store_description')}</p>
                            </div>

                            <div>
                                <h4 className="mb-4 font-semibold">{t('quick_links')}</h4>
                                <ul className="space-y-2 text-gray-400">
                                    <li>
                                        <Link href="/about" className="footer-link md:footer-link-none hover:text-white">
                                            {t('about_us')}
                                        </Link>
                                    </li>
                                    <li>
                                        <Link href="/contact" className="footer-link md:footer-link-none hover:text-white">
                                            {t('contact.title')}
                                        </Link>
                                    </li>
                                    <li>
                                        <Link href="/faq" className="footer-link md:footer-link-none hover:text-white">
                                            {t('faq')}
                                        </Link>
                                    </li>
                                </ul>
                            </div>

                            <div>
                                <h4 className="mb-4 font-semibold">{t('legal')}</h4>
                                <ul className="space-y-2 text-gray-400">
                                    <li>
                                        <Link href="/privacy" className="footer-link md:footer-link-none hover:text-white">
                                            {t('privacy_policy')}
                                        </Link>
                                    </li>
                                    <li>
                                        <Link href="/terms" className="footer-link md:footer-link-none hover:text-white">
                                            {t('terms_of_service')}
                                        </Link>
                                    </li>
                                    <li>
                                        <Link href="/shipping-returns" className="footer-link md:footer-link-none hover:text-white">
                                            {t('shipping_returns.title')}
                                        </Link>
                                    </li>
                                </ul>
                            </div>

                            <div>
                                <h4 className="mb-4 font-semibold">{t('follow_us')}</h4>
                                <p className="mb-4 text-gray-400">{t('stay_connected')}</p>
                                <div className="flex space-x-4">
                                    {/* <a href="#" target="_blank" rel="noopener noreferrer" aria-label="Follow us on Facebook">
                                        <Button variant="ghost" size="icon" className="text-gray-400 hover:text-black">
                                            <FacebookIcon className="w-5 h-5" />
                                            <span className="sr-only">Facebook</span>
                                        </Button>
                                    </a> */}
                                    <a
                                        href="https://instagram.com/rsp.industries"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label="Follow us on Instagram"
                                    >
                                        <Button variant="ghost" size="icon" className="text-gray-400 hover:text-black">
                                            <InstagramIcon className="h-5 w-5" />
                                            <span className="sr-only">Instagram</span>
                                        </Button>
                                    </a>
                                    <a
                                        href="https://www.tiktok.com/@rspindustrie"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label="Follow us on TikTok"
                                    >
                                        <Button variant="ghost" size="icon" className="text-gray-400 hover:text-black">
                                            <TikTokIcon className="h-5 w-5" />
                                            <span className="sr-only">TikTok</span>
                                        </Button>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div className="mt-8 border-t border-gray-800 pt-8 text-center text-gray-400">
                            <p>&copy; 2025 Store. {t('rights_reserved')}</p>
                        </div>
                    </div>
                </footer>
            </LoadingOverlay>
        </>
    );
}

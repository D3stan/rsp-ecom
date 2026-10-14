import FooterLegalLine from '@/components/footer-legal-line';
import FooterSocialLinks from '@/components/footer-social-links';
import Header from '@/components/header';
import LoadingOverlay from '@/components/LoadingOverlay';
// import { VideoPlayer } from '@/components/VideoPlayer';
import RingMaskedImage from '@/components/RingMaskedImage/RingMaskedImage';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useMobileInteractions } from '@/hooks/use-mobile-interactions';
import { useMoney } from '@/hooks/use-money';
import { useSetting } from '@/hooks/use-setting';
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
    defaultVariantId?: number;
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
    // const [videoLoaded, setVideoLoaded] = useState(false);
    // const [imageLoaded, setImageLoaded] = useState(false);
    const [addingToCartId, setAddingToCartId] = useState<number | null>(null);
    const [backgroundBlur, setBackgroundBlur] = useState(3); // Tunable blur amount (0-10)
    const { t, isLoading } = useTranslation();
    const formatCurrency = useMoney();
    const storeName = useSetting('store.name', 'Store');
    const { featuredProducts, categories } = usePage<HomePageProps>().props;

    // Mobile interactions for products and categories
    const productInteractions = useMobileInteractions<number>();
    const categoryInteractions = useMobileInteractions<number>();

    // Preload the background image first - Now using RingMaskedImage
    // Commented out since we're using RingMaskedImage component instead
    /*
    useEffect(() => {
        const img = new Image();
        img.onload = () => {
            setImageLoaded(true);
        };
        img.onerror = () => {
            // Even if image fails to load, allow video to try loading
            setImageLoaded(true);
        };
        // Use highest priority for the background image
        img.loading = 'eager';
        img.src = '/images/homepage.png';
    }, []);
    */

    const handleAddToCart = async (product: Product) => {
        if (addingToCartId === product.id) return;

        if (!product.defaultVariantId) {
            console.error('Product has no default variant');
            cartService.triggerCartAnimation('error');
            return;
        }

        setAddingToCartId(product.id);

        try {
            const cartData: AddToCartData = {
                product_id: product.id,
                product_variant_id: product.defaultVariantId,
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

                {/* Hero Section with Ring Masked Image */}
                <section className="hero-section relative -mt-16 flex overflow-hidden pt-16" style={{ height: '100vh' }}>
                    {/* Background Image Layer */}
                    <div className="absolute inset-0 z-0" style={{ transform: 'scale(1.05)' }}>
                        <img
                            src="/images/angels.png"
                            alt="RSP Background"
                            className="w-full h-full object-cover object-center"
                            style={{
                                minWidth: '100%',
                                minHeight: '100%',
                                filter: `blur(${backgroundBlur}px)`,
                                transform: 'scale(1.02)', // Slight scale to prevent edge artifacts
                            }}
                        />
                    </div>

                    {/* Ring Masked Image Layer */}
                    <div className="absolute inset-0 z-5 flex items-center justify-center">
                        <RingMaskedImage
                            src="/images/angel-eye.png"
                            alt="Angel Eye - Discover Amazing Products"
                            engine="framer"
                            mode="continuous"
                            innerRadiusPct={15}
                            outerRadiusPct={21.6}
                            sliceDegrees={40}
                            baseOpacity={0.7}
                            ringOpacity={1.0}
                            secondsPerTurn={5}
                            clockwise={true}
                            introEnabled={false}
                            introTTLSeconds={86400}
                            sessionKey="heroRingIntroSeen"
                            respectReducedMotion={false}
                            disableResponsiveConstraints={true}
                            preserveAspectRatio="xMidYMid meet"
                            className="w-full h-full mx-auto max-w-[1400px]"
                        />
                    </div>

                    {/* Black Angel Eye Image Layer */}
                    <div className="absolute inset-0 z-4 flex items-center justify-center">
                        <img
                            src="/images/angel-eye.png"
                            alt="Angel Eye Black"
                            className="w-full h-full mx-auto max-w-[1400px]"
                            style={{
                                filter: 'brightness(0.2) saturate(100%)',
                                objectFit: 'contain',
                                objectPosition: 'center',
                                height: '100vh'
                            }}
                        />
                    </div>

                    {/* Overlay for Text Readability */}
                    <div className="absolute inset-0 z-4" style={{ background: 'linear-gradient(rgba(0,0,0,0.1), rgba(0,0,0,0.2))' }} />

                    {/* Hero Content - Positioned in the lower part */}
                    <div className="absolute left-0 right-0 z-20 mx-auto max-w-5xl px-4 text-center text-white sm:px-6 lg:px-8" style={{ bottom: '20%' }}>
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
                    <div className="absolute bottom-6 left-1/2 z-20 -translate-x-1/2 transform sm:bottom-8">
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
                                                        handleAddToCart(product);
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
                                                    <span className="text-lg font-bold text-black">{formatCurrency(Number(product.price))}</span>
                                                    {product.originalPrice && (
                                                        <span className="text-sm text-gray-500 line-through">{formatCurrency(Number(product.originalPrice))}</span>
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
                                        href={`/products?category=${category.slug}`}
                                        className="group"
                                        {...categoryInteractions.getInteractionProps(category.id, () => {
                                            window.location.href = `/products?category=${category.slug}`;
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
                                <h3 className="mb-4 text-xl font-bold">{storeName}</h3>
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

                            <FooterSocialLinks hoverClassName="hover:text-black" />
                        </div>

                        <div className="mt-8 border-t border-gray-800 pt-8 text-center text-gray-400">
                            <p>
                                &copy; {new Date().getFullYear()} {storeName}. {t('rights_reserved')}
                            </p>
                            <FooterLegalLine />
                        </div>
                    </div>
                </footer>
            </LoadingOverlay>
        </>
    );
}

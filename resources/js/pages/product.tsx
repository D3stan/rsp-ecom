import Header from '@/components/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { VariantSelector } from '@/components/VariantSelector';
import { WishlistButton } from '@/components/wishlist-button';
import { useIsMobile } from '@/hooks/use-mobile';
import { useTranslation } from '@/hooks/useTranslation';
import { formatCurrency } from '@/lib/utils';
import { cartService, type AddToCartData } from '@/services/cartService';
import { useToast } from '@/contexts/ToastContext';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronLeft, ChevronRight, Heart, Minus, Plus, Share2, ShoppingCart, Star } from 'lucide-react';
import { useState, useEffect } from 'react';

interface ProductVariant {
    id: number;
    name: string;
    price: number;
    stock_quantity: number;
    images: string[];
    description: string;
    is_default: boolean;
}

interface Product {
    id: number;
    name: string;
    slug: string;
    category: {
        id: number;
        name: string;
        slug: string;
    } | null;
    variants: ProductVariant[];
    shipping: {
        size: string;
    };
}

interface Review {
    id: number;
    user: string;
    rating: number;
    comment: string;
    date: string;
}

interface RelatedProduct {
    id: number;
    name: string;
    slug: string;
    price: number;
    image: string;
}

interface ProductPageProps extends SharedData {
    product: Product;
    reviews: Review[];
    relatedProducts: RelatedProduct[];
    breadcrumb: string[];
}

const renderStars = (rating: number, size: 'sm' | 'md' | 'lg' = 'md') => {
    const sizeClasses = {
        sm: 'w-4 h-4',
        md: 'w-5 h-5',
        lg: 'w-6 h-6',
    };

    return Array.from({ length: 5 }, (_, i) => (
        <Star key={i} className={`${sizeClasses[size]} ${i < Math.floor(rating) ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} />
    ));
};

export default function Product() {
    const { product, reviews, relatedProducts, breadcrumb, auth } = usePage<ProductPageProps>().props;
    const { addToast } = useToast();
    const isMobile = useIsMobile();
    const { t } = useTranslation();

    // Find default variant
    const defaultVariant = product.variants.find((v) => v.is_default) ?? product.variants[0];

    // State
    const [selectedVariantId, setSelectedVariantId] = useState<number>(defaultVariant.id);
    const [currentImageIndex, setCurrentImageIndex] = useState(0);
    const [quantity, setQuantity] = useState(1);
    const [isAddingToCart, setIsAddingToCart] = useState(false);
    const [isDescriptionOpen, setIsDescriptionOpen] = useState(false);
    const [isSpecsOpen, setIsSpecsOpen] = useState(false);
    const [isReviewsOpen, setIsReviewsOpen] = useState(false);

    // Get selected variant
    const selectedVariant = product.variants.find((v) => v.id === selectedVariantId)!;

    // Reset image index when variant changes
    useEffect(() => {
        setCurrentImageIndex(0);
    }, [selectedVariantId]);

    // Display images from selected variant
    const displayImages = selectedVariant.images.length > 0 ? selectedVariant.images : ['/images/product.png'];

    // Price is directly from variant
    const currentPrice = selectedVariant.price;

    const handleImageChange = (index: number) => {
        setCurrentImageIndex(index);
    };

    const handlePrevImage = () => {
        setCurrentImageIndex((prev) => (prev === 0 ? displayImages.length - 1 : prev - 1));
    };

    const handleNextImage = () => {
        setCurrentImageIndex((prev) => (prev === displayImages.length - 1 ? 0 : prev + 1));
    };

    const handleQuantityChange = (delta: number) => {
        setQuantity((prev) => {
            const newQuantity = prev + delta;
            return Math.max(1, Math.min(newQuantity, selectedVariant.stock_quantity));
        });
    };

    const handleAddToCart = async () => {
        if (isAddingToCart) return;

        setIsAddingToCart(true);

        try {
            const cartData: AddToCartData = {
                product_id: product.id,
                product_variant_id: selectedVariantId,
                quantity: quantity,
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
            setIsAddingToCart(false);
        }
    };

    const handleShare = async () => {
        const shareUrl = window.location.href;

        try {
            if (navigator.share) {
                await navigator.share({
                    title: product.name,
                    text: selectedVariant.description || product.name,
                    url: shareUrl,
                });
                return;
            }
        } catch (error) {
            if (error instanceof Error && error.name === 'AbortError') {
                return;
            }
        }

        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(shareUrl);
                addToast({
                    type: 'success',
                    title: 'Link copied',
                    description: 'Product link copied to clipboard.',
                });
                return;
            }
        } catch {
            // Fallback below
        }

        const textArea = document.createElement('textarea');
        textArea.value = shareUrl;
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);

        addToast({
            type: 'success',
            title: 'Link copied',
            description: 'Product link copied to clipboard.',
        });
    };

    return (
        <>
            <Head title={product.name} />

            <div className="min-h-screen bg-white">
                {/* Header */}
                <Header />

                {/* Breadcrumb */}
                <div className="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                    <nav className="flex" aria-label="Breadcrumb">
                        <ol className="flex items-center space-x-2 text-sm">
                            {breadcrumb.map((item, index) => (
                                <li key={index} className="flex items-center">
                                    {index > 0 && <ChevronRight className="mx-2 h-4 w-4 text-gray-400" />}
                                    {index < breadcrumb.length - 1 ? (
                                        <Link
                                            href={index === 0 ? '/' : index === 1 ? '/products' : '#'}
                                            className="text-gray-600 hover:text-gray-900"
                                        >
                                            {index === 0 ? t('product.breadcrumb.home') : index === 1 ? t('product.breadcrumb.products') : item}
                                        </Link>
                                    ) : (
                                        <span className="font-medium text-gray-900">{item}</span>
                                    )}
                                </li>
                            ))}
                        </ol>
                    </nav>
                </div>

                {/* Main Content */}
                <div className={`mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 ${isMobile ? 'pb-24' : 'pb-16'}`}>
                    <div className="grid grid-cols-1 gap-8 lg:grid-cols-2 lg:gap-16">
                        {/* Image Carousel */}
                        <div className="space-y-4">
                            <div className="relative aspect-square overflow-hidden rounded-2xl bg-gray-100">
                                <img src={displayImages[currentImageIndex]} alt={product.name} className="h-full w-full object-cover" />

                                {/* Navigation buttons */}
                                {displayImages.length > 1 && (
                                    <>
                                        <button
                                            onClick={handlePrevImage}
                                            className="absolute top-1/2 left-4 -translate-y-1/2 transform rounded-full bg-white/80 p-2 shadow-lg transition-all hover:bg-white"
                                        >
                                            <ChevronLeft className="h-5 w-5" />
                                        </button>
                                        <button
                                            onClick={handleNextImage}
                                            className="absolute top-1/2 right-4 -translate-y-1/2 transform rounded-full bg-white/80 p-2 shadow-lg transition-all hover:bg-white"
                                        >
                                            <ChevronRight className="h-5 w-5" />
                                        </button>
                                    </>
                                )}

                                {/* Badge - removed as not in variant model */}
                            </div>

                            {/* Thumbnail images */}
                            {displayImages.length > 1 && (
                                <div className="flex space-x-2 overflow-x-auto pb-2">
                                    {displayImages.map((image, index) => (
                                        <button
                                            key={index}
                                            onClick={() => handleImageChange(index)}
                                            className={`h-20 w-20 flex-shrink-0 overflow-hidden rounded-lg border-2 transition-all ${
                                                index === currentImageIndex ? 'border-black' : 'border-gray-200 hover:border-gray-400'
                                            }`}
                                        >
                                            <img src={image} alt={`${product.name} ${index + 1}`} className="h-full w-full object-cover" />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Product Info */}
                        <div className="space-y-6">
                            {/* Product Header */}
                            <div>
                                {product.category && <p className="mb-2 text-sm text-gray-600">{product.category.name}</p>}
                                <h1 className="mb-4 text-3xl font-bold text-gray-900 lg:text-4xl">{product.name}</h1>

                                {/* Rating - placeholder for now */}
                                <div className="mb-4 flex items-center space-x-2">
                                    <div className="flex">{renderStars(0)}</div>
                                    <span className="text-sm text-gray-600">(0 reviews)</span>
                                </div>

                                {/* Price */}
                                <div className="mb-6 flex items-center space-x-3">
                                    <span className="text-3xl font-bold text-gray-900">{formatCurrency(currentPrice)}</span>
                                </div>
                            </div>

                            {/* Variant Selector */}
                            {product.variants.length > 1 && (
                                <VariantSelector
                                    variants={product.variants}
                                    selectedVariantId={selectedVariantId}
                                    onVariantChange={setSelectedVariantId}
                                />
                            )}

                            {/* Quantity Selector */}
                            <div>
                                <Label className="mb-3 block text-sm font-medium text-gray-900">{t('product.quantity')}</Label>
                                <div className="flex items-center space-x-4">
                                    <div className="flex items-center rounded-lg border border-gray-300">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleQuantityChange(-1)}
                                            disabled={quantity <= 1}
                                            className="h-10 w-10 p-0 hover:bg-gray-100"
                                        >
                                            <Minus className="h-4 w-4 text-black" />
                                        </Button>
                                        <span className="min-w-[3rem] px-4 py-2 text-center text-sm font-medium text-black">{quantity}</span>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleQuantityChange(1)}
                                            disabled={quantity >= selectedVariant.stock_quantity}
                                            className="h-10 w-10 p-0 hover:bg-gray-100"
                                        >
                                            <Plus className="h-4 w-4 text-black" />
                                        </Button>
                                    </div>
                                    <span className="text-sm text-gray-600">
                                        {selectedVariant.stock_quantity} {t('product.in_stock')}
                                    </span>
                                </div>
                            </div>

                            {/* Action Buttons */}
                            <div className="space-y-3">
                                <Button
                                    onClick={handleAddToCart}
                                    disabled={selectedVariant.stock_quantity === 0 || isAddingToCart}
                                    className="h-12 w-full text-lg font-semibold"
                                    size="lg"
                                >
                                    <ShoppingCart className="mr-2 h-5 w-5" />
                                    {isAddingToCart ? t('product.adding') : selectedVariant.stock_quantity > 0 ? t('product.add_to_cart') : t('product.out_of_stock')}
                                </Button>

                                <div className="grid grid-cols-2 gap-3">
                                    {auth.user ? (
                                        <WishlistButton
                                            productId={product.id}
                                            initialInWishlist={false}
                                            showText={true}
                                            variant="outline"
                                            className="h-12"
                                        />
                                    ) : (
                                        <Button variant="outline" className="h-12" asChild>
                                            <Link href={route('login')}>
                                                <Heart className="mr-2 h-5 w-5" />
                                                {t('product.wishlist')}
                                            </Link>
                                        </Button>
                                    )}

                                    <Button variant="outline" className="h-12" onClick={handleShare}>
                                        <Share2 className="mr-2 h-5 w-5" />
                                        {t('product.share')}
                                    </Button>
                                </div>
                            </div>

                            {/* Short Description */}
                            {selectedVariant.description && (
                                <div className="border-t border-gray-200 pt-6">
                                    <p className="leading-relaxed text-gray-700">{selectedVariant.description}</p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Reviews */}
                    {reviews.length > 0 && (
                        <div className="mt-16 space-y-4">
                            <Collapsible open={isReviewsOpen} onOpenChange={setIsReviewsOpen} className="rounded-lg border border-gray-300">
                                <CollapsibleTrigger className="flex w-full items-center justify-between rounded-lg bg-gray-50 p-4 transition-colors hover:bg-gray-100">
                                    <span className="text-lg font-semibold text-gray-900">
                                        {t('product.reviews_count', { count: reviews.length })}
                                    </span>
                                    <ChevronDown
                                        className={`h-5 w-5 text-black transition-transform duration-200 ${isReviewsOpen ? 'rotate-180' : ''}`}
                                    />
                                </CollapsibleTrigger>
                                <CollapsibleContent className="mt-2 rounded-lg p-4">
                                    <div className="space-y-6">
                                        {reviews.map((review) => (
                                            <div key={review.id} className="border-b border-gray-200 pb-6 last:border-b-0">
                                                <div className="mb-2 flex items-center justify-between">
                                                    <span className="font-medium text-gray-900">{review.user}</span>
                                                    <span className="text-sm text-gray-500">{new Date(review.date).toLocaleDateString()}</span>
                                                </div>
                                                <div className="mb-2 flex items-center">{renderStars(review.rating, 'sm')}</div>
                                                <p className="text-gray-700">{review.comment}</p>
                                            </div>
                                        ))}
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>
                        </div>
                    )}

                    {/* Related Products */}
                    {relatedProducts.length > 0 && (
                        <div className={`mt-20 border-t border-gray-200 pt-8 ${isMobile ? 'mb-8' : ''}`}>
                            <h2 className="mb-8 text-2xl font-bold text-gray-900">{t('product.you_may_also_like')}</h2>
                            <div className="grid grid-cols-2 gap-6 md:grid-cols-4">
                                {relatedProducts.map((relatedProduct) => (
                                    <Link key={relatedProduct.id} href={`/products/${relatedProduct.slug}`} className="group">
                                        <div className="mb-3 aspect-square overflow-hidden rounded-lg bg-gray-100">
                                            <img
                                                src={relatedProduct.image}
                                                alt={relatedProduct.name}
                                                className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                            />
                                        </div>
                                        <h3 className="mb-1 line-clamp-2 font-medium text-gray-900">{relatedProduct.name}</h3>
                                        <p className="text-lg font-bold text-gray-900">{formatCurrency(Number(relatedProduct.price))}</p>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* Sticky Footer on Mobile */}
                {isMobile && (
                    <div className="fixed right-0 bottom-0 left-0 z-50 border-t border-gray-200 bg-white p-4 shadow-lg">
                        <div className="flex items-center justify-between space-x-4">
                            <div>
                                <p className="text-lg font-bold text-gray-900">{formatCurrency(currentPrice)}</p>
                            </div>
                            <Button
                                onClick={handleAddToCart}
                                disabled={selectedVariant.stock_quantity === 0 || isAddingToCart}
                                className="h-12 flex-1 font-semibold"
                            >
                                <ShoppingCart className="mr-2 h-5 w-5" />
                                {isAddingToCart ? t('product.adding') : selectedVariant.stock_quantity > 0 ? t('product.add_to_cart') : t('product.out_of_stock')}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

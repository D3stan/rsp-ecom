import Header from '@/components/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useIsMobile } from '@/hooks/use-mobile';
import { useTranslation } from '@/hooks/useTranslation';
import { cartService, type AddToCartData } from '@/services/cartService';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronLeft, ChevronRight, Heart, Minus, Plus, Share2, ShoppingCart, Star } from 'lucide-react';
import { useState } from 'react';

interface Product {
    id: number;
    name: string;
    slug: string;
    description: string;
    shortDescription?: string;
    price: number;
    originalPrice?: number;
    rating: number;
    reviews: number;
    images: string[];
    badge?: string;
    inStock: boolean;
    stockQuantity: number;
    specifications?: Record<string, string>;
    category?: {
        id: number;
        name: string;
        slug: string;
    };
    sizes: Array<{
        id: number;
        name: string;
        price_adjustment: number;
    }>;
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
    const { product, reviews, relatedProducts, breadcrumb } = usePage<ProductPageProps>().props;
    const isMobile = useIsMobile();
    const { t } = useTranslation();

    // State for image carousel
    const [currentImageIndex, setCurrentImageIndex] = useState(0);
    const [quantity, setQuantity] = useState(1);
    const [selectedSize, setSelectedSize] = useState<string>('');

    // Handle empty images array
    const displayImages = product.images.length > 0 ? product.images : ['/images/product.png'];

    // State for collapsibles
    const [isDescriptionOpen, setIsDescriptionOpen] = useState(false);
    const [isSpecsOpen, setIsSpecsOpen] = useState(false);
    const [isReviewsOpen, setIsReviewsOpen] = useState(false);
    const [isAddingToCart, setIsAddingToCart] = useState(false);

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
            return Math.max(1, Math.min(newQuantity, product.stockQuantity));
        });
    };

    const handleAddToCart = async () => {
        if (isAddingToCart) return;

        setIsAddingToCart(true);

        try {
            const cartData: AddToCartData = {
                product_id: product.id,
                quantity: quantity,
            };

            if (selectedSize) {
                cartData.size_id = parseInt(selectedSize);
            }

            const response = await cartService.addToCart(cartData);

            if (response.success) {
                cartService.triggerCartUpdate(); // Trigger cart count refresh
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

    const handleAddToWishlist = () => {
        // TODO: Implement add to wishlist functionality
        console.log('Adding to wishlist:', product.id);
    };

    const currentPrice = selectedSize
        ? Number(product.price) + (product.sizes.find((s) => s.id.toString() === selectedSize)?.price_adjustment || 0)
        : Number(product.price);

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

                                {/* Badge */}
                                {product.badge && <Badge className="absolute top-4 left-4">{product.badge}</Badge>}
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

                                {/* Rating */}
                                <div className="mb-4 flex items-center space-x-2">
                                    <div className="flex">{renderStars(product.rating)}</div>
                                    <span className="text-sm text-gray-600">({product.reviews} reviews)</span>
                                </div>

                                {/* Price */}
                                <div className="mb-6 flex items-center space-x-3">
                                    <span className="text-3xl font-bold text-gray-900">${currentPrice.toFixed(2)}</span>
                                    {product.originalPrice && (
                                        <span className="text-xl text-gray-500 line-through">${Number(product.originalPrice).toFixed(2)}</span>
                                    )}
                                </div>
                            </div>

                            {/* Size Selector */}
                            {product.sizes.length > 0 && (
                                <div>
                                    <Label className="mb-3 block text-sm font-medium text-gray-900">{t('product.size')}</Label>
                                    <Select value={selectedSize} onValueChange={setSelectedSize}>
                                        <SelectTrigger className="w-full text-black">
                                            <SelectValue placeholder={t('product.select_size')} />
                                        </SelectTrigger>
                                        <SelectContent className="bg-white text-gray-900">
                                            {product.sizes.map((size) => (
                                                <SelectItem key={size.id} value={size.id.toString()}>
                                                    {size.name}
                                                    {size.price_adjustment !== 0 && (
                                                        <span className="ml-2 text-gray-500">
                                                            ({size.price_adjustment > 0 ? '+' : ''}${size.price_adjustment})
                                                        </span>
                                                    )}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
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
                                            disabled={quantity >= product.stockQuantity}
                                            className="h-10 w-10 p-0 hover:bg-gray-100"
                                        >
                                            <Plus className="h-4 w-4 text-black" />
                                        </Button>
                                    </div>
                                    <span className="text-sm text-gray-600">
                                        {product.stockQuantity} {t('product.in_stock')}
                                    </span>
                                </div>
                            </div>

                            {/* Action Buttons */}
                            <div className="space-y-3">
                                <Button
                                    onClick={handleAddToCart}
                                    disabled={!product.inStock || (product.sizes.length > 0 && !selectedSize) || isAddingToCart}
                                    className="h-12 w-full text-lg font-semibold"
                                    size="lg"
                                >
                                    <ShoppingCart className="mr-2 h-5 w-5" />
                                    {isAddingToCart ? t('product.adding') : product.inStock ? t('product.add_to_cart') : t('product.out_of_stock')}
                                </Button>

                                <div className="grid grid-cols-2 gap-3">
                                    <Button variant="outline" onClick={handleAddToWishlist} className="h-12">
                                        <Heart className="mr-2 h-5 w-5" />
                                        {t('product.wishlist')}
                                    </Button>

                                    <Button variant="outline" className="h-12">
                                        <Share2 className="mr-2 h-5 w-5" />
                                        {t('product.share')}
                                    </Button>
                                </div>
                            </div>

                            {/* Short Description */}
                            {product.shortDescription && (
                                <div className="border-t border-gray-200 pt-6">
                                    <p className="leading-relaxed text-gray-700">{product.shortDescription}</p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Collapsible Sections */}
                    <div className="mt-16 space-y-4">
                        {/* Product Description */}
                        {product.description && (
                            <Collapsible open={isDescriptionOpen} onOpenChange={setIsDescriptionOpen} className="rounded-lg border border-gray-300">
                                <CollapsibleTrigger className="flex w-full items-center justify-between rounded-lg bg-gray-50 p-4 transition-colors hover:bg-gray-200">
                                    <span className="text-lg font-semibold text-gray-900">{t('product.product_description')}</span>
                                    <ChevronDown
                                        className={`h-5 w-5 text-black transition-transform duration-200 ${isDescriptionOpen ? 'rotate-180' : ''}`}
                                    />
                                </CollapsibleTrigger>
                                <CollapsibleContent className="mt-2 rounded-lg p-4 text-black">
                                    <div className="prose prose-gray max-w-none" dangerouslySetInnerHTML={{ __html: product.description }} />
                                </CollapsibleContent>
                            </Collapsible>
                        )}

                        {/* Specifications */}
                        {product.specifications && Object.keys(product.specifications).length > 0 && (
                            <Collapsible open={isSpecsOpen} onOpenChange={setIsSpecsOpen} className="rounded-lg border border-gray-300">
                                <CollapsibleTrigger className="flex w-full items-center justify-between rounded-lg bg-gray-50 p-4 transition-colors hover:bg-gray-100">
                                    <span className="text-lg font-semibold text-gray-900">{t('product.specifications')}</span>
                                    <ChevronDown
                                        className={`h-5 w-5 text-black transition-transform duration-200 ${isSpecsOpen ? 'rotate-180' : ''}`}
                                    />
                                </CollapsibleTrigger>
                                <CollapsibleContent className="mt-2 rounded-lg border border-gray-200 p-4">
                                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        {Object.entries(product.specifications).map(([key, value]) => (
                                            <div key={key}>
                                                <dt className="text-sm font-medium text-gray-600">{key}</dt>
                                                <dd className="mt-1 text-sm text-gray-900">{value}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                </CollapsibleContent>
                            </Collapsible>
                        )}

                        {/* Reviews */}
                        {reviews.length > 0 && (
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
                        )}
                    </div>

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
                                        <p className="text-lg font-bold text-gray-900">${Number(relatedProduct.price).toFixed(2)}</p>
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
                                <p className="text-lg font-bold text-gray-900">${currentPrice.toFixed(2)}</p>
                                {product.originalPrice && (
                                    <p className="text-sm text-gray-500 line-through">${Number(product.originalPrice).toFixed(2)}</p>
                                )}
                            </div>
                            <Button
                                onClick={handleAddToCart}
                                disabled={!product.inStock || (product.sizes.length > 0 && !selectedSize) || isAddingToCart}
                                className="h-12 flex-1 font-semibold"
                            >
                                <ShoppingCart className="mr-2 h-5 w-5" />
                                {isAddingToCart ? t('product.adding') : product.inStock ? t('product.add_to_cart') : t('product.out_of_stock')}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

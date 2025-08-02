import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import Header from '@/components/header';
import { useState } from 'react';
import { useIsMobile } from '@/hooks/use-mobile';
import { cartService, type AddToCartData } from '@/services/cartService';
import { useToast } from '@/contexts/ToastContext';
import { 
    Star, 
    ChevronLeft,
    ChevronRight,
    Plus,
    Minus,
    ShoppingCart,
    Heart,
    Share2,
    ChevronDown,
    ChevronUp
} from 'lucide-react';

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
        lg: 'w-6 h-6'
    };
    
    return Array.from({ length: 5 }, (_, i) => (
        <Star 
            key={i} 
            className={`${sizeClasses[size]} ${i < Math.floor(rating) ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} 
        />
    ));
};

export default function Product() {
    const { product, reviews, relatedProducts, breadcrumb } = usePage<ProductPageProps>().props;
    const isMobile = useIsMobile();
    const { addToast } = useToast();
    
    // State for image carousel
    const [currentImageIndex, setCurrentImageIndex] = useState(0);
    const [quantity, setQuantity] = useState(1);
    const [selectedSize, setSelectedSize] = useState<string>('');
    
    // State for collapsibles
    const [isDescriptionOpen, setIsDescriptionOpen] = useState(false);
    const [isSpecsOpen, setIsSpecsOpen] = useState(false);
    const [isReviewsOpen, setIsReviewsOpen] = useState(false);
    const [isAddingToCart, setIsAddingToCart] = useState(false);

    const handleImageChange = (index: number) => {
        setCurrentImageIndex(index);
    };

    const handlePrevImage = () => {
        setCurrentImageIndex((prev) => 
            prev === 0 ? product.images.length - 1 : prev - 1
        );
    };

    const handleNextImage = () => {
        setCurrentImageIndex((prev) => 
            prev === product.images.length - 1 ? 0 : prev + 1
        );
    };

    const handleQuantityChange = (delta: number) => {
        setQuantity(prev => {
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
                addToast({
                    type: 'success',
                    title: 'Added to cart!',
                    description: `${product.name} has been added to your cart.`,
                });
            } else {
                addToast({
                    type: 'error',
                    title: 'Failed to add to cart',
                    description: response.message,
                });
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            addToast({
                type: 'error',
                title: 'Error',
                description: 'An unexpected error occurred. Please try again.',
            });
        } finally {
            setIsAddingToCart(false);
        }
    };

    const handleAddToWishlist = () => {
        // TODO: Implement add to wishlist functionality
        console.log('Adding to wishlist:', product.id);
    };

    const currentPrice = selectedSize ? 
        Number(product.price) + (product.sizes.find(s => s.id.toString() === selectedSize)?.price_adjustment || 0) :
        Number(product.price);

    return (
        <>
            <Head title={product.name} />
            
            <div className="min-h-screen bg-white">
                {/* Header */}
                <Header />
                
                {/* Breadcrumb */}
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                    <nav className="flex" aria-label="Breadcrumb">
                        <ol className="flex items-center space-x-2 text-sm">
                            {breadcrumb.map((item, index) => (
                                <li key={index} className="flex items-center">
                                    {index > 0 && (
                                        <ChevronRight className="w-4 h-4 text-gray-400 mx-2" />
                                    )}
                                    {index < breadcrumb.length - 1 ? (
                                        <Link 
                                            href={index === 0 ? '/' : index === 1 ? '/products' : '#'}
                                            className="text-gray-600 hover:text-gray-900"
                                        >
                                            {item}
                                        </Link>
                                    ) : (
                                        <span className="text-gray-900 font-medium">{item}</span>
                                    )}
                                </li>
                            ))}
                        </ol>
                    </nav>
                </div>

                {/* Main Content */}
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-16">
                        {/* Image Carousel */}
                        <div className="space-y-4">
                            <div className="relative aspect-square bg-gray-100 rounded-2xl overflow-hidden">
                                <img
                                    src={product.images[currentImageIndex]}
                                    alt={product.name}
                                    className="w-full h-full object-cover"
                                />
                                
                                {/* Navigation buttons */}
                                {product.images.length > 1 && (
                                    <>
                                        <button
                                            onClick={handlePrevImage}
                                            className="absolute left-4 top-1/2 transform -translate-y-1/2 bg-white/80 hover:bg-white rounded-full p-2 shadow-lg transition-all"
                                        >
                                            <ChevronLeft className="w-5 h-5" />
                                        </button>
                                        <button
                                            onClick={handleNextImage}
                                            className="absolute right-4 top-1/2 transform -translate-y-1/2 bg-white/80 hover:bg-white rounded-full p-2 shadow-lg transition-all"
                                        >
                                            <ChevronRight className="w-5 h-5" />
                                        </button>
                                    </>
                                )}

                                {/* Badge */}
                                {product.badge && (
                                    <Badge className="absolute top-4 left-4">
                                        {product.badge}
                                    </Badge>
                                )}
                            </div>
                            
                            {/* Thumbnail images */}
                            {product.images.length > 1 && (
                                <div className="flex space-x-2 overflow-x-auto pb-2">
                                    {product.images.map((image, index) => (
                                        <button
                                            key={index}
                                            onClick={() => handleImageChange(index)}
                                            className={`flex-shrink-0 w-20 h-20 rounded-lg overflow-hidden border-2 transition-all ${
                                                index === currentImageIndex 
                                                    ? 'border-black' 
                                                    : 'border-gray-200 hover:border-gray-400'
                                            }`}
                                        >
                                            <img
                                                src={image}
                                                alt={`${product.name} ${index + 1}`}
                                                className="w-full h-full object-cover"
                                            />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Product Info */}
                        <div className="space-y-6">
                            {/* Product Header */}
                            <div>
                                {product.category && (
                                    <p className="text-sm text-gray-600 mb-2">{product.category.name}</p>
                                )}
                                <h1 className="text-3xl lg:text-4xl font-bold text-gray-900 mb-4">
                                    {product.name}
                                </h1>
                                
                                {/* Rating */}
                                <div className="flex items-center space-x-2 mb-4">
                                    <div className="flex">
                                        {renderStars(product.rating)}
                                    </div>
                                    <span className="text-sm text-gray-600">
                                        ({product.reviews} reviews)
                                    </span>
                                </div>

                                {/* Price */}
                                <div className="flex items-center space-x-3 mb-6">
                                    <span className="text-3xl font-bold text-gray-900">
                                        ${currentPrice.toFixed(2)}
                                    </span>
                                    {product.originalPrice && (
                                        <span className="text-xl text-gray-500 line-through">
                                            ${Number(product.originalPrice).toFixed(2)}
                                        </span>
                                    )}
                                </div>
                            </div>

                            {/* Size Selector */}
                            {product.sizes.length > 0 && (
                                <div>
                                    <Label className="text-sm font-medium text-gray-900 mb-3 block">
                                        Size
                                    </Label>
                                    <Select value={selectedSize} onValueChange={setSelectedSize}>
                                        <SelectTrigger className="w-full text-black">
                                            <SelectValue placeholder="Select size" />
                                        </SelectTrigger>
                                        <SelectContent>
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
                                <Label className="text-sm font-medium text-gray-900 mb-3 block">
                                    Quantity
                                </Label>
                                <div className="flex items-center space-x-4">
                                    <div className="flex items-center border border-gray-300 rounded-lg">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleQuantityChange(-1)}
                                            disabled={quantity <= 1}
                                            className="h-10 w-10 p-0 hover:bg-gray-100"
                                        >
                                            <Minus className="w-4 h-4 text-black" />
                                        </Button>
                                        <span className="px-4 py-2 text-sm font-medium min-w-[3rem] text-center text-black">
                                            {quantity}
                                        </span>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleQuantityChange(1)}
                                            disabled={quantity >= product.stockQuantity}
                                            className="h-10 w-10 p-0 hover:bg-gray-100"
                                        >
                                            <Plus className="w-4 h-4 text-black" />
                                        </Button>
                                    </div>
                                    <span className="text-sm text-gray-600">
                                        {product.stockQuantity} in stock
                                    </span>
                                </div>
                            </div>

                            {/* Action Buttons */}
                            <div className="space-y-3">
                                <Button 
                                    onClick={handleAddToCart}
                                    disabled={!product.inStock || (product.sizes.length > 0 && !selectedSize) || isAddingToCart}
                                    className="w-full h-12 text-lg font-semibold"
                                    size="lg"
                                >
                                    <ShoppingCart className="w-5 h-5 mr-2" />
                                    {isAddingToCart ? 'Adding...' : (product.inStock ? 'Add to Cart' : 'Out of Stock')}
                                </Button>
                                
                                <div className="grid grid-cols-2 gap-3">
                                    <Button 
                                        variant="outline" 
                                        onClick={handleAddToWishlist}
                                        className="h-12"
                                    >
                                        <Heart className="w-5 h-5 mr-2" />
                                        Wishlist
                                    </Button>
                                    
                                    <Button 
                                        variant="outline"
                                        className="h-12"
                                    >
                                        <Share2 className="w-5 h-5 mr-2" />
                                        Share
                                    </Button>
                                </div>
                            </div>

                            {/* Short Description */}
                            {product.shortDescription && (
                                <div className="pt-6 border-t border-gray-200">
                                    <p className="text-gray-700 leading-relaxed">
                                        {product.shortDescription}
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Collapsible Sections */}
                    <div className="mt-16 space-y-4">
                        {/* Product Description */}
                        {product.description && (
                            <Collapsible
                                open={isDescriptionOpen}
                                onOpenChange={setIsDescriptionOpen}
                            >
                                <CollapsibleTrigger className="flex items-center justify-between w-full p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                                    <span className="text-lg font-semibold text-gray-900">
                                        Product Description
                                    </span>
                                    {isDescriptionOpen ? (
                                        <ChevronUp className="w-5 h-5" />
                                    ) : (
                                        <ChevronDown className="w-5 h-5" />
                                    )}
                                </CollapsibleTrigger>
                                <CollapsibleContent className="p-4 border border-gray-200 rounded-lg mt-2 text-black">
                                    <div 
                                        className="prose prose-gray max-w-none"
                                        dangerouslySetInnerHTML={{ __html: product.description }}
                                    />
                                </CollapsibleContent>
                            </Collapsible>
                        )}

                        {/* Specifications */}
                        {product.specifications && Object.keys(product.specifications).length > 0 && (
                            <Collapsible
                                open={isSpecsOpen}
                                onOpenChange={setIsSpecsOpen}
                            >
                                <CollapsibleTrigger className="flex items-center justify-between w-full p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                                    <span className="text-lg font-semibold text-gray-900">
                                        Specifications
                                    </span>
                                    {isSpecsOpen ? (
                                        <ChevronUp className="w-5 h-5" />
                                    ) : (
                                        <ChevronDown className="w-5 h-5" />
                                    )}
                                </CollapsibleTrigger>
                                <CollapsibleContent className="p-4 border border-gray-200 rounded-lg mt-2">
                                    <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        {Object.entries(product.specifications).map(([key, value]) => (
                                            <div key={key}>
                                                <dt className="text-sm font-medium text-gray-600">{key}</dt>
                                                <dd className="text-sm text-gray-900 mt-1">{value}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                </CollapsibleContent>
                            </Collapsible>
                        )}

                        {/* Reviews */}
                        {reviews.length > 0 && (
                            <Collapsible
                                open={isReviewsOpen}
                                onOpenChange={setIsReviewsOpen}
                            >
                                <CollapsibleTrigger className="flex items-center justify-between w-full p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                                    <span className="text-lg font-semibold text-gray-900">
                                        Reviews ({reviews.length})
                                    </span>
                                    {isReviewsOpen ? (
                                        <ChevronUp className="w-5 h-5" />
                                    ) : (
                                        <ChevronDown className="w-5 h-5" />
                                    )}
                                </CollapsibleTrigger>
                                <CollapsibleContent className="p-4 border border-gray-200 rounded-lg mt-2">
                                    <div className="space-y-6">
                                        {reviews.map((review) => (
                                            <div key={review.id} className="border-b border-gray-200 pb-6 last:border-b-0">
                                                <div className="flex items-center justify-between mb-2">
                                                    <span className="font-medium text-gray-900">{review.user}</span>
                                                    <span className="text-sm text-gray-500">
                                                        {new Date(review.date).toLocaleDateString()}
                                                    </span>
                                                </div>
                                                <div className="flex items-center mb-2">
                                                    {renderStars(review.rating, 'sm')}
                                                </div>
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
                        <div className="mt-16">
                            <h2 className="text-2xl font-bold text-gray-900 mb-8">You may also like</h2>
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
                                {relatedProducts.map((relatedProduct) => (
                                    <Link
                                        key={relatedProduct.id}
                                        href={`/products/${relatedProduct.slug}`}
                                        className="group"
                                    >
                                        <div className="aspect-square bg-gray-100 rounded-lg overflow-hidden mb-3">
                                            <img
                                                src={relatedProduct.image}
                                                alt={relatedProduct.name}
                                                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                            />
                                        </div>
                                        <h3 className="font-medium text-gray-900 mb-1 line-clamp-2">
                                            {relatedProduct.name}
                                        </h3>
                                        <p className="text-lg font-bold text-gray-900">
                                            ${Number(relatedProduct.price).toFixed(2)}
                                        </p>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* Sticky Footer on Mobile */}
                {isMobile && (
                    <div className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-4 z-50">
                        <div className="flex items-center justify-between space-x-4">
                            <div>
                                <p className="text-lg font-bold text-gray-900">
                                    ${currentPrice.toFixed(2)}
                                </p>
                                {product.originalPrice && (
                                    <p className="text-sm text-gray-500 line-through">
                                        ${Number(product.originalPrice).toFixed(2)}
                                    </p>
                                )}
                            </div>
                            <Button 
                                onClick={handleAddToCart}
                                disabled={!product.inStock || (product.sizes.length > 0 && !selectedSize) || isAddingToCart}
                                className="flex-1 h-12 font-semibold"
                            >
                                <ShoppingCart className="w-5 h-5 mr-2" />
                                {isAddingToCart ? 'Adding...' : (product.inStock ? 'Add to Cart' : 'Out of Stock')}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

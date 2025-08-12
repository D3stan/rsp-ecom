import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/useTranslation';
import type { Product } from '@/pages/products';
import { cartService, type AddToCartData } from '@/services/cartService';
import { router } from '@inertiajs/react';
import { Eye, Heart, Minus, Plus, ShoppingCart, Star } from 'lucide-react';
import { useState } from 'react';

const renderStars = (rating: number) => {
    return Array.from({ length: 5 }, (_, i) => (
        <Star key={i} className={`h-4 w-4 ${i < Math.floor(rating) ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} />
    ));
};

// Quick View Modal Component
interface QuickViewModalProps {
    product: Product;
    isOpen: boolean;
    onClose: () => void;
    onAddToCart: (quantity: number) => void;
    onNavigateToProduct: () => void;
}

function QuickViewModal({ product, isOpen, onClose, onAddToCart, onNavigateToProduct }: QuickViewModalProps) {
    const [quantity, setQuantity] = useState(1);
    const { t } = useTranslation();
    const defaultImage = '/images/product.png';
    const [imageSrc, setImageSrc] = useState(() => {
        if (!product.image || product.image === '' || product.image === '/images/product.png') {
            return defaultImage;
        }
        return product.image;
    });

    const decreaseQuantity = () => {
        if (quantity > 1) setQuantity(quantity - 1);
    };

    const increaseQuantity = () => {
        if (quantity < product.stockQuantity) setQuantity(quantity + 1);
    };

    const handleImageError = () => {
        console.log('QuickView Image failed to load:', imageSrc);
        // Set to reliable placeholder if not already using it
        if (!imageSrc.includes('placeholder')) {
            setImageSrc(defaultImage);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] max-w-4xl overflow-y-auto bg-white" aria-describedby="quick-view-description">
                <DialogHeader>
                    <DialogTitle className="text-gray-900">{t('products.quick_view')}</DialogTitle>
                </DialogHeader>
                <div id="quick-view-description" className="sr-only">
                    Quick view modal for {product.name}. View product details, select quantity, and add to cart.
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    {/* Product Image */}
                    <div className="aspect-square overflow-hidden rounded-lg bg-gray-100">
                        <img src={imageSrc} alt={product.name} onError={handleImageError} className="h-full w-full object-cover" />
                    </div>

                    {/* Product Details */}
                    <div className="space-y-4">
                        {product.badge && (
                            <Badge variant={product.badge === 'Sale' ? 'destructive' : 'secondary'} className="w-fit">
                                {product.badge}
                            </Badge>
                        )}

                        <div>
                            <h2 className="text-2xl font-bold text-gray-900">{product.name}</h2>
                            {product.category && <p className="mt-1 text-sm text-gray-600">{product.category.name}</p>}
                        </div>

                        <div className="flex items-center space-x-2">
                            <div className="flex">{renderStars(product.rating)}</div>
                            <span className="text-sm text-gray-500">
                                ({product.reviews} {t('products.reviews')})
                            </span>
                        </div>

                        <div className="flex items-center space-x-3">
                            <span className="text-3xl font-bold text-gray-900">${product.price}</span>
                            {product.originalPrice && <span className="text-xl text-gray-500 line-through">${product.originalPrice}</span>}
                        </div>

                        <div className="flex items-center space-x-4">
                            <Label className="text-sm font-medium text-gray-900">{t('products.quantity')}:</Label>
                            <div className="flex items-center rounded-md border border-gray-300">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={decreaseQuantity}
                                    disabled={quantity <= 1}
                                    className="h-8 w-8 p-0 hover:bg-gray-200"
                                >
                                    <Minus className="h-4 w-4 text-black" />
                                </Button>
                                <span className="min-w-[2rem] px-3 py-1 text-center text-sm font-medium text-gray-900">{quantity}</span>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={increaseQuantity}
                                    disabled={quantity >= product.stockQuantity}
                                    className="h-8 w-8 p-0 hover:bg-gray-200"
                                >
                                    <Plus className="h-4 w-4 text-black" />
                                </Button>
                            </div>
                            <span className="text-sm text-gray-600">
                                {product.stockQuantity} {t('products.in_stock_count')}
                            </span>
                        </div>

                        <div className="space-y-3 pt-4">
                            <Button onClick={() => onAddToCart(quantity)} disabled={!product.inStock} className="w-full" size="lg">
                                <ShoppingCart className="mr-2 h-5 w-5" />
                                {product.inStock ? t('products.add_to_cart') : t('products.out_of_stock')}
                            </Button>

                            <Button variant="outline" className="w-full" onClick={onNavigateToProduct}>
                                {t('products.view_full_details')}
                            </Button>

                            <Button variant="outline" size="sm" className="w-full">
                                <Heart className="mr-2 h-4 w-4" />
                                {t('products.add_to_wishlist')}
                            </Button>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

// Product Card Component
interface ProductCardProps {
    product: Product;
    viewMode: 'grid' | 'list';
}

export default function ProductCard({ product, viewMode }: ProductCardProps) {
    const [isQuickViewOpen, setIsQuickViewOpen] = useState(false);
    const [quantity] = useState(1);
    const [isAddingToCart, setIsAddingToCart] = useState(false);
    const { t } = useTranslation();
    // Use a more reliable fallback strategy
    const defaultImage = '/images/product.png';
    const [imageSrc, setImageSrc] = useState(() => {
        if (!product.image || product.image === '' || product.image === '/images/product.png') {
            return defaultImage;
        }
        return product.image;
    });

    const handleAddToCart = async (addQuantity: number = quantity) => {
        if (isAddingToCart) return;

        setIsAddingToCart(true);

        try {
            const cartData: AddToCartData = {
                product_id: product.id,
                quantity: addQuantity,
            };

            const response = await cartService.addToCart(cartData);

            if (response.success) {
                cartService.triggerCartUpdate(); // Trigger cart count refresh
                cartService.triggerCartAnimation('success'); // Trigger success animation
            } else {
                cartService.triggerCartAnimation('error'); // Trigger error animation
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            cartService.triggerCartAnimation('error'); // Trigger error animation
        } finally {
            setIsAddingToCart(false);
        }
    };

    const handleNavigateToProduct = () => {
        router.visit(`/products/${product.slug}`);
    };

    const handleImageError = () => {
        console.log('ProductCard Image failed to load:', imageSrc, 'Product:', product.name);
        // Set to reliable placeholder if not already using it
        if (!imageSrc.includes('placeholder')) {
            setImageSrc(defaultImage);
        }
    };

    if (viewMode === 'list') {
        return (
            <Card
                className="cursor-pointer overflow-hidden border border-gray-200 bg-white transition-all duration-300 hover:shadow-xl"
                onClick={handleNavigateToProduct}
            >
                <div className="flex">
                    <div className="relative h-48 w-48 flex-shrink-0 bg-gray-50">
                        <img src={imageSrc} alt={product.name} onError={handleImageError} className="relative z-10 h-full w-full object-cover" />
                    </div>
                    <CardContent className="flex-1 bg-white p-8">
                        <div className="flex h-full justify-between">
                            <div className="flex-1">
                                {product.badge && (
                                    <Badge variant={product.badge === 'Sale' ? 'destructive' : 'secondary'} className="mb-3 font-medium">
                                        {product.badge}
                                    </Badge>
                                )}
                                <h3 className="mb-2 text-2xl font-bold text-gray-900">{product.name}</h3>
                                {product.category && <p className="mb-3 text-base font-medium text-gray-600">{product.category.name}</p>}
                                <div className="mb-4 flex items-center space-x-2">
                                    <div className="flex">{renderStars(product.rating)}</div>
                                    <span className="text-base font-medium text-gray-500">
                                        ({product.reviews} {t('products.reviews')})
                                    </span>
                                </div>
                                <div className="flex items-center space-x-3">
                                    <span className="text-3xl font-bold text-gray-900">${product.price}</span>
                                    {product.originalPrice && <span className="text-xl text-gray-500 line-through">${product.originalPrice}</span>}
                                </div>
                            </div>
                            <div className="ml-8 flex flex-col space-y-3">
                                <Button
                                    size="lg"
                                    variant="outline"
                                    className="w-full font-semibold"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        setIsQuickViewOpen(true);
                                    }}
                                >
                                    <Eye className="mr-2 h-5 w-5" />
                                    {t('products.quick_view')}
                                </Button>
                                <Button
                                    size="lg"
                                    disabled={!product.inStock}
                                    className="w-full bg-black font-semibold text-white transition-all duration-200 hover:bg-gray-900 hover:shadow-lg"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        handleAddToCart();
                                    }}
                                >
                                    <ShoppingCart className="mr-2 h-5 w-5 text-white" />
                                    {product.inStock ? t('products.add_to_cart') : t('products.out_of_stock')}
                                </Button>
                                <Button variant="outline" size="lg" className="w-full" onClick={(e) => e.stopPropagation()}>
                                    <Heart className="mr-2 h-5 w-5" />
                                    {t('products.add_to_wishlist')}
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </div>

                {/* Quick View Modal */}
                <QuickViewModal
                    product={product}
                    isOpen={isQuickViewOpen}
                    onClose={() => setIsQuickViewOpen(false)}
                    onAddToCart={handleAddToCart}
                    onNavigateToProduct={() => {
                        setIsQuickViewOpen(false);
                        handleNavigateToProduct();
                    }}
                />
            </Card>
        );
    }

    return (
        <Card className="group flex h-full cursor-pointer flex-col gap-0 overflow-hidden border border-gray-200 bg-white py-0 transition-all duration-300 hover:shadow-xl">
            <div className="relative">
                {product.badge && (
                    <Badge variant={product.badge === 'Sale' ? 'destructive' : 'secondary'} className="absolute top-4 left-4 z-10 font-medium">
                        {product.badge}
                    </Badge>
                )}
                <div className="relative aspect-square overflow-hidden bg-gray-50">
                    <img
                        src={imageSrc}
                        alt={product.name}
                        onError={handleImageError}
                        className="relative z-10 h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
                </div>
                <div className="bg-opacity-0 group-hover:bg-opacity-20 absolute inset-0 z-20 flex items-center justify-center transition-all">
                    <Button
                        className="opacity-0 shadow-lg transition-opacity group-hover:opacity-100"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            setIsQuickViewOpen(true);
                        }}
                    >
                        <Eye className="mr-2 h-4 w-4" />
                        Quick View
                    </Button>
                </div>
            </div>

            {/* Separator line above the button */}
            <div className="my-4 border-t border-black" />
            <CardContent className="flex h-full flex-col bg-white p-6" onClick={handleNavigateToProduct}>
                <div className="flex-1">
                    {product.category && <p className="mb-2 text-sm font-medium text-gray-600">{product.category.name}</p>}
                    <h3 className="mb-3 line-clamp-2 text-lg leading-tight font-bold text-gray-900">{product.name}</h3>
                    <div className="mb-4 flex items-center space-x-2">
                        <div className="flex">{renderStars(product.rating)}</div>
                        <span className="text-sm font-medium text-gray-500">
                            ({product.reviews} {t('products.reviews')})
                        </span>
                    </div>
                    <div className="mb-4 flex items-center justify-between">
                        <div className="flex items-center space-x-2">
                            <span className="text-xl font-bold text-gray-900">${product.price}</span>
                            {product.originalPrice && <span className="text-base text-gray-500 line-through">${product.originalPrice}</span>}
                        </div>
                    </div>
                </div>
                <Button
                    className="mt-auto min-h-[44px] w-full bg-black px-3 py-2 text-center text-sm leading-tight font-semibold break-words whitespace-normal text-white transition-all duration-200 hover:bg-gray-900 hover:shadow-lg"
                    disabled={!product.inStock}
                    onClick={(e) => {
                        e.stopPropagation();
                        handleAddToCart();
                    }}
                >
                    <ShoppingCart className="mr-2 h-5 w-5 text-white" />
                    {product.inStock ? t('products.add_to_cart') : t('products.out_of_stock')}
                </Button>
            </CardContent>

            {/* Quick View Modal */}
            <QuickViewModal
                product={product}
                isOpen={isQuickViewOpen}
                onClose={() => setIsQuickViewOpen(false)}
                onAddToCart={handleAddToCart}
                onNavigateToProduct={() => {
                    setIsQuickViewOpen(false);
                    handleNavigateToProduct();
                }}
            />
        </Card>
    );
}

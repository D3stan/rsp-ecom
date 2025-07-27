import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useState } from 'react';
import type { Product } from '@/pages/products';
import { 
    ShoppingCart, 
    Star, 
    Heart,
    Eye,
    Plus,
    Minus
} from 'lucide-react';

const renderStars = (rating: number) => {
    return Array.from({ length: 5 }, (_, i) => (
        <Star 
            key={i} 
            className={`w-4 h-4 ${
                i < Math.floor(rating) 
                    ? 'fill-yellow-400 text-yellow-400' 
                    : 'text-gray-300'
            }`} 
        />
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
    const [imageSrc, setImageSrc] = useState(product.image);

    const decreaseQuantity = () => {
        if (quantity > 1) setQuantity(quantity - 1);
    };

    const increaseQuantity = () => {
        if (quantity < product.stockQuantity) setQuantity(quantity + 1);
    };

    const handleImageError = () => {
        setImageSrc('/images/product.png');
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto bg-white" aria-describedby="quick-view-description">
                <DialogHeader>
                    <DialogTitle className="text-gray-900">Quick View</DialogTitle>
                </DialogHeader>
                <div id="quick-view-description" className="sr-only">
                    Quick view modal for {product.name}. View product details, select quantity, and add to cart.
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Product Image */}
                    <div className="aspect-square overflow-hidden rounded-lg bg-gray-100">
                        <img 
                            src={imageSrc} 
                            alt={product.name}
                            onError={handleImageError}
                            className="w-full h-full object-cover"
                        />
                    </div>
                    
                    {/* Product Details */}
                    <div className="space-y-4">
                        {product.badge && (
                            <Badge 
                                variant={product.badge === 'Sale' ? 'destructive' : 'secondary'}
                                className="w-fit"
                            >
                                {product.badge}
                            </Badge>
                        )}
                        
                        <div>
                            <h2 className="text-2xl font-bold text-gray-900">{product.name}</h2>
                            {product.category && (
                                <p className="text-sm text-gray-600 mt-1">{product.category.name}</p>
                            )}
                        </div>
                        
                        <div className="flex items-center space-x-2">
                            <div className="flex">
                                {renderStars(product.rating)}
                            </div>
                            <span className="text-sm text-gray-500">({product.reviews} reviews)</span>
                        </div>
                        
                        <div className="flex items-center space-x-3">
                            <span className="text-3xl font-bold text-gray-900">${product.price}</span>
                            {product.originalPrice && (
                                <span className="text-xl text-gray-500 line-through">
                                    ${product.originalPrice}
                                </span>
                            )}
                        </div>
                        
                        <div className="flex items-center space-x-4">
                            <Label className="text-sm font-medium">Quantity:</Label>
                            <div className="flex items-center border border-gray-300 rounded-md">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={decreaseQuantity}
                                    disabled={quantity <= 1}
                                    className="h-8 w-8 p-0"
                                >
                                    <Minus className="w-4 h-4" />
                                </Button>
                                <span className="px-3 py-1 text-sm font-medium min-w-[2rem] text-center">
                                    {quantity}
                                </span>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={increaseQuantity}
                                    disabled={quantity >= product.stockQuantity}
                                    className="h-8 w-8 p-0"
                                >
                                    <Plus className="w-4 h-4" />
                                </Button>
                            </div>
                            <span className="text-sm text-gray-600">
                                {product.stockQuantity} in stock
                            </span>
                        </div>
                        
                        <div className="space-y-3 pt-4">
                            <Button 
                                onClick={() => onAddToCart(quantity)}
                                disabled={!product.inStock}
                                className="w-full"
                                size="lg"
                            >
                                <ShoppingCart className="w-5 h-5 mr-2" />
                                {product.inStock ? 'Add to Cart' : 'Out of Stock'}
                            </Button>
                            
                            <Button 
                                variant="outline" 
                                className="w-full"
                                onClick={onNavigateToProduct}
                            >
                                View Full Details
                            </Button>
                            
                            <Button variant="outline" size="sm" className="w-full">
                                <Heart className="w-4 h-4 mr-2" />
                                Add to Wishlist
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
    const [imageSrc, setImageSrc] = useState(product.image);

    const handleAddToCart = () => {
        // TODO: Implement add to cart functionality
        console.log('Adding to cart:', product.id, quantity);
    };

    const handleNavigateToProduct = () => {
        router.visit(`/products/${product.slug}`);
    };

    const handleImageError = () => {
        setImageSrc('/images/product.png');
    };

    if (viewMode === 'list') {
        return (
            <Card 
                className="overflow-hidden hover:shadow-xl transition-all duration-300 bg-white border border-gray-200 cursor-pointer"
                onClick={handleNavigateToProduct}
            >
                <div className="flex">
                    <div className="w-48 h-48 flex-shrink-0 bg-gray-50">
                        <img 
                            src={imageSrc} 
                            alt={product.name}
                            onError={handleImageError}
                            className="w-full h-full object-cover"
                        />
                    </div>
                    <CardContent className="flex-1 p-8 bg-white">
                        <div className="flex justify-between h-full">
                            <div className="flex-1">
                                {product.badge && (
                                    <Badge 
                                        variant={product.badge === 'Sale' ? 'destructive' : 'secondary'}
                                        className="mb-3 font-medium"
                                    >
                                        {product.badge}
                                    </Badge>
                                )}
                                <h3 className="text-2xl font-bold mb-2 text-gray-900">{product.name}</h3>
                                {product.category && (
                                    <p className="text-base text-gray-600 mb-3 font-medium">{product.category.name}</p>
                                )}
                                <div className="flex items-center space-x-2 mb-4">
                                    <div className="flex">
                                        {renderStars(product.rating)}
                                    </div>
                                    <span className="text-base text-gray-500 font-medium">({product.reviews})</span>
                                </div>
                                <div className="flex items-center space-x-3">
                                    <span className="text-3xl font-bold text-gray-900">${product.price}</span>
                                    {product.originalPrice && (
                                        <span className="text-xl text-gray-500 line-through">
                                            ${product.originalPrice}
                                        </span>
                                    )}
                                </div>
                            </div>
                            <div className="flex flex-col space-y-3 ml-8">
                                <Button 
                                    size="lg" 
                                    variant="outline"
                                    className="w-full font-semibold"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        setIsQuickViewOpen(true);
                                    }}
                                >
                                    <Eye className="w-5 h-5 mr-2" />
                                    Quick View
                                </Button>
                                <Button 
                                    size="lg" 
                                    disabled={!product.inStock}
                                    className="w-full font-semibold bg-black text-white hover:bg-gray-900 hover:shadow-lg transition-all duration-200"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        handleAddToCart();
                                    }}
                                >
                                    <ShoppingCart className="w-5 h-5 mr-2 text-white" />
                                    {product.inStock ? 'Add to Cart' : 'Out of Stock'}
                                </Button>
                                <Button 
                                    variant="outline" 
                                    size="lg" 
                                    className="w-full"
                                    onClick={(e) => e.stopPropagation()}
                                >
                                    <Heart className="w-5 h-5 mr-2" />
                                    Wishlist
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
                    onNavigateToProduct={handleNavigateToProduct}
                />
            </Card>
        );
    }

    return (
        <Card 
            className="group overflow-hidden hover:shadow-xl transition-all duration-300 bg-white border border-gray-200 flex flex-col h-full cursor-pointer"
            onClick={handleNavigateToProduct}
        >
            <div className="relative">
                {product.badge && (
                    <Badge 
                        variant={product.badge === 'Sale' ? 'destructive' : 'secondary'}
                        className="absolute top-4 left-4 z-10 font-medium"
                    >
                        {product.badge}
                    </Badge>
                )}
                <div className="aspect-square overflow-hidden bg-gray-50">
                    <img 
                        src={imageSrc} 
                        alt={product.name}
                        onError={handleImageError}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    />
                </div>
                <div className="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all flex items-center justify-center">
                    <Button 
                        className="opacity-0 group-hover:opacity-100 transition-opacity shadow-lg"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            setIsQuickViewOpen(true);
                        }}
                    >
                        <Eye className="w-4 h-4 mr-2" />
                        Quick View
                    </Button>
                </div>
            </div>
            <CardContent className="p-6 bg-white flex flex-col h-full">
                <div className="flex-1">
                    {product.category && (
                        <p className="text-sm text-gray-600 mb-2 font-medium">{product.category.name}</p>
                    )}
                    <h3 className="font-bold text-lg mb-3 line-clamp-2 text-gray-900 leading-tight">{product.name}</h3>
                    <div className="flex items-center space-x-2 mb-4">
                        <div className="flex">
                            {renderStars(product.rating)}
                        </div>
                        <span className="text-sm text-gray-500 font-medium">({product.reviews})</span>
                    </div>
                    <div className="flex items-center justify-between mb-4">
                        <div className="flex items-center space-x-2">
                            <span className="text-xl font-bold text-gray-900">${product.price}</span>
                            {product.originalPrice && (
                                <span className="text-base text-gray-500 line-through">
                                    ${product.originalPrice}
                                </span>
                            )}
                        </div>
                    </div>
                </div>
                <Button 
                    className="w-full h-11 font-semibold bg-black text-white hover:bg-gray-900 hover:shadow-lg transition-all duration-200 mt-auto" 
                    disabled={!product.inStock}
                    onClick={(e) => {
                        e.stopPropagation();
                        handleAddToCart();
                    }}
                >
                    <ShoppingCart className="w-5 h-5 mr-2 text-white" />
                    {product.inStock ? 'Add to Cart' : 'Out of Stock'}
                </Button>
            </CardContent>

            {/* Quick View Modal */}
            <QuickViewModal
                product={product}
                isOpen={isQuickViewOpen}
                onClose={() => setIsQuickViewOpen(false)}
                onAddToCart={handleAddToCart}
                onNavigateToProduct={handleNavigateToProduct}
            />
        </Card>
    );
}

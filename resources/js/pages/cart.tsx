import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import Header from '@/components/header';
import { useState } from 'react';
import { useIsMobile } from '@/hooks/use-mobile';
import { cartService } from '@/services/cartService';
import { 
    ShoppingCart,
    Plus,
    Minus,
    Package,
    ArrowRight,
    Trash2
} from 'lucide-react';

interface CartItem {
    id: number;
    product: {
        id: number;
        name: string;
        slug: string;
        image: string;
        category?: string;
    };
    quantity: number;
    price: number;
    total: number;
    size?: string;
}

interface Cart {
    id: number;
}

interface CartPageProps extends SharedData {
    cart: Cart | null;
    cartItems: CartItem[];
    subtotal: number;
    shippingCost: number;
    total: number;
    totalItems: number;
}

export default function Cart() {
    const pageProps = usePage<CartPageProps>().props;
    const { cartItems, subtotal, shippingCost, total, totalItems } = pageProps;
    const { auth } = pageProps;
    const isMobile = useIsMobile();
    
    const [isUpdating, setIsUpdating] = useState<number | null>(null);

    // Image handling helper
    const getImageSrc = (item: CartItem) => {
        const defaultImage = '/images/product.png';
        if (!item.product.image || item.product.image === '' || item.product.image === 'product.png') {
            return defaultImage;
        }
        return item.product.image;
    };

    const handleImageError = (e: React.SyntheticEvent<HTMLImageElement>) => {
        const target = e.target as HTMLImageElement;
        if (!target.src.includes('product.png')) {
            target.src = '/images/product.png';
        }
    };

    const updateQuantity = async (itemId: number, newQuantity: number) => {
        if (newQuantity < 1) return;
        
        setIsUpdating(itemId);
        
        try {
            await router.put(`/cart/items/${itemId}`, {
                quantity: newQuantity,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    // Cart will be refreshed automatically by Inertia
                    cartService.triggerCartUpdate(); // Update header cart count
                },
                onError: (errors) => {
                    console.error('Failed to update cart:', errors);
                },
                onFinish: () => {
                    setIsUpdating(null);
                }
            });
        } catch (error) {
            console.error('Error updating cart:', error);
            setIsUpdating(null);
        }
    };

    const removeItem = async (itemId: number) => {
        try {
            await router.delete(`/cart/items/${itemId}`, {
                preserveScroll: true,
                onSuccess: () => {
                    // Cart will be refreshed automatically by Inertia
                    cartService.triggerCartUpdate(); // Update header cart count
                },
                onError: (errors) => {
                    console.error('Failed to remove item:', errors);
                }
            });
        } catch (error) {
            console.error('Error removing item:', error);
        }
    };

    const proceedToCheckout = () => {
        if (auth?.user) {
            // User is authenticated, proceed to checkout details page
            router.get('/checkout');
        } else {
            // User is not authenticated, redirect to guest checkout details page
            router.get('/guest/checkout');
        }
    };

    if (!cartItems || cartItems.length === 0) {
        return (
            <>
                <Head title="Your Cart" />
                
                <div className="min-h-screen bg-white">
                    <Header />
                    
                    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                        {/* Empty Cart State */}
                        <div className="text-center">
                            <div className="flex justify-center mb-6">
                                <div className="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center">
                                    <ShoppingCart className="w-12 h-12 text-gray-400" />
                                </div>
                            </div>
                            
                            <h1 className="text-3xl font-bold text-gray-900 mb-4">
                                Your cart is empty
                            </h1>
                            <p className="text-lg text-gray-600 mb-8">
                                Add items to get started
                            </p>
                            
                            <Button asChild size="lg" className="px-8">
                                <Link href="/products">
                                    Browse Products
                                    <ArrowRight className="w-5 h-5 ml-2" />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`Your Cart (${totalItems})`} />
            
            <div className={`min-h-screen bg-white ${isMobile ? 'pb-24' : ''}`}>
                <Header />
                
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Cart Header */}
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-gray-900">
                            Your Cart
                        </h1>
                        <p className="text-gray-600 mt-2">
                            {totalItems} {totalItems === 1 ? 'item' : 'items'} in your cart
                        </p>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        {/* Cart Items */}
                        <div className="lg:col-span-2 space-y-4">
                            {cartItems.map((item) => (
                                <div
                                    key={item.id}
                                    className="bg-white border border-gray-200 rounded-lg p-4 sm:p-6 hover:shadow-md transition-shadow"
                                >
                                    {/* Top Section: Image and Product Details */}
                                    <div className="flex items-start space-x-4 mb-4">
                                        {/* Product Image - Left Side */}
                                        <Link
                                            href={`/products/${item.product.slug}`}
                                            className="flex-shrink-0"
                                        >
                                            <div className="w-20 h-20 sm:w-24 sm:h-24 overflow-hidden rounded-lg bg-gray-100">
                                                <img
                                                    src={getImageSrc(item)}
                                                    alt={item.product.name}
                                                    onError={handleImageError}
                                                    className="w-full h-full object-cover hover:opacity-80 transition-opacity"
                                                />
                                            </div>
                                        </Link>

                                        {/* Product Details - Right Side */}
                                        <div className="flex-1 min-w-0">
                                            <Link
                                                href={`/products/${item.product.slug}`}
                                                className="block hover:text-blue-600 transition-colors"
                                            >
                                                <h3 className="text-lg font-semibold text-gray-900 line-clamp-2 mb-2">
                                                    {item.product.name}
                                                </h3>
                                            </Link>
                                            
                                            <div className="space-y-1">
                                                {item.product.category && (
                                                    <p className="text-sm text-gray-500">
                                                        Category: {item.product.category}
                                                    </p>
                                                )}
                                                
                                                {item.size && (
                                                    <p className="text-sm text-gray-600">
                                                        Size: {item.size}
                                                    </p>
                                                )}
                                            </div>

                                            {/* Quantity Controls */}
                                            <div className="flex items-center space-x-2 mt-3">
                                                <Label className="text-sm font-medium text-gray-700">
                                                    Quantity:
                                                </Label>
                                                <div className="flex items-center border border-gray-300 rounded-lg text-black">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => updateQuantity(item.id, item.quantity - 1)}
                                                        disabled={item.quantity <= 1 || isUpdating === item.id}
                                                        className="h-8 w-8 p-0"
                                                    >
                                                        <Minus className="w-4 h-4" />
                                                    </Button>
                                                    <span className="px-3 py-1 text-sm font-medium min-w-[2rem] text-center">
                                                        {item.quantity}
                                                    </span>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => updateQuantity(item.id, item.quantity + 1)}
                                                        disabled={isUpdating === item.id}
                                                        className="h-8 w-8 p-0"
                                                    >
                                                        <Plus className="w-4 h-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Bottom Section: Price and Delete Button */}
                                    <div className="flex items-center justify-between pt-4 border-t border-gray-100">
                                        <div>
                                            <p className="text-lg font-semibold text-gray-900">
                                                ${item.total.toFixed(2)}
                                            </p>
                                            <p className="text-sm text-gray-500">
                                                ${item.price.toFixed(2)} each
                                            </p>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => removeItem(item.id)}
                                            className="text-red-600 hover:text-red-800 hover:bg-red-50"
                                        >
                                            <Trash2 className="w-4 h-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Cart Summary */}
                        <div className="lg:col-span-1">
                            <div className="bg-gray-50 rounded-lg p-6 space-y-6">
                                {/* Order Summary */}
                                <div className="space-y-3">
                                    <div className="flex justify-between text-gray-700">
                                        <span>Subtotal</span>
                                        <span>${subtotal.toFixed(2)}</span>
                                    </div>
                                    <div className="flex justify-between text-gray-700">
                                        <span>Shipping</span>
                                        <span>
                                            {shippingCost > 0 ? `$${shippingCost.toFixed(2)}` : 'Calculated at checkout'}
                                        </span>
                                    </div>
                                    <div className="border-t border-gray-200 pt-3">
                                        <div className="flex justify-between text-lg font-semibold text-gray-900">
                                            <span>Total</span>
                                            <span>${total.toFixed(2)}</span>
                                        </div>
                                    </div>
                                </div>

                                {/* Checkout Buttons */}
                                <div className="space-y-3">
                                    {/* Main Checkout Button - Hidden on mobile, shown on desktop */}
                                    <Button
                                        onClick={proceedToCheckout}
                                        className="w-full h-12 text-lg font-semibold border border-black hidden md:flex items-center justify-center"
                                        size="lg"
                                    >
                                        <Package className="w-5 h-5 mr-2" />
                                        Proceed to Checkout
                                    </Button>

                                    {/* Alternative options for non-authenticated users - Hidden on mobile */}
                                    {!auth?.user && (
                                        <div className="text-center hidden md:block">
                                            <p className="text-sm text-gray-600 mb-2">
                                                Already have an account?
                                            </p>
                                            <Button
                                                variant="outline"
                                                asChild
                                                className="w-full"
                                            >
                                                <Link href="/login">
                                                    Sign In to Continue
                                                </Link>
                                            </Button>
                                        </div>
                                    )}
                                </div>

                                {/* Continue Shopping - Hidden on mobile */}
                                <Button
                                    variant="outline"
                                    asChild
                                    className="w-full hidden md:flex"
                                >
                                    <Link href="/products">
                                        Continue Shopping
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Mobile Sticky Footer */}
                {isMobile && (
                    <div className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-4 z-50">
                        <div className="flex items-center justify-between space-x-4">
                            <div>
                                <p className="text-lg font-bold text-gray-900">
                                    ${total.toFixed(2)}
                                </p>
                                <p className="text-sm text-gray-500">
                                    {totalItems} items
                                </p>
                            </div>
                            <Button
                                onClick={proceedToCheckout}
                                className="flex-1 h-12 font-semibold border border-black"
                            >
                                <Package className="w-5 h-5 mr-2" />
                                Checkout
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

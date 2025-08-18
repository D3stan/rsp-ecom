import Header from '@/components/header';
import LoadingOverlay from '@/components/LoadingOverlay';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useIsMobile } from '@/hooks/use-mobile';
import { useTranslation } from '@/hooks/useTranslation';
import { formatCurrency } from '@/lib/utils';
import { cartService } from '@/services/cartService';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, Minus, Package, Plus, ShoppingCart, Trash2 } from 'lucide-react';
import { useState } from 'react';

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
    const { t, isLoading } = useTranslation();

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
            await router.put(
                `/cart/items/${itemId}`,
                {
                    quantity: newQuantity,
                },
                {
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
                    },
                },
            );
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
                },
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
                <Head title={t('cart.title')} />

                <LoadingOverlay isLoading={isLoading} className="min-h-screen bg-white">
                    <Header />

                    <div className="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
                        {/* Empty Cart State */}
                        <div className="text-center">
                            <div className="mb-6 flex justify-center">
                                <div className="flex h-24 w-24 items-center justify-center rounded-full bg-gray-100">
                                    <ShoppingCart className="h-12 w-12 text-gray-400" />
                                </div>
                            </div>

                            <h1 className="mb-4 text-3xl font-bold text-gray-900">{t('cart.empty_cart')}</h1>
                            <p className="mb-8 text-lg text-gray-600">Add items to get started</p>

                            <Button asChild size="lg" className="px-8">
                                <Link href="/products">
                                    {t('cart.browse_products')}
                                    <ArrowRight className="ml-2 h-5 w-5" />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </LoadingOverlay>
            </>
        );
    }

    return (
        <>
            <Head title={t('cart.title_with_items', { count: totalItems })} />

            <LoadingOverlay isLoading={isLoading} className={`min-h-screen bg-white ${isMobile ? 'pb-24' : ''}`}>
                <Header />

                <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                    {/* Cart Header */}
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-gray-900">{t('cart.title')}</h1>
                        <p className="mt-2 text-gray-600">
                            {t('cart.items_in_cart', {
                                count: totalItems,
                                item_word: totalItems === 1 ? t('cart.item') : t('cart.items'),
                            })}
                        </p>
                    </div>

                    <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                        {/* Cart Items */}
                        <div className="space-y-4 lg:col-span-2">
                            {cartItems.map((item) => (
                                <div
                                    key={item.id}
                                    className="rounded-lg border border-gray-200 bg-white p-4 transition-shadow hover:shadow-md sm:p-6"
                                >
                                    {/* Top Section: Image and Product Details */}
                                    <div className="mb-4 flex items-start space-x-4">
                                        {/* Product Image - Left Side */}
                                        <Link href={`/products/${item.product.slug}`} className="flex-shrink-0">
                                            <div className="h-20 w-20 overflow-hidden rounded-lg bg-gray-100 sm:h-24 sm:w-24">
                                                <img
                                                    src={getImageSrc(item)}
                                                    alt={item.product.name}
                                                    onError={handleImageError}
                                                    className="h-full w-full object-cover transition-opacity hover:opacity-80"
                                                />
                                            </div>
                                        </Link>

                                        {/* Product Details - Right Side */}
                                        <div className="min-w-0 flex-1">
                                            <Link href={`/products/${item.product.slug}`} className="block transition-colors hover:text-blue-600">
                                                <h3 className="mb-2 line-clamp-2 text-lg font-semibold text-gray-900">{item.product.name}</h3>
                                            </Link>

                                            <div className="space-y-1">
                                                {item.product.category && (
                                                    <p className="text-sm text-gray-500">
                                                        {t('cart.category')}: {item.product.category}
                                                    </p>
                                                )}

                                                {item.size && (
                                                    <p className="text-sm text-gray-600">
                                                        {t('cart.size')}: {item.size}
                                                    </p>
                                                )}
                                            </div>

                                            {/* Quantity Controls */}
                                            <div className="mt-3 flex items-center space-x-2">
                                                <Label className="text-sm font-medium text-gray-700">{t('cart.quantity')}:</Label>
                                                <div className="flex items-center rounded-lg border border-gray-300 text-black">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => updateQuantity(item.id, item.quantity - 1)}
                                                        disabled={item.quantity <= 1 || isUpdating === item.id}
                                                        className="h-8 w-8 p-0"
                                                    >
                                                        <Minus className="h-4 w-4" />
                                                    </Button>
                                                    <span className="min-w-[2rem] px-3 py-1 text-center text-sm font-medium">{item.quantity}</span>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => updateQuantity(item.id, item.quantity + 1)}
                                                        disabled={isUpdating === item.id}
                                                        className="h-8 w-8 p-0"
                                                    >
                                                        <Plus className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Bottom Section: Price and Delete Button */}
                                    <div className="flex items-center justify-between border-t border-gray-100 pt-4">
                                        <div>
                                            <p className="text-lg font-semibold text-gray-900">{formatCurrency(item.total)}</p>
                                            <p className="text-sm text-gray-500">
                                                {formatCurrency(item.price)} {t('cart.each')}
                                            </p>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => removeItem(item.id)}
                                            className="text-red-600 hover:bg-red-50 hover:text-red-800"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Cart Summary */}
                        <div className="lg:col-span-1">
                            <div className="space-y-6 rounded-lg bg-gray-50 p-6">
                                {/* Order Summary */}
                                <div className="space-y-3">
                                    <div className="flex justify-between text-gray-700">
                                        <span>{t('cart.subtotal')}</span>
                                        <span>{formatCurrency(subtotal)}</span>
                                    </div>
                                    <div className="flex justify-between text-gray-700">
                                        <span>{t('cart.shipping')}</span>
                                        <span>{shippingCost > 0 ? formatCurrency(shippingCost) : t('cart.calculated_at_checkout')}</span>
                                    </div>
                                    <div className="border-t border-gray-200 pt-3">
                                        <div className="flex justify-between text-lg font-semibold text-gray-900">
                                            <span>{t('cart.total')}</span>
                                            <span>{formatCurrency(total)}</span>
                                        </div>
                                    </div>
                                </div>

                                {/* Checkout Buttons */}
                                <div className="space-y-3">
                                    {/* Main Checkout Button - Hidden on mobile, shown on desktop */}
                                    <Button
                                        onClick={proceedToCheckout}
                                        className="hidden h-12 w-full items-center justify-center border border-black text-lg font-semibold md:flex"
                                        size="lg"
                                    >
                                        <Package className="mr-2 h-5 w-5" />
                                        {t('cart.proceed_to_checkout')}
                                    </Button>

                                    {/* Alternative options for non-authenticated users - Hidden on mobile */}
                                    {!auth?.user && (
                                        <div className="hidden text-center md:block">
                                            <p className="mb-2 text-sm text-gray-600">{t('cart.already_have_account')}</p>
                                            <Button variant="outline" asChild className="w-full">
                                                <Link href="/login">{t('cart.sign_in_to_continue')}</Link>
                                            </Button>
                                        </div>
                                    )}
                                </div>

                                {/* Continue Shopping - Hidden on mobile */}
                                <Button variant="outline" asChild className="hidden w-full md:flex">
                                    <Link href="/products">{t('cart.continue_shopping')}</Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Mobile Sticky Footer */}
                {isMobile && (
                    <div className="fixed right-0 bottom-0 left-0 z-50 border-t border-gray-200 bg-white p-4">
                        <div className="flex items-center justify-between space-x-4">
                            <div>
                                <p className="text-lg font-bold text-gray-900">{formatCurrency(total)}</p>
                                <p className="text-sm text-gray-500">
                                    {totalItems} {t('cart.items')}
                                </p>
                            </div>
                            <Button onClick={proceedToCheckout} className="h-12 flex-1 border border-black font-semibold">
                                <Package className="mr-2 h-5 w-5" />
                                {t('cart.checkout')}
                            </Button>
                        </div>
                    </div>
                )}
            </LoadingOverlay>
        </>
    );
}

import Header from '@/components/header';
import LoadingOverlay from '@/components/LoadingOverlay';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/contexts/ToastContext';
import { useIsMobile } from '@/hooks/use-mobile';
import { useTranslation } from '@/hooks/useTranslation';
import { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Calculator, ChevronDown, ChevronUp, CreditCard, FileText, Tag, Truck } from 'lucide-react';
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

interface ShippingAddress {
    [key: string]: string | undefined;
    first_name: string;
    last_name: string;
    company?: string;
    address_line_1: string;
    address_line_2?: string;
    city: string;
    state: string;
    postal_code: string;
    country: string;
    phone?: string;
}

interface ContactInformation {
    email: string;
}

interface CheckoutDetailsProps extends SharedData {
    cart: Cart | null;
    cartItems: CartItem[];
    subtotal: number;
    shippingCost: number;
    taxAmount: number;
    discountAmount?: number;
    total: number;
    totalItems: number;
}



export default function CheckoutDetails() {
    const pageProps = usePage<CheckoutDetailsProps>().props;
    const { cartItems, subtotal, shippingCost, taxAmount, discountAmount = 0, total, totalItems, auth } = pageProps;
    const { t, isLoading } = useTranslation();
    const isMobile = useIsMobile();
    const { addToast } = useToast();

    // Collapsible states
    const [isContactOpen, setIsContactOpen] = useState(true);
    const [isShippingOpen, setIsShippingOpen] = useState(true);
    const [isDiscountOpen, setIsDiscountOpen] = useState(false);
    const [isNotesOpen, setIsNotesOpen] = useState(false);
    const [isTotalsOpen, setIsTotalsOpen] = useState(true);

    // Form states
    const [contactInfo, setContactInfo] = useState<ContactInformation>({
        email: auth?.user?.email || '',
    });

    const [shippingAddress, setShippingAddress] = useState<ShippingAddress>({
        first_name: auth?.user?.name?.split(' ')[0] || '',
        last_name: auth?.user?.name?.split(' ').slice(1).join(' ') || '',
        company: '',
        address_line_1: '',
        address_line_2: '',
        city: '',
        state: '',
        postal_code: '',
        country: 'IT',
        phone: '',
    });

    const [couponCode, setCouponCode] = useState('');
    const [orderNotes, setOrderNotes] = useState('');
    const [isApplyingCoupon, setIsApplyingCoupon] = useState(false);
    const [isProcessing, setIsProcessing] = useState(false);

    const updateShippingAddress = (field: keyof ShippingAddress, value: string) => {
        setShippingAddress((prev) => ({
            ...prev,
            [field]: value,
        }));
    };

    const updateContactInfo = (field: keyof ContactInformation, value: string) => {
        setContactInfo((prev) => ({
            ...prev,
            [field]: value,
        }));
    };

    const applyCoupon = async () => {
        if (!couponCode.trim()) return;

        setIsApplyingCoupon(true);

        try {
            await router.post(
                '/cart/apply-coupon',
                {
                    code: couponCode,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setCouponCode('');
                        addToast({
                            type: 'success',
                            title: 'Coupon Applied',
                            description: 'Your discount code has been successfully applied.',
                        });
                    },
                    onError: (errors) => {
                        console.error('Failed to apply coupon:', errors);
                        addToast({
                            type: 'error',
                            title: 'Invalid Coupon',
                            description: 'The discount code you entered is not valid or has expired.',
                        });
                    },
                    onFinish: () => {
                        setIsApplyingCoupon(false);
                    },
                },
            );
        } catch (error) {
            console.error('Error applying coupon:', error);
            addToast({
                type: 'error',
                title: 'Error',
                description: 'An unexpected error occurred while applying the coupon.',
            });
            setIsApplyingCoupon(false);
        }
    };

    const handleBackToCart = () => {
        router.get('/cart');
    };

    const proceedToPayment = () => {
        // Validate required fields
        if (!contactInfo.email.trim()) {
            addToast({
                type: 'error',
                title: 'Missing Information',
                description: 'Please provide a valid email address.',
            });
            return;
        }

        if (
            !shippingAddress.first_name.trim() ||
            !shippingAddress.last_name.trim() ||
            !shippingAddress.address_line_1.trim() ||
            !shippingAddress.city.trim() ||
            !shippingAddress.state.trim() ||
            !shippingAddress.postal_code.trim() ||
            !shippingAddress.country.trim()
        ) {
            addToast({
                type: 'error',
                title: 'Missing Information',
                description: 'Please fill in all required shipping address fields.',
            });
            return;
        }

        // Basic email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(contactInfo.email)) {
            addToast({
                type: 'error',
                title: 'Invalid Email',
                description: 'Please provide a valid email address.',
            });
            return;
        }

        setIsProcessing(true);

        // Prepare checkout data with proper typing
        const checkoutData = {
            email: contactInfo.email,
            shipping_address: shippingAddress,
            order_notes: orderNotes,
            coupon_code: couponCode,
        };

        if (auth?.user) {
            // User is authenticated, proceed to cart checkout using Cashier
            router.post('/checkout/cart', checkoutData, {
                onSuccess: (page: { props: { url?: string } }) => {
                    // Redirect to Stripe checkout will be handled by the backend
                    if (page.props?.url) {
                        window.location.href = page.props.url;
                    }
                },
                onError: (errors) => {
                    console.error('Checkout errors:', errors);
                    addToast({
                        type: 'error',
                        title: 'Checkout Error',
                        description: 'There was an error processing your checkout. Please try again.',
                    });
                    setIsProcessing(false);
                },
                onFinish: () => {
                    setIsProcessing(false);
                },
            });
        } else {
            // User is not authenticated, redirect to guest cart checkout
            router.post('/guest/checkout/cart', checkoutData, {
                onSuccess: (page: { props: { url?: string } }) => {
                    // Redirect to Stripe checkout will be handled by the backend
                    if (page.props?.url) {
                        window.location.href = page.props.url;
                    }
                },
                onError: (errors) => {
                    console.error('Checkout errors:', errors);
                    addToast({
                        type: 'error',
                        title: 'Checkout Error',
                        description: 'There was an error processing your checkout. Please try again.',
                    });
                    setIsProcessing(false);
                },
                onFinish: () => {
                    setIsProcessing(false);
                },
            });
        }
    };

    if (!cartItems || cartItems.length === 0) {
        return (
            <>
                <Head title={t('checkout.title')} />

                <div className="min-h-screen bg-white">
                    <Header />

                    <div className="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h1 className="mb-4 text-3xl font-bold text-gray-900">{t('checkout.cart_empty')}</h1>
                            <p className="mb-8 text-lg text-gray-600">{t('checkout.continue_shopping')}</p>

                            <Button asChild size="lg" className="px-8">
                                <Link href="/products">{t('cart.browse_products')}</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`${t('checkout.title')} - ${totalItems} ${totalItems === 1 ? t('cart.item') : t('cart.items')}`} />

            <LoadingOverlay isLoading={isLoading} className={`min-h-screen bg-white ${isMobile ? 'pb-24' : ''}`}>
                <Header />

                <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
                    {/* Checkout Header */}
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-gray-900">{t('checkout.title')}</h1>
                        <p className="mt-2 text-gray-600">
                            {totalItems} {totalItems === 1 ? t('cart.item') : t('cart.items')} {t('checkout.ready_for_checkout')}
                        </p>
                    </div>

                    <div className="space-y-6">
                        {/* Contact Information */}
                        <Card className="border-2 border-gray-700 bg-white shadow-md">
                            <Collapsible open={isContactOpen} onOpenChange={setIsContactOpen}>
                                <CollapsibleTrigger className="w-full">
                                    <CardHeader className="transition-colors hover:bg-gray-50">
                                        <CardTitle className="flex items-center justify-between text-black">
                                            <div className="flex items-center gap-2">
                                                <CreditCard className="h-5 w-5" />
                                                {t('checkout.contact_information')}
                                            </div>
                                            {isContactOpen ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                                        </CardTitle>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent className="space-y-4">
                                        <div>
                                            <Label htmlFor="email" className="font-medium text-black">
                                                {t('checkout.email')} *
                                            </Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                value={contactInfo.email}
                                                onChange={(e) => updateContactInfo('email', e.target.value)}
                                                required
                                                placeholder={t('checkout.email_placeholder')}
                                                className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                            />
                                            <p className="mt-1 text-sm text-gray-600">{t('checkout.email_confirmation_text')}</p>
                                        </div>
                                    </CardContent>
                                </CollapsibleContent>
                            </Collapsible>
                        </Card>

                        {/* Shipping Information */}
                        <Card className="border-2 border-gray-700 bg-white shadow-md">
                            <Collapsible open={isShippingOpen} onOpenChange={setIsShippingOpen}>
                                <CollapsibleTrigger className="w-full">
                                    <CardHeader className="transition-colors hover:bg-gray-50">
                                        <CardTitle className="flex items-center justify-between text-black">
                                            <div className="flex items-center gap-2">
                                                <Truck className="h-5 w-5" />
                                                {t('checkout.shipping_information')}
                                            </div>
                                            {isShippingOpen ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                                        </CardTitle>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent className="space-y-4">
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div>
                                                <Label htmlFor="first_name" className="font-medium text-black">
                                                    {t('checkout.first_name')} *
                                                </Label>
                                                <Input
                                                    id="first_name"
                                                    value={shippingAddress.first_name}
                                                    onChange={(e) => updateShippingAddress('first_name', e.target.value)}
                                                    required
                                                    className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="last_name" className="font-medium text-black">
                                                    {t('checkout.last_name')} *
                                                </Label>
                                                <Input
                                                    id="last_name"
                                                    value={shippingAddress.last_name}
                                                    onChange={(e) => updateShippingAddress('last_name', e.target.value)}
                                                    required
                                                    className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                                />
                                            </div>
                                        </div>

                                        <div>
                                            <Label htmlFor="company" className="text-gray-600">
                                                {t('checkout.company')} ({t('checkout.optional')})
                                            </Label>
                                            <Input
                                                id="company"
                                                value={shippingAddress.company}
                                                onChange={(e) => updateShippingAddress('company', e.target.value)}
                                                className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                            />
                                        </div>

                                        <div>
                                            <Label htmlFor="address_line_1" className="font-medium text-black">
                                                {t('checkout.address_line_1')} *
                                            </Label>
                                            <Input
                                                id="address_line_1"
                                                value={shippingAddress.address_line_1}
                                                onChange={(e) => updateShippingAddress('address_line_1', e.target.value)}
                                                required
                                                className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                            />
                                        </div>

                                        <div>
                                            <Label htmlFor="address_line_2" className="text-gray-600">
                                                {t('checkout.address_line_2')} ({t('checkout.optional')})
                                            </Label>
                                            <Input
                                                id="address_line_2"
                                                value={shippingAddress.address_line_2}
                                                onChange={(e) => updateShippingAddress('address_line_2', e.target.value)}
                                                className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                            />
                                        </div>

                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                            <div>
                                                <Label htmlFor="city" className="font-medium text-black">
                                                    {t('checkout.city')} *
                                                </Label>
                                                <Input
                                                    id="city"
                                                    value={shippingAddress.city}
                                                    onChange={(e) => updateShippingAddress('city', e.target.value)}
                                                    required
                                                    className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="state" className="font-medium text-black">
                                                    {t('checkout.state')} *
                                                </Label>
                                                <Input
                                                    id="state"
                                                    value={shippingAddress.state}
                                                    onChange={(e) => updateShippingAddress('state', e.target.value)}
                                                    required
                                                    className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="postal_code" className="font-medium text-black">
                                                    {t('checkout.postal_code')} *
                                                </Label>
                                                <Input
                                                    id="postal_code"
                                                    value={shippingAddress.postal_code}
                                                    onChange={(e) => updateShippingAddress('postal_code', e.target.value)}
                                                    required
                                                    className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                                />
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div>
                                                <Label htmlFor="country" className="font-medium text-black">
                                                    {t('checkout.country')} *
                                                </Label>
                                                <Select
                                                    value={shippingAddress.country}
                                                    onValueChange={(value) => updateShippingAddress('country', value)}
                                                >
                                                    <SelectTrigger className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent className="bg-white text-black">
                                                        <SelectItem value="IT">Italy</SelectItem>
                                                        <SelectItem value="DE">Germany</SelectItem>
                                                        <SelectItem value="GB">United Kingdom</SelectItem>
                                                        <SelectItem value="FR">France</SelectItem>
                                                        <SelectItem value="ES">Spain</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div>
                                                <Label htmlFor="phone" className="text-gray-600">
                                                    {t('checkout.phone')}
                                                </Label>
                                                <Input
                                                    id="phone"
                                                    value={shippingAddress.phone}
                                                    onChange={(e) => updateShippingAddress('phone', e.target.value)}
                                                    className="border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                                />
                                            </div>
                                        </div>
                                    </CardContent>
                                </CollapsibleContent>
                            </Collapsible>
                        </Card>

                        {/* Discount Code */}
                        <Card className="border-2 border-gray-700 bg-white shadow-md">
                            <Collapsible open={isDiscountOpen} onOpenChange={setIsDiscountOpen}>
                                <CollapsibleTrigger className="w-full">
                                    <CardHeader className="transition-colors hover:bg-gray-50">
                                        <CardTitle className="flex items-center justify-between text-black">
                                            <div className="flex items-center gap-2">
                                                <Tag className="h-5 w-5" />
                                                {t('cart.discount_code')}
                                            </div>
                                            {isDiscountOpen ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                                        </CardTitle>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent>
                                        <div className="flex space-x-2">
                                            <Input
                                                type="text"
                                                placeholder={t('cart.coupon_placeholder')}
                                                value={couponCode}
                                                onChange={(e) => setCouponCode(e.target.value)}
                                                className="flex-1 border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                            />
                                            <Button
                                                variant="outline"
                                                onClick={applyCoupon}
                                                disabled={!couponCode.trim() || isApplyingCoupon}
                                                className="border-gray-300 hover:bg-gray-50"
                                            >
                                                {isApplyingCoupon ? t('cart.applying') : t('cart.apply')}
                                            </Button>
                                        </div>
                                        {discountAmount > 0 && (
                                            <div className="mt-3 rounded-lg border border-green-200 bg-green-50 p-3">
                                                <p className="text-sm text-green-800">
                                                    {t('cart.discount_applied', { amount: discountAmount.toFixed(2) })}
                                                </p>
                                            </div>
                                        )}
                                    </CardContent>
                                </CollapsibleContent>
                            </Collapsible>
                        </Card>

                        {/* Order Notes */}
                        <Card className="border-2 border-gray-700 bg-white shadow-md">
                            <Collapsible open={isNotesOpen} onOpenChange={setIsNotesOpen}>
                                <CollapsibleTrigger className="w-full">
                                    <CardHeader className="transition-colors hover:bg-gray-50">
                                        <CardTitle className="flex items-center justify-between text-black">
                                            <div className="flex items-center gap-2">
                                                <FileText className="h-5 w-5" />
                                                {t('checkout.order_notes')} ({t('checkout.optional')})
                                            </div>
                                            {isNotesOpen ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                                        </CardTitle>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent>
                                        <Textarea
                                            placeholder={t('checkout.order_notes_placeholder')}
                                            value={orderNotes}
                                            onChange={(e) => setOrderNotes(e.target.value)}
                                            maxLength={200}
                                            className="resize-none border-gray-300 bg-white text-black focus:border-gray-500 focus:ring-gray-500"
                                            rows={3}
                                        />
                                        <p className="mt-1 text-xs text-gray-500">
                                            {orderNotes.length}/200 {t('checkout.characters')}
                                        </p>
                                    </CardContent>
                                </CollapsibleContent>
                            </Collapsible>
                        </Card>

                        {/* Order Total */}
                        <Card className="border-2 border-gray-700 bg-white shadow-md">
                            <Collapsible open={isTotalsOpen} onOpenChange={setIsTotalsOpen}>
                                <CollapsibleTrigger className="w-full">
                                    <CardHeader className="transition-colors hover:bg-gray-50">
                                        <CardTitle className="flex items-center justify-between text-black">
                                            <div className="flex items-center gap-2">
                                                <Calculator className="h-5 w-5" />
                                                {t('cart.order_summary')}
                                            </div>
                                            {isTotalsOpen ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                                        </CardTitle>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent className="space-y-3">
                                        <div className="flex justify-between text-gray-600">
                                            <span>{t('cart.subtotal')}</span>
                                            <span className="font-medium text-black">${subtotal.toFixed(2)}</span>
                                        </div>
                                        <div className="flex justify-between text-gray-600">
                                            <span>{t('cart.shipping')}</span>
                                            <span className="font-medium text-black">
                                                {shippingCost > 0 ? `$${shippingCost.toFixed(2)}` : t('cart.free')}
                                            </span>
                                        </div>
                                        <div className="flex justify-between text-gray-600">
                                            <span>{t('cart.tax')}</span>
                                            <span className="font-medium text-black">${taxAmount.toFixed(2)}</span>
                                        </div>
                                        {discountAmount > 0 && (
                                            <div className="flex justify-between text-green-600">
                                                <span>{t('cart.discount')}</span>
                                                <span className="font-medium">-${discountAmount.toFixed(2)}</span>
                                            </div>
                                        )}
                                        <div className="border-t border-gray-200 pt-3">
                                            <div className="flex justify-between text-lg font-semibold text-black">
                                                <span>{t('cart.total')}</span>
                                                <span>${total.toFixed(2)}</span>
                                            </div>
                                        </div>
                                    </CardContent>
                                </CollapsibleContent>
                            </Collapsible>
                        </Card>

                        {/* Action Buttons - Hidden on mobile, only show on desktop */}
                        <div className="grid hidden grid-cols-1 gap-4 pt-6 md:grid md:grid-cols-2">
                            <Button variant="outline" onClick={handleBackToCart} size="lg" className="w-full border-gray-300 hover:bg-gray-50">
                                <ArrowLeft className="mr-2 h-5 w-5" />
                                {t('cart.back_to_cart')}
                            </Button>
                            <Button
                                onClick={proceedToPayment}
                                disabled={isProcessing}
                                size="lg"
                                className="w-full bg-black text-white hover:bg-gray-800"
                            >
                                <CreditCard className="mr-2 h-5 w-5" />
                                {isProcessing ? t('checkout.processing') : t('checkout.pay', { amount: total.toFixed(2) })}
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Mobile Sticky Footer */}
                {isMobile && (
                    <div className="fixed right-0 bottom-0 left-0 z-50 border-t border-gray-300 bg-white p-4 shadow-lg">
                        <div className="grid grid-cols-2 gap-4">
                            <Button variant="outline" onClick={handleBackToCart} className="h-12 border-gray-300 hover:bg-gray-50">
                                <ArrowLeft className="mr-2 h-5 w-5" />
                                {t('cart.back')}
                            </Button>
                            <Button
                                onClick={proceedToPayment}
                                disabled={isProcessing}
                                className="h-12 bg-black font-semibold text-white hover:bg-gray-800"
                            >
                                <CreditCard className="mr-2 h-5 w-5" />
                                {isProcessing ? t('checkout.processing') : t('checkout.pay', { amount: total.toFixed(2) })}
                            </Button>
                        </div>
                    </div>
                )}
            </LoadingOverlay>
        </>
    );
}

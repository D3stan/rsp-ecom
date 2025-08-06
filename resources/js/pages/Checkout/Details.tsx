import { Head, Link, router, usePage } from '@inertiajs/react';
import Header from '@/components/header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useState } from 'react';
import { useIsMobile } from '@/hooks/use-mobile';
import { useToast } from '@/contexts/ToastContext';
import { SharedData } from '@/types';
import { 
    ChevronDown,
    ChevronUp,
    Truck,
    Tag,
    FileText,
    Calculator,
    ArrowLeft,
    CreditCard
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
        email: auth?.user?.email || ''
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
        phone: ''
    });
    
    const [couponCode, setCouponCode] = useState('');
    const [orderNotes, setOrderNotes] = useState('');
    const [isApplyingCoupon, setIsApplyingCoupon] = useState(false);
    const [isProcessing, setIsProcessing] = useState(false);

    const updateShippingAddress = (field: keyof ShippingAddress, value: string) => {
        setShippingAddress(prev => ({
            ...prev,
            [field]: value
        }));
    };

    const updateContactInfo = (field: keyof ContactInformation, value: string) => {
        setContactInfo(prev => ({
            ...prev,
            [field]: value
        }));
    };

    const applyCoupon = async () => {
        if (!couponCode.trim()) return;
        
        setIsApplyingCoupon(true);
        
        try {
            await router.post('/cart/apply-coupon', {
                code: couponCode,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    setCouponCode('');
                    addToast({
                        type: "success",
                        title: "Coupon Applied",
                        description: "Your discount code has been successfully applied.",
                    });
                },
                onError: (errors) => {
                    console.error('Failed to apply coupon:', errors);
                    addToast({
                        type: "error",
                        title: "Invalid Coupon",
                        description: "The discount code you entered is not valid or has expired.",
                    });
                },
                onFinish: () => {
                    setIsApplyingCoupon(false);
                }
            });
        } catch (error) {
            console.error('Error applying coupon:', error);
            addToast({
                type: "error",
                title: "Error",
                description: "An unexpected error occurred while applying the coupon.",
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
                type: "error",
                title: "Missing Information",
                description: "Please provide a valid email address.",
            });
            return;
        }

        if (!shippingAddress.first_name.trim() || 
            !shippingAddress.last_name.trim() || 
            !shippingAddress.address_line_1.trim() || 
            !shippingAddress.city.trim() || 
            !shippingAddress.state.trim() || 
            !shippingAddress.postal_code.trim() || 
            !shippingAddress.country.trim()) {
            addToast({
                type: "error",
                title: "Missing Information",
                description: "Please fill in all required shipping address fields.",
            });
            return;
        }

        // Basic email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(contactInfo.email)) {
            addToast({
                type: "error",
                title: "Invalid Email",
                description: "Please provide a valid email address.",
            });
            return;
        }

        setIsProcessing(true);
        
        // Prepare checkout data with proper typing
        const checkoutData = {
            email: contactInfo.email,
            shipping_address: shippingAddress,
            order_notes: orderNotes,
            coupon_code: couponCode
        };

        if (auth?.user) {
            // User is authenticated, proceed to cart checkout using Cashier
            router.post('/checkout/cart', checkoutData, {
                onSuccess: (page: any) => {
                    // Redirect to Stripe checkout will be handled by the backend
                    if (page.props?.url) {
                        window.location.href = page.props.url;
                    }
                },
                onError: (errors) => {
                    console.error('Checkout errors:', errors);
                    addToast({
                        type: "error",
                        title: "Checkout Error",
                        description: "There was an error processing your checkout. Please try again.",
                    });
                    setIsProcessing(false);
                },
                onFinish: () => {
                    setIsProcessing(false);
                }
            });
        } else {
            // User is not authenticated, redirect to guest cart checkout
            router.post('/guest/checkout/cart', checkoutData, {
                onSuccess: (page: any) => {
                    // Redirect to Stripe checkout will be handled by the backend
                    if (page.props?.url) {
                        window.location.href = page.props.url;
                    }
                },
                onError: (errors) => {
                    console.error('Checkout errors:', errors);
                    addToast({
                        type: "error",
                        title: "Checkout Error",
                        description: "There was an error processing your checkout. Please try again.",
                    });
                    setIsProcessing(false);
                },
                onFinish: () => {
                    setIsProcessing(false);
                }
            });
        }
    };

    if (!cartItems || cartItems.length === 0) {
        return (
            <>
                <Head title="Checkout" />
                
                <div className="min-h-screen bg-white">
                    <Header />
                    
                    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                        <div className="text-center">
                            <h1 className="text-3xl font-bold text-gray-900 mb-4">
                                Your cart is empty
                            </h1>
                            <p className="text-lg text-gray-600 mb-8">
                                Add items to proceed with checkout
                            </p>
                            
                            <Button asChild size="lg" className="px-8">
                                <Link href="/products">
                                    Browse Products
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
            <Head title={`Checkout - ${totalItems} items`} />
            
            <div className={`min-h-screen bg-white ${isMobile ? 'pb-24' : ''}`}>
                <Header />
                
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Checkout Header */}
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-gray-900">
                            Checkout Details
                        </h1>
                        <p className="text-gray-600 mt-2">
                            {totalItems} {totalItems === 1 ? 'item' : 'items'} ready for checkout
                        </p>
                    </div>

                    <div className="space-y-6">
                        {/* Contact Information */}
                        <Card className="border-2 border-gray-700 bg-white shadow-md">
                            <Collapsible open={isContactOpen} onOpenChange={setIsContactOpen}>
                                <CollapsibleTrigger className="w-full">
                                    <CardHeader className="hover:bg-gray-50 transition-colors">
                                        <CardTitle className="flex items-center justify-between text-black">
                                            <div className="flex items-center gap-2">
                                                <CreditCard className="h-5 w-5" />
                                                Contact Information
                                            </div>
                                            {isContactOpen ? (
                                                <ChevronUp className="h-5 w-5" />
                                            ) : (
                                                <ChevronDown className="h-5 w-5" />
                                            )}
                                        </CardTitle>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent className="space-y-4">
                                        <div>
                                            <Label htmlFor="email" className="text-black font-medium">Email Address *</Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                value={contactInfo.email}
                                                onChange={(e) => updateContactInfo('email', e.target.value)}
                                                required
                                                placeholder="your@email.com"
                                                className="text-black bg-white border-gray-300 focus:border-gray-500 focus:ring-gray-500"
                                            />
                                            <p className="text-sm text-gray-600 mt-1">
                                                We'll send your order confirmation and receipt to this email address.
                                            </p>
                                        </div>
                                    </CardContent>
                                </CollapsibleContent>
                            </Collapsible>
                        </Card>

                        {/* Shipping Information */}
                        <Card className="border-2 border-gray-700 bg-white shadow-md">
                            <Collapsible open={isShippingOpen} onOpenChange={setIsShippingOpen}>
                                <CollapsibleTrigger className="w-full">
                                    <CardHeader className="hover:bg-gray-50 transition-colors">
                                        <CardTitle className="flex items-center justify-between text-black">
                                            <div className="flex items-center gap-2">
                                                <Truck className="h-5 w-5" />
                                                Shipping Information
                                            </div>
                                            {isShippingOpen ? (
                                                <ChevronUp className="h-5 w-5" />
                                            ) : (
                                                <ChevronDown className="h-5 w-5" />
                                            )}
                                        </CardTitle>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent className="space-y-4">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <Label htmlFor="first_name" className="text-black font-medium">First Name *</Label>
                                                <Input
                                                    id="first_name"
                                                    value={shippingAddress.first_name}
                                                    onChange={(e) => updateShippingAddress('first_name', e.target.value)}
                                                    required
                                                    className="text-black bg-white border-gray-300 focus:border-gray-500 focus:ring-gray-500"
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="last_name" className="text-black font-medium">Last Name *</Label>
                                                <Input
                                                    id="last_name"
                                                    value={shippingAddress.last_name}
                                                    onChange={(e) => updateShippingAddress('last_name', e.target.value)}
                                                    required
                                                    className="text-black bg-white border-gray-300 focus:border-gray-500 focus:ring-gray-500"
                                                />
                                            </div>
                                        </div>

                                        <div>
                                            <Label htmlFor="company" className="text-gray-600">Company (optional)</Label>
                                            <Input
                                                id="company"
                                                value={shippingAddress.company}
                                                onChange={(e) => updateShippingAddress('company', e.target.value)}
                                                className="text-black bg-white border-gray-300 focus:border-gray-500 focus:ring-gray-500"
                                            />
                                        </div>

                                        <div>
                                            <Label htmlFor="address_line_1" className="text-black font-medium">Address Line 1 *</Label>
                                            <Input
                                                id="address_line_1"
                                                value={shippingAddress.address_line_1}
                                                onChange={(e) => updateShippingAddress('address_line_1', e.target.value)}
                                                required
                                                className="text-black bg-white border-gray-300 focus:border-gray-500 focus:ring-gray-500"
                                            />
                                        </div>

                                        <div>
                                            <Label htmlFor="address_line_2" className="text-gray-600">Address Line 2 (optional)</Label>
                                            <Input
                                                id="address_line_2"
                                                value={shippingAddress.address_line_2}
                                                onChange={(e) => updateShippingAddress('address_line_2', e.target.value)}
                                                className="text-black bg-white border-gray-300 focus:border-gray-500 focus:ring-gray-500"
                                            />
                                        </div>

                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <Label htmlFor="city" className="text-black font-medium">City *</Label>
                                                <Input
                                                    id="city"
                                                    value={shippingAddress.city}
                                                    onChange={(e) => updateShippingAddress('city', e.target.value)}
                                                    required
                                                    className="text-black bg-white border-gray-300 focus:border-gray-500 focus:ring-gray-500"
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="state" className="text-black font-medium">State *</Label>
                                                <Input
                                                    id="state"
                                                    value={shippingAddress.state}
                                                    onChange={(e) => updateShippingAddress('state', e.target.value)}
                                                    required
                                                    className="text-black bg-white border-gray-300 focus:border-gray-500 focus:ring-gray-500"
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="postal_code" className="text-black font-medium">Postal Code *</Label>
                                                <Input
                                                    id="postal_code"
                                                    value={shippingAddress.postal_code}
                                                    onChange={(e) => updateShippingAddress('postal_code', e.target.value)}
                                                    required
                                                    className="text-black bg-white border-gray-300 focus:border-gray-500 focus:ring-gray-500"
                                                />
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <Label htmlFor="country" className="text-black font-medium">Country *</Label>
                                                <Select value={shippingAddress.country} onValueChange={(value) => updateShippingAddress('country', value)}>
                                                    <SelectTrigger className="bg-white text-black border-gray-300 focus:border-gray-500 focus:ring-gray-500">
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
                                                <Label htmlFor="phone" className="text-gray-600">Phone</Label>
                                                <Input
                                                    id="phone"
                                                    value={shippingAddress.phone}
                                                    onChange={(e) => updateShippingAddress('phone', e.target.value)}
                                                    className="text-black bg-white border-gray-300 focus:border-gray-500 focus:ring-gray-500"
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
                                    <CardHeader className="hover:bg-gray-50 transition-colors">
                                        <CardTitle className="flex items-center justify-between text-black">
                                            <div className="flex items-center gap-2">
                                                <Tag className="h-5 w-5" />
                                                Discount Code
                                            </div>
                                            {isDiscountOpen ? (
                                                <ChevronUp className="h-5 w-5" />
                                            ) : (
                                                <ChevronDown className="h-5 w-5" />
                                            )}
                                        </CardTitle>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent>
                                        <div className="flex space-x-2">
                                            <Input
                                                type="text"
                                                placeholder="Enter discount code"
                                                value={couponCode}
                                                onChange={(e) => setCouponCode(e.target.value)}
                                                className="flex-1 text-black bg-white border-gray-300 focus:border-gray-500 focus:ring-gray-500"
                                            />
                                            <Button
                                                variant="outline"
                                                onClick={applyCoupon}
                                                disabled={!couponCode.trim() || isApplyingCoupon}
                                                className="border-gray-300 hover:bg-gray-50"
                                            >
                                                {isApplyingCoupon ? 'Applying...' : 'Apply'}
                                            </Button>
                                        </div>
                                        {discountAmount > 0 && (
                                            <div className="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                                <p className="text-sm text-green-800">
                                                    Discount applied: -${discountAmount.toFixed(2)}
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
                                    <CardHeader className="hover:bg-gray-50 transition-colors">
                                        <CardTitle className="flex items-center justify-between text-black">
                                            <div className="flex items-center gap-2">
                                                <FileText className="h-5 w-5" />
                                                Order Notes (Optional)
                                            </div>
                                            {isNotesOpen ? (
                                                <ChevronUp className="h-5 w-5" />
                                            ) : (
                                                <ChevronDown className="h-5 w-5" />
                                            )}
                                        </CardTitle>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent>
                                        <Textarea
                                            placeholder="Special instructions for your order..."
                                            value={orderNotes}
                                            onChange={(e) => setOrderNotes(e.target.value)}
                                            maxLength={200}
                                            className="resize-none bg-white text-black border-gray-300 focus:border-gray-500 focus:ring-gray-500"
                                            rows={3}
                                        />
                                        <p className="text-xs text-gray-500 mt-1">
                                            {orderNotes.length}/200 characters
                                        </p>
                                    </CardContent>
                                </CollapsibleContent>
                            </Collapsible>
                        </Card>

                        {/* Order Total */}
                        <Card className="border-2 border-gray-700 bg-white shadow-md">
                            <Collapsible open={isTotalsOpen} onOpenChange={setIsTotalsOpen}>
                                <CollapsibleTrigger className="w-full">
                                    <CardHeader className="hover:bg-gray-50 transition-colors">
                                        <CardTitle className="flex items-center justify-between text-black">
                                            <div className="flex items-center gap-2">
                                                <Calculator className="h-5 w-5" />
                                                Order Summary
                                            </div>
                                            {isTotalsOpen ? (
                                                <ChevronUp className="h-5 w-5" />
                                            ) : (
                                                <ChevronDown className="h-5 w-5" />
                                            )}
                                        </CardTitle>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent className="space-y-3">
                                        <div className="flex justify-between text-gray-600">
                                            <span>Subtotal ({totalItems} items)</span>
                                            <span className="text-black font-medium">${subtotal.toFixed(2)}</span>
                                        </div>
                                        <div className="flex justify-between text-gray-600">
                                            <span>Shipping</span>
                                            <span className="text-black font-medium">
                                                {shippingCost > 0 ? `$${shippingCost.toFixed(2)}` : 'Free'}
                                            </span>
                                        </div>
                                        <div className="flex justify-between text-gray-600">
                                            <span>Tax</span>
                                            <span className="text-black font-medium">${taxAmount.toFixed(2)}</span>
                                        </div>
                                        {discountAmount > 0 && (
                                            <div className="flex justify-between text-green-600">
                                                <span>Discount</span>
                                                <span className="font-medium">-${discountAmount.toFixed(2)}</span>
                                            </div>
                                        )}
                                        <div className="border-t border-gray-200 pt-3">
                                            <div className="flex justify-between text-lg font-semibold text-black">
                                                <span>Total</span>
                                                <span>${total.toFixed(2)}</span>
                                            </div>
                                        </div>
                                    </CardContent>
                                </CollapsibleContent>
                            </Collapsible>
                        </Card>

                        {/* Action Buttons - Hidden on mobile, only show on desktop */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-6 hidden md:grid">
                            <Button
                                variant="outline"
                                onClick={handleBackToCart}
                                size="lg"
                                className="w-full border-gray-300 hover:bg-gray-50"
                            >
                                <ArrowLeft className="w-5 h-5 mr-2" />
                                Back to Cart
                            </Button>
                            <Button
                                onClick={proceedToPayment}
                                disabled={isProcessing}
                                size="lg"
                                className="w-full bg-black text-white hover:bg-gray-800"
                            >
                                <CreditCard className="w-5 h-5 mr-2" />
                                {isProcessing ? 'Processing...' : `Pay $${total.toFixed(2)}`}
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Mobile Sticky Footer */}
                {isMobile && (
                    <div className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-300 p-4 z-50 shadow-lg">
                        <div className="grid grid-cols-2 gap-4">
                            <Button
                                variant="outline"
                                onClick={handleBackToCart}
                                className="h-12 border-gray-300 hover:bg-gray-50"
                            >
                                <ArrowLeft className="w-5 h-5 mr-2" />
                                Back
                            </Button>
                            <Button
                                onClick={proceedToPayment}
                                disabled={isProcessing}
                                className="h-12 bg-black text-white hover:bg-gray-800 font-semibold"
                            >
                                <CreditCard className="w-5 h-5 mr-2" />
                                {isProcessing ? 'Processing...' : `Pay $${total.toFixed(2)}`}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import Header from '@/components/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';
// Badge component not used in this file
import { useToast } from '@/contexts/ToastContext';
import { CreditCard, Truck, ShoppingBag, AlertCircle } from 'lucide-react';

interface CartItem {
    id: number;
    product: {
        id: number;
        name: string;
        price: number;
        image_url?: string;
        sku?: string;
    };
    size?: {
        id: number;
        name: string;
    };
    quantity: number;
}

interface Address {
    first_name: string;
    last_name: string;
    email: string;
    phone?: string;
    company?: string;
    address_line_1: string;
    address_line_2?: string;
    city: string;
    state: string;
    postal_code: string;
    country: string;
}

interface CheckoutFormData extends Record<string, unknown> {
    billing_address: Address;
    shipping_address: Address;
    shipping_same_as_billing: boolean;
    payment_method_id?: string;
    save_payment_method: boolean;
    promotion_code?: string;
    allow_promotion_codes: boolean;
    collect_tax_id: boolean;
    tax_id_type?: string;
    tax_id_value?: string;
    checkout_mode: 'payment' | 'subscription';
    locale: string;
}

interface Props {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
        };
    };
    cartItems: CartItem[];
    totals: {
        subtotal: number;
        tax_amount: number;
        tax_rate: number;
        shipping_cost: number;
        total: number;
        total_quantity: number;
    };
    addresses: Address[];
    paymentMethods: Record<string, unknown>[];
    stripeKey: string;
    errors?: Record<string, string>;
}

export default function CheckoutIndex({ auth, cartItems, totals, errors }: Props) {
    const { addToast } = useToast();
    const [isProcessing, setIsProcessing] = useState(false);

    const { data, setData, post, processing } = useForm<CheckoutFormData>({
        billing_address: {
            first_name: auth.user.name.split(' ')[0] || '',
            last_name: auth.user.name.split(' ').slice(1).join(' ') || '',
            email: auth.user.email,
            phone: '',
            company: '',
            address_line_1: '',
            address_line_2: '',
            city: '',
            state: '',
            postal_code: '',
            country: 'US',
        },
        shipping_address: {
            first_name: '',
            last_name: '',
            email: '',
            phone: '',
            company: '',
            address_line_1: '',
            address_line_2: '',
            city: '',
            state: '',
            postal_code: '',
            country: 'US',
        },
        shipping_same_as_billing: true,
        payment_method_id: '',
        save_payment_method: false,
        promotion_code: '',
        allow_promotion_codes: true,
        collect_tax_id: false,
        tax_id_type: '',
        tax_id_value: '',
        checkout_mode: 'payment',
        locale: 'en',
    });

    const { showToast } = useToast();
    
    // Image handling helper
    const getImageSrc = (item: CartItem) => {
        return item.product.image_url || '/images/product.png';
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsProcessing(true);

        post(route('checkout.session'), {
            onSuccess: (response: { props?: { sessionId?: string; url?: string } }) => {
                if (response.props?.sessionId && response.props?.url) {
                    // Redirect to Stripe Checkout
                    window.location.href = response.props.url;
                } else {
                    addToast({
                        type: "error",
                        title: "Error",
                        description: "Unable to create checkout session. Please try again.",
                    });
                    setIsProcessing(false);
                }
            },
            onError: (errors) => {
                console.error('Checkout errors:', errors);
                addToast({
                    type: "error",
                    title: "Validation Error",
                    description: "Please check your information and try again.",
                });
                setIsProcessing(false);
            },
        });
    };

    const updateBillingAddress = (field: keyof Address, value: string) => {
        setData(prevData => ({
            ...prevData,
            billing_address: {
                ...prevData.billing_address,
                [field]: value,
            },
            shipping_address: prevData.shipping_same_as_billing 
                ? { ...prevData.billing_address, [field]: value }
                : prevData.shipping_address
        }));
    };

    const updateShippingAddress = (field: keyof Address, value: string) => {
        setData(prevData => ({
            ...prevData,
            shipping_address: {
                ...prevData.shipping_address,
                [field]: value,
            }
        }));
    };

    const toggleShippingSameAsBilling = (checked: boolean) => {
        setData(prevData => ({
            ...prevData,
            shipping_same_as_billing: checked,
            shipping_address: checked ? { ...prevData.billing_address } : prevData.shipping_address
        }));
    };

    if (cartItems.length === 0) {
        return (
            <>
                <Head title="Checkout" />
                <Header />
                <div className="container mx-auto px-4 py-8">
                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            Your cart is empty. <Link href={route('products')} className="underline">Continue shopping</Link>
                        </AlertDescription>
                    </Alert>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Checkout" />
            <Header />
            
            <div className="min-h-screen bg-white">
                <div className="container mx-auto px-4 py-8">
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-gray-900">Checkout</h1>
                        <p className="text-gray-600">Complete your order</p>
                    </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {/* Main Form */}
                    <div className="lg:col-span-2">
                        <form onSubmit={handleSubmit} className="space-y-8">
                            {/* Billing Address */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <CreditCard className="h-5 w-5" />
                                        Billing Address
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <Label htmlFor="billing_first_name">First Name *</Label>
                                            <Input
                                                id="billing_first_name"
                                                value={data.billing_address.first_name}
                                                onChange={(e) => updateBillingAddress('first_name', e.target.value)}
                                                required
                                            />
                                            {errors?.['billing_address.first_name'] && (
                                                <p className="text-sm text-red-500 mt-1">{errors['billing_address.first_name']}</p>
                                            )}
                                        </div>
                                        <div>
                                            <Label htmlFor="billing_last_name">Last Name *</Label>
                                            <Input
                                                id="billing_last_name"
                                                value={data.billing_address.last_name}
                                                onChange={(e) => updateBillingAddress('last_name', e.target.value)}
                                                required
                                            />
                                            {errors?.['billing_address.last_name'] && (
                                                <p className="text-sm text-red-500 mt-1">{errors['billing_address.last_name']}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div>
                                        <Label htmlFor="billing_email">Email *</Label>
                                        <Input
                                            id="billing_email"
                                            type="email"
                                            value={data.billing_address.email}
                                            onChange={(e) => updateBillingAddress('email', e.target.value)}
                                            required
                                        />
                                        {errors?.['billing_address.email'] && (
                                            <p className="text-sm text-red-500 mt-1">{errors['billing_address.email']}</p>
                                        )}
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <Label htmlFor="billing_phone">Phone</Label>
                                            <Input
                                                id="billing_phone"
                                                value={data.billing_address.phone}
                                                onChange={(e) => updateBillingAddress('phone', e.target.value)}
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="billing_company">Company</Label>
                                            <Input
                                                id="billing_company"
                                                value={data.billing_address.company}
                                                onChange={(e) => updateBillingAddress('company', e.target.value)}
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <Label htmlFor="billing_address_1">Address Line 1 *</Label>
                                        <Input
                                            id="billing_address_1"
                                            value={data.billing_address.address_line_1}
                                            onChange={(e) => updateBillingAddress('address_line_1', e.target.value)}
                                            required
                                        />
                                        {errors?.['billing_address.address_line_1'] && (
                                            <p className="text-sm text-red-500 mt-1">{errors['billing_address.address_line_1']}</p>
                                        )}
                                    </div>

                                    <div>
                                        <Label htmlFor="billing_address_2">Address Line 2</Label>
                                        <Input
                                            id="billing_address_2"
                                            value={data.billing_address.address_line_2}
                                            onChange={(e) => updateBillingAddress('address_line_2', e.target.value)}
                                        />
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <Label htmlFor="billing_city">City *</Label>
                                            <Input
                                                id="billing_city"
                                                value={data.billing_address.city}
                                                onChange={(e) => updateBillingAddress('city', e.target.value)}
                                                required
                                            />
                                            {errors?.['billing_address.city'] && (
                                                <p className="text-sm text-red-500 mt-1">{errors['billing_address.city']}</p>
                                            )}
                                        </div>
                                        <div>
                                            <Label htmlFor="billing_state">State *</Label>
                                            <Input
                                                id="billing_state"
                                                value={data.billing_address.state}
                                                onChange={(e) => updateBillingAddress('state', e.target.value)}
                                                required
                                            />
                                            {errors?.['billing_address.state'] && (
                                                <p className="text-sm text-red-500 mt-1">{errors['billing_address.state']}</p>
                                            )}
                                        </div>
                                        <div>
                                            <Label htmlFor="billing_postal">Postal Code *</Label>
                                            <Input
                                                id="billing_postal"
                                                value={data.billing_address.postal_code}
                                                onChange={(e) => updateBillingAddress('postal_code', e.target.value)}
                                                required
                                            />
                                            {errors?.['billing_address.postal_code'] && (
                                                <p className="text-sm text-red-500 mt-1">{errors['billing_address.postal_code']}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div>
                                        <Label htmlFor="billing_country">Country *</Label>
                                        <Select value={data.billing_address.country} onValueChange={(value) => updateBillingAddress('country', value)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="US">United States</SelectItem>
                                                <SelectItem value="CA">Canada</SelectItem>
                                                <SelectItem value="GB">United Kingdom</SelectItem>
                                                <SelectItem value="DE">Germany</SelectItem>
                                                <SelectItem value="FR">France</SelectItem>
                                                <SelectItem value="ES">Spain</SelectItem>
                                                <SelectItem value="IT">Italy</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Shipping Address */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Truck className="h-5 w-5" />
                                        Shipping Address
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        <div className="flex items-center space-x-2">
                                            <Checkbox
                                                id="same_as_billing"
                                                checked={data.shipping_same_as_billing}
                                                onCheckedChange={toggleShippingSameAsBilling}
                                            />
                                            <Label htmlFor="same_as_billing">
                                                Same as billing address
                                            </Label>
                                        </div>

                                        {!data.shipping_same_as_billing && (
                                            <div className="space-y-4">
                                                {/* Similar form fields as billing address */}
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <Label htmlFor="shipping_first_name">First Name *</Label>
                                                        <Input
                                                            id="shipping_first_name"
                                                            value={data.shipping_address.first_name}
                                                            onChange={(e) => updateShippingAddress('first_name', e.target.value)}
                                                            required
                                                        />
                                                    </div>
                                                    <div>
                                                        <Label htmlFor="shipping_last_name">Last Name *</Label>
                                                        <Input
                                                            id="shipping_last_name"
                                                            value={data.shipping_address.last_name}
                                                            onChange={(e) => updateShippingAddress('last_name', e.target.value)}
                                                            required
                                                        />
                                                    </div>
                                                </div>
                                                {/* Add other shipping address fields as needed */}
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Submit Button */}
                            <Button 
                                type="submit" 
                                className="w-full" 
                                size="lg"
                                disabled={processing || isProcessing}
                            >
                                {(processing || isProcessing) ? 'Processing...' : `Complete Order - $${totals.total.toFixed(2)}`}
                            </Button>
                        </form>
                    </div>

                    {/* Order Summary */}
                    <div>
                        <Card className="sticky top-8">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ShoppingBag className="h-5 w-5" />
                                    Order Summary
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Cart Items */}
                                <div className="space-y-3">
                                    {cartItems.map((item) => (
                                        <div key={item.id} className="flex items-center space-x-3">
                                            <img
                                                src={getImageSrc(item)}
                                                alt={item.product.name}
                                                className="h-12 w-12 rounded object-cover"
                                            />
                                            <div className="flex-1">
                                                <h4 className="font-medium text-sm">{item.product.name}</h4>
                                                {item.size && (
                                                    <p className="text-xs text-muted-foreground">Size: {item.size.name}</p>
                                                )}
                                                <p className="text-xs text-muted-foreground">Qty: {item.quantity}</p>
                                            </div>
                                            <p className="font-medium">${(item.product.price * item.quantity).toFixed(2)}</p>
                                        </div>
                                    ))}
                                </div>

                                <Separator />

                                {/* Totals */}
                                <div className="space-y-2">
                                    <div className="flex justify-between">
                                        <span>Subtotal</span>
                                        <span>${totals.subtotal.toFixed(2)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Shipping</span>
                                        <span>{totals.shipping_cost === 0 ? 'Free' : `$${totals.shipping_cost.toFixed(2)}`}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Tax</span>
                                        <span>${totals.tax_amount.toFixed(2)}</span>
                                    </div>
                                    <Separator />
                                    <div className="flex justify-between font-bold text-lg">
                                        <span>Total</span>
                                        <span>${totals.total.toFixed(2)}</span>
                                    </div>
                                </div>

                                <div className="text-xs text-gray-600">
                                    <p>• Secure payment powered by Stripe</p>
                                    <p>• Free shipping on orders over $100</p>
                                    <p>• 30-day return policy</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
            </div>
        </>
    );
}

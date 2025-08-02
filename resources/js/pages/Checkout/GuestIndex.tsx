import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Head } from '@inertiajs/react';

interface CartItem {
    id: string;
    product: {
        id: number;
        name: string;
        slug: string;
        price: number;
        images?: string[];
    };
    size?: {
        id: number;
        name: string;
        price: number;
    };
    quantity: number;
    price: number;
    total: number;
}



interface Totals {
    subtotal: number;
    tax_amount: number;
    shipping_cost: number;
    total: number;
}

interface Props {
    cartItems: CartItem[];
    totals: Totals;
    guestSessionId: string;
    stripeKey: string;
}

export default function GuestCheckout({ cartItems, totals, guestSessionId }: Props) {
    const [shippingSameAsBilling, setShippingSameAsBilling] = useState(true);
    const [collectTaxId, setCollectTaxId] = useState(false);
    const [marketingConsent, setMarketingConsent] = useState(false);

    const { data, setData, post, processing, errors } = useForm<{
        guest_email: string;
        guest_phone: string;
        billing_address: {
            first_name: string;
            last_name: string;
            address_line_1: string;
            address_line_2: string;
            city: string;
            state: string;
            postal_code: string;
            country: string;
        };
        shipping_same_as_billing: boolean;
        shipping_address: {
            first_name: string;
            last_name: string;
            address_line_1: string;
            address_line_2: string;
            city: string;
            state: string;
            postal_code: string;
            country: string;
        };
        payment_method_type: string;
        save_payment_info: boolean;
        promotion_code: string;
        collect_tax_id: boolean;
        tax_id: string;
        cart_session_id: string;
        accept_terms: boolean;
        marketing_consent: boolean;
    }>({
        // Guest contact information
        guest_email: '',
        guest_phone: '',
        
        // Billing address
        billing_address: {
            first_name: '',
            last_name: '',
            address_line_1: '',
            address_line_2: '',
            city: '',
            state: '',
            postal_code: '',
            country: 'US',
        },
        
        // Shipping address
        shipping_same_as_billing: true,
        shipping_address: {
            first_name: '',
            last_name: '',
            address_line_1: '',
            address_line_2: '',
            city: '',
            state: '',
            postal_code: '',
            country: 'US',
        },
        
        // Payment options
        payment_method_type: 'card',
        save_payment_info: false,
        promotion_code: '',
        
        // Tax ID collection
        collect_tax_id: false,
        tax_id: '',
        
        // Cart session
        cart_session_id: guestSessionId,
        
        // Terms and conditions
        accept_terms: false,
        marketing_consent: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        post(route('checkout.guest.session'), {
            onSuccess: (response: { props?: { checkout_url?: string } }) => {
                if (response.props?.checkout_url) {
                    window.location.href = response.props.checkout_url;
                }
            },
            onError: (errors) => {
                console.error('Guest checkout failed:', errors);
            }
        });
    };

    const updateBillingAddress = (field: string, value: string) => {
        setData('billing_address', {
            ...data.billing_address,
            [field]: value,
        });
    };

    // Function to update shipping address fields
    // const updateShippingAddress = (field: string, value: string) => {
    //     setData('shipping_address', {
    //         ...data.shipping_address,
    //         [field]: value,
    //     });
    // };

    const getImageSrc = (item: CartItem) => {
        const defaultImage = '/images/product.png';
        if (!item.product.images || item.product.images[0] === '' || item.product.images[0] === 'product.png') {
            return defaultImage;
        }
        return `/storage/${item.product.images[0]}`;
    };

    return (
        <>
            <Head title="Guest Checkout" />
            
            <div className="min-h-screen bg-gray-50 py-12">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        {/* Checkout Form */}
                        <div className="bg-white rounded-lg shadow-sm p-6">
                            <h1 className="text-2xl font-bold text-gray-900 mb-6">Guest Checkout</h1>
                            
                            <form onSubmit={handleSubmit} className="space-y-6">
                                {/* Contact Information */}
                                <div>
                                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Contact Information</h2>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label htmlFor="guest_email" className="block text-sm font-medium text-gray-700">
                                                Email Address *
                                            </label>
                                            <input
                                                type="email"
                                                id="guest_email"
                                                required
                                                value={data.guest_email}
                                                onChange={(e) => setData('guest_email', e.target.value)}
                                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            />
                                            {errors.guest_email && (
                                                <p className="mt-1 text-sm text-red-600">{errors.guest_email}</p>
                                            )}
                                        </div>
                                        <div>
                                            <label htmlFor="guest_phone" className="block text-sm font-medium text-gray-700">
                                                Phone Number
                                            </label>
                                            <input
                                                type="tel"
                                                id="guest_phone"
                                                value={data.guest_phone}
                                                onChange={(e) => setData('guest_phone', e.target.value)}
                                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            />
                                            {errors.guest_phone && (
                                                <p className="mt-1 text-sm text-red-600">{errors.guest_phone}</p>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                {/* Billing Address */}
                                <div>
                                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Billing Address</h2>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label htmlFor="billing_first_name" className="block text-sm font-medium text-gray-700">
                                                First Name *
                                            </label>
                                            <input
                                                type="text"
                                                id="billing_first_name"
                                                required
                                                value={data.billing_address.first_name}
                                                onChange={(e) => updateBillingAddress('first_name', e.target.value)}
                                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label htmlFor="billing_last_name" className="block text-sm font-medium text-gray-700">
                                                Last Name *
                                            </label>
                                            <input
                                                type="text"
                                                id="billing_last_name"
                                                required
                                                value={data.billing_address.last_name}
                                                onChange={(e) => updateBillingAddress('last_name', e.target.value)}
                                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            />
                                        </div>
                                        <div className="md:col-span-2">
                                            <label htmlFor="billing_address_1" className="block text-sm font-medium text-gray-700">
                                                Address Line 1 *
                                            </label>
                                            <input
                                                type="text"
                                                id="billing_address_1"
                                                required
                                                value={data.billing_address.address_line_1}
                                                onChange={(e) => updateBillingAddress('address_line_1', e.target.value)}
                                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            />
                                        </div>
                                        <div className="md:col-span-2">
                                            <label htmlFor="billing_address_2" className="block text-sm font-medium text-gray-700">
                                                Address Line 2
                                            </label>
                                            <input
                                                type="text"
                                                id="billing_address_2"
                                                value={data.billing_address.address_line_2}
                                                onChange={(e) => updateBillingAddress('address_line_2', e.target.value)}
                                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label htmlFor="billing_city" className="block text-sm font-medium text-gray-700">
                                                City *
                                            </label>
                                            <input
                                                type="text"
                                                id="billing_city"
                                                required
                                                value={data.billing_address.city}
                                                onChange={(e) => updateBillingAddress('city', e.target.value)}
                                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label htmlFor="billing_state" className="block text-sm font-medium text-gray-700">
                                                State *
                                            </label>
                                            <input
                                                type="text"
                                                id="billing_state"
                                                required
                                                value={data.billing_address.state}
                                                onChange={(e) => updateBillingAddress('state', e.target.value)}
                                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label htmlFor="billing_postal_code" className="block text-sm font-medium text-gray-700">
                                                Postal Code *
                                            </label>
                                            <input
                                                type="text"
                                                id="billing_postal_code"
                                                required
                                                value={data.billing_address.postal_code}
                                                onChange={(e) => updateBillingAddress('postal_code', e.target.value)}
                                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label htmlFor="billing_country" className="block text-sm font-medium text-gray-700">
                                                Country *
                                            </label>
                                            <select
                                                id="billing_country"
                                                required
                                                value={data.billing_address.country}
                                                onChange={(e) => updateBillingAddress('country', e.target.value)}
                                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            >
                                                <option value="US">United States</option>
                                                <option value="CA">Canada</option>
                                                <option value="GB">United Kingdom</option>
                                                <option value="DE">Germany</option>
                                                <option value="FR">France</option>
                                                <option value="IT">Italy</option>
                                                <option value="ES">Spain</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {/* Shipping Address Toggle */}
                                <div>
                                    <label className="flex items-center">
                                        <input
                                            type="checkbox"
                                            checked={shippingSameAsBilling}
                                            onChange={(e) => {
                                                setShippingSameAsBilling(e.target.checked);
                                                setData('shipping_same_as_billing', e.target.checked);
                                            }}
                                            className="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                        />
                                        <span className="ml-2 text-sm text-gray-700">
                                            Shipping address is the same as billing address
                                        </span>
                                    </label>
                                </div>

                                {/* Options */}
                                <div className="space-y-4">
                                    <label className="flex items-center">
                                        <input
                                            type="checkbox"
                                            checked={collectTaxId}
                                            onChange={(e) => {
                                                setCollectTaxId(e.target.checked);
                                                setData('collect_tax_id', e.target.checked);
                                            }}
                                            className="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                        />
                                        <span className="ml-2 text-sm text-gray-700">
                                            Provide tax ID for business purchases
                                        </span>
                                    </label>

                                    <label className="flex items-center">
                                        <input
                                            type="checkbox"
                                            checked={marketingConsent}
                                            onChange={(e) => {
                                                setMarketingConsent(e.target.checked);
                                                setData('marketing_consent', e.target.checked);
                                            }}
                                            className="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                        />
                                        <span className="ml-2 text-sm text-gray-700">
                                            I would like to receive marketing emails
                                        </span>
                                    </label>

                                    <label className="flex items-center">
                                        <input
                                            type="checkbox"
                                            required
                                            checked={data.accept_terms}
                                            onChange={(e) => setData('accept_terms', e.target.checked)}
                                            className="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                        />
                                        <span className="ml-2 text-sm text-gray-700">
                                            I accept the <a href="#" className="text-blue-600 hover:text-blue-500">terms and conditions</a> *
                                        </span>
                                    </label>
                                    {errors.accept_terms && (
                                        <p className="text-sm text-red-600">{errors.accept_terms}</p>
                                    )}
                                </div>

                                {/* Submit Button */}
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full bg-blue-600 border border-transparent rounded-md py-3 px-4 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {processing ? 'Processing...' : 'Continue to Payment'}
                                </button>
                            </form>
                        </div>

                        {/* Order Summary */}
                        <div className="bg-white rounded-lg shadow-sm p-6 h-fit">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Order Summary</h2>
                            
                            {/* Cart Items */}
                            <div className="space-y-4 mb-6">
                                {cartItems.map((item) => (
                                    <div key={item.id} className="flex items-start space-x-4">
                                        {item.product.images && item.product.images[0] && (
                                            <img
                                                src={getImageSrc(item)}
                                                alt={item.product.name}
                                                className="w-16 h-16 object-cover rounded-md"
                                            />
                                        )}
                                        <div className="flex-1 min-w-0">
                                            <h3 className="text-sm font-medium text-gray-900">
                                                {item.product.name}
                                            </h3>
                                            {item.size && (
                                                <p className="text-sm text-gray-500">Size: {item.size.name}</p>
                                            )}
                                            <p className="text-sm text-gray-500">Qty: {item.quantity}</p>
                                        </div>
                                        <div className="text-sm font-medium text-gray-900">
                                            ${item.total.toFixed(2)}
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Totals */}
                            <div className="border-t border-gray-200 pt-4 space-y-2">
                                <div className="flex justify-between text-sm text-gray-600">
                                    <span>Subtotal</span>
                                    <span>${totals.subtotal.toFixed(2)}</span>
                                </div>
                                <div className="flex justify-between text-sm text-gray-600">
                                    <span>Shipping</span>
                                    <span>${totals.shipping_cost.toFixed(2)}</span>
                                </div>
                                <div className="flex justify-between text-sm text-gray-600">
                                    <span>Tax</span>
                                    <span>${totals.tax_amount.toFixed(2)}</span>
                                </div>
                                <div className="flex justify-between text-base font-medium text-gray-900 border-t border-gray-200 pt-2">
                                    <span>Total</span>
                                    <span>${totals.total.toFixed(2)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

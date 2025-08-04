import React from 'react';
import { Head, Link } from '@inertiajs/react';
import Header from '@/components/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { CheckCircle, Package, Truck, CreditCard, User, Mail } from 'lucide-react';

interface OrderItem {
    id: number | string;
    product: {
        id: number | string;
        name: string;
        price: number;
        image_url?: string;
    };
    size?: {
        id: number;
        name: string;
    } | null;
    quantity: number;
    price: number;
    total: number;
}

interface Order {
    id: number | string;
    total_amount: number;
    status: string;
    payment_status: string;
    subtotal: number;
    tax_amount: number;
    shipping_cost: number;
    currency?: string;
    created_at: string;
    orderItems: OrderItem[];
}

interface Session {
    id: string;
    payment_status: string;
    customer_email: string;
    amount_total?: number;
    currency?: string;
    created?: number;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface Props {
    order: Order;
    session: Session;
    isGuest: boolean;
    user?: User;
}

export default function CheckoutSuccess({ order, session, isGuest, user }: Props) {
    return (
        <>
            <Head title="Order Confirmation" />
            <Header />
            
            <div className="min-h-screen bg-white">
                <div className="container mx-auto px-4 py-8 max-w-4xl">
                    {/* Success Header */}
                    <div className="text-center mb-8">
                        <div className="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                            <CheckCircle className="h-8 w-8 text-green-600" />
                        </div>
                        <h1 className="text-3xl font-bold text-gray-900 mb-2">Order Confirmed!</h1>
                        <p className="text-gray-600">
                            {isGuest 
                                ? 'Thank you for your purchase. Your order has been successfully placed.'
                                : `Thank you, ${user?.name}! Your order has been successfully placed.`
                            }
                        </p>
                    </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {/* Order Details */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Order Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Package className="h-5 w-5" />
                                    Order Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div className="flex justify-between items-center">
                                        <span className="text-sm text-gray-600">Order Number</span>
                                        <span className="font-mono text-sm">
                                            {order.id === 'pending' 
                                                ? 'Processing...' 
                                                : `#${order.id.toString().padStart(6, '0')}`
                                            }
                                        </span>
                                    </div>
                                    <div className="flex justify-between items-center">
                                        <span className="text-sm text-gray-600">Order Date</span>
                                        <span className="text-sm">
                                            {new Date(order.created_at).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit'
                                            })}
                                        </span>
                                    </div>
                                    <div className="flex justify-between items-center">
                                        <span className="text-sm text-gray-600">Payment Status</span>
                                        <Badge variant={session.payment_status === 'paid' ? 'default' : 'secondary'}>
                                            {session.payment_status}
                                        </Badge>
                                    </div>
                                    <div className="flex justify-between items-center">
                                        <span className="text-sm text-gray-600">
                                            {isGuest ? 'Email' : 'Account'}
                                        </span>
                                        <div className="flex items-center gap-2">
                                            {isGuest ? (
                                                <>
                                                    <Mail className="h-4 w-4 text-gray-400" />
                                                    <span className="text-sm">{session.customer_email}</span>
                                                </>
                                            ) : (
                                                <>
                                                    <User className="h-4 w-4 text-gray-400" />
                                                    <span className="text-sm">{user?.email}</span>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                    
                                    {/* Guest Order Notice */}
                                    {isGuest && (
                                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                            <p className="text-sm text-blue-800">
                                                <strong>Guest Order:</strong> You can track this order using the order number above. 
                                                Consider <Link href={route('register')} className="underline">creating an account</Link> for easier order management.
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Order Items */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Items Ordered</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {order.orderItems && order.orderItems.length > 0 ? (
                                        order.orderItems.map((item, index) => (
                                            <div key={item.id || index} className="flex items-center space-x-4 py-4 border-b last:border-b-0">
                                                {item.product.image_url && (
                                                    <img
                                                        src={item.product.image_url}
                                                        alt={item.product.name}
                                                        className="h-16 w-16 rounded-lg object-cover"
                                                    />
                                                )}
                                                <div className="flex-1">
                                                    <h4 className="font-medium">{item.product.name}</h4>
                                                    {item.size && (
                                                        <p className="text-sm text-gray-600">Size: {item.size.name}</p>
                                                    )}
                                                    <p className="text-sm text-gray-600">Qty: {item.quantity}</p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="font-medium">${item.total.toFixed(2)}</p>
                                                    <p className="text-sm text-gray-600">${item.price.toFixed(2)} each</p>
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        <div className="text-center py-8 text-gray-500">
                                            <Package className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                                            <p>Order items are being processed...</p>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Next Steps */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Truck className="h-5 w-5" />
                                    What's Next?
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    <div className="flex items-start gap-3">
                                        <div className="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                            <span className="text-xs font-medium text-blue-600">1</span>
                                        </div>
                                        <div>
                                            <p className="font-medium">Order Confirmation</p>
                                            <p className="text-sm text-gray-600">
                                                You'll receive a confirmation email shortly with your order details.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <div className="w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                            <span className="text-xs font-medium text-gray-600">2</span>
                                        </div>
                                        <div>
                                            <p className="font-medium">Processing</p>
                                            <p className="text-sm text-gray-600">
                                                We'll prepare your order for shipment within 1-2 business days.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <div className="w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                            <span className="text-xs font-medium text-gray-600">3</span>
                                        </div>
                                        <div>
                                            <p className="font-medium">Shipping</p>
                                            <p className="text-sm text-gray-600">
                                                You'll receive tracking information once your order ships.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Order Summary Sidebar */}
                    <div>
                        <Card className="sticky top-8">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CreditCard className="h-5 w-5" />
                                    Order Summary
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Totals */}
                                <div className="space-y-2">
                                    <div className="flex justify-between">
                                        <span className="text-sm">Subtotal</span>
                                        <span className="text-sm">${(order.subtotal || 0).toFixed(2)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-sm">Shipping</span>
                                        <span className="text-sm">
                                            {(order.shipping_cost || 0) === 0 ? 'Free' : `$${(order.shipping_cost || 0).toFixed(2)}`}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-sm">Tax</span>
                                        <span className="text-sm">${(order.tax_amount || 0).toFixed(2)}</span>
                                    </div>
                                    <Separator />
                                    <div className="flex justify-between font-bold text-lg">
                                        <span>Total</span>
                                        <span>
                                            {order.currency && order.currency !== 'USD' 
                                                ? `${(order.total_amount || 0).toFixed(2)} ${order.currency.toUpperCase()}`
                                                : `$${(order.total_amount || 0).toFixed(2)}`
                                            }
                                        </span>
                                    </div>
                                </div>

                                <Separator />

                                {/* Actions */}
                                <div className="space-y-3">
                                    <Button asChild className="w-full">
                                        <Link href={route('products')}>
                                            Continue Shopping
                                        </Link>
                                    </Button>
                                    
                                    {/* Different action based on user type */}
                                    {isGuest ? (
                                        <Button variant="outline" asChild className="w-full">
                                            <Link href={route('register')}>
                                                Create Account
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button variant="outline" asChild className="w-full">
                                            <Link href={route('dashboard')}>
                                                View Order History
                                            </Link>
                                        </Button>
                                    )}
                                </div>

                                {/* Support */}
                                <div className="pt-4 border-t">
                                    <p className="text-xs text-gray-600 text-center">
                                        Need help? <Link href={route('contact')} className="underline">Contact Support</Link>
                                    </p>
                                    {isGuest && (
                                        <p className="text-xs text-gray-500 text-center mt-2">
                                            Save your order number for future reference
                                        </p>
                                    )}
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

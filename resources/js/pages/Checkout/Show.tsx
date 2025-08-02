import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { AlertCircle, CreditCard, Clock, CheckCircle, XCircle } from 'lucide-react';

interface OrderItem {
    id: number;
    product: {
        id: number;
        name: string;
        price: number;
        image_url?: string;
    };
    size?: {
        id: number;
        name: string;
    };
    quantity: number;
    price: number;
    total: number;
}

interface Order {
    id: number;
    total_amount: number;
    status: string;
    payment_status: string;
    subtotal: number;
    tax_amount: number;
    shipping_cost: number;
    created_at: string;
    orderItems: OrderItem[];
}

interface Session {
    id: string;
    status: string;
    payment_status: string;
    customer_email?: string;
}

interface Props {
    session: Session;
    order?: Order;
}

export default function CheckoutShow({ session, order }: Props) {
    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'complete':
                return <CheckCircle className="h-5 w-5 text-green-600" />;
            case 'expired':
                return <XCircle className="h-5 w-5 text-red-600" />;
            case 'open':
                return <Clock className="h-5 w-5 text-blue-600" />;
            default:
                return <AlertCircle className="h-5 w-5 text-gray-600" />;
        }
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'complete':
                return 'bg-green-100 text-green-800';
            case 'expired':
                return 'bg-red-100 text-red-800';
            case 'open':
                return 'bg-blue-100 text-blue-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    const getPaymentStatusColor = (status: string) => {
        switch (status) {
            case 'paid':
                return 'bg-green-100 text-green-800';
            case 'unpaid':
                return 'bg-yellow-100 text-yellow-800';
            case 'no_payment_required':
                return 'bg-gray-100 text-gray-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    return (
        <AppLayout>
            <Head title="Checkout Session" />
            
            <div className="container mx-auto px-4 py-8 max-w-4xl">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-gray-900 mb-2">Checkout Session</h1>
                    <p className="text-gray-600">
                        Session ID: <span className="font-mono text-sm">{session.id}</span>
                    </p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {/* Session Details */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Session Status */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CreditCard className="h-5 w-5" />
                                    Session Status
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div className="flex justify-between items-center">
                                        <span className="text-sm text-gray-600">Session Status</span>
                                        <div className="flex items-center gap-2">
                                            {getStatusIcon(session.status)}
                                            <Badge className={getStatusColor(session.status)}>
                                                {session.status}
                                            </Badge>
                                        </div>
                                    </div>
                                    
                                    <div className="flex justify-between items-center">
                                        <span className="text-sm text-gray-600">Payment Status</span>
                                        <Badge className={getPaymentStatusColor(session.payment_status)}>
                                            {session.payment_status}
                                        </Badge>
                                    </div>

                                    {session.customer_email && (
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Customer Email</span>
                                            <span className="text-sm">{session.customer_email}</span>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Order Details (if available) */}
                        {order && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Order Details</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Order Number</span>
                                            <span className="font-mono text-sm">#{order.id.toString().padStart(6, '0')}</span>
                                        </div>

                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Order Status</span>
                                            <Badge variant="secondary">{order.status}</Badge>
                                        </div>

                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Payment Status</span>
                                            <Badge variant="secondary">{order.payment_status}</Badge>
                                        </div>

                                        <Separator />

                                        {/* Order Items */}
                                        <div className="space-y-3">
                                            <h4 className="font-medium">Items</h4>
                                            {order.orderItems.map((item) => (
                                                <div key={item.id} className="flex items-center space-x-3 py-2">
                                                    {item.product.image_url && (
                                                        <img
                                                            src={item.product.image_url}
                                                            alt={item.product.name}
                                                            className="h-12 w-12 rounded object-cover"
                                                        />
                                                    )}
                                                    <div className="flex-1">
                                                        <h5 className="font-medium text-sm">{item.product.name}</h5>
                                                        {item.size && (
                                                            <p className="text-xs text-gray-600">Size: {item.size.name}</p>
                                                        )}
                                                        <p className="text-xs text-gray-600">Qty: {item.quantity}</p>
                                                    </div>
                                                    <p className="font-medium">${item.total.toFixed(2)}</p>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Status-based Actions */}
                        {session.status === 'open' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Action Required</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        <p className="text-sm text-gray-600">
                                            This checkout session is still open. You can complete your payment or cancel the session.
                                        </p>
                                        <div className="flex gap-4">
                                            <Button asChild>
                                                <Link href={route('checkout')}>
                                                    Complete Payment
                                                </Link>
                                            </Button>
                                            <Button variant="outline" asChild>
                                                <Link href={route('checkout.cancel', { session_id: session.id })}>
                                                    Cancel Session
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {session.status === 'expired' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Session Expired</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        <p className="text-sm text-gray-600">
                                            This checkout session has expired. You can start a new checkout process.
                                        </p>
                                        <Button asChild>
                                            <Link href={route('checkout')}>
                                                Start New Checkout
                                            </Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Order Summary Sidebar (if order exists) */}
                    {order && (
                        <div>
                            <Card className="sticky top-8">
                                <CardHeader>
                                    <CardTitle>Order Summary</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-2">
                                        <div className="flex justify-between">
                                            <span className="text-sm">Subtotal</span>
                                            <span className="text-sm">${order.subtotal.toFixed(2)}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-sm">Shipping</span>
                                            <span className="text-sm">
                                                {order.shipping_cost === 0 ? 'Free' : `$${order.shipping_cost.toFixed(2)}`}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-sm">Tax</span>
                                            <span className="text-sm">${order.tax_amount.toFixed(2)}</span>
                                        </div>
                                        <Separator />
                                        <div className="flex justify-between font-bold text-lg">
                                            <span>Total</span>
                                            <span>${order.total_amount.toFixed(2)}</span>
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="space-y-3">
                                        <Button variant="outline" asChild className="w-full">
                                            <Link href={route('dashboard')}>
                                                View Orders
                                            </Link>
                                        </Button>
                                        <Button variant="ghost" asChild className="w-full">
                                            <Link href={route('products')}>
                                                Continue Shopping
                                            </Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    {/* Navigation Sidebar (if no order) */}
                    {!order && (
                        <div>
                            <Card className="sticky top-8">
                                <CardHeader>
                                    <CardTitle>Navigation</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <Button asChild className="w-full">
                                        <Link href={route('cart')}>
                                            View Cart
                                        </Link>
                                    </Button>
                                    <Button variant="outline" asChild className="w-full">
                                        <Link href={route('products')}>
                                            Continue Shopping
                                        </Link>
                                    </Button>
                                    <Button variant="ghost" asChild className="w-full">
                                        <Link href={route('dashboard')}>
                                            Dashboard
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

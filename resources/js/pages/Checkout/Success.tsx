import React from 'react';
import { Head, Link } from '@inertiajs/react';
import Header from '@/components/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { CheckCircle, Package, Truck, CreditCard, User, Mail } from 'lucide-react';
import useTranslation from '@/hooks/useTranslation';
import LoadingOverlay from '@/components/LoadingOverlay';

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
    const { t } = useTranslation();
    
    // Add loading state for when order is still being processed
    const isLoading = order.id === 'pending';
    
    // Image handling helper
    const getImageSrc = (item: OrderItem) => {
        return item.product.image_url || '/images/product.png';
    };

    return (
        <>
            <Head title="Order Confirmation" />
            <Header />
            
            <LoadingOverlay isLoading={isLoading}>
                <div className="min-h-screen bg-white">
                <div className="container mx-auto px-4 py-8 max-w-4xl">
                    {/* Success Header */}
                    <div className="text-center mb-8">
                        <div className="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                            <CheckCircle className="h-8 w-8 text-green-600" />
                        </div>
                        <h1 className="text-3xl font-bold text-gray-900 mb-2">{t('checkout.success.title')}</h1>
                        <p className="text-gray-600">
                            {isGuest 
                                ? t('checkout.success.thank_you_guest')
                                : t('checkout.success.thank_you_user', { name: user?.name || '' })
                            }
                        </p>
                    </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {/* Order Details */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Order Information */}
                        <Card className="bg-white">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-gray-800">
                                    <Package className="h-5 w-5" />
                                    {t('checkout.success.order_details')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div className="flex justify-between items-center">
                                        <span className="text-sm text-gray-600">{t('checkout.success.order_number')}</span>
                                        <span className="font-mono text-sm text-black">
                                            {order.id === 'pending' 
                                                ? 'Processing...' 
                                                : `#${order.id.toString().padStart(6, '0')}`
                                            }
                                        </span>
                                    </div>
                                    <div className="flex justify-between items-center">
                                        <span className="text-sm text-gray-600">{t('checkout.success.order_date')}</span>
                                        <span className="text-sm text-black">
                                            {new Date(order.created_at).toLocaleDateString('it-IT', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit'
                                            })}
                                        </span>
                                    </div>
                                    <div className="flex justify-between items-center">
                                        <span className="text-sm text-gray-600">{t('checkout.success.payment_status')}</span>
                                        <Badge variant={session.payment_status === 'paid' ? 'default' : 'secondary'}>
                                            {session.payment_status}
                                        </Badge>
                                    </div>
                                    <div className="flex justify-between items-center">
                                        <span className="text-sm text-gray-600">
                                            {isGuest ? t('checkout.success.email') : t('checkout.success.account')}
                                        </span>
                                        <div className="flex items-center gap-2">
                                            {isGuest ? (
                                                <>
                                                    <Mail className="h-4 w-4 text-gray-400" />
                                                    <span className="text-sm text-black">{session.customer_email}</span>
                                                </>
                                            ) : (
                                                <>
                                                    <User className="h-4 w-4 text-gray-400" />
                                                    <span className="text-sm text-black">{user?.email}</span>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                    
                                    {/* Guest Order Notice */}
                                    {isGuest && (
                                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                            <p className="text-sm text-blue-800">
                                                {t('checkout.success.guest_order_notice')} {' '}
                                                <Link href={route('register')} className="underline">{t('checkout.success.creating_account')}</Link> for easier order management.
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Order Items */}
                        <Card className='bg-white shadow-sm'>
                            <CardHeader className='text-black'>
                                <CardTitle>{t('checkout.success.items_ordered')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4 text-black">
                                    {order.orderItems && order.orderItems.length > 0 ? (
                                        order.orderItems.map((item, index) => (
                                            <div key={item.id || index} className="flex items-center space-x-4 py-4 border-b last:border-b-0">
                                                <img
                                                    src={getImageSrc(item)}
                                                    alt={item.product.name}
                                                    className="h-16 w-16 rounded-lg object-cover"
                                                />
                                                <div className="flex-1">
                                                    <h4 className="font-medium">{item.product.name}</h4>
                                                    {item.size && (
                                                        <p className="text-sm text-gray-600">{t('checkout.success.size')}: {item.size.name}</p>
                                                    )}
                                                    <p className="text-sm text-gray-600">{t('checkout.success.qty')}: {item.quantity}</p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="font-medium">€{(item.quantity * item.price).toFixed(2)}</p>
                                                    {item.quantity > 1 && <p className="text-sm text-gray-600">€{item.price.toFixed(2)} {t('checkout.success.each')}</p>}
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        <div className="text-center py-8 text-gray-500">
                                            <Package className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                                            <p>{t('checkout.success.processing_items')}</p>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Next Steps */}
                        <Card className='bg-white shadow-sm'>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-black">
                                    <Truck className="h-5 w-5" />
                                    {t('checkout.success.whats_next')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    <div className="flex items-start gap-3">
                                        <div className="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                            <span className="text-xs font-medium text-blue-600">1</span>
                                        </div>
                                        <div>
                                            <p className="font-medium text-gray-700">{t('checkout.success.step_1_title')}</p>
                                            <p className="text-sm text-gray-600">
                                                {t('checkout.success.step_1_desc')}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <div className="w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                            <span className="text-xs font-medium text-gray-600">2</span>
                                        </div>
                                        <div>
                                            <p className="font-medium text-gray-700">{t('checkout.success.step_2_title')}</p>
                                            <p className="text-sm text-gray-600">
                                                {t('checkout.success.step_2_desc')}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <div className="w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                            <span className="text-xs font-medium text-gray-600">3</span>
                                        </div>
                                        <div>
                                            <p className="font-medium text-gray-700">{t('checkout.success.step_3_title')}</p>
                                            <p className="text-sm text-gray-600">
                                                {t('checkout.success.step_3_desc')}
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
                                    {t('checkout.success.order_summary')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Totals */}
                                <div className="space-y-2">
                                    <div className="flex justify-between">
                                        <span className="text-sm">{t('checkout.success.subtotal')}</span>
                                        <span className="text-sm">€{((order.subtotal || 0) + (order.tax_amount || 0)).toFixed(2)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-sm">{t('checkout.success.shipping')}</span>
                                        <span className="text-sm">
                                            {(order.shipping_cost || 0) === 0 ? t('checkout.success.free') : `€${(order.shipping_cost || 0).toFixed(2)}`}
                                        </span>
                                    </div>
                                    <Separator />
                                    <div className="flex justify-between font-bold text-lg">
                                        <span>{t('checkout.success.total')}</span>
                                        <span>€{(order.total_amount || 0).toFixed(2)}</span>
                                    </div>
                                </div>

                                <Separator />

                                {/* Actions */}
                                <div className="space-y-3">
                                    <Button asChild className="w-full">
                                        <Link href={route('products')}>
                                            {t('checkout.success.continue_shopping')}
                                        </Link>
                                    </Button>
                                    
                                    {/* Different action based on user type */}
                                    {isGuest ? (
                                        <Button variant="outline" asChild className="w-full">
                                            <Link href={route('register')}>
                                                {t('checkout.success.create_account')}
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button variant="outline" asChild className="w-full">
                                            <Link href={route('dashboard')}>
                                                {t('checkout.success.view_order_history')}
                                            </Link>
                                        </Button>
                                    )}
                                </div>

                                {/* Support */}
                                <div className="pt-4 border-t">
                                    <p className="text-xs text-gray-300 text-center">
                                        {t('checkout.success.need_help')} <Link href={route('contact')} className="underline">Contact Support</Link>
                                    </p>
                                    {isGuest && (
                                        <p className="text-xs text-gray-400 text-center mt-2">
                                            {t('checkout.success.save_order_number')}
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
                </div>
            </div>
            </LoadingOverlay>
        </>
    );
}

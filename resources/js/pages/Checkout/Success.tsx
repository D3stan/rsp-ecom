import CreateAccountOffer from '@/components/checkout/CreateAccountOffer';
import PendingOrderNotice from '@/components/checkout/PendingOrderNotice';
import Header from '@/components/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { formatRate } from '@/hooks/use-money';
import { usePolicyValues } from '@/hooks/use-policy-values';
import useTranslation from '@/hooks/useTranslation';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle, CreditCard, Mail, Package, Truck, User } from 'lucide-react';

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
    variant?: {
        id: number;
        name: string;
    } | null;
    quantity: number;
    price: number;
    total: number;
}

interface Order {
    id: number | string;
    order_number?: string;
    total_amount: number;
    status: string;
    payment_status: string;
    subtotal: number;
    tax_amount: number;
    tax_rate?: number | null;
    shipping_amount?: number;
    shipping_cost?: number;
    discount_amount?: number;
    currency?: string;
    created_at: string;
    orderItems: OrderItem[];
}

interface Session {
    id: string;
    payment_status: string;
    customer_email: string;
    customer_name?: string;
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
    restricted?: boolean;
    user?: User;
}

export default function CheckoutSuccess({ order, session, isGuest, restricted = false, user }: Props) {
    const { t, locale } = useTranslation();
    const { dispatch } = usePolicyValues();

    const isPending = order.id === 'pending';

    const formatMoney = (value: number) => {
        const currency = (order.currency || session.currency || 'EUR').toUpperCase();
        try {
            return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(value);
        } catch {
            return `${value.toFixed(2)} ${currency}`;
        }
    };

    const formatOrderDate = (iso: string) => {
        try {
            return new Date(iso).toLocaleDateString(locale, {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch {
            return new Date(iso).toLocaleDateString(locale);
        }
    };

    const shippingAmount = order.shipping_amount ?? order.shipping_cost ?? 0;
    const discountAmount = order.discount_amount ?? 0;
    const hasVatBreakdown = order.tax_rate !== null && order.tax_rate !== undefined;
    const subtotalAmount = hasVatBreakdown ? order.subtotal || 0 : (order.subtotal || 0) + (order.tax_amount || 0);
    const customerEmail = isGuest ? session.customer_email : user?.email;
    const showInboxNotice = isGuest || restricted;

    const getImageSrc = (item: OrderItem) => {
        return item.product.image_url || '/images/product.png';
    };

    if (isPending) {
        return (
            <>
                <Head title={t('checkout.success.head_title')} />
                <Header />
                <PendingOrderNotice total={formatMoney(session.amount_total ?? order.total_amount ?? 0)} reference={session.id} />
            </>
        );
    }

    return (
        <>
            <Head title={t('checkout.success.head_title')} />
            <Header />

            <>
                <div className="min-h-screen bg-white">
                    <div className="container mx-auto max-w-4xl px-4 py-8">

                        <div className="mb-8 text-center">
                            <div className="success-pop mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                                <CheckCircle className="h-8 w-8 text-green-600" />
                            </div>
                            <h1 className="success-rise mb-2 text-3xl font-bold text-gray-900" style={{ animationDelay: '120ms' }}>
                                {t('checkout.success.title')}
                            </h1>
                            <p className="success-rise text-gray-600" style={{ animationDelay: '180ms' }}>
                                {isGuest ? t('checkout.success.thank_you_guest') : t('checkout.success.thank_you_user', { name: user?.name || '' })}
                            </p>
                        </div>

                        <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">

                            <div className="space-y-6 lg:col-span-2">

                                <Card className="bg-white">
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-gray-800">
                                            <Package className="h-5 w-5" />
                                            {t('checkout.success.order_details')}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4">
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-600">{t('checkout.success.order_number')}</span>
                                                <span className="font-mono text-sm text-black">
                                                    {!order.order_number ? t('checkout.success.processing') : order.order_number}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-600">{t('checkout.success.order_date')}</span>
                                                <span className="text-sm text-black">{formatOrderDate(order.created_at)}</span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-600">{t('checkout.success.payment_status')}</span>
                                                <Badge variant={session.payment_status === 'paid' ? 'default' : 'secondary'}>
                                                    {session.payment_status}
                                                </Badge>
                                            </div>
                                            {customerEmail && (
                                                <div className="flex items-center justify-between">
                                                    <span className="text-sm text-gray-600">
                                                        {isGuest ? t('checkout.success.email') : t('checkout.success.account')}
                                                    </span>
                                                    <div className="flex items-center gap-2">
                                                        {isGuest ? (
                                                            <Mail className="h-4 w-4 text-gray-400" />
                                                        ) : (
                                                            <User className="h-4 w-4 text-gray-400" />
                                                        )}
                                                        <span className="text-sm text-black">{customerEmail}</span>
                                                    </div>
                                                </div>
                                            )}

                                            {isGuest && !restricted && !user && session.customer_email && (
                                                <CreateAccountOffer name={session.customer_name ?? ''} email={session.customer_email} />
                                            )}

                                            {showInboxNotice && (
                                                <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
                                                    <p className="text-sm font-medium text-blue-900">{t('checkout.success.restricted_title')}</p>
                                                    <p className="mt-1 text-sm text-blue-800">{t('checkout.success.restricted_body')}</p>
                                                </div>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card className="bg-white shadow-sm">
                                    <CardHeader className="text-black">
                                        <CardTitle>{t('checkout.success.items_ordered')}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4 text-black">
                                            {order.orderItems && order.orderItems.length > 0 ? (
                                                order.orderItems.map((item, index) => (
                                                    <div key={item.id || index} className="flex items-center space-x-4 border-b py-4 last:border-b-0">
                                                        <img
                                                            src={getImageSrc(item)}
                                                            alt={item.product.name}
                                                            className="h-16 w-16 rounded-lg object-cover"
                                                        />
                                                        <div className="flex-1">
                                                            <h4 className="font-medium">{item.product.name}</h4>
                                                            {item.variant?.name && <p className="text-sm text-gray-600">{item.variant.name}</p>}
                                                            <p className="text-sm text-gray-600">
                                                                {t('checkout.success.qty')}: {item.quantity}
                                                            </p>
                                                        </div>
                                                        <div className="text-right">
                                                            <p className="font-medium">{formatMoney(item.quantity * item.price)}</p>
                                                            {item.quantity > 1 && (
                                                                <p className="text-sm text-gray-600">
                                                                    {formatMoney(item.price)} {t('checkout.success.each')}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                ))
                                            ) : (
                                                <div className="py-8 text-center text-gray-500">
                                                    <Package className="mx-auto mb-4 h-12 w-12 text-gray-300" />
                                                    <p>{t('checkout.success.processing_items')}</p>
                                                </div>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card className="bg-white shadow-sm">
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-black">
                                            <Truck className="h-5 w-5" />
                                            {t('checkout.success.whats_next')}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-3">
                                            <div className="flex items-start gap-3">
                                                <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-blue-100">
                                                    <span className="text-xs font-medium text-blue-600">1</span>
                                                </div>
                                                <div>
                                                    <p className="font-medium text-gray-700">{t('checkout.success.step_1_title')}</p>
                                                    <p className="text-sm text-gray-600">{t('checkout.success.step_1_desc')}</p>
                                                </div>
                                            </div>
                                            <div className="flex items-start gap-3">
                                                <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-gray-100">
                                                    <span className="text-xs font-medium text-gray-600">2</span>
                                                </div>
                                                <div>
                                                    <p className="font-medium text-gray-700">{t('checkout.success.step_2_title')}</p>
                                                    <p className="text-sm text-gray-600">{t('checkout.success.step_2_desc', { dispatch })}</p>
                                                </div>
                                            </div>
                                            <div className="flex items-start gap-3">
                                                <div className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-gray-100">
                                                    <span className="text-xs font-medium text-gray-600">3</span>
                                                </div>
                                                <div>
                                                    <p className="font-medium text-gray-700">{t('checkout.success.step_3_title')}</p>
                                                    <p className="text-sm text-gray-600">{t('checkout.success.step_3_desc')}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <div>
                                <Card className="sticky top-8">
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <CreditCard className="h-5 w-5" />
                                            {t('checkout.success.order_summary')}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">

                                        <div className="space-y-2">
                                            <div className="flex justify-between">
                                                <span className="text-sm">{t('checkout.success.subtotal')}</span>
                                                <span className="text-sm">{formatMoney(subtotalAmount)}</span>
                                            </div>
                                            {discountAmount > 0 && (
                                                <div className="flex justify-between">
                                                    <span className="text-sm">{t('checkout.success.discount')}</span>
                                                    <span className="text-sm">-{formatMoney(discountAmount)}</span>
                                                </div>
                                            )}
                                            <div className="flex justify-between">
                                                <span className="text-sm">{t('checkout.success.shipping')}</span>
                                                <span className="text-sm">{formatMoney(shippingAmount)}</span>
                                            </div>
                                            <Separator />
                                            <div className="flex justify-between text-lg font-bold">
                                                <span>{t('checkout.success.total')}</span>
                                                <span>{formatMoney(order.total_amount || 0)}</span>
                                            </div>
                                            {hasVatBreakdown ? (
                                                <div className="flex justify-between text-xs text-gray-500">
                                                    <span>{t('checkout.vat_portion', { rate: formatRate(order.tax_rate ?? 0, locale) })}</span>
                                                    <span>{formatMoney(order.tax_amount || 0)}</span>
                                                </div>
                                            ) : (
                                                <p className="text-right text-xs text-gray-500">{t('checkout.success.vat_included')}</p>
                                            )}
                                        </div>

                                        <Separator />

                                        <div className="space-y-3">
                                            <Button asChild className="w-full">
                                                <Link href={route('products')}>{t('checkout.success.continue_shopping')}</Link>
                                            </Button>

                                            {isGuest ? (
                                                <Button variant="outline" asChild className="w-full">
                                                    <Link href={route('register')}>{t('checkout.success.create_account')}</Link>
                                                </Button>
                                            ) : (
                                                <Button variant="outline" asChild className="w-full">
                                                    <Link href={route('dashboard')}>{t('checkout.success.view_order_history')}</Link>
                                                </Button>
                                            )}
                                        </div>

                                        <div className="border-t pt-4">
                                            <p className="text-center text-xs text-gray-300">
                                                {t('checkout.success.need_help')}{' '}
                                                <Link href={route('contact')} className="underline">
                                                    {t('checkout.success.pending_contact')}
                                                </Link>
                                            </p>
                                            {isGuest && (
                                                <p className="mt-2 text-center text-xs text-gray-400">{t('checkout.success.save_order_number')}</p>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>
                </div>
            </>
        </>
    );
}

import Header from '@/components/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import useTranslation from '@/hooks/useTranslation';
import { Head, Link } from '@inertiajs/react';
import { MapPin, Package, Truck } from 'lucide-react';

interface OrderItem {
    id: number;
    name: string | null;
    size: string | null;
    quantity: number;
    price: number;
    total: number;
    image_url: string | null;
}

interface ShippingAddress {
    name: string;
    line1: string;
    line2: string | null;
    city: string;
    state: string | null;
    postal_code: string;
    country: string;
}

interface Order {
    order_number: string;
    status: string;
    payment_status: string;
    created_at: string | null;
    tracking_number: string | null;
    currency: string | null;
    subtotal: number;
    shipping_amount: number;
    discount_amount: number;
    total_amount: number;
    items: OrderItem[];
    shipping_address: ShippingAddress | null;
}

interface Props {
    order: Order;
    supportEmail?: string | null;
}

const KNOWN_STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

export default function GuestOrderShow({ order, supportEmail }: Props) {
    const { t, locale } = useTranslation();

    const formatMoney = (amount: number) => {
        try {
            return new Intl.NumberFormat(locale, { style: 'currency', currency: (order.currency || 'EUR').toUpperCase() }).format(amount);
        } catch {
            return `${amount.toFixed(2)} ${order.currency ?? ''}`.trim();
        }
    };

    const statusLabel = KNOWN_STATUSES.includes(order.status) ? t(`guest_order.status.${order.status}`) : order.status;
    const address = order.shipping_address;

    return (
        <>
            <Head title={`${t('guest_order.title')} ${order.order_number}`} />
            <Header />

            <div className="min-h-screen bg-white">
                <div className="container mx-auto max-w-3xl px-4 py-8">
                    <div className="mb-8 text-center">
                        <h1 className="mb-2 text-3xl font-bold text-gray-900">{t('guest_order.title')}</h1>
                        <p className="font-mono text-sm text-gray-600">{order.order_number}</p>
                        <div className="mt-3">
                            <Badge variant={order.status === 'cancelled' ? 'secondary' : 'default'}>{statusLabel}</Badge>
                        </div>
                    </div>

                    <div className="space-y-6">
                        {order.tracking_number && (
                            <Card className="bg-white">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-gray-800">
                                        <Truck className="h-5 w-5" />
                                        {t('guest_order.tracking')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="font-mono text-sm break-all text-black">{order.tracking_number}</p>
                                </CardContent>
                            </Card>
                        )}

                        <Card className="bg-white">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-gray-800">
                                    <Package className="h-5 w-5" />
                                    {t('guest_order.items')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4 text-black">
                                    {order.items.map((item) => (
                                        <div key={item.id} className="flex items-center space-x-4 border-b py-4 last:border-b-0">
                                            <img
                                                src={item.image_url || '/images/product.png'}
                                                alt={item.name ?? ''}
                                                className="h-16 w-16 rounded-lg object-cover"
                                            />
                                            <div className="min-w-0 flex-1">
                                                <h4 className="font-medium break-words">{item.name}</h4>
                                                {item.size && (
                                                    <p className="text-sm text-gray-600">
                                                        {t('guest_order.size')}: {item.size}
                                                    </p>
                                                )}
                                                <p className="text-sm text-gray-600">
                                                    {t('guest_order.quantity')}: {item.quantity}
                                                </p>
                                            </div>
                                            <p className="font-medium">{formatMoney(item.total)}</p>
                                        </div>
                                    ))}
                                </div>

                                <Separator className="my-4" />

                                <div className="space-y-2 text-black">
                                    <div className="flex justify-between text-sm">
                                        <span>{t('guest_order.subtotal')}</span>
                                        <span>{formatMoney(order.subtotal)}</span>
                                    </div>
                                    {order.discount_amount > 0 && (
                                        <div className="flex justify-between text-sm">
                                            <span>{t('guest_order.discount')}</span>
                                            <span>-{formatMoney(order.discount_amount)}</span>
                                        </div>
                                    )}
                                    <div className="flex justify-between text-sm">
                                        <span>{t('guest_order.shipping')}</span>
                                        <span>{order.shipping_amount === 0 ? t('guest_order.free') : formatMoney(order.shipping_amount)}</span>
                                    </div>
                                    <Separator />
                                    <div className="flex justify-between text-lg font-bold">
                                        <span>{t('guest_order.total')}</span>
                                        <span>{formatMoney(order.total_amount)}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {address && (
                            <Card className="bg-white">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-gray-800">
                                        <MapPin className="h-5 w-5" />
                                        {t('guest_order.shipping_address')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <address className="text-sm leading-relaxed text-black not-italic">
                                        {address.name}
                                        <br />
                                        {address.line1}
                                        {address.line2 && (
                                            <>
                                                <br />
                                                {address.line2}
                                            </>
                                        )}
                                        <br />
                                        {address.postal_code} {address.city}
                                        {address.state ? ` (${address.state})` : ''}
                                        <br />
                                        {address.country}
                                    </address>
                                </CardContent>
                            </Card>
                        )}

                        <div className="border-t pt-6 text-center">
                            <p className="mb-3 text-sm text-gray-600">{t('guest_order.need_help')}</p>
                            <Button variant="outline" asChild>
                                <Link href={route('contact')}>{t('guest_order.contact_us')}</Link>
                            </Button>
                            {supportEmail && (
                                <p className="mt-3 text-xs text-gray-500">
                                    <a href={`mailto:${supportEmail}`} className="underline">
                                        {supportEmail}
                                    </a>
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

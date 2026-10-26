import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useMoney } from '@/hooks/use-money';
import { useTranslation } from '@/hooks/useTranslation';
import { type Order, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { CheckCircle, Clock, Package, ShoppingBag, Truck } from 'lucide-react';

export const LatestOrderCard = () => {
    const { props } = usePage<SharedData & { recentOrders?: Order[] }>();
    const recentOrders = props.recentOrders || [];
    const { t, locale } = useTranslation();

    const formatCurrency = useMoney();

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString(locale, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const getOrderStatusIcon = (status: string) => {
        switch (status.toLowerCase()) {
            case 'pending':
                return <Clock className="h-4 w-4" />;
            case 'processing':
                return <Package className="h-4 w-4" />;
            case 'shipped':
                return <Truck className="h-4 w-4" />;
            case 'delivered':
                return <CheckCircle className="h-4 w-4" />;
            default:
                return <Package className="h-4 w-4" />;
        }
    };

    const getOrderStatusColor = (status: string) => {
        switch (status.toLowerCase()) {
            case 'pending':
                return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'processing':
                return 'bg-indigo-100 text-indigo-800 border-indigo-200';
            case 'shipped':
                return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'delivered':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'cancelled':
                return 'bg-red-100 text-red-800 border-red-200';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    };

    const latestOrder = recentOrders[0];

    return (
        <Card className="w-full overflow-hidden">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <ShoppingBag className="h-5 w-5 flex-shrink-0" />
                    <span className="truncate">{t('dashboard.recent_orders')}</span>
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {latestOrder ? (
                    <div className="space-y-4">
                        {/* Latest Order */}
                        <div className="overflow-hidden rounded-lg border bg-muted/20 p-4">
                            <div className="mb-3 flex items-center justify-between gap-4">
                                <div className="min-w-0 flex-1">
                                    <h4 className="truncate font-medium">{t('dashboard.order_number', { id: latestOrder.order_number })}</h4>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {formatDate(latestOrder.created_at)} • {latestOrder.order_items.length}{' '}
                                        {latestOrder.order_items.length === 1 ? t('dashboard.item') : t('dashboard.items')}
                                    </p>
                                </div>
                                <div className="flex-shrink-0 text-right">
                                    <div className="font-medium">{formatCurrency(latestOrder.total_amount || latestOrder.total)}</div>
                                    <Badge variant="outline" className={`${getOrderStatusColor(latestOrder.status)} mt-1 flex items-center gap-1`}>
                                        {getOrderStatusIcon(latestOrder.status)}
                                        {t(`orders.status.${latestOrder.status}`, { fallback: latestOrder.status })}
                                    </Badge>
                                </div>
                            </div>

                            {/* Order Items Preview */}
                            <div className="space-y-2 overflow-hidden">
                                {latestOrder.order_items.slice(0, 2).map((item) => (
                                    <div key={item.id} className="flex items-center justify-between gap-4 text-sm">
                                        <div className="min-w-0 flex-1">
                                            <span className="block truncate font-medium">{item.product.name}</span>
                                            {item.variant_name && <span className="block truncate text-muted-foreground">• {item.variant_name}</span>}
                                        </div>
                                        <div className="flex-shrink-0 text-muted-foreground">
                                            {t('dashboard.qty_price', { qty: item.quantity, price: formatCurrency(item.price) })}
                                        </div>
                                    </div>
                                ))}
                                {latestOrder.order_items.length > 2 && (
                                    <div className="text-sm text-muted-foreground">
                                        +{latestOrder.order_items.length - 2} {t('dashboard.more_items')}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Additional Orders */}
                        {recentOrders.slice(1, 3).map((order) => (
                            <div key={order.id} className="flex items-center justify-between gap-4 overflow-hidden rounded-lg border p-3">
                                <div className="flex min-w-0 flex-1 items-center gap-3">
                                    <div className="flex-shrink-0">{getOrderStatusIcon(order.status)}</div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">{t('dashboard.order_number', { id: order.order_number })}</p>
                                        <p className="truncate text-xs text-muted-foreground">{formatDate(order.created_at)}</p>
                                    </div>
                                </div>
                                <div className="flex-shrink-0 text-right">
                                    <div className="text-sm font-medium">{formatCurrency(order.total_amount || order.total)}</div>
                                    <Badge variant="outline" className={`${getOrderStatusColor(order.status)} text-xs`}>
                                        {t(`orders.status.${order.status}`, { fallback: order.status })}
                                    </Badge>
                                </div>
                            </div>
                        ))}

                        <Button variant="outline" className="w-full" asChild>
                            <Link href={route('orders.index')}>{t('dashboard.view_all_orders')}</Link>
                        </Button>
                    </div>
                ) : (
                    <div className="py-8 text-center">
                        <ShoppingBag className="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 className="mt-2 text-sm font-medium">{t('dashboard.no_orders_yet')}</h3>
                        <p className="mt-1 text-sm text-muted-foreground">{t('dashboard.no_orders_description')}</p>
                        <div className="mt-6">
                            <Button asChild>
                                <Link href={route('products')}>{t('dashboard.browse_products')}</Link>
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
};

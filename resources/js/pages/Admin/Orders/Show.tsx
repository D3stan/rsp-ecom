import OrderRefunds, { type OrderRefund, type RefundInfo } from '@/components/admin/orders/OrderRefunds';
import OrderStatusActions from '@/components/admin/orders/OrderStatusActions';
import OrderTimeline, { type OrderTimelineEvent } from '@/components/admin/orders/OrderTimeline';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import useTranslation from '@/hooks/useTranslation';
import AppLayout from '@/layouts/app-layout';
import { forwardTransitions } from '@/lib/order-transitions';
import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle,
    ChevronDown,
    ChevronUp,
    CreditCard,
    DollarSign,
    Edit,
    Package,
    Plus,
    SkipForward,
    Truck,
    User,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
// Removed EditOrderDialog import as we're now using a dedicated edit page

interface OrderItem {
    id: number;
    product: {
        id: string | number;
        name: string;
        image_url?: string;
    };
    quantity: number;
    price: number;
    total: number;
}

interface Address {
    id: number;
    first_name: string;
    last_name: string;
    address_line_1: string;
    address_line_2?: string;
    city: string;
    state: string;
    postal_code: string;
    country: string;
    phone?: string;
}

interface Order {
    id: number;
    order_number: string;
    user?: {
        id: number;
        name: string;
        email: string;
        phone?: string;
    };
    guest_email?: string;
    guest_phone?: string;
    status: string;
    payment_status: string;
    subtotal: number;
    tax_amount: number;
    // VAT rate (percent) stored with the order; null on orders from before it was recorded (their tax is not part of the total)
    tax_rate?: number | null;
    shipping_amount: number;
    total_amount: number;
    currency: string;
    notes?: string;
    tracking_number?: string;
    tracking_carrier?: string | null;
    tracking_driver?: string | null;
    tracking_status?: string | null;
    tracking_last_synced_at?: string | null;
    tracking_events?: TrackingEvent[];
    created_at: string;
    updated_at: string;
    orderItems: OrderItem[];
    billingAddress?: Address;
    shippingAddress?: Address;
}

interface TrackingEvent {
    id: number;
    status: string;
    description?: string | null;
    location?: string | null;
    occurred_at: string;
}

interface Carrier {
    code: string;
    name: string;
}

interface StockInfo {
    has_oversold_items: boolean;
    // order item id => units sold beyond the available stock
    oversold: Record<string, number>;
}

interface Props {
    order: Order;
    // statuses this order may move to next (server-side order lifecycle)
    allowed_transitions: string[];
    // processing and paid: the ship form is available (Order::canShip)
    can_ship?: boolean;
    // audit trail (order_events), oldest first
    events?: OrderTimelineEvent[];
    // cancelled while money is still captured (customer service owes a refund)
    refund_due?: boolean;
    // money returned so far and what can still be refunded (RefundService)
    refunds?: OrderRefund[];
    refund?: RefundInfo;
    carriers?: Carrier[];
    // label shipments with a stored PDF (optional label layer)
    shipments?: Array<{
        id: number;
        carrier_code: string;
        driver?: string;
        tracking_number: string | null;
        can_cancel: boolean;
        status?: string;
    }>;
    stock?: StockInfo;
}

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800',
    processing: 'bg-blue-100 text-blue-800',
    shipped: 'bg-purple-100 text-purple-800',
    delivered: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
};

const paymentStatusColors = {
    pending: 'bg-yellow-100 text-yellow-800',
    processing: 'bg-blue-100 text-blue-800',
    succeeded: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800',
    partially_refunded: 'bg-orange-100 text-orange-800',
    refunded: 'bg-gray-100 text-gray-800',
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'pending':
            return <Package className="h-4 w-4" />;
        case 'processing':
            return <Package className="h-4 w-4" />;
        case 'shipped':
            return <Truck className="h-4 w-4" />;
        case 'delivered':
            return <CheckCircle className="h-4 w-4" />;
        case 'cancelled':
            return <XCircle className="h-4 w-4" />;
        default:
            return <Package className="h-4 w-4" />;
    }
};

export default function OrderShow({
    order,
    allowed_transitions = [],
    can_ship = false,
    refund_due = false,
    events = [],
    refunds = [],
    refund,
    carriers = [],
    shipments = [],
    stock,
}: Props) {
    const { t, locale } = useTranslation();
    const [cancelShipmentId, setCancelShipmentId] = useState<number | null>(null);
    const carrierName = carriers.find((carrier) => carrier.code === order.tracking_carrier)?.name ?? order.tracking_carrier;
    // ?label=<shipment id>: the ship page just bought this label, offer to print it right away
    const labelId = Number(new URLSearchParams(usePage().url.split('?')[1] ?? '').get('label'));
    const justBought = shipments.find((shipment) => shipment.id === labelId && shipment.status !== 'cancelled');
    const [isUpdating, setIsUpdating] = useState(false);
    const [isAddingTracking, setIsAddingTracking] = useState(false);
    const [trackingNumber, setTrackingNumber] = useState(order.tracking_number || '');
    const [collapsedSections, setCollapsedSections] = useState({
        orderStatus: false,
        orderItems: false,
        timeline: false,
        customer: false,
        summary: false,
        addresses: false,
        notes: false,
        shipping: false,
    });

    const toggleSection = (section: keyof typeof collapsedSections) => {
        setCollapsedSections((prev) => ({
            ...prev,
            [section]: !prev[section],
        }));
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: order.currency || 'EUR',
        }).format(amount);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString(locale, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getCustomerInfo = () => {
        if (order.user) {
            return {
                name: order.user.name,
                email: order.user.email,
                phone: order.user.phone || order.shippingAddress?.phone,
                type: t('admin.orders.registered_customer'),
            };
        }
        return {
            name: t('admin.orders.guest_customer'),
            email: order.guest_email || t('admin.orders.not_available'),
            phone: order.guest_phone || order.shippingAddress?.phone,
            type: t('admin.orders.guest'),
        };
    };

    // Status buttons never offer "shipped": the order ships through the ship form (carrier, tracking, one email)
    const updateOrderStatus = (newStatus: string) => {
        setIsUpdating(true);
        router.patch(`/admin/orders/${order.id}`, { status: newStatus }, { onFinish: () => setIsUpdating(false) });
    };

    const getNextStatus = () => forwardTransitions(order.status, allowed_transitions)[0] ?? null;

    const navigateToCustomer = () => {
        // TODO: Implement customer navigation when customer management is ready
        if (order.user) {
            // router.visit(`/admin/customers/${order.user.id}`);
            console.log('Customer navigation not yet implemented');
        }
    };

    const updateTrackingNumber = () => {
        if (!trackingNumber.trim()) return;

        setIsUpdating(true);
        router.patch(
            `/admin/orders/${order.id}`,
            { tracking_number: trackingNumber.trim() },
            {
                onSuccess: () => {
                    setIsAddingTracking(false);
                },
                onFinish: () => setIsUpdating(false),
            },
        );
    };

    const clearTrackingNumber = () => {
        setIsUpdating(true);
        router.patch(
            `/admin/orders/${order.id}`,
            { tracking_number: null },
            {
                onSuccess: () => {
                    setIsAddingTracking(false);
                    setTrackingNumber('');
                },
                onFinish: () => setIsUpdating(false),
            },
        );
    };

    const customer = getCustomerInfo();

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('admin.nav.admin'), href: '/admin/dashboard' },
                { title: t('admin.orders.title'), href: '/admin/orders' },
                { title: order.order_number, href: `/admin/orders/${order.id}` },
            ]}
        >
            <Head title={t('admin.orders.show_head_title', { order_number: order.order_number })} />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">{order.order_number}</h1>
                            <p className="text-muted-foreground">{t('admin.orders.placed_on', { date: formatDate(order.created_at) })}</p>
                        </div>
                    </div>
                    <div className="flex items-center space-x-2">
                        {can_ship && (
                            <Button size="sm" onClick={() => router.visit(`/admin/orders/${order.id}/ship`)}>
                                <Truck className="mr-2 h-4 w-4" />
                                {t('admin.orders.ship.button')}
                            </Button>
                        )}
                        <Button variant="outline" size="sm" onClick={() => router.visit(`/admin/orders/${order.id}/edit`)}>
                            <Edit className="mr-2 h-4 w-4" />
                            {t('admin.orders.edit_order')}
                        </Button>
                    </div>
                </div>

                {justBought && (
                    <div className="flex flex-col gap-3 rounded-lg border border-green-200 bg-green-50 p-4 text-green-900 sm:flex-row sm:items-center sm:justify-between dark:border-green-900 dark:bg-green-950 dark:text-green-100">
                        <div className="flex items-center gap-3">
                            <CheckCircle className="h-5 w-5 shrink-0" />
                            <p className="font-medium">
                                {t('admin.orders.ship.label_bought')}
                                <span className="ml-2 font-normal">
                                    {carriers.find((carrier) => carrier.code === justBought.carrier_code)?.name ?? justBought.carrier_code}
                                    {justBought.tracking_number && <span className="ml-1 font-mono">{justBought.tracking_number}</span>}
                                </span>
                            </p>
                        </div>
                        <Button size="sm" asChild>
                            <a href={`/admin/orders/${order.id}/shipments/${justBought.id}/label`} target="_blank" rel="noopener noreferrer">
                                {t('admin.orders.ship.print_label')}
                            </a>
                        </Button>
                    </div>
                )}

                {stock?.has_oversold_items && (
                    <div className="flex items-start space-x-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900">
                        <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" />
                        <div>
                            <p className="font-medium">{t('admin.orders.oversold_title')}</p>
                            <p className="text-sm">{t('admin.orders.oversold_body')}</p>
                        </div>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Order Details */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Order Status */}
                        <Collapsible open={!collapsedSections.orderStatus} onOpenChange={() => toggleSection('orderStatus')}>
                            <Card className="p-6">
                                <CollapsibleTrigger className="mb-4 flex w-full items-center justify-between">
                                    <h2 className="text-lg font-semibold">{t('admin.orders.section_order_status')}</h2>
                                    {collapsedSections.orderStatus ? <ChevronDown className="h-4 w-4" /> : <ChevronUp className="h-4 w-4" />}
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div className="mb-4 flex items-center justify-between">
                                        <div className="flex items-center space-x-2">
                                            <Badge className={statusColors[order.status as keyof typeof statusColors]} variant="secondary">
                                                {getStatusIcon(order.status)}
                                                <span className="ml-1 capitalize">{t(`admin.orders.status.${order.status}`)}</span>
                                            </Badge>
                                            <Badge
                                                className={paymentStatusColors[order.payment_status as keyof typeof paymentStatusColors]}
                                                variant="secondary"
                                            >
                                                <CreditCard className="mr-1 h-4 w-4" />
                                                {t(`admin.orders.payment_status.${order.payment_status}`)}
                                            </Badge>
                                            {refund_due && (
                                                <Badge variant="secondary" className="bg-red-100 text-red-800">
                                                    {t('admin.orders.refund_due')}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>

                                    {/* Quick Status Update: only the moves the order lifecycle allows */}
                                    <OrderStatusActions
                                        orderId={order.id}
                                        status={order.status}
                                        allowedTransitions={allowed_transitions}
                                        disabled={isUpdating}
                                        onAdvance={updateOrderStatus}
                                        refundableCents={refund?.has_payment_intent ? refund.refundable_cents : 0}
                                        currency={order.currency}
                                    />
                                </CollapsibleContent>
                            </Card>
                        </Collapsible>

                        {/* Timeline */}
                        <Collapsible open={!collapsedSections.timeline} onOpenChange={() => toggleSection('timeline')}>
                            <Card className="p-6">
                                <CollapsibleTrigger className="mb-4 flex w-full items-center justify-between">
                                    <h2 className="text-lg font-semibold">{t('admin.orders.section_order_timeline')}</h2>
                                    {collapsedSections.timeline ? <ChevronDown className="h-4 w-4" /> : <ChevronUp className="h-4 w-4" />}
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div className="space-y-4">
                                        <OrderTimeline createdAt={order.created_at} status={order.status} events={events} formatDate={formatDate} />
                                        {getNextStatus() && (
                                            <Button size="sm" onClick={() => updateOrderStatus(getNextStatus()!)} disabled={isUpdating}>
                                                <SkipForward className="mr-2 h-4 w-4" />
                                                {t('admin.orders.mark_as', { status: t(`admin.orders.status.${getNextStatus()!}`) })}
                                            </Button>
                                        )}
                                    </div>
                                </CollapsibleContent>
                            </Card>
                        </Collapsible>

                        {/* Order Items */}
                        <Collapsible open={!collapsedSections.orderItems} onOpenChange={() => toggleSection('orderItems')}>
                            <Card className="p-6">
                                <CollapsibleTrigger className="mb-4 flex w-full items-center justify-between">
                                    <h2 className="text-lg font-semibold">{t('admin.orders.section_order_items')}</h2>
                                    {collapsedSections.orderItems ? <ChevronDown className="h-4 w-4" /> : <ChevronUp className="h-4 w-4" />}
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div className="space-y-4">
                                        {order.orderItems.map((item) => (
                                            <div key={item.id} className="flex items-center space-x-4 rounded-lg border p-4">
                                                <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-muted">
                                                    {item.product.image_url ? (
                                                        <img
                                                            src={item.product.image_url}
                                                            alt={item.product.name}
                                                            className="h-full w-full rounded-lg object-cover"
                                                        />
                                                    ) : (
                                                        <Package className="h-6 w-6 text-muted-foreground" />
                                                    )}
                                                </div>
                                                <div className="flex-1">
                                                    <h3 className="font-medium">{item.product.name}</h3>
                                                    <p className="text-sm text-muted-foreground">
                                                        {t('admin.orders.qty_price', {
                                                            quantity: item.quantity,
                                                            price: formatCurrency(item.price),
                                                        })}
                                                    </p>
                                                    {stock?.oversold[item.id] ? (
                                                        <p className="text-sm font-medium text-amber-700">
                                                            {t('admin.orders.oversold_line', {
                                                                missing: stock.oversold[item.id],
                                                                ordered: item.quantity,
                                                            })}
                                                        </p>
                                                    ) : null}
                                                </div>
                                                <div className="text-right">
                                                    <p className="font-medium">{formatCurrency(item.total)}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CollapsibleContent>
                            </Card>
                        </Collapsible>

                        {/* Shipping Information */}
                        <Collapsible open={!collapsedSections.shipping} onOpenChange={() => toggleSection('shipping')}>
                            <Card className="p-6">
                                <CollapsibleTrigger className="mb-4 flex w-full items-center justify-between">
                                    <h2 className="flex items-center text-lg font-semibold">
                                        <Truck className="mr-2 h-5 w-5" />
                                        {t('admin.orders.section_shipping_info')}
                                    </h2>
                                    {collapsedSections.shipping ? <ChevronDown className="h-4 w-4" /> : <ChevronUp className="h-4 w-4" />}
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div className="space-y-4">
                                        <div className="grid grid-cols-1 gap-4">
                                            <div>
                                                <p className="text-sm font-medium text-muted-foreground">{t('admin.orders.shipping_cost')}</p>
                                                <p className="text-sm">{formatCurrency(order.shipping_amount)}</p>
                                            </div>
                                        </div>

                                        {/* Tracking Number Section */}
                                        <div className="grid grid-cols-1 gap-4">
                                            <div>
                                                <p className="text-sm font-medium text-muted-foreground">{t('admin.orders.tracking_number')}</p>
                                                {isAddingTracking ? (
                                                    <div className="mt-1 flex space-x-2">
                                                        <Input
                                                            value={trackingNumber}
                                                            onChange={(e) => setTrackingNumber(e.target.value)}
                                                            placeholder={t('admin.orders.enter_tracking_number')}
                                                            className="text-sm"
                                                        />
                                                        <Button
                                                            size="sm"
                                                            onClick={updateTrackingNumber}
                                                            disabled={isUpdating || !trackingNumber.trim()}
                                                        >
                                                            {t('admin.orders.save')}
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => {
                                                                setIsAddingTracking(false);
                                                                setTrackingNumber(order.tracking_number || '');
                                                            }}
                                                        >
                                                            {t('admin.orders.cancel')}
                                                        </Button>
                                                    </div>
                                                ) : order.tracking_number ? (
                                                    <div className="mt-1 flex flex-wrap items-center gap-2">
                                                        <span className="font-mono text-sm">{order.tracking_number}</span>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-7 px-2 text-xs"
                                                            onClick={() => {
                                                                setTrackingNumber(order.tracking_number || '');
                                                                setIsAddingTracking(true);
                                                            }}
                                                            disabled={isUpdating}
                                                        >
                                                            <Edit className="mr-1 h-3 w-3" />
                                                            {t('admin.orders.edit_tracking')}
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-7 px-2 text-xs text-red-600 hover:bg-red-50 hover:text-red-700"
                                                            onClick={clearTrackingNumber}
                                                            disabled={isUpdating}
                                                        >
                                                            <XCircle className="mr-1 h-3 w-3" />
                                                            {t('admin.orders.clear_tracking')}
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <div className="mt-1 flex items-center space-x-2">
                                                        <p className="text-sm text-muted-foreground">{t('admin.orders.not_assigned')}</p>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => {
                                                                setTrackingNumber('');
                                                                setIsAddingTracking(true);
                                                            }}
                                                        >
                                                            <Plus className="mr-1 h-3 w-3" />
                                                            {t('admin.orders.add')}
                                                        </Button>
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        {/* Carrier tracking (set from the edit page; updated automatically when a tracking driver is active) */}
                                        {(carrierName || order.tracking_driver || order.tracking_status) && (
                                            <div className="grid grid-cols-1 gap-2">
                                                {carrierName && (
                                                    <div>
                                                        <p className="text-sm font-medium text-muted-foreground">{t('tracking_admin.carrier')}</p>
                                                        <p className="text-sm">{carrierName}</p>
                                                    </div>
                                                )}
                                                {order.tracking_driver && (
                                                    <div>
                                                        <p className="text-sm font-medium text-muted-foreground">
                                                            {t('tracking_admin.provider', { defaultValue: 'Tracking Provider' })}
                                                        </p>
                                                        <Badge variant="outline" className="mt-0.5 text-xs">
                                                            {order.tracking_driver}
                                                        </Badge>
                                                    </div>
                                                )}
                                                {order.tracking_status && (
                                                    <div>
                                                        <p className="text-sm font-medium text-muted-foreground">
                                                            {t('tracking_admin.section_title')}
                                                        </p>
                                                        <p className="text-sm">{t(`tracking_admin.status.${order.tracking_status}`)}</p>
                                                        {order.tracking_last_synced_at && (
                                                            <p className="text-xs text-muted-foreground">
                                                                {t('tracking_admin.last_sync')}: {formatDate(order.tracking_last_synced_at)}
                                                            </p>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                        {order.tracking_events && order.tracking_events.length > 0 && (
                                            <div>
                                                <p className="text-sm font-medium text-muted-foreground">{t('tracking_admin.events_title')}</p>
                                                <ul className="mt-1 space-y-1 text-sm">
                                                    {order.tracking_events.map((event) => (
                                                        <li key={event.id}>
                                                            <span className="text-xs text-muted-foreground">{formatDate(event.occurred_at)}</span>{' '}
                                                            {event.description || t(`tracking_admin.status.${event.status}`)}
                                                            {event.location ? ` (${event.location})` : ''}
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        )}

                                        {/* Status Display */}
                                        {order.status === 'shipped' || order.status === 'delivered' ? (
                                            <div className="rounded-lg bg-blue-50 p-3">
                                                <p className="text-sm font-medium text-blue-900">{t('admin.orders.package_status')}</p>
                                                <p className="text-sm text-blue-700">
                                                    {order.status === 'shipped' ? t('admin.orders.in_transit') : t('admin.orders.status.delivered')}
                                                </p>
                                                {order.tracking_number && (
                                                    <p className="mt-1 text-xs text-blue-600">
                                                        {t('admin.orders.track_package_with', { tracking_number: order.tracking_number })}
                                                    </p>
                                                )}
                                            </div>
                                        ) : (
                                            <div className="rounded-lg bg-yellow-50 p-3">
                                                <p className="text-sm font-medium text-yellow-900">{t('admin.orders.preparing_shipment')}</p>
                                                <p className="text-sm text-yellow-700">{t('admin.orders.order_being_processed')}</p>
                                            </div>
                                        )}

                                        {/* Label shipments: download the PDF, cancel at the carrier (optional label layer) */}
                                        {shipments.length > 0 && (
                                            <div className="space-y-2">
                                                {shipments.map((shipment) => (
                                                    <div
                                                        key={shipment.id}
                                                        className="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm"
                                                    >
                                                        <span className="font-mono">{shipment.tracking_number ?? '—'}</span>
                                                        {shipment.driver && (
                                                            <Badge variant="secondary" className="text-xs">
                                                                {shipment.driver}
                                                            </Badge>
                                                        )}
                                                        {shipment.status === 'cancelled' ? (
                                                            <Badge variant="outline" className="border-red-200 bg-red-50 text-xs text-red-700">
                                                                {t('admin.orders.ship.status_cancelled')}
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="outline" className="border-green-200 bg-green-50 text-xs text-green-700">
                                                                {t('admin.orders.ship.status_active')}
                                                            </Badge>
                                                        )}
                                                        <div className="ml-auto flex gap-2">
                                                            <Button variant="outline" size="sm" asChild>
                                                                <a
                                                                    href={`/admin/orders/${order.id}/shipments/${shipment.id}/label`}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                >
                                                                    {t('admin.orders.ship.download_label')}
                                                                </a>
                                                            </Button>
                                                            {shipment.can_cancel && (
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    className="text-red-600"
                                                                    onClick={() => setCancelShipmentId(shipment.id)}
                                                                >
                                                                    {t('admin.orders.ship.cancel_shipment')}
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        )}

                                        {/* Shipping Address */}
                                        {order.shippingAddress && (
                                            <div>
                                                <p className="mb-2 text-sm font-medium text-muted-foreground">{t('admin.orders.shipping_to')}</p>
                                                <div className="rounded-lg bg-muted p-3 text-sm">
                                                    <p className="font-medium">
                                                        {order.shippingAddress.first_name} {order.shippingAddress.last_name}
                                                    </p>
                                                    <p>{order.shippingAddress.address_line_1}</p>
                                                    {order.shippingAddress.address_line_2 && <p>{order.shippingAddress.address_line_2}</p>}
                                                    <p>
                                                        {order.shippingAddress.city}, {order.shippingAddress.state}{' '}
                                                        {order.shippingAddress.postal_code}
                                                    </p>
                                                    <p>{order.shippingAddress.country}</p>
                                                    {order.shippingAddress.phone && (
                                                        <p className="mt-1 text-muted-foreground">📞 {order.shippingAddress.phone}</p>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </CollapsibleContent>
                            </Card>
                        </Collapsible>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Customer Info */}
                        <Collapsible open={!collapsedSections.customer} onOpenChange={() => toggleSection('customer')}>
                            <Card className="p-6">
                                <CollapsibleTrigger className="mb-4 flex w-full items-center justify-between">
                                    <h2 className="flex items-center text-lg font-semibold">
                                        <User className="mr-2 h-5 w-5" />
                                        {t('admin.orders.customer')}
                                    </h2>
                                    {collapsedSections.customer ? <ChevronDown className="h-4 w-4" /> : <ChevronUp className="h-4 w-4" />}
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div className="space-y-2">
                                        <p className="font-medium">{customer.name}</p>
                                        <p className="text-sm text-muted-foreground">{customer.email}</p>
                                        {customer.phone && <p className="text-sm text-muted-foreground">📞 {customer.phone}</p>}
                                        <Badge variant="secondary" className="text-xs">
                                            {customer.type}
                                        </Badge>
                                        {order.user && (
                                            <div className="pt-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={navigateToCustomer}
                                                    disabled
                                                    title={t('admin.orders.customer_management_not_implemented')}
                                                >
                                                    <ArrowRight className="mr-2 h-4 w-4" />
                                                    {t('admin.orders.view_profile')}
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                </CollapsibleContent>
                            </Card>
                        </Collapsible>

                        {/* Order Summary */}
                        <Collapsible open={!collapsedSections.summary} onOpenChange={() => toggleSection('summary')}>
                            <Card className="p-6">
                                <CollapsibleTrigger className="mb-4 flex w-full items-center justify-between">
                                    <h2 className="flex items-center text-lg font-semibold">
                                        <DollarSign className="mr-2 h-5 w-5" />
                                        {t('admin.orders.order_summary')}
                                    </h2>
                                    {collapsedSections.summary ? <ChevronDown className="h-4 w-4" /> : <ChevronUp className="h-4 w-4" />}
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div className="space-y-2">
                                        <div className="flex justify-between">
                                            <span>{t('admin.orders.subtotal')}</span>
                                            <span>{formatCurrency(order.subtotal)}</span>
                                        </div>
                                        {order.tax_amount > 0 && order.tax_rate == null && (
                                            <div className="flex justify-between">
                                                <span>{t('admin.orders.tax')}</span>
                                                <span>{formatCurrency(order.tax_amount)}</span>
                                            </div>
                                        )}
                                        {order.shipping_amount > 0 && (
                                            <div className="flex justify-between">
                                                <span>{t('admin.orders.shipping')}</span>
                                                <span>{formatCurrency(order.shipping_amount)}</span>
                                            </div>
                                        )}
                                        <Separator />
                                        <div className="flex justify-between font-semibold">
                                            <span>{t('admin.orders.total')}</span>
                                            <span>{formatCurrency(order.total_amount)}</span>
                                        </div>
                                        {order.tax_rate != null && (
                                            <div className="flex justify-between text-sm text-muted-foreground">
                                                <span>{t('admin.orders.vat_portion', { rate: order.tax_rate })}</span>
                                                <span>{formatCurrency(order.tax_amount)}</span>
                                            </div>
                                        )}
                                    </div>
                                </CollapsibleContent>
                            </Card>
                        </Collapsible>

                        {refund && (
                            <OrderRefunds
                                orderId={order.id}
                                currency={order.currency}
                                shippingAmount={Number(order.shipping_amount)}
                                lines={order.orderItems.map((item) => ({ id: item.id, name: item.product.name, quantity: item.quantity }))}
                                refunds={refunds}
                                info={refund}
                                formatDate={formatDate}
                            />
                        )}

                        {/* Notes */}
                        {order.notes && (
                            <Collapsible open={!collapsedSections.notes} onOpenChange={() => toggleSection('notes')}>
                                <Card className="p-6">
                                    <CollapsibleTrigger className="mb-4 flex w-full items-center justify-between">
                                        <h2 className="text-lg font-semibold">{t('admin.orders.notes')}</h2>
                                        {collapsedSections.notes ? <ChevronDown className="h-4 w-4" /> : <ChevronUp className="h-4 w-4" />}
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <p className="text-sm whitespace-pre-wrap text-muted-foreground">{order.notes}</p>
                                    </CollapsibleContent>
                                </Card>
                            </Collapsible>
                        )}
                    </div>
                </div>
            </div>
            <AlertDialog open={cancelShipmentId !== null} onOpenChange={(open) => !open && setCancelShipmentId(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('admin.orders.ship.cancel_shipment_confirm_title')}</AlertDialogTitle>
                        <AlertDialogDescription>{t('admin.orders.ship.cancel_shipment_confirm_body')}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{t('admin.orders.keep_as_is')}</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            onClick={() => {
                                if (cancelShipmentId !== null) {
                                    router.delete(`/admin/orders/${order.id}/shipments/${cancelShipmentId}`, {
                                        preserveScroll: true,
                                        onFinish: () => setCancelShipmentId(null),
                                    });
                                }
                            }}
                        >
                            {t('admin.orders.ship.cancel_shipment')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}

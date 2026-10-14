import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import useTranslation from '@/hooks/useTranslation';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
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
    carriers?: Carrier[];
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

export default function OrderShow({ order, carriers = [], stock }: Props) {
    const { t } = useTranslation();
    const carrierName = carriers.find((carrier) => carrier.code === order.tracking_carrier)?.name ?? order.tracking_carrier;
    const [isUpdating, setIsUpdating] = useState(false);
    const [isAddingTracking, setIsAddingTracking] = useState(false);
    const [trackingNumber, setTrackingNumber] = useState(order.tracking_number || '');
    const [showShipConfirmation, setShowShipConfirmation] = useState(false);
    const [pendingStatus, setPendingStatus] = useState<string | null>(null);
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
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: order.currency || 'EUR',
        }).format(amount);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('it-IT', {
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
                type: 'Registered Customer',
            };
        }
        return {
            name: 'Guest Customer',
            email: order.guest_email || 'N/A',
            phone: order.guest_phone || order.shippingAddress?.phone,
            type: 'Guest',
        };
    };

    const updateOrderStatus = (newStatus: string) => {
        // Check if status is being changed to shipped without a tracking number
        const isChangingToShipped = newStatus === 'shipped' && order.status !== 'shipped';
        const hasTrackingNumber = order.tracking_number && order.tracking_number.trim() !== '';

        if (isChangingToShipped && !hasTrackingNumber) {
            // Show confirmation modal for status change to shipped without tracking
            setPendingStatus(newStatus);
            setShowShipConfirmation(true);
            return;
        }

        // Proceed with status update
        submitStatusUpdate(newStatus);
    };

    const submitStatusUpdate = (newStatus: string) => {
        setIsUpdating(true);
        router.patch(
            `/admin/orders/${order.id}`,
            { status: newStatus },
            {
                onFinish: () => {
                    setIsUpdating(false);
                    setPendingStatus(null);
                },
            },
        );
    };

    const handleConfirmShip = () => {
        setShowShipConfirmation(false);
        if (pendingStatus) {
            submitStatusUpdate(pendingStatus);
        }
    };

    const getNextStatus = () => {
        const statusFlow = ['pending', 'processing', 'shipped', 'delivered'];
        const currentIndex = statusFlow.indexOf(order.status);
        return currentIndex < statusFlow.length - 1 ? statusFlow[currentIndex + 1] : null;
    };

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

    const customer = getCustomerInfo();

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin/dashboard' },
                { title: 'Orders', href: '/admin/orders' },
                { title: order.order_number, href: `/admin/orders/${order.id}` },
            ]}
        >
            <Head title={`Order ${order.order_number} - Admin`} />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">{order.order_number}</h1>
                            <p className="text-muted-foreground">Placed on {formatDate(order.created_at)}</p>
                        </div>
                    </div>
                    <div className="flex items-center space-x-2">
                        <Button variant="outline" size="sm" onClick={() => router.visit(`/admin/orders/${order.id}/edit`)}>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit Order
                        </Button>
                    </div>
                </div>

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
                                    <h2 className="text-lg font-semibold">Order Status</h2>
                                    {collapsedSections.orderStatus ? <ChevronDown className="h-4 w-4" /> : <ChevronUp className="h-4 w-4" />}
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div className="mb-4 flex items-center justify-between">
                                        <div className="flex items-center space-x-2">
                                            <Badge className={statusColors[order.status as keyof typeof statusColors]} variant="secondary">
                                                {getStatusIcon(order.status)}
                                                <span className="ml-1 capitalize">{order.status}</span>
                                            </Badge>
                                            <Badge
                                                className={paymentStatusColors[order.payment_status as keyof typeof paymentStatusColors]}
                                                variant="secondary"
                                            >
                                                <CreditCard className="mr-1 h-4 w-4" />
                                                {order.payment_status}
                                            </Badge>
                                        </div>
                                    </div>

                                    {/* Quick Status Update */}
                                    <div className="flex flex-wrap gap-2">
                                        {['pending', 'processing', 'shipped', 'delivered'].map((status) => (
                                            <Button
                                                key={status}
                                                variant={order.status === status ? 'default' : 'outline'}
                                                size="sm"
                                                onClick={() => updateOrderStatus(status)}
                                                disabled={isUpdating}
                                            >
                                                {status.charAt(0).toUpperCase() + status.slice(1)}
                                            </Button>
                                        ))}
                                        {order.status !== 'cancelled' && order.status !== 'delivered' && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => updateOrderStatus('cancelled')}
                                                disabled={isUpdating}
                                                className="text-red-600 hover:text-red-700"
                                            >
                                                Cancel
                                            </Button>
                                        )}
                                    </div>
                                </CollapsibleContent>
                            </Card>
                        </Collapsible>

                        {/* Timeline */}
                        <Collapsible open={!collapsedSections.timeline} onOpenChange={() => toggleSection('timeline')}>
                            <Card className="p-6">
                                <CollapsibleTrigger className="mb-4 flex w-full items-center justify-between">
                                    <h2 className="text-lg font-semibold">Order Timeline</h2>
                                    {collapsedSections.timeline ? <ChevronDown className="h-4 w-4" /> : <ChevronUp className="h-4 w-4" />}
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div className="space-y-4">
                                        <div className="flex items-center space-x-3">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-green-100">
                                                <CheckCircle className="h-4 w-4 text-green-600" />
                                            </div>
                                            <div>
                                                <p className="font-medium">Order Placed</p>
                                                <p className="text-sm text-muted-foreground">{formatDate(order.created_at)}</p>
                                            </div>
                                        </div>
                                        {order.status !== 'pending' && (
                                            <div className="flex items-center space-x-3">
                                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100">
                                                    <Package className="h-4 w-4 text-blue-600" />
                                                </div>
                                                <div>
                                                    <p className="font-medium">Order Confirmed</p>
                                                    <p className="text-sm text-muted-foreground">{formatDate(order.updated_at)}</p>
                                                </div>
                                            </div>
                                        )}
                                        {(order.status === 'shipped' || order.status === 'delivered') && (
                                            <div className="flex items-center space-x-3">
                                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100">
                                                    <Truck className="h-4 w-4 text-purple-600" />
                                                </div>
                                                <div>
                                                    <p className="font-medium">Order Shipped</p>
                                                    <p className="text-sm text-muted-foreground">{formatDate(order.updated_at)}</p>
                                                </div>
                                            </div>
                                        )}
                                        {order.status === 'delivered' && (
                                            <div className="flex items-center space-x-3">
                                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-green-100">
                                                    <CheckCircle className="h-4 w-4 text-green-600" />
                                                </div>
                                                <div>
                                                    <p className="font-medium">Order Delivered</p>
                                                    <p className="text-sm text-muted-foreground">{formatDate(order.updated_at)}</p>
                                                </div>
                                            </div>
                                        )}
                                        {getNextStatus() && (
                                            <Button size="sm" onClick={() => updateOrderStatus(getNextStatus()!)} disabled={isUpdating}>
                                                <SkipForward className="mr-2 h-4 w-4" />
                                                Mark as {getNextStatus()!.charAt(0).toUpperCase() + getNextStatus()!.slice(1)}
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
                                    <h2 className="text-lg font-semibold">Order Items</h2>
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
                                                        Qty: {item.quantity} × {formatCurrency(item.price)}
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
                                        Shipping Information
                                    </h2>
                                    {collapsedSections.shipping ? <ChevronDown className="h-4 w-4" /> : <ChevronUp className="h-4 w-4" />}
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div className="space-y-4">
                                        <div className="grid grid-cols-1 gap-4">
                                            <div>
                                                <p className="text-sm font-medium text-muted-foreground">Shipping Cost</p>
                                                <p className="text-sm">{formatCurrency(order.shipping_amount)}</p>
                                            </div>
                                        </div>

                                        {/* Tracking Number Section */}
                                        <div className="grid grid-cols-1 gap-4">
                                            <div>
                                                <p className="text-sm font-medium text-muted-foreground">Tracking Number</p>
                                                {order.tracking_number ? (
                                                    <p className="font-mono text-sm">{order.tracking_number}</p>
                                                ) : isAddingTracking ? (
                                                    <div className="mt-1 flex space-x-2">
                                                        <Input
                                                            value={trackingNumber}
                                                            onChange={(e) => setTrackingNumber(e.target.value)}
                                                            placeholder="Enter tracking number"
                                                            className="text-sm"
                                                        />
                                                        <Button
                                                            size="sm"
                                                            onClick={updateTrackingNumber}
                                                            disabled={isUpdating || !trackingNumber.trim()}
                                                        >
                                                            Save
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => {
                                                                setIsAddingTracking(false);
                                                                setTrackingNumber(order.tracking_number || '');
                                                            }}
                                                        >
                                                            Cancel
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <div className="flex items-center space-x-2">
                                                        <p className="text-sm text-muted-foreground">Not assigned</p>
                                                        <Button size="sm" variant="outline" onClick={() => setIsAddingTracking(true)}>
                                                            <Plus className="mr-1 h-3 w-3" />
                                                            Add
                                                        </Button>
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        {/* Carrier tracking (set from the edit page; updated automatically when a tracking driver is active) */}
                                        {(carrierName || order.tracking_status) && (
                                            <div className="grid grid-cols-1 gap-2">
                                                {carrierName && (
                                                    <div>
                                                        <p className="text-sm font-medium text-muted-foreground">{t('tracking_admin.carrier')}</p>
                                                        <p className="text-sm">{carrierName}</p>
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
                                                <p className="text-sm font-medium text-blue-900">Package Status</p>
                                                <p className="text-sm text-blue-700">{order.status === 'shipped' ? 'In Transit' : 'Delivered'}</p>
                                                {order.tracking_number && (
                                                    <p className="mt-1 text-xs text-blue-600">Track your package with: {order.tracking_number}</p>
                                                )}
                                            </div>
                                        ) : (
                                            <div className="rounded-lg bg-yellow-50 p-3">
                                                <p className="text-sm font-medium text-yellow-900">Preparing for Shipment</p>
                                                <p className="text-sm text-yellow-700">Order is being processed</p>
                                            </div>
                                        )}

                                        {/* Shipping Address */}
                                        {order.shippingAddress && (
                                            <div>
                                                <p className="mb-2 text-sm font-medium text-muted-foreground">Shipping To</p>
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
                                        Customer
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
                                                    title="Customer management not yet implemented"
                                                >
                                                    <ArrowRight className="mr-2 h-4 w-4" />
                                                    View Profile
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
                                        Order Summary
                                    </h2>
                                    {collapsedSections.summary ? <ChevronDown className="h-4 w-4" /> : <ChevronUp className="h-4 w-4" />}
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div className="space-y-2">
                                        <div className="flex justify-between">
                                            <span>Subtotal</span>
                                            <span>{formatCurrency(order.subtotal)}</span>
                                        </div>
                                        {order.tax_amount > 0 && order.tax_rate == null && (
                                            <div className="flex justify-between">
                                                <span>Tax</span>
                                                <span>{formatCurrency(order.tax_amount)}</span>
                                            </div>
                                        )}
                                        {order.shipping_amount > 0 && (
                                            <div className="flex justify-between">
                                                <span>Shipping</span>
                                                <span>{formatCurrency(order.shipping_amount)}</span>
                                            </div>
                                        )}
                                        <Separator />
                                        <div className="flex justify-between font-semibold">
                                            <span>Total</span>
                                            <span>{formatCurrency(order.total_amount)}</span>
                                        </div>
                                        {order.tax_rate != null && (
                                            <div className="flex justify-between text-sm text-muted-foreground">
                                                <span>Of which VAT ({order.tax_rate}%)</span>
                                                <span>{formatCurrency(order.tax_amount)}</span>
                                            </div>
                                        )}
                                    </div>
                                </CollapsibleContent>
                            </Card>
                        </Collapsible>

                        {/* Notes */}
                        {order.notes && (
                            <Collapsible open={!collapsedSections.notes} onOpenChange={() => toggleSection('notes')}>
                                <Card className="p-6">
                                    <CollapsibleTrigger className="mb-4 flex w-full items-center justify-between">
                                        <h2 className="text-lg font-semibold">Notes</h2>
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

            {/* Confirmation Modal for Manual Status Change to Shipped */}
            <Dialog open={showShipConfirmation} onOpenChange={setShowShipConfirmation}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Mark Order as Shipped?</DialogTitle>
                        <DialogDescription>
                            You are about to mark this order as shipped without a tracking number. A shipping notification email will be sent to the
                            customer without tracking information.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setShowShipConfirmation(false);
                                setPendingStatus(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button onClick={handleConfirmShip} disabled={isUpdating}>
                            {isUpdating ? 'Processing...' : 'Confirm & Send Email'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

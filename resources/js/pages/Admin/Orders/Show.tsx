import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Head, Link, router } from '@inertiajs/react';
import { 
    ArrowLeft, 
    Edit, 
    Package, 
    Truck, 
    CheckCircle, 
    XCircle,
    CreditCard,
    MapPin,
    User,
    Calendar,
    DollarSign
} from 'lucide-react';
import { useState } from 'react';

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
    };
    guest_email?: string;
    status: string;
    payment_status: string;
    subtotal: number;
    tax_amount: number;
    shipping_amount: number;
    total_amount: number;
    currency: string;
    notes?: string;
    created_at: string;
    updated_at: string;
    orderItems: OrderItem[];
    billingAddress?: Address;
    shippingAddress?: Address;
}

interface Props {
    order: Order;
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

export default function OrderShow({ order }: Props) {
    const [isUpdating, setIsUpdating] = useState(false);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: order.currency || 'USD',
        }).format(amount);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
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
                type: 'Registered Customer',
            };
        }
        return {
            name: 'Guest Customer',
            email: order.guest_email || 'N/A',
            type: 'Guest',
        };
    };

    const updateOrderStatus = (newStatus: string) => {
        setIsUpdating(true);
        router.patch(`/admin/orders/${order.id}`, 
            { status: newStatus },
            {
                onFinish: () => setIsUpdating(false),
            }
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
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/admin/orders">
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                Back to Orders
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">{order.order_number}</h1>
                            <p className="text-muted-foreground">
                                Placed on {formatDate(order.created_at)}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center space-x-2">
                        <Button variant="outline" size="sm">
                            <Edit className="h-4 w-4 mr-2" />
                            Edit Order
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Order Details */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Order Status */}
                        <Card className="p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="text-lg font-semibold">Order Status</h2>
                                <div className="flex items-center space-x-2">
                                    <Badge
                                        className={statusColors[order.status as keyof typeof statusColors]}
                                        variant="secondary"
                                    >
                                        {getStatusIcon(order.status)}
                                        <span className="ml-1 capitalize">{order.status}</span>
                                    </Badge>
                                    <Badge
                                        className={paymentStatusColors[order.payment_status as keyof typeof paymentStatusColors]}
                                        variant="secondary"
                                    >
                                        <CreditCard className="h-4 w-4 mr-1" />
                                        {order.payment_status}
                                    </Badge>
                                </div>
                            </div>
                            
                            {/* Quick Status Update */}
                            <div className="flex flex-wrap gap-2">
                                {['pending', 'processing', 'shipped', 'delivered'].map((status) => (
                                    <Button
                                        key={status}
                                        variant={order.status === status ? "default" : "outline"}
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
                        </Card>

                        {/* Order Items */}
                        <Card className="p-6">
                            <h2 className="text-lg font-semibold mb-4">Order Items</h2>
                            <div className="space-y-4">
                                {order.orderItems.map((item) => (
                                    <div key={item.id} className="flex items-center space-x-4 p-4 border rounded-lg">
                                        <div className="w-16 h-16 bg-muted rounded-lg flex items-center justify-center">
                                            {item.product.image_url ? (
                                                <img
                                                    src={item.product.image_url}
                                                    alt={item.product.name}
                                                    className="w-full h-full object-cover rounded-lg"
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
                                        </div>
                                        <div className="text-right">
                                            <p className="font-medium">{formatCurrency(item.total)}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Card>

                        {/* Timeline */}
                        <Card className="p-6">
                            <h2 className="text-lg font-semibold mb-4">Order Timeline</h2>
                            <div className="space-y-4">
                                <div className="flex items-center space-x-3">
                                    <div className="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                        <CheckCircle className="h-4 w-4 text-green-600" />
                                    </div>
                                    <div>
                                        <p className="font-medium">Order Placed</p>
                                        <p className="text-sm text-muted-foreground">{formatDate(order.created_at)}</p>
                                    </div>
                                </div>
                                {order.status !== 'pending' && (
                                    <div className="flex items-center space-x-3">
                                        <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <Package className="h-4 w-4 text-blue-600" />
                                        </div>
                                        <div>
                                            <p className="font-medium">Order Confirmed</p>
                                            <p className="text-sm text-muted-foreground">{formatDate(order.updated_at)}</p>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </Card>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Customer Info */}
                        <Card className="p-6">
                            <h2 className="text-lg font-semibold mb-4 flex items-center">
                                <User className="h-5 w-5 mr-2" />
                                Customer
                            </h2>
                            <div className="space-y-2">
                                <p className="font-medium">{customer.name}</p>
                                <p className="text-sm text-muted-foreground">{customer.email}</p>
                                <Badge variant="secondary" className="text-xs">
                                    {customer.type}
                                </Badge>
                            </div>
                        </Card>

                        {/* Order Summary */}
                        <Card className="p-6">
                            <h2 className="text-lg font-semibold mb-4 flex items-center">
                                <DollarSign className="h-5 w-5 mr-2" />
                                Order Summary
                            </h2>
                            <div className="space-y-2">
                                <div className="flex justify-between">
                                    <span>Subtotal</span>
                                    <span>{formatCurrency(order.subtotal)}</span>
                                </div>
                                {order.tax_amount > 0 && (
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
                            </div>
                        </Card>

                        {/* Addresses */}
                        {(order.billingAddress || order.shippingAddress) && (
                            <Card className="p-6">
                                <h2 className="text-lg font-semibold mb-4 flex items-center">
                                    <MapPin className="h-5 w-5 mr-2" />
                                    Addresses
                                </h2>
                                <div className="space-y-4">
                                    {order.billingAddress && (
                                        <div>
                                            <h3 className="font-medium mb-2">Billing Address</h3>
                                            <div className="text-sm text-muted-foreground space-y-1">
                                                <p>{order.billingAddress.first_name} {order.billingAddress.last_name}</p>
                                                <p>{order.billingAddress.address_line_1}</p>
                                                {order.billingAddress.address_line_2 && (
                                                    <p>{order.billingAddress.address_line_2}</p>
                                                )}
                                                <p>
                                                    {order.billingAddress.city}, {order.billingAddress.state} {order.billingAddress.postal_code}
                                                </p>
                                                <p>{order.billingAddress.country}</p>
                                                {order.billingAddress.phone && (
                                                    <p>{order.billingAddress.phone}</p>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                    
                                    {order.shippingAddress && (
                                        <div>
                                            <h3 className="font-medium mb-2">Shipping Address</h3>
                                            <div className="text-sm text-muted-foreground space-y-1">
                                                <p>{order.shippingAddress.first_name} {order.shippingAddress.last_name}</p>
                                                <p>{order.shippingAddress.address_line_1}</p>
                                                {order.shippingAddress.address_line_2 && (
                                                    <p>{order.shippingAddress.address_line_2}</p>
                                                )}
                                                <p>
                                                    {order.shippingAddress.city}, {order.shippingAddress.state} {order.shippingAddress.postal_code}
                                                </p>
                                                <p>{order.shippingAddress.country}</p>
                                                {order.shippingAddress.phone && (
                                                    <p>{order.shippingAddress.phone}</p>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </Card>
                        )}

                        {/* Notes */}
                        {order.notes && (
                            <Card className="p-6">
                                <h2 className="text-lg font-semibold mb-4">Notes</h2>
                                <p className="text-sm text-muted-foreground whitespace-pre-wrap">
                                    {order.notes}
                                </p>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

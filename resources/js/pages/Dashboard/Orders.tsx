import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Timeline, TimelineBody, TimelineConnector, TimelineHeader, TimelineIcon, TimelineItem, TimelineTitle } from '@/components/ui/timeline';
import AppLayout from '@/layouts/app-layout';
import { type Order, type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { 
    CheckCircle, 
    Circle, 
    Package, 
    ShoppingBag, 
    Clock, 
    Truck, 
    ArrowLeft,
    Download,
    Calendar,
    CreditCard,
    Star,
    MapPin
} from 'lucide-react';
import React from 'react';

interface OrdersPageProps {
    orders: Order[];
    userReviews?: { product_id: number }[]; // Products the user has already reviewed
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: route('dashboard'),
    },
    {
        title: 'My Orders',
        href: route('orders.index'),
    },
];

const OrderStatusTimeline: React.FC<{ status: string }> = ({ status }) => {
    const statuses = [
        { key: 'pending', label: 'Order Placed' },
        { key: 'processing', label: 'Processing' },
        { key: 'shipped', label: 'Shipped' },
        { key: 'delivered', label: 'Delivered' }
    ];
    
    const currentStatusIndex = statuses.findIndex(s => s.key === status.toLowerCase());

    const getStatusIcon = (stepIndex: number) => {
        if (stepIndex < currentStatusIndex) {
            return <CheckCircle className="h-4 w-4 text-green-500" />;
        }
        if (stepIndex === currentStatusIndex) {
            return <Package className="h-4 w-4 text-blue-500" />;
        }
        return <Circle className="h-4 w-4 text-gray-400" />;
    };

    const getStatusColor = (stepIndex: number) => {
        if (stepIndex < currentStatusIndex) return 'text-green-600';
        if (stepIndex === currentStatusIndex) return 'text-blue-600 font-medium';
        return 'text-gray-400';
    };

    return (
        <Timeline>
            {statuses.map((step, index) => (
                <TimelineItem key={step.key}>
                    {index < statuses.length - 1 && <TimelineConnector />}
                    <TimelineHeader>
                        <TimelineIcon>{getStatusIcon(index)}</TimelineIcon>
                        <TimelineTitle className={`text-sm ${getStatusColor(index)}`}>
                            {step.label}
                        </TimelineTitle>
                    </TimelineHeader>
                    <TimelineBody />
                </TimelineItem>
            ))}
        </Timeline>
    );
};

const OrdersPage: React.FC<OrdersPageProps> = ({ orders, userReviews = [] }) => {
    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', { 
            style: 'currency', 
            currency: 'EUR' 
        }).format(amount);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const getOrderStatusIcon = (status: string) => {
        switch (status.toLowerCase()) {
            case 'pending':
                return <Clock className="h-4 w-4" />;
            case 'completed':
            case 'delivered':
                return <CheckCircle className="h-4 w-4" />;
            case 'shipped':
                return <Truck className="h-4 w-4" />;
            default:
                return <Package className="h-4 w-4" />;
        }
    };

    const getOrderStatusColor = (status: string) => {
        switch (status.toLowerCase()) {
            case 'pending':
                return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'completed':
            case 'delivered':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'shipped':
                return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'cancelled':
                return 'bg-red-100 text-red-800 border-red-200';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    };

    const hasUserReviewedProduct = (productId: number) => {
        return userReviews.some(review => review.product_id === productId);
    };

    const canReviewOrder = (order: Order) => {
        return ['completed', 'delivered'].includes(order.status.toLowerCase());
    };

    const getUnreviewedProducts = (order: Order) => {
        if (!canReviewOrder(order)) return [];
        return order.order_items?.filter(item => 
            item.product_id && !hasUserReviewedProduct(item.product_id)
        ) || [];
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Orders" />

            <div className="space-y-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 mb-2">
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={route('dashboard')}>
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                            <h1 className="text-2xl font-bold tracking-tight">My Orders</h1>
                        </div>
                        <p className="text-muted-foreground">
                            Track and manage your order history
                        </p>
                    </div>
                    
                    {orders.length > 0 && (
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm">
                                <Download className="h-4 w-4 mr-2" />
                                Export
                            </Button>
                        </div>
                    )}
                </div>

                {/* Orders List */}
                {orders.length === 0 ? (
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center py-8">
                                <ShoppingBag className="mx-auto h-12 w-12 text-muted-foreground" />
                                <h3 className="mt-2 text-lg font-medium">No orders yet</h3>
                                <p className="mt-1 text-muted-foreground">
                                    You haven't placed any orders. Start shopping to see your orders here.
                                </p>
                                <div className="mt-6">
                                    <Button asChild>
                                        <Link href={route('products')}>
                                            Browse Products
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {orders.map((order) => (
                            <Card key={order.id} className="overflow-hidden">
                                <CardHeader className="pb-4">
                                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="space-y-2">
                                            <CardTitle className="flex items-center gap-2">
                                                <Package className="h-5 w-5" />
                                                Order #{order.id}
                                            </CardTitle>
                                            <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                                                <div className="flex items-center gap-1">
                                                    <Calendar className="h-4 w-4" />
                                                    {formatDate(order.created_at)}
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <CreditCard className="h-4 w-4" />
                                                    {formatCurrency(order.total_amount || order.total)}
                                                </div>
                                                <div>
                                                    {order.order_items.length} {order.order_items.length === 1 ? 'item' : 'items'}
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div className="flex flex-col gap-2 sm:items-end">
                                            <Badge 
                                                variant="outline" 
                                                className={`${getOrderStatusColor(order.status)} flex items-center gap-1 w-fit`}
                                            >
                                                {getOrderStatusIcon(order.status)}
                                                {order.status}
                                            </Badge>
                                            
                                            {/* Tracking Number for Shipped Orders */}
                                            {order.status.toLowerCase() === 'shipped' && order.tracking_number && (
                                                <div className="text-xs text-muted-foreground flex items-center gap-1">
                                                    <MapPin className="h-3 w-3" />
                                                    Tracking: {order.tracking_number}
                                                </div>
                                            )}
                                            
                                            {/* Review Button for Completed Orders */}
                                            {canReviewOrder(order) && getUnreviewedProducts(order).length > 0 && (
                                                <Button size="sm" className="w-fit" asChild>
                                                    <Link href={route('reviews.create', { product: getUnreviewedProducts(order)[0].product_id })}>
                                                        <Star className="h-4 w-4 mr-2" />
                                                        Write Review
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </CardHeader>

                                <CardContent>
                                    <Accordion type="single" collapsible className="w-full">
                                        <AccordionItem value="details" className="border-none">
                                            <AccordionTrigger className="hover:no-underline py-2">
                                                <span className="text-sm font-medium">View Order Details</span>
                                            </AccordionTrigger>
                                            <AccordionContent>
                                                <div className="grid gap-6 pt-4 lg:grid-cols-2">
                                                    {/* Order Items */}
                                                    <div className="space-y-4">
                                                        <h4 className="font-medium">Items Ordered</h4>
                                                        <div className="space-y-3">
                                                            {order.order_items.map((item) => (
                                                                <div key={item.id} className="flex gap-3 p-3 border rounded-lg">
                                                                    <div className="flex-shrink-0">
                                                                        {item.product.image_url ? (
                                                                            <img
                                                                                src={item.product.image_url}
                                                                                alt={item.product.name}
                                                                                className="h-16 w-16 rounded-md object-cover"
                                                                            />
                                                                        ) : (
                                                                            <div className="h-16 w-16 rounded-md bg-muted flex items-center justify-center">
                                                                                <Package className="h-6 w-6 text-muted-foreground" />
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                    
                                                                    <div className="flex-1 min-w-0">
                                                                        <h5 className="font-medium truncate">{item.product.name}</h5>
                                                                        <div className="flex flex-wrap gap-2 mt-1 text-sm text-muted-foreground">
                                                                            {item.size && (
                                                                                <span>Size: {item.size.name}</span>
                                                                            )}
                                                                            <span>Qty: {item.quantity}</span>
                                                                        </div>
                                                                        <div className="flex items-center justify-between mt-2">
                                                                            <div className="font-medium">
                                                                                {formatCurrency(item.price * item.quantity)}
                                                                            </div>
                                                                            {canReviewOrder(order) && item.product_id && !hasUserReviewedProduct(item.product_id) && (
                                                                                <Button size="sm" variant="outline" asChild>
                                                                                    <Link href={route('reviews.create', { product: item.product_id })}>
                                                                                        <Star className="h-4 w-4 mr-1" />
                                                                                        Review
                                                                                    </Link>
                                                                                </Button>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>

                                                    {/* Order Tracking */}
                                                    <div className="space-y-4">
                                                        <h4 className="font-medium">Order Status</h4>
                                                        <div className="p-4 border rounded-lg space-y-4">
                                                            <OrderStatusTimeline status={order.status} />
                                                            
                                                            {/* Tracking Information */}
                                                            {order.tracking_number && ['shipped', 'delivered'].includes(order.status.toLowerCase()) && (
                                                                <div className="pt-4 border-t">
                                                                    <div className="flex items-center gap-2 text-sm">
                                                                        <MapPin className="h-4 w-4 text-muted-foreground" />
                                                                        <span className="font-medium">Tracking Number:</span>
                                                                        <span className="font-mono text-blue-600">{order.tracking_number}</span>
                                                                    </div>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </AccordionContent>
                                        </AccordionItem>
                                    </Accordion>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
};

export default OrdersPage;

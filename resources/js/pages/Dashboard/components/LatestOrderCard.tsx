import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Link, usePage } from '@inertiajs/react';
import { type Order, type SharedData } from '@/types';
import { ShoppingBag, Package, Clock, CheckCircle, Truck } from 'lucide-react';

export const LatestOrderCard = () => {
    const { props } = usePage<SharedData & { recentOrders?: Order[] }>();
    const recentOrders = props.recentOrders || [];

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'EUR',
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
                return 'bg-green-100 text-green-800 border-green-200';
            case 'shipped':
                return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'cancelled':
                return 'bg-red-100 text-red-800 border-red-200';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    };

    const latestOrder = recentOrders[0];

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <ShoppingBag className="h-5 w-5" />
                    Recent Orders
                </CardTitle>
            </CardHeader>
            <CardContent>
                {latestOrder ? (
                    <div className="space-y-4">
                        {/* Latest Order */}
                        <div className="p-4 border rounded-lg bg-muted/20">
                            <div className="flex items-center justify-between mb-3">
                                <div>
                                    <h4 className="font-medium">Order #{latestOrder.id}</h4>
                                    <p className="text-sm text-muted-foreground">
                                        {formatDate(latestOrder.created_at)} • {latestOrder.order_items.length} items
                                    </p>
                                </div>
                                <div className="text-right">
                                    <div className="font-medium">{formatCurrency(latestOrder.total_amount || latestOrder.total)}</div>
                                    <Badge 
                                        variant="outline" 
                                        className={`${getOrderStatusColor(latestOrder.status)} flex items-center gap-1 mt-1`}
                                    >
                                        {getOrderStatusIcon(latestOrder.status)}
                                        {latestOrder.status}
                                    </Badge>
                                </div>
                            </div>
                            
                            {/* Order Items Preview */}
                            <div className="space-y-2">
                                {latestOrder.order_items.slice(0, 2).map((item) => (
                                    <div key={item.id} className="flex items-center justify-between text-sm">
                                        <div className="flex-1">
                                            <span className="font-medium">{item.product.name}</span>
                                            {item.size && (
                                                <span className="text-muted-foreground ml-2">
                                                    • Size: {item.size.name}
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {item.quantity}x {formatCurrency(item.price)}
                                        </div>
                                    </div>
                                ))}
                                {latestOrder.order_items.length > 2 && (
                                    <div className="text-sm text-muted-foreground">
                                        +{latestOrder.order_items.length - 2} more items
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Additional Orders */}
                        {recentOrders.slice(1, 3).map((order) => (
                            <div key={order.id} className="flex items-center justify-between p-3 border rounded-lg">
                                <div className="flex items-center gap-3">
                                    <div className="flex-shrink-0">
                                        {getOrderStatusIcon(order.status)}
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium">Order #{order.id}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatDate(order.created_at)}
                                        </p>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <div className="text-sm font-medium">{formatCurrency(order.total_amount || order.total)}</div>
                                    <Badge 
                                        variant="outline" 
                                        className={`${getOrderStatusColor(order.status)} text-xs`}
                                    >
                                        {order.status}
                                    </Badge>
                                </div>
                            </div>
                        ))}

                        <Button variant="outline" className="w-full" asChild>
                            <Link href={route('orders.index')}>
                                View All Orders
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="text-center py-8">
                        <ShoppingBag className="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 className="mt-2 text-sm font-medium">No orders yet</h3>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Start shopping to see your orders here.
                        </p>
                        <div className="mt-6">
                            <Button asChild>
                                <Link href={route('products')}>
                                    Browse Products
                                </Link>
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
};

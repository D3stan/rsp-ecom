import React from 'react';
import { Head } from '@inertiajs/react';
import { Order } from '@/types';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Timeline, TimelineItem, TimelineConnector, TimelineHeader, TimelineIcon, TimelineTitle, TimelineBody } from '@/components/ui/timeline';
import { CheckCircle, Circle, Package } from 'lucide-react';

interface OrdersPageProps {
    orders: Order[];
}

const OrderStatusTimeline: React.FC<{ status: string }> = ({ status }) => {
    const statuses = ['pending', 'processing', 'shipped', 'delivered'];
    const currentStatusIndex = statuses.indexOf(status.toLowerCase());

    const getStatusIcon = (stepStatus: string, stepIndex: number) => {
        if (stepIndex < currentStatusIndex) {
            return <CheckCircle className="h-5 w-5 text-green-500" />;
        }
        if (stepIndex === currentStatusIndex) {
            return <Package className="h-5 w-5 text-blue-500" />;
        }
        return <Circle className="h-5 w-5 text-gray-400" />;
    };

    return (
        <Timeline>
            {statuses.map((step, index) => (
                <TimelineItem key={step}>
                    {index < statuses.length - 1 && <TimelineConnector />}
                    <TimelineHeader>
                        <TimelineIcon>{getStatusIcon(step, index)}</TimelineIcon>
                        <TimelineTitle className="capitalize">{step}</TimelineTitle>
                    </TimelineHeader>
                    <TimelineBody />
                </TimelineItem>
            ))}
        </Timeline>
    );
};


const OrdersPage: React.FC<OrdersPageProps> = ({ orders }) => {
    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    return (
        <AppLayout>
            <Head title="My Orders" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900 dark:text-gray-100">
                            <h1 className="text-2xl font-bold mb-6">Your Order History</h1>
                            {orders.length === 0 ? (
                                <p>You have not placed any orders yet.</p>
                            ) : (
                                <div className="space-y-6">
                                    {orders.map((order) => (
                                        <Card key={order.id} className="rounded-2xl shadow-sm">
                                            <CardHeader className="flex flex-row items-center justify-between">
                                                <CardTitle>Order #{order.id}</CardTitle>
                                                <Badge>{order.status}</Badge>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="flex flex-col md:flex-row justify-between">
                                                    <div>
                                                        <p><strong>Date:</strong> {formatDate(order.created_at)}</p>
                                                        <p><strong>Total:</strong> {formatCurrency(order.total)}</p>
                                                    </div>
                                                    <div className="mt-4 md:mt-0">
                                                        <Button>View Invoice</Button>
                                                    </div>
                                                </div>
                                                <Accordion type="single" collapsible className="w-full mt-4">
                                                    <AccordionItem value="item-1">
                                                        <AccordionTrigger>View Details</AccordionTrigger>
                                                        <AccordionContent>
                                                            <div className="grid md:grid-cols-2 gap-6">
                                                                <div>
                                                                    <h4 className="font-bold mb-4">Items:</h4>
                                                                    <ul className="space-y-4">
                                                                        {order.order_items.map((item) => (
                                                                            <li key={item.id} className="flex items-center space-x-4">
                                                                                <img src={item.product.image_url} alt={item.product.name} className="w-16 h-16 object-cover rounded-md" />
                                                                                <div>
                                                                                    <p className="font-semibold">{item.product.name}</p>
                                                                                    {order.size && <p className="text-sm text-muted-foreground">Size: {order.size.name}</p>}
                                                                                    <p className="text-sm text-muted-foreground">Quantity: {item.quantity}</p>
                                                                                    <p className="font-medium">{formatCurrency(item.price)}</p>
                                                                                </div>
                                                                            </li>
                                                                        ))}
                                                                    </ul>
                                                                </div>
                                                                <div>
                                                                    <h4 className="font-bold mb-4">Tracking:</h4>
                                                                    <OrderStatusTimeline status={order.status} />
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
                    </div>
                </div>
            </div>
        </AppLayout>
    );
};

export default OrdersPage;

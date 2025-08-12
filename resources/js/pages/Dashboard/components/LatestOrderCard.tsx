import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';

export const LatestOrderCard = () => {
    // Mock data for now
    const latestOrder = {
        id: '12345',
        status: 'Shipped',
        date: '2024-08-12',
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Latest Order</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="flex justify-between items-center">
                    <div>
                        <p>Order #{latestOrder.id}</p>
                        <p>{latestOrder.date}</p>
                    </div>
                    <Badge>{latestOrder.status}</Badge>
                </div>
                <Link href={route('orders.index')} className="mt-4">
                    <Button variant="outline">View All Orders</Button>
                </Link>
            </CardContent>
        </Card>
    );
};

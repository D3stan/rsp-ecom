import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';

export const WishlistCard = () => {
    // Mock data for now
    const wishlistItems = [
        { id: 1, name: 'Product A' },
        { id: 2, name: 'Product B' },
        { id: 3, name: 'Product C' },
    ];

    return (
        <Card>
            <CardHeader>
                <CardTitle>Wishlist</CardTitle>
            </CardHeader>
            <CardContent>
                <ul>
                    {wishlistItems.slice(0, 3).map((item) => (
                        <li key={item.id}>{item.name}</li>
                    ))}
                </ul>
                <Link href={route('wishlist.index')} className="mt-4">
                    <Button variant="outline">View Wishlist</Button>
                </Link>
            </CardContent>
        </Card>
    );
};

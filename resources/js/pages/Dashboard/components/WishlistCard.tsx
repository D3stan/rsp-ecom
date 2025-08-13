import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Link, usePage } from '@inertiajs/react';
import { Heart, ShoppingCart } from 'lucide-react';
import { type SharedData, type WishlistItem } from '@/types';
import { WishlistCard as WishlistItemCard } from '@/components/wishlist-card';

export const WishlistCard = () => {
    const { props } = usePage<SharedData & { wishlistItems?: WishlistItem[] }>();
    const wishlistItems = props.wishlistItems || [];

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Heart className="h-5 w-5" />
                    Wishlist
                </CardTitle>
            </CardHeader>
            <CardContent>
                {wishlistItems.length > 0 ? (
                    <div className="space-y-4">
                        {/* Wishlist Items - Show first 3 in compact mode */}
                        <div className="space-y-3">
                            {wishlistItems.slice(0, 3).map((item) => (
                                <div key={item.id} className="flex items-center gap-3 p-3 border rounded-lg">
                                    <div className="flex-shrink-0">
                                        {item.product.image ? (
                                            <img 
                                                src={item.product.image} 
                                                alt={item.product.name}
                                                className="h-12 w-12 rounded object-cover"
                                            />
                                        ) : (
                                            <div className="h-12 w-12 rounded bg-muted flex items-center justify-center">
                                                <ShoppingCart className="h-6 w-6 text-muted-foreground" />
                                            </div>
                                        )}
                                    </div>
                                    
                                    <div className="flex-1 min-w-0">
                                        <h4 className="text-sm font-medium truncate">
                                            {item.product.name}
                                        </h4>
                                        <div className="flex items-center gap-2 mt-1">
                                            <span className="text-sm font-medium">
                                                {new Intl.NumberFormat('en-US', {
                                                    style: 'currency',
                                                    currency: 'EUR',
                                                }).format(item.product.price)}
                                            </span>
                                            {item.product.rating && (
                                                <div className="flex items-center gap-1">
                                                    <span className="text-xs text-muted-foreground">
                                                        ★{item.product.rating.toFixed(1)}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    
                                    <Button size="sm" variant="ghost" asChild>
                                        <Link href={`/products/${item.product.slug}`}>
                                            View
                                        </Link>
                                    </Button>
                                </div>
                            ))}
                        </div>

                        {/* Show more indicator */}
                        {wishlistItems.length > 3 && (
                            <div className="text-center text-sm text-muted-foreground py-2">
                                +{wishlistItems.length - 3} more items in your wishlist
                            </div>
                        )}

                        {/* View All Button */}
                        <Button variant="outline" className="w-full" asChild>
                            <Link href={route('wishlist.index')}>
                                View Full Wishlist ({wishlistItems.length} items)
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="text-center py-8">
                        <Heart className="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 className="mt-2 text-sm font-medium">No items in wishlist</h3>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Save products you love for later.
                        </p>
                        <div className="mt-6">
                            <Button variant="outline" asChild>
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

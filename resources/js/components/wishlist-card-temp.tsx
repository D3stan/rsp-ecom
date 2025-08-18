import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Link } from '@inertiajs/react';
import { 
    ShoppingCart, 
    Star, 
    ExternalLink,
    Trash2,
    Eye,
    Package
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { useWishlist } from '@/hooks/useWishlist';
import { useTranslation } from '@/hooks/useTranslation';
import type { Wishlist } from '@/types';

interface WishlistCardProps {
    item: Wishlist;
    variant?: 'compact' | 'detailed';
    showActions?: boolean;
    className?: string;
}

export const WishlistCard: React.FC<WishlistCardProps> = ({
    item,
    variant = 'detailed',
    showActions = true,
    className,
}) => {
    const { isLoading, removeFromWishlist } = useWishlist({
        productId: item.product.id,
        initialInWishlist: true,
    });
    const { t } = useTranslation();

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

    const renderStars = (rating: number) => {
        return Array.from({ length: 5 }, (_, i) => (
            <Star
                key={i}
                className={`h-4 w-4 ${
                    i < Math.floor(rating)
                        ? 'fill-yellow-400 text-yellow-400'
                        : 'text-gray-300'
                }`}
            />
        ));
    };

    const getStockStatus = () => {
        if (item.product.status !== 'active') {
            return { status: t('products.discontinued'), color: 'bg-red-100 text-red-800 border-red-200' };
        }
        if (!item.product.stock_quantity || item.product.stock_quantity === 0) {
            return { status: t('products.out_of_stock'), color: 'bg-red-100 text-red-800 border-red-200' };
        }
        if ((item.product.stock_quantity || 0) <= 5) {
            return { status: t('products.low_stock'), color: 'bg-yellow-100 text-yellow-800 border-yellow-200' };
        }
        return { status: t('products.in_stock'), color: 'bg-green-100 text-green-800 border-green-200' };
    };

    const handleRemove = () => {
        if (isLoading) return;
        removeFromWishlist();
    };

    if (variant === 'compact') {
        return (
            <div className={cn('flex items-center gap-3 p-3 border rounded-lg', className)}>
                <div className="flex-shrink-0">
                    {item.product.image_url ? (
                        <img 
                            src={item.product.image_url} 
                            alt={item.product.name}
                            className="h-12 w-12 rounded object-cover"
                        />
                    ) : (
                        <div className="h-12 w-12 rounded bg-muted flex items-center justify-center">
                            <Package className="h-6 w-6 text-muted-foreground" />
                        </div>
                    )}
                </div>
                
                <div className="flex-1 min-w-0">
                    <h4 className="text-sm font-medium truncate">
                        {item.product.name}
                    </h4>
                    <div className="flex items-center gap-2 mt-1">
                        <span className="text-sm font-medium">
                            {formatCurrency(item.product.price)}
                        </span>
                        {item.product.rating && (
                            <div className="flex items-center gap-1">
                                {renderStars(item.product.rating)}
                            </div>
                        )}
                    </div>
                </div>
                
                {showActions && (
                    <div className="flex gap-2">
                        <Button size="sm" variant="ghost" asChild>
                            <Link href={`/products/${item.product.slug}`}>
                                <ExternalLink className="h-4 w-4" />
                            </Link>
                        </Button>
                        <Button 
                            size="sm" 
                            variant="ghost" 
                            onClick={handleRemove}
                            disabled={isLoading}
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                )}
            </div>
        );
    }

    const stockInfo = getStockStatus();

    return (
        <Card className={cn('overflow-hidden hover:shadow-md transition-shadow', className)}>
            <div className="relative">
                {/* Product Image */}
                <div className="aspect-square overflow-hidden">
                    {item.product.image_url ? (
                        <img
                            src={item.product.image_url}
                            alt={item.product.name}
                            className="w-full h-full object-cover transition-transform hover:scale-105"
                        />
                    ) : (
                        <div className="w-full h-full bg-muted flex items-center justify-center">
                            <Package className="h-12 w-12 text-muted-foreground" />
                        </div>
                    )}
                </div>

                {/* Actions */}
                {showActions && (
                    <Button
                        size="sm"
                        variant="destructive"
                        className="absolute top-2 right-2 h-8 w-8 p-0"
                        onClick={handleRemove}
                        disabled={isLoading}
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                )}

                {/* Stock Status Badge */}
                <Badge 
                    variant="outline" 
                    className={`absolute top-2 left-2 ${stockInfo.color}`}
                >
                    {stockInfo.status}
                </Badge>
            </div>

            <CardHeader className="pb-4">
                <div className="space-y-2">
                    <CardTitle className="text-lg leading-tight">
                        {item.product.name}
                    </CardTitle>
                    
                    {/* Category */}
                    {item.product.category && (
                        <p className="text-sm text-muted-foreground">
                            {item.product.category.name}
                        </p>
                    )}

                    {/* Rating */}
                    {item.product.rating && (
                        <div className="flex items-center gap-2">
                            <div className="flex">
                                {renderStars(item.product.rating)}
                            </div>
                            <span className="text-sm text-muted-foreground">
                                ({item.product.rating.toFixed(1)})
                            </span>
                        </div>
                    )}

                    {/* Price */}
                    <div className="text-2xl font-bold">
                        {formatCurrency(item.product.price)}
                    </div>

                    {/* Added Date */}
                    <p className="text-xs text-muted-foreground">
                        {t('wishlist.added')} {formatDate(item.created_at)}
                    </p>
                </div>
            </CardHeader>

            {showActions && (
                <CardContent>
                    <div className="space-y-3">
                        {/* Description Preview */}
                        {item.product.description && (
                            <p className="text-sm text-muted-foreground line-clamp-2 whitespace-pre-wrap">
                                {item.product.description}
                            </p>
                        )}

                        {/* Action Buttons */}
                        <div className="flex flex-col gap-2">
                            <Button 
                                size="sm" 
                                className="w-full"
                                disabled={item.product.status !== 'active' || item.product.stock_quantity === 0}
                                asChild={item.product.status === 'active' && item.product.stock_quantity !== 0}
                            >
                                {item.product.status === 'active' && item.product.stock_quantity !== 0 ? (
                                    <Link href={`/products/${item.product.slug}`}>
                                        <ShoppingCart className="h-4 w-4 mr-2" />
                                        {t('products.add_to_cart')}
                                    </Link>
                                ) : (
                                    <>
                                        <ShoppingCart className="h-4 w-4 mr-2" />
                                        {item.product.status !== 'active' ? t('products.discontinued') : t('products.out_of_stock')}
                                    </>
                                )}
                            </Button>
                            
                            <Button size="sm" variant="outline" className="w-full" asChild>
                                <Link href={`/products/${item.product.slug}`}>
                                    <Eye className="h-4 w-4 mr-2" />
                                    {t('products.view_full_details')}
                                </Link>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            )}
        </Card>
    );
};

import { Button } from '@/components/ui/button';
import { Heart } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useWishlist } from '@/hooks/useWishlist';

interface WishlistButtonProps {
    productId: number;
    initialInWishlist?: boolean;
    size?: 'sm' | 'default' | 'lg';
    variant?: 'default' | 'outline' | 'ghost' | 'destructive' | 'secondary';
    className?: string;
    showText?: boolean;
    disabled?: boolean;
}

export const WishlistButton: React.FC<WishlistButtonProps> = ({
    productId,
    initialInWishlist = false,
    size = 'default',
    variant = 'outline',
    className,
    showText = false,
    disabled = false,
}) => {
    const { inWishlist, isLoading, toggleWishlist } = useWishlist({
        productId,
        initialInWishlist,
    });

    const handleClick = () => {
        if (disabled || isLoading) return;
        toggleWishlist();
    };

    const getButtonVariant = () => {
        if (inWishlist) {
            return 'default';
        }
        return variant;
    };

    const getHeartClass = () => {
        return cn(
            'h-4 w-4 transition-colors',
            inWishlist ? 'fill-red-500 text-red-500' : 'text-current'
        );
    };

    return (
        <Button
            variant={getButtonVariant()}
            size={size}
            onClick={handleClick}
            disabled={disabled || isLoading}
            className={cn(
                'transition-all duration-200',
                inWishlist && 'bg-red-50 border-red-200 text-red-700 hover:bg-red-100',
                isLoading && 'opacity-60',
                className
            )}
        >
            <Heart className={getHeartClass()} />
            {showText && (
                <span className="ml-2">
                    {isLoading ? 'Loading...' : inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'}
                </span>
            )}
        </Button>
    );
};

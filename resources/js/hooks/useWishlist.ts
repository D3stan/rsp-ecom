import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';

interface UseWishlistOptions {
    productId: number;
    initialInWishlist?: boolean;
}

interface UseWishlistReturn {
    inWishlist: boolean;
    isLoading: boolean;
    toggleWishlist: () => Promise<void>;
    addToWishlist: () => Promise<void>;
    removeFromWishlist: () => Promise<void>;
    checkWishlistStatus: () => Promise<void>;
}

export const useWishlist = ({
    productId,
    initialInWishlist = false,
}: UseWishlistOptions): UseWishlistReturn => {
    const [inWishlist, setInWishlist] = useState(initialInWishlist);
    const [isLoading, setIsLoading] = useState(false);

    const getCsrfToken = () => {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    };

    const makeApiCall = async (endpoint: string, data: any) => {
        const response = await fetch(`/api/wishlist/${endpoint}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(data),
        });

        if (!response.ok) {
            throw new Error(`Failed to ${endpoint} wishlist`);
        }

        return response.json();
    };

    const toggleWishlist = async () => {
        if (isLoading) return;

        setIsLoading(true);
        
        try {
            const result = await makeApiCall('toggle', { product_id: productId });
            setInWishlist(result.in_wishlist);
            
            // Refresh wishlist data on dashboard pages
            if (window.location.pathname.includes('dashboard')) {
                router.reload({ only: ['wishlistItems', 'wishlistCount'] });
            }
        } catch (error) {
            console.error('Error toggling wishlist:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const addToWishlist = async () => {
        if (isLoading) return;

        setIsLoading(true);
        
        try {
            const result = await makeApiCall('', { product_id: productId });
            setInWishlist(result.in_wishlist);
            
            if (window.location.pathname.includes('dashboard')) {
                router.reload({ only: ['wishlistItems', 'wishlistCount'] });
            }
        } catch (error) {
            console.error('Error adding to wishlist:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const removeFromWishlist = async () => {
        if (isLoading) return;

        setIsLoading(true);
        
        try {
            const response = await fetch('/api/wishlist/product', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ product_id: productId }),
            });

            if (!response.ok) {
                throw new Error('Failed to remove from wishlist');
            }

            const result = await response.json();
            setInWishlist(result.in_wishlist);
            
            if (window.location.pathname.includes('dashboard')) {
                router.reload({ only: ['wishlistItems', 'wishlistCount'] });
            }
        } catch (error) {
            console.error('Error removing from wishlist:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const checkWishlistStatus = async () => {
        try {
            const result = await makeApiCall('check', { product_id: productId });
            setInWishlist(result.in_wishlist);
        } catch (error) {
            console.error('Error checking wishlist status:', error);
        }
    };

    return {
        inWishlist,
        isLoading,
        toggleWishlist,
        addToWishlist,
        removeFromWishlist,
        checkWishlistStatus,
    };
};

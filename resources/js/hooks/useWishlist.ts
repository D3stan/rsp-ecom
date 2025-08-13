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
        const response = await fetch(`/wishlist/${endpoint}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(data),
        });

        if (!response.ok) {
            const text = await response.text();
            console.error(`API Error (${response.status}):`, text);
            
            // If we get HTML back, it's likely an authentication issue
            if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                throw new Error('Authentication required. Please login and try again.');
            }
            
            throw new Error(`Failed to ${endpoint} wishlist: ${response.status}`);
        }

        const text = await response.text();
        
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Invalid response from server');
        }
    };

    const toggleWishlist = async () => {
        if (isLoading) return;

        setIsLoading(true);
        
        try {
            const result = await makeApiCall('toggle', { product_id: productId });
            setInWishlist(result.in_wishlist);
            
            // Dispatch wishlist update event for header count
            window.dispatchEvent(new CustomEvent('wishlistUpdated'));
            
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
            const result = await makeApiCall('add', { product_id: productId });
            setInWishlist(result.in_wishlist);
            
            // Dispatch wishlist update event for header count
            window.dispatchEvent(new CustomEvent('wishlistUpdated'));
            
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
            const response = await fetch('/wishlist/remove', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ product_id: productId }),
            });

            if (!response.ok) {
                const text = await response.text();
                console.error(`API Error (${response.status}):`, text);
                
                if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                    throw new Error('Authentication required. Please login and try again.');
                }
                
                throw new Error(`Failed to remove from wishlist: ${response.status}`);
            }

            const text = await response.text();
            
            try {
                const result = JSON.parse(text);
                setInWishlist(result.in_wishlist);
                
                // Dispatch wishlist update event for header count
                window.dispatchEvent(new CustomEvent('wishlistUpdated'));
                
                if (window.location.pathname.includes('dashboard')) {
                    router.reload({ only: ['wishlistItems', 'wishlistCount'] });
                }
            } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error('Invalid response from server');
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

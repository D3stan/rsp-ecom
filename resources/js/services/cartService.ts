import { router } from '@inertiajs/react';

export interface AddToCartData {
    product_id: number;
    quantity: number;
    size_id?: number;
}

export interface CartResponse {
    success: boolean;
    message: string;
    cartCount?: number;
    maxQuantity?: number;
}

class CartService {
    /**
     * Get CSRF token from meta tag
     */
    private getCSRFToken(): string | null {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        return token || null;
    }

    /**
     * Add a product to cart
     */
    async addToCart(data: AddToCartData): Promise<CartResponse> {
        try {
            const token = this.getCSRFToken();
            
            const response = await fetch('/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token && { 'X-CSRF-TOKEN': token }),
                },
                body: JSON.stringify(data),
            });

            const result = await response.json();
            
            if (!response.ok) {
                return {
                    success: false,
                    message: result.message || 'Failed to add product to cart.',
                };
            }

            return result;
        } catch (error) {
            console.error('Error adding to cart:', error);
            return {
                success: false,
                message: 'Network error occurred. Please try again.',
            };
        }
    }

    /**
     * Update cart item quantity
     */
    async updateCartItem(itemId: number, quantity: number): Promise<CartResponse> {
        try {
            const token = this.getCSRFToken();
            
            const response = await fetch(`/cart/items/${itemId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token && { 'X-CSRF-TOKEN': token }),
                },
                body: JSON.stringify({ quantity }),
            });

            const result = await response.json();
            
            if (!response.ok) {
                return {
                    success: false,
                    message: result.message || 'Failed to update cart.',
                };
            }

            return result;
        } catch (error) {
            console.error('Error updating cart:', error);
            return {
                success: false,
                message: 'Network error occurred. Please try again.',
            };
        }
    }

    /**
     * Remove item from cart
     */
    async removeFromCart(itemId: number): Promise<CartResponse> {
        try {
            const token = this.getCSRFToken();
            
            const response = await fetch(`/cart/items/${itemId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token && { 'X-CSRF-TOKEN': token }),
                },
            });

            const result = await response.json();
            
            if (!response.ok) {
                return {
                    success: false,
                    message: result.message || 'Failed to remove item.',
                };
            }

            return result;
        } catch (error) {
            console.error('Error removing from cart:', error);
            return {
                success: false,
                message: 'Network error occurred. Please try again.',
            };
        }
    }

    /**
     * Get current cart count
     */
    async getCartCount(): Promise<number> {
        try {
            const response = await fetch('/cart/count', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            
            if (!response.ok) {
                throw new Error('Failed to fetch cart count');
            }
            
            const data = await response.json();
            return data.count || 0;
        } catch (error) {
            console.error('Failed to get cart count:', error);
            return 0;
        }
    }

    /**
     * Clear entire cart
     */
    async clearCart(): Promise<CartResponse> {
        try {
            const token = this.getCSRFToken();
            
            const response = await fetch('/cart/clear', {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token && { 'X-CSRF-TOKEN': token }),
                },
            });

            const result = await response.json();
            
            if (!response.ok) {
                return {
                    success: false,
                    message: result.message || 'Failed to clear cart.',
                };
            }

            return result;
        } catch (error) {
            console.error('Error clearing cart:', error);
            return {
                success: false,
                message: 'Network error occurred. Please try again.',
            };
        }
    }
}

export const cartService = new CartService();

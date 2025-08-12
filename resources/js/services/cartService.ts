// Cart service for managing cart operations

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

interface CustomWindow extends Window {
    inertiaProps?: {
        csrf_token?: string | null;
    };
}

class CartService {
    /**
     * Get CSRF token from meta tag or Inertia props
     */
    private getCSRFToken(): string | null {
        // First try to get from meta tag
        const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (metaToken) {
            return metaToken;
        }

        // Fallback to Inertia shared props (if available)
        try {
            const inertiaProps = (window as CustomWindow)?.inertiaProps || {};
            return inertiaProps.csrf_token || null;
        } catch {
            return null;
        }
    }

    /**
     * Refresh CSRF token from server
     */
    private async refreshCSRFToken(): Promise<string | null> {
        try {
            const response = await fetch('/csrf-token', {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                const data = await response.json();
                // Update the meta tag with the new token
                const metaTag = document.querySelector('meta[name="csrf-token"]');
                if (metaTag && data.token) {
                    metaTag.setAttribute('content', data.token);
                }
                return data.token;
            }
        } catch (error) {
            console.error('Failed to refresh CSRF token:', error);
        }
        return null;
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
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token && { 'X-CSRF-TOKEN': token }),
                },
                body: JSON.stringify(data),
            });

            // If we get a CSRF error (419), try to refresh the token and retry
            if (response.status === 419) {
                const newToken = await this.refreshCSRFToken();
                if (newToken) {
                    const retryResponse = await fetch('/cart/add', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': newToken,
                        },
                        body: JSON.stringify(data),
                    });

                    const retryResult = await retryResponse.json();

                    if (!retryResponse.ok) {
                        return {
                            success: false,
                            message: retryResult.message || 'Failed to add product to cart after token refresh.',
                        };
                    }

                    return retryResult;
                }
            }

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
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token && { 'X-CSRF-TOKEN': token }),
                },
                body: JSON.stringify({ quantity }),
            });

            // If we get a CSRF error (419), try to refresh the token and retry
            if (response.status === 419) {
                const newToken = await this.refreshCSRFToken();
                if (newToken) {
                    const retryResponse = await fetch(`/cart/items/${itemId}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': newToken,
                        },
                        body: JSON.stringify({ quantity }),
                    });

                    const retryResult = await retryResponse.json();

                    if (!retryResponse.ok) {
                        return {
                            success: false,
                            message: retryResult.message || 'Failed to update cart after token refresh.',
                        };
                    }

                    return retryResult;
                }
            }

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
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token && { 'X-CSRF-TOKEN': token }),
                },
            });

            // If we get a CSRF error (419), try to refresh the token and retry
            if (response.status === 419) {
                const newToken = await this.refreshCSRFToken();
                if (newToken) {
                    const retryResponse = await fetch(`/cart/items/${itemId}`, {
                        method: 'DELETE',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': newToken,
                        },
                    });

                    const retryResult = await retryResponse.json();

                    if (!retryResponse.ok) {
                        return {
                            success: false,
                            message: retryResult.message || 'Failed to remove item after token refresh.',
                        };
                    }

                    return retryResult;
                }
            }

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
                    Accept: 'application/json',
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
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token && { 'X-CSRF-TOKEN': token }),
                },
            });

            // If we get a CSRF error (419), try to refresh the token and retry
            if (response.status === 419) {
                const newToken = await this.refreshCSRFToken();
                if (newToken) {
                    const retryResponse = await fetch('/cart/clear', {
                        method: 'DELETE',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': newToken,
                        },
                    });

                    const retryResult = await retryResponse.json();

                    if (!retryResponse.ok) {
                        return {
                            success: false,
                            message: retryResult.message || 'Failed to clear cart after token refresh.',
                        };
                    }

                    return retryResult;
                }
            }

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

    /**
     * Trigger cart count refresh event
     */
    triggerCartUpdate(): void {
        window.dispatchEvent(new CustomEvent('cartUpdated'));
    }

    /**
     * Trigger cart animation event
     */
    triggerCartAnimation(type: 'success' | 'error' = 'success'): void {
        window.dispatchEvent(
            new CustomEvent('cartAnimation', {
                detail: { type },
            }),
        );
    }
}

export const cartService = new CartService();

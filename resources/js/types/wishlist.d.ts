import type { Product } from './product';

export interface Wishlist {
    id: number;
    user_id: number;
    product_id: number;
    created_at: string;
    updated_at: string;
    product: Product & {
        category?: {
            id: number;
            name: string;
            slug: string;
        } | null;
    };
}

export interface WishlistItem {
    id: number;
    product: {
        id: number;
        name: string;
        slug: string;
        price: number;
        image?: string;
        rating?: number;
    };
}

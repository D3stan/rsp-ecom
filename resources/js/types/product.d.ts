import { Category } from './category';

export interface Product {
    id: number;
    name: string;
    slug: string;
    description?: string;
    price: number;
    images: string[];
    image_url?: string;
    category?: Category;
    stock_quantity?: number;
    status?: 'active' | 'inactive' | 'draft';
    is_active?: boolean; // deprecated, use status instead
    rating?: number;
    inWishlist?: boolean;
    // Add other product fields as needed
}

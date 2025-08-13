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
    is_active?: boolean;
    rating?: number;
    // Add other product fields as needed
}

import type { Product } from './product';
import type { User } from './index';

export interface Review {
    id: number;
    user_id: number;
    product_id: number;
    rating: number;
    comment: string | null;
    is_approved: boolean;
    created_at: string;
    updated_at: string;
    user?: User;
    product: Product;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
}

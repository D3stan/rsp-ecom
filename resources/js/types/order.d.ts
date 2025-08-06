
import { Product } from './product';
import { Size } from './size';

export interface Order {
    id: number;
    user_id: number;
    total: number;
    status: string;
    created_at: string;
    updated_at: string;
    order_items: OrderItem[];
    size?: Size;
}

export interface OrderItem {
    id: number;
    order_id: number;
    product_id: number;
    size_id: number;
    quantity: number;
    price: number;
    product: Product;
    size: Size;
}

import { Product } from './product';
import { Size } from './size';

export interface Order {
    id: number;
    order_number: string;
    user_id: number;
    status: string;
    subtotal: number;
    tax_amount: number;
    shipping_amount: number;
    total_amount: number;
    total: number; // Alias for total_amount for backward compatibility
    currency: string;
    payment_status: string;
    payment_method?: string;
    tracking_number?: string;
    notes?: string;
    created_at: string;
    updated_at: string;
    order_items: OrderItem[];
    orderItems: OrderItem[]; // Alternative naming
}

export interface OrderItem {
    id: number;
    order_id: number;
    product_id: number;
    product_name: string;
    quantity: number;
    price: number;
    total: number;
    product: Product;
    size?: Size;
}

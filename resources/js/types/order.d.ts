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
    tracking_carrier?: string | null;
    tracking_status?: string | null;
    tracking_last_synced_at?: string | null;
    // customer-facing tracking (Order::customerTrackingArray)
    carrier_name?: string | null;
    tracking_url?: string | null;
    tracking_events?: TrackingEvent[];
    notes?: string;
    created_at: string;
    updated_at: string;
    order_items: OrderItem[];
    orderItems: OrderItem[]; // Alternative naming
}

export interface TrackingEvent {
    // provider-neutral status value, e.g. "in_transit" (rendered via tracking_admin.status.<value>)
    status: string;
    description: string;
    location?: string | null;
    occurred_at?: string | null;
}

export interface OrderItem {
    id: number;
    order_id: number;
    product_id: number;
    product_name: string;
    /** The chosen variant; null when it has since been deleted. */
    variant_name?: string | null;
    quantity: number;
    price: number;
    total: number;
    product: Product;
    size?: Size;
}

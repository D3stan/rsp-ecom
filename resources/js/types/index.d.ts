import { LucideIcon } from 'lucide-react';
import type { Config } from 'ziggy-js';

export * from './category';
export * from './order';
export * from './product';
export * from './review';
export * from './size';
export * from './wishlist';

export interface Auth {
    user: User | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    children?: NavItem[];
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    google_id?: string | null;
    password?: string | null;
    has_password: boolean;
    role?: string;
    is_active?: boolean;
    [key: string]: unknown; // This allows for additional properties...
}

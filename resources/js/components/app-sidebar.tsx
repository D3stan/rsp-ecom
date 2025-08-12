import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { BookOpen, Folder, LayoutGrid, ShoppingBag, User, CreditCard, Heart, Settings, MessageSquare, Star } from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: route('dashboard'),
            icon: LayoutGrid,
        },
        {
            title: 'Orders',
            href: route('orders.index'),
            icon: ShoppingBag,
        },
        {
            title: 'Profile',
            href: route('profile.edit'),
            icon: User,
        },
        {
            title: 'Payment Methods',
            href: '#',
            icon: CreditCard,
        },
        {
            title: 'Wishlist',
            href: route('wishlist.index'),
            icon: Heart,
        },
        {
            title: 'Account Settings',
            href: '#',
            icon: Settings,
        },
        {
            title: 'Reviews',
            href: '#',
            icon: Star,
        },
        {
            title: 'Support',
            href: '#',
            icon: MessageSquare,
        },
    ];

    const footerNavItems: NavItem[] = [
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

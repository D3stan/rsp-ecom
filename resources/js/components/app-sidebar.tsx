import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import useTranslation from '@/hooks/useTranslation';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { LayoutGrid, ShoppingBag, User, CreditCard, Heart, Settings, MessageSquare, Star } from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { t } = useTranslation();
    
    const mainNavItems: NavItem[] = [
        {
            title: t('dashboard.title'),
            href: route('dashboard'),
            icon: LayoutGrid,
        },
        {
            title: t('orders.title'),
            href: route('orders.index'),
            icon: ShoppingBag,
        },
        {
            title: t('wishlist.title'),
            href: route('wishlist.index'),
            icon: Heart,
        },
        {
            title: t('reviews.title'),
            href: route('reviews.index'),
            icon: Star,
        },
    ];

    const footerNavItems: NavItem[] = [
        {
            title: t('support'),
            href: route('contact'),
            icon: MessageSquare,
        }
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

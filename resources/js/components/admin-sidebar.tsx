import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslation } from '@/hooks/useTranslation';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    BarChart3,
    LayoutGrid,
    MessageSquare,
    Newspaper,
    Package,
    Plus,
    Settings,
    ShoppingCart,
    Tags,
    Users,
} from 'lucide-react';
import AppLogo from './app-logo';

export function AdminSidebar() {
    const { t } = useTranslation();
    const { adminPendingOrders: pendingOrders = 0 } = usePage<SharedData>().props;

    const mainNavItems: NavItem[] = [
        {
            title: t('admin.nav.dashboard'),
            href: '/admin/dashboard',
            icon: LayoutGrid,
        },
        {
            title: t('admin.nav.orders'),
            href: '/admin/orders',
            icon: ShoppingCart,
        },
        {
            title: t('admin.nav.products'),
            href: '/admin/products',
            icon: Package,
        },
        {
            title: t('admin.nav.categories_sizes'),
            href: '/admin/categories-sizes',
            icon: Tags,
        },
        {
            title: t('admin.nav.customers'),
            href: '/admin/customers',
            icon: Users,
        },
        {
            title: t('admin.nav.reviews'),
            href: '/admin/reviews',
            icon: MessageSquare,
        },
        {
            title: t('admin.nav.newsletter'),
            href: '/admin/newsletter/subscribers',
            icon: Newspaper,
        },
        {
            title: t('admin.nav.settings'),
            href: '/admin/settings',
            icon: Settings,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/admin/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />

                {/* Quick Actions */}
                <SidebarGroup>
                    <SidebarGroupLabel>{t('admin.sidebar.quick_actions')}</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <div className="space-y-2 px-2">
                            <Button variant="outline" size="sm" className="w-full justify-start" asChild>
                                <Link href="/admin/products/create">
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('admin.sidebar.add_product')}
                                </Link>
                            </Button>

                            <Button variant="outline" size="sm" className="w-full justify-start" asChild>
                                <Link href="/admin/orders?status=pending">
                                    <AlertTriangle className="mr-2 h-4 w-4" />
                                    {t('admin.sidebar.pending_orders', { count: pendingOrders })}
                                </Link>
                            </Button>

                            <Button variant="outline" size="sm" className="w-full justify-start" asChild>
                                <Link href="/admin/products?stock_filter=low-stock">
                                    <Package className="mr-2 h-4 w-4" />
                                    {t('admin.sidebar.low_stock')}
                                </Link>
                            </Button>

                            <Button variant="outline" size="sm" className="w-full justify-start" asChild>
                                <Link href="/admin/reports">
                                    <BarChart3 className="mr-2 h-4 w-4" />
                                    {t('admin.sidebar.view_reports')}
                                </Link>
                            </Button>
                        </div>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
